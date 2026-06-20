<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Shift;
use App\Models\Product;
use App\Models\PrintPrice;
use App\Models\AddonService;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\StockMovement;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class TransactionSeeder extends Seeder
{
    public function run(): void
    {
        $kasir = User::where('role', 'kasir')->first();
        if (!$kasir) return;

        $products = Product::where('type', 'barang')->get();
        $printPrices = PrintPrice::all();
        $addons = AddonService::all();

        if ($products->isEmpty() || $printPrices->isEmpty()) return;

        $paymentMethods = ['cash', 'cash', 'cash', 'qris', 'transfer']; // Bias ke cash

        // Buat data untuk 30 hari ke belakang
        for ($daysAgo = 30; $daysAgo >= 0; $daysAgo--) {
            $date = Carbon::now()->subDays($daysAgo);
            
            // 1 shift per hari (08:00 - 17:00)
            $shiftStart = $date->copy()->setTime(8, 0, 0);
            $shiftEnd = $date->copy()->setTime(17, 0, 0);
            
            $shift = Shift::create([
                'user_id' => $kasir->id,
                'started_at' => $shiftStart,
                'ended_at' => $shiftEnd,
                'cash_start' => 200000,
                'cash_end' => 0, // Akan diupdate di bawah
                'cash_expected' => 200000,
                'cash_difference' => 0,
                'status' => 'closed', // Tutup shift di masa lalu
                'notes' => 'Shift otomatis dari seeder',
            ]);

            // Jika ini adalah hari ini, biarkan shift tetap open dan hapus ended_at
            if ($daysAgo === 0) {
                $shift->update([
                    'status' => 'open',
                    'ended_at' => null,
                ]);
            }

            // Generate 5-20 transaksi per shift
            $numTransactions = rand(5, 20);
            $shiftCashExpected = 200000;

            for ($t = 0; $t < $numTransactions; $t++) {
                // Waktu transaksi acak dalam rentang shift
                $txTime = $shiftStart->copy()->addMinutes(rand(10, 500));
                if ($daysAgo === 0 && $txTime->isAfter(now())) {
                    $txTime = now()->subMinutes(rand(1, 60)); // Jangan sampai di masa depan
                }

                $invoiceNumber = $this->generateInvoiceNumber($txTime, $t + 1);
                $paymentMethod = $paymentMethods[array_rand($paymentMethods)];
                
                $transaction = Transaction::create([
                    'invoice_number' => $invoiceNumber,
                    'user_id' => $kasir->id,
                    'shift_id' => $shift->id,
                    'subtotal' => 0,
                    'discount' => 0,
                    'total' => 0,
                    'payment_method' => $paymentMethod,
                    'cash_paid' => 0,
                    'cash_change' => 0,
                    'notes' => null,
                    'created_at' => $txTime,
                    'updated_at' => $txTime,
                ]);

                $subtotal = 0;
                $numItems = rand(1, 4);

                for ($i = 0; $i < $numItems; $i++) {
                    $type = rand(1, 10) > 3 ? 'product' : 'print'; // 70% product, 30% print

                    if ($type === 'product') {
                        $product = $products->random();
                        $qty = rand(1, 5);
                        $itemSubtotal = $product->price * $qty;
                        $subtotal += $itemSubtotal;

                        TransactionItem::create([
                            'transaction_id' => $transaction->id,
                            'item_type' => 'product',
                            'product_id' => $product->id,
                            'description' => $product->name,
                            'qty' => $qty,
                            'unit_price' => $product->price,
                            'cost_price' => $product->cost_price,
                            'discount' => 0,
                            'subtotal' => $itemSubtotal,
                            'created_at' => $txTime,
                            'updated_at' => $txTime,
                        ]);

                        // Update Stock & Movement
                        if ($product->stock >= $qty) {
                            $product->decrement('stock', $qty);
                            StockMovement::create([
                                'product_id' => $product->id,
                                'type' => 'out',
                                'qty' => -$qty,
                                'reference' => $invoiceNumber,
                                'notes' => 'Penjualan',
                                'user_id' => $kasir->id,
                                'created_at' => $txTime,
                                'updated_at' => $txTime,
                            ]);
                        }
                    } else {
                        $print = $printPrices->random();
                        $qty = rand(5, 50); // Jumlah halaman
                        $itemSubtotal = $print->price_per_sheet * $qty;
                        
                        $desc = "Print {$print->paper_size} " . strtoupper($print->color_type) . " " . ucfirst($print->side_type);
                        
                        $printItem = TransactionItem::create([
                            'transaction_id' => $transaction->id,
                            'item_type' => 'print',
                            'print_price_id' => $print->id,
                            'description' => $desc,
                            'qty' => $qty,
                            'unit_price' => $print->price_per_sheet,
                            'cost_price' => $print->cost_per_sheet,
                            'discount' => 0,
                            'subtotal' => $itemSubtotal,
                            'created_at' => $txTime,
                            'updated_at' => $txTime,
                        ]);
                        $subtotal += $itemSubtotal;

                        // Addon service (20% chance)
                        if (rand(1, 100) <= 20) {
                            $addon = $addons->random();
                            $addonQty = rand(1, 2);
                            $addonSubtotal = $addon->price * $addonQty;
                            
                            TransactionItem::create([
                                'transaction_id' => $transaction->id,
                                'item_type' => 'addon',
                                'parent_item_id' => $printItem->id,
                                'addon_service_id' => $addon->id,
                                'description' => $addon->name,
                                'qty' => $addonQty,
                                'unit_price' => $addon->price,
                                'cost_price' => 0,
                                'discount' => 0,
                                'subtotal' => $addonSubtotal,
                                'created_at' => $txTime,
                                'updated_at' => $txTime,
                            ]);
                            $subtotal += $addonSubtotal;
                        }
                    }
                }

                $discount = rand(1, 10) > 8 ? 5000 : 0; // 20% kemungkinan dapat diskon
                if ($subtotal < $discount) $discount = 0;
                $total = $subtotal - $discount;

                $cashPaid = 0;
                $cashChange = 0;
                
                if ($paymentMethod === 'cash') {
                    // Logic uang pas atau lebih
                    $pecahan = [10000, 20000, 50000, 100000];
                    $cashPaid = $total;
                    if (rand(1, 2) == 1) { // 50% bayar pakai uang yang lebih besar
                        foreach ($pecahan as $p) {
                            if ($p > $total) {
                                $cashPaid = ceil($total / 50000) * 50000;
                                break;
                            }
                        }
                        if ($cashPaid < $total) $cashPaid = ceil($total / 100000) * 100000;
                    }
                    $cashChange = $cashPaid - $total;
                    $shiftCashExpected += $total;
                }

                $transaction->update([
                    'subtotal' => $subtotal,
                    'discount' => $discount,
                    'total' => $total,
                    'cash_paid' => $cashPaid,
                    'cash_change' => $cashChange,
                ]);
            }

            // Update shift final cash jika sudah closed
            if ($shift->status === 'closed') {
                $cashEnd = $shiftCashExpected; 
                // Kadang ada selisih sedikit (10% kemungkinan)
                if (rand(1, 100) <= 10) {
                    $cashEnd += (rand(-2000, 2000));
                }
                
                $shift->update([
                    'cash_expected' => $shiftCashExpected,
                    'cash_end' => $cashEnd,
                    'cash_difference' => $cashEnd - $shiftCashExpected,
                ]);
            } else {
                // Shift hari ini masih open
                $shift->update([
                    'cash_expected' => $shiftCashExpected,
                ]);
            }
        }
    }

    private function generateInvoiceNumber(Carbon $date, int $sequence): string
    {
        $dateStr = $date->format('Ymd');
        return sprintf('INV-%s-%04d', $dateStr, $sequence);
    }
}
