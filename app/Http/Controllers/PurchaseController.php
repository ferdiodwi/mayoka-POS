<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Exports\PurchasesExport;

class PurchaseController extends Controller
{
    /**
     * List all purchases with pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Purchase::with(['user:id,name', 'items.product:id,name,unit'])
            ->orderByDesc('purchase_date')
            ->orderByDesc('id');

        if ($request->filled('date_from')) {
            $query->whereDate('purchase_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('purchase_date', '<=', $request->date_to);
        }
        if ($request->filled('supplier')) {
            $query->where('supplier_name', 'like', '%' . $request->supplier . '%');
        }

        $purchases = $query->paginate(20);

        return response()->json($purchases);
    }

    /**
     * Export Purchases (Excel/PDF).
     */
    public function exportPurchases(Request $request)
    {
        $from = $request->get('date_from');
        $to = $request->get('date_to');
        $supplier = $request->get('supplier');
        $format = $request->get('format', 'excel');

        if ($format === 'pdf') {
            $query = Purchase::with(['user', 'items.product'])
                ->orderByDesc('purchase_date')
                ->orderByDesc('id');

            if ($from) $query->whereDate('purchase_date', '>=', $from);
            if ($to) $query->whereDate('purchase_date', '<=', $to);
            if ($supplier) $query->where('supplier_name', 'like', '%' . $supplier . '%');

            $purchases = $query->get();
            $pdf = Pdf::loadView('exports.purchases_pdf', compact('purchases', 'from', 'to', 'supplier'));
            return $pdf->download('Laporan_Pembelian_MAYOKA_' . date('Ymd_His') . '.pdf');
        }

        return Excel::download(new PurchasesExport($from, $to, $supplier), 'Laporan_Pembelian_MAYOKA_' . date('Ymd_His') . '.xlsx');
    }

    /**
     * Store a new purchase and update stock.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'supplier_name' => 'nullable|string|max:150',
            'purchase_date' => 'required|date',
            'payment_status' => 'required|in:paid,unpaid',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.unit_name' => 'nullable|string|max:20',
            'items.*.base_multiplier' => 'nullable|integer|min:1',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        $purchase = DB::transaction(function () use ($request) {
            // Generate purchase number: PUR-YYYYMMDD-XXXX
            $today = now()->format('Ymd');
            $count = Purchase::whereDate('purchase_date', now()->toDateString())->count() + 1;
            $purchaseNumber = sprintf('PUR-%s-%04d', $today, $count);

            $totalAmount = 0;
            $items = [];

            foreach ($request->items as $item) {
                $subtotal = $item['qty'] * $item['unit_price'];
                $totalAmount += $subtotal;
                $items[] = [
                    'product_id' => $item['product_id'],
                    'unit_name' => $item['unit_name'] ?? 'PCS',
                    'base_multiplier' => $item['base_multiplier'] ?? 1,
                    'qty' => $item['qty'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $subtotal,
                ];
            }

            $purchase = Purchase::create([
                'user_id' => auth()->id(),
                'purchase_number' => $purchaseNumber,
                'supplier_name' => $request->supplier_name,
                'purchase_date' => $request->purchase_date,
                'total_amount' => $totalAmount,
                'payment_status' => $request->payment_status,
                'notes' => $request->notes,
            ]);

            foreach ($items as $item) {
                $purchase->items()->create($item);

                // Update product stock
                $product = Product::find($item['product_id']);
                if ($product && $product->type === 'barang') {
                    $stockAddition = $item['qty'] * $item['base_multiplier'];
                    $product->increment('stock', $stockAddition);

                    // Update cost_price to latest purchase price (per base unit)
                    $costPerBaseUnit = $item['unit_price'] / $item['base_multiplier'];
                    $product->update(['cost_price' => $costPerBaseUnit]);

                    // Record stock movement
                    StockMovement::create([
                        'product_id' => $product->id,
                        'type' => 'in',
                        'qty' => $stockAddition,
                        'reference' => $purchaseNumber,
                        'notes' => 'Pembelian dari ' . ($request->supplier_name ?: 'supplier'),
                        'user_id' => auth()->id(),
                    ]);
                }
            }

            return $purchase->load('items.product:id,name');
        });

        event(new \App\Events\DashboardUpdated());
        foreach ($request->items as $item) {
            $product = Product::find($item['product_id']);
            if ($product && $product->type === 'barang') {
                event(new \App\Events\ProductStockUpdated($product->id, $product->stock));
            }
        }

        return response()->json([
            'message' => 'Pembelian berhasil disimpan.',
            'purchase' => $purchase,
        ], 201);
    }

    /**
     * Show a single purchase.
     */
    public function show(Purchase $purchase): JsonResponse
    {
        return response()->json([
            'purchase' => $purchase->load(['user:id,name', 'items.product:id,name']),
        ]);
    }

    /**
     * Mark a purchase as paid.
     */
    public function markAsPaid(Purchase $purchase): JsonResponse
    {
        if ($purchase->payment_status === 'paid') {
            return response()->json(['message' => 'Pembelian sudah lunas.'], 400);
        }

        $purchase->update(['payment_status' => 'paid']);
        event(new \App\Events\DashboardUpdated());

        return response()->json(['message' => 'Status pembelian berhasil diubah menjadi Lunas.']);
    }

    /**
     * Delete a purchase (only recent, no stock reversal for simplicity).
     */
    public function destroy(Purchase $purchase): JsonResponse
    {
        $purchase->delete();

        return response()->json(['message' => 'Data pembelian dihapus.']);
    }
}
