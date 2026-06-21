<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Shift;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    /**
     * Process checkout — create transaction, reduce stock, record movements.
     */
    public function checkout(Request $request): JsonResponse
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.itemType' => 'required|in:print,product',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.unitPrice' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.addons' => 'nullable|array',
            'payment_method' => 'required|in:cash,qris,transfer',
            'cash_paid' => 'required_if:payment_method,cash|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $user = $request->user();

        // Check active shift
        $shift = Shift::active()->where('user_id', $user->id)->first();
        if (!$shift) {
            return response()->json(['message' => 'Anda belum membuka shift.'], 422);
        }

        try {
            $transaction = DB::transaction(function () use ($request, $user, $shift) {
                $items = $request->items;
                $transactionDiscount = $request->discount ?? 0;

                // Calculate totals
                $subtotal = 0;
                foreach ($items as $item) {
                    $itemTotal = $item['qty'] * $item['unitPrice'] - ($item['discount'] ?? 0);
                    $addonsTotal = 0;
                    foreach ($item['addons'] ?? [] as $addon) {
                        $addonsTotal += $addon['price'] * ($addon['qty'] ?? 1);
                    }
                    $subtotal += $itemTotal + $addonsTotal;
                }

                $total = $subtotal - $transactionDiscount;

                // Validate cash payment
                if ($request->payment_method === 'cash') {
                    if ($request->cash_paid < $total) {
                        throw new \Exception('Uang yang dibayarkan kurang dari total.');
                    }
                }

                $cashPaid = $request->payment_method === 'cash' ? $request->cash_paid : $total;
                $cashChange = $request->payment_method === 'cash' ? $cashPaid - $total : 0;

                // Create transaction
                $transaction = Transaction::create([
                    'invoice_number' => Transaction::generateInvoiceNumber(),
                    'user_id' => $user->id,
                    'shift_id' => $shift->id,
                    'subtotal' => $subtotal,
                    'discount' => $transactionDiscount,
                    'total' => $total,
                    'payment_method' => $request->payment_method,
                    'cash_paid' => $cashPaid,
                    'cash_change' => $cashChange,
                    'notes' => $request->notes,
                ]);

                // Create transaction items & handle stock
                foreach ($items as $item) {
                    $itemSubtotal = $item['qty'] * $item['unitPrice'] - ($item['discount'] ?? 0);

                    $txItemData = [
                        'transaction_id' => $transaction->id,
                        'item_type' => $item['itemType'],
                        'description' => $item['description'] ?? '',
                        'qty' => $item['qty'],
                        'unit_price' => $item['unitPrice'],
                        'cost_price' => $item['costPrice'] ?? $item['costPerSheet'] ?? 0,
                        'discount' => $item['discount'] ?? 0,
                        'subtotal' => $itemSubtotal,
                    ];

                    // Set FK references
                    if ($item['itemType'] === 'product') {
                        $txItemData['product_id'] = $item['productId'] ?? null;

                        // Reduce stock for barang type
                        if (!empty($item['productId'])) {
                            $product = Product::find($item['productId']);
                            if ($product && $product->type === 'barang') {
                                if ($product->stock < $item['qty']) {
                                    throw new \Exception("Stok {$product->name} tidak mencukupi (sisa: {$product->stock}).");
                                }
                                $product->decrement('stock', $item['qty']);

                                StockMovement::create([
                                    'product_id' => $product->id,
                                    'type' => 'out',
                                    'qty' => -$item['qty'],
                                    'reference' => $transaction->invoice_number,
                                    'notes' => 'Penjualan',
                                    'user_id' => $user->id,
                                ]);
                            }
                        }
                    } elseif ($item['itemType'] === 'print') {
                        $txItemData['print_price_id'] = $item['printPriceId'] ?? null;
                    }

                    $txItem = TransactionItem::create($txItemData);

                    // Process addons (child items)
                    foreach ($item['addons'] ?? [] as $addon) {
                        TransactionItem::create([
                            'transaction_id' => $transaction->id,
                            'item_type' => 'addon',
                            'addon_service_id' => $addon['addonServiceId'] ?? null,
                            'parent_item_id' => $txItem->id,
                            'description' => $addon['name'] ?? '',
                            'qty' => $addon['qty'] ?? 1,
                            'unit_price' => $addon['price'],
                            'cost_price' => 0,
                            'discount' => 0,
                            'subtotal' => $addon['price'] * ($addon['qty'] ?? 1),
                        ]);
                    }
                }

                return $transaction;
            });

            return response()->json([
                'message' => 'Transaksi berhasil!',
                'transaction' => $transaction->load('items'),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get transaction detail (for receipt reprint).
     */
    public function show(Transaction $transaction): JsonResponse
    {
        return response()->json([
            'transaction' => $transaction->load(['items', 'user:id,name']),
        ]);
    }

    /**
     * Get receipt data formatted for printing.
     */
    public function receipt(Transaction $transaction): JsonResponse
    {
        $transaction->load(['items', 'user:id,name']);

        $mainItems = $transaction->items->where('item_type', '!=', 'addon');
        $receiptItems = [];

        foreach ($mainItems as $item) {
            $entry = [
                'description' => $item->description,
                'qty' => $item->qty,
                'unit_price' => $item->unit_price,
                'subtotal' => $item->subtotal,
                'addons' => [],
            ];

            // Find child addons
            $addons = $transaction->items->where('parent_item_id', $item->id);
            foreach ($addons as $addon) {
                $entry['addons'][] = [
                    'description' => $addon->description,
                    'price' => $addon->subtotal,
                ];
            }

            $receiptItems[] = $entry;
        }

        return response()->json([
            'receipt' => [
                'store_name' => 'MAYOKA FOTOKOPI & ATK',
                'store_address' => 'Jl. Bondowoso - Jember, Utara Sungai, Dadapan, Kec. Grujugan, Kabupaten Bondowoso, Jawa Timur 68261',
                'invoice_number' => $transaction->invoice_number,
                'date' => $transaction->created_at->format('d/m/Y H:i'),
                'cashier' => $transaction->user->name,
                'items' => $receiptItems,
                'subtotal' => $transaction->subtotal,
                'discount' => $transaction->discount,
                'total' => $transaction->total,
                'payment_method' => $transaction->payment_method,
                'cash_paid' => $transaction->cash_paid,
                'cash_change' => $transaction->cash_change,
            ],
        ]);
    }

    /**
     * List recent transactions (owner + kasir own transactions).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Transaction::with(['user:id,name', 'items']);

        // Check for global search by invoice number
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where('invoice_number', 'like', "%$search%");
            // Notice: We bypass the Kasir restriction here so they can find ANY transaction by invoice
        } else {
            // Kasir only sees own transactions by default
            if ($request->user()->isKasir()) {
                $query->where('user_id', $request->user()->id);
            }
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $transactions = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($transactions);
    }
}
