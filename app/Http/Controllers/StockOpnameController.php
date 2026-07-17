<?php

namespace App\Http\Controllers;

use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class StockOpnameController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 20);
        $query = StockOpname::with('user:id,name')->orderBy('id', 'desc');

        if ($request->filled('date_from')) {
            $query->whereDate('opname_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('opname_date', '<=', $request->date_to);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->paginate($perPage));
    }

    public function show(StockOpname $stockOpname): JsonResponse
    {
        $stockOpname->load(['user:id,name', 'items.product:id,name,stock']);
        return response()->json(['stock_opname' => $stockOpname]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'opname_date' => 'required|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.physical_stock' => 'required|integer|min:0',
        ]);

        try {
            DB::beginTransaction();

            $lastOpname = StockOpname::withoutGlobalScope('branch')->orderBy('id', 'desc')->first();
            $nextId = $lastOpname ? $lastOpname->id + 1 : 1;
            $opnameNumber = 'OPN-' . date('Ymd', strtotime($request->opname_date)) . '-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

            $opname = StockOpname::create([
                'opname_number' => $opnameNumber,
                'opname_date' => $request->opname_date,
                'user_id' => auth()->id(),
                'status' => 'draft',
                'notes' => $request->notes,
            ]);

            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);
                if ($product) {
                    $systemStock = $product->stock;
                    $physicalStock = $item['physical_stock'];
                    $difference = $physicalStock - $systemStock;

                    StockOpnameItem::create([
                        'stock_opname_id' => $opname->id,
                        'product_id' => $product->id,
                        'system_stock' => $systemStock,
                        'physical_stock' => $physicalStock,
                        'difference' => $difference,
                        'notes' => $item['notes'] ?? null,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Stok Opname (Draft) berhasil disimpan.',
                'stock_opname' => $opname->load('items.product'),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal menyimpan data: ' . $e->getMessage()], 422);
        }
    }

    public function update(Request $request, StockOpname $stockOpname): JsonResponse
    {
        if ($stockOpname->status === 'completed') {
            return response()->json(['message' => 'Stok Opname yang sudah final tidak dapat diubah.'], 400);
        }

        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|exists:stock_opname_items,id',
            'items.*.physical_stock' => 'required|integer|min:0',
        ]);

        try {
            DB::beginTransaction();

            $stockOpname->update([
                'notes' => $request->notes ?? $stockOpname->notes,
            ]);

            foreach ($request->items as $itemData) {
                $item = StockOpnameItem::where('id', $itemData['id'])
                            ->where('stock_opname_id', $stockOpname->id)
                            ->first();

                if ($item) {
                    $difference = $itemData['physical_stock'] - $item->system_stock;
                    $item->update([
                        'physical_stock' => $itemData['physical_stock'],
                        'difference' => $difference,
                        'notes' => $itemData['notes'] ?? $item->notes,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Draft Stok Opname berhasil diperbarui.',
                'stock_opname' => $stockOpname->fresh('items.product'),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal memperbarui data: ' . $e->getMessage()], 422);
        }
    }

    public function complete(Request $request, StockOpname $stockOpname): JsonResponse
    {
        if ($stockOpname->status === 'completed') {
            return response()->json(['message' => 'Stok Opname sudah final.'], 400);
        }

        try {
            DB::beginTransaction();

            $items = $stockOpname->items()->with('product')->get();

            foreach ($items as $item) {
                if ($item->difference !== 0) {
                    $product = $item->product;
                    
                    // The system stock at the time of finalization might be different from the time draft was created.
                    // We must apply the difference to the current stock.
                    // E.g., if draft said -2, and current stock is 10, new stock is 8.
                    // Alternatively, we set the stock exactly to physical_stock if we assume no sales happened.
                    // In a live system, applying the difference is safer.
                    
                    $product->increment('stock', $item->difference);

                    StockMovement::create([
                        'product_id' => $product->id,
                        'type' => 'adjustment',
                        'qty' => $item->difference,
                        'reference' => $stockOpname->opname_number,
                        'notes' => 'Stok Opname' . ($item->notes ? ': ' . $item->notes : ''),
                        'user_id' => auth()->id(),
                    ]);
                }
            }

            $stockOpname->update(['status' => 'completed']);

            DB::commit();

            event(new \App\Events\DashboardUpdated());
            foreach ($items as $item) {
                if ($item->difference !== 0) {
                    event(new \App\Events\ProductStockUpdated($item->product_id, $item->product->stock));
                }
            }

            return response()->json(['message' => 'Stok Opname berhasil difinalisasi. Stok produk telah diperbarui.']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal memfinalisasi Stok Opname: ' . $e->getMessage()], 422);
        }
    }

    public function export(Request $request)
    {
        // For simplicity, returning a CSV response. In real app, might use Maatwebsite Excel.
        $stockOpname = StockOpname::with('items.product')->findOrFail($request->id);
        
        $filename = "StokOpname_{$stockOpname->opname_number}.csv";
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];
        
        $callback = function() use ($stockOpname) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['No. Opname', $stockOpname->opname_number]);
            fputcsv($file, ['Tanggal', $stockOpname->opname_date->format('Y-m-d')]);
            fputcsv($file, ['Status', $stockOpname->status]);
            fputcsv($file, ['']);
            fputcsv($file, ['ID Produk', 'Nama Produk', 'Stok Sistem', 'Stok Fisik', 'Selisih', 'Catatan']);
            
            foreach ($stockOpname->items as $item) {
                fputcsv($file, [
                    $item->product->id,
                    $item->product->name,
                    $item->system_stock,
                    $item->physical_stock,
                    $item->difference,
                    $item->notes
                ]);
            }
            fclose($file);
        };
        
        return response()->stream($callback, 200, $headers);
    }
}
