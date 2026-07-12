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
use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\EscposImage;

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
            'items.*.unitName' => 'nullable|string|max:20',
            'items.*.baseMultiplier' => 'nullable|integer|min:1',
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
                    'customer_id' => $request->customer_id ?? null,
                    'price_level' => $request->price_level ?? 'h1',
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
                        $txItemData['unit_name'] = $item['unitName'] ?? 'PCS';
                        $txItemData['base_multiplier'] = $item['baseMultiplier'] ?? 1;

                        // Reduce stock for barang type
                        if (!empty($item['productId'])) {
                            $product = Product::find($item['productId']);
                            if ($product && $product->type === 'barang') {
                                $stockReduction = $item['qty'] * $txItemData['base_multiplier'];
                                if ($product->stock < $stockReduction) {
                                    throw new \Exception("Stok {$product->name} tidak mencukupi (butuh: {$stockReduction}, sisa: {$product->stock}).");
                                }
                                $product->decrement('stock', $stockReduction);

                                StockMovement::create([
                                    'product_id' => $product->id,
                                    'type' => 'out',
                                    'qty' => -$stockReduction,
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

            // Dispatch events for realtime updates
            event(new \App\Events\DashboardUpdated());
            foreach ($request->items as $item) {
                if ($item['itemType'] === 'product' && isset($item['productId'])) {
                    $product = Product::find($item['productId']);
                    if ($product && $product->type === 'barang') {
                        event(new \App\Events\ProductStockUpdated($product->id, $product->stock));
                    }
                }
            }

            $receiptData = $this->buildReceiptData($transaction);

            // Try to print via raw ESC/POS
            $printError = null;
            try {
                $this->printRawReceipt($receiptData);
            } catch (\Exception $e) {
                $printError = $e->getMessage();
            }

            return response()->json([
                'message' => 'Transaksi berhasil!',
                'transaction' => $transaction->load('items'),
                'receipt' => $receiptData,
                'print_error' => $printError,
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
        return response()->json([
            'receipt' => $this->buildReceiptData($transaction),
        ]);
    }

    /**
     * Build receipt data structure for printing.
     */
    private function buildReceiptData(Transaction $transaction): array
    {
        $transaction->load(['items', 'user:id,name', 'customer']);

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

            $addons = $transaction->items->where('parent_item_id', $item->id);
            foreach ($addons as $addon) {
                $entry['addons'][] = [
                    'description' => $addon->description,
                    'price' => $addon->subtotal,
                ];
            }

            $receiptItems[] = $entry;
        }

        return [
            'store_name' => 'MAYOKA ATK',
            'store_address' => "TOKO ALAT TULIS KANTOR\nJL. JEMBER DESA TAMAN\n082 234 278 798",
            'invoice_number' => $transaction->invoice_number,
            'date' => $transaction->created_at->format('Y/m/d H:i:s'),
            'cashier' => $transaction->user->name,
            'customer_name' => $transaction->customer ? $transaction->customer->name : null,
            'customer_type' => $transaction->customer ? $transaction->customer->type : null,
            'items' => $receiptItems,
            'subtotal' => $transaction->subtotal,
            'discount' => $transaction->discount,
            'total' => $transaction->total,
            'payment_method' => $transaction->payment_method,
            'cash_paid' => $transaction->cash_paid,
            'cash_change' => $transaction->cash_change,
        ];
    }

    /**
     * Print receipt using ESC/POS directly to /dev/usb/lp0
     */
    private function printRawReceipt(array $r): void
    {
        $printerOs = env('PRINTER_OS', 'linux');
        $printerPath = env('PRINTER_PATH', '/dev/usb/lp0');

        if (strtolower($printerOs) === 'windows') {
            $connector = new WindowsPrintConnector($printerPath);
        } else {
            // Linux/Mac default
            $connector = new FilePrintConnector($printerPath);
        }
        
        $printer = new Printer($connector);

        try {
            // Header
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH | Printer::MODE_DOUBLE_HEIGHT);
            $printer->text($r['store_name'] . "\n");

            $printer->selectPrintMode();
            $printer->text($r['store_address'] . "\n");
            $printer->feed();

            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->text("Tanggal: " . $r['date'] . "\n");
            $printer->text("No     : " . $r['invoice_number'] . "\n");
            $printer->text("Kasir  : " . $r['cashier'] . "\n");
            
            if (!empty($r['customer_name']) && $r['customer_type'] === 'member') {
                $printer->text("Member : " . $r['customer_name'] . "\n");
            }
            
            $printer->text("--------------------------------\n");

            // Items
            foreach ($r['items'] as $item) {
                // Main item line
                $printer->setJustification(Printer::JUSTIFY_LEFT);
                $printer->text($item['description'] . "\n");

                // Qty x Price          Subtotal
                $priceStr = number_format($item['unit_price'], 0, ',', '.');
                $subtotalStr = number_format($item['subtotal'], 0, ',', '.');
                $qtyLine = $item['qty'] . " x " . $priceStr;

                // Calculate spacing to right-align subtotal
                $spaces = 32 - strlen($qtyLine) - strlen($subtotalStr);
                $spaces = $spaces > 0 ? $spaces : 1;
                $printer->text($qtyLine . str_repeat(" ", $spaces) . $subtotalStr . "\n");

                // Addons
                foreach ($item['addons'] as $addon) {
                    $addonPrice = number_format($addon['price'], 0, ',', '.');
                    $addonLine = " + " . $addon['description'];
                    $spaces = 32 - strlen($addonLine) - strlen($addonPrice);
                    $spaces = $spaces > 0 ? $spaces : 1;
                    $printer->text($addonLine . str_repeat(" ", $spaces) . $addonPrice . "\n");
                }
            }
            $printer->text("--------------------------------\n");

            // Totals
            $subtotalStr = number_format($r['subtotal'], 0, ',', '.');
            $spaces = 32 - 8 - strlen($subtotalStr); // 'Subtotal' = 8
            $printer->text("Subtotal" . str_repeat(" ", $spaces) . $subtotalStr . "\n");

            if ($r['discount'] > 0) {
                $discountStr = "-" . number_format($r['discount'], 0, ',', '.');
                $spaces = 32 - 6 - strlen($discountStr); // 'Diskon' = 6
                $printer->text("Diskon" . str_repeat(" ", $spaces) . $discountStr . "\n");
            }

            // Grand Total (Bold)
            $printer->setEmphasis(true);
            $totalStr = number_format($r['total'], 0, ',', '.');
            $spaces = 32 - 5 - strlen($totalStr); // 'TOTAL' = 5
            $printer->text("TOTAL" . str_repeat(" ", $spaces) . $totalStr . "\n");
            $printer->setEmphasis(false);
            $printer->text("--------------------------------\n");

            // Payment
            $payMethod = strtoupper($r['payment_method']);
            $paidStr = number_format($r['cash_paid'], 0, ',', '.');
            $payLabel = "Bayar ({$payMethod})";
            $spaces = 32 - strlen($payLabel) - strlen($paidStr);
            $printer->text($payLabel . str_repeat(" ", $spaces > 0 ? $spaces : 1) . $paidStr . "\n");

            if ($r['payment_method'] === 'cash') {
                $changeStr = number_format($r['cash_change'], 0, ',', '.');
                $spaces = 32 - 7 - strlen($changeStr); // 'Kembali' = 7
                $printer->text("Kembali" . str_repeat(" ", $spaces) . $changeStr . "\n");
            }
            $printer->text("--------------------------------\n");

            // Footer
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text("Terima Kasih Atas Kunjungan Anda");
            $printer->feed(3);

            // Cut and close
            $printer->cut();
        } finally {
            $printer->close();
        }
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
