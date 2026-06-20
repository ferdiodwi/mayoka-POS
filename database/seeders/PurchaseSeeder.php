<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Seeder;

class PurchaseSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::where('role', 'owner')->first();
        if (!$owner) return;

        $products = Product::where('type', 'barang')->get();
        if ($products->isEmpty()) return;

        $suppliers = [
            'Toko Jaya Abadi',
            'CV Sumber Makmur',
            'UD Cahaya Ilmu',
            'Toko Harapan Baru',
            null, // tanpa supplier
        ];

        // Buat 5 pembelian historis (30 hari terakhir)
        for ($i = 0; $i < 5; $i++) {
            $date = now()->subDays(rand(1, 30));
            $purchaseNumber = sprintf('PUR-%s-%04d', $date->format('Ymd'), $i + 1);

            $purchase = Purchase::create([
                'user_id' => $owner->id,
                'purchase_number' => $purchaseNumber,
                'supplier_name' => $suppliers[array_rand($suppliers)],
                'purchase_date' => $date->toDateString(),
                'total_amount' => 0,
                'payment_status' => $i < 4 ? 'paid' : 'unpaid',
                'notes' => $i === 0 ? 'Restock awal bulan' : ($i === 2 ? 'Restock kertas' : null),
            ]);

            // 2-5 item per purchase
            $itemCount = rand(2, 5);
            $selectedProducts = $products->random($itemCount);
            $totalAmount = 0;

            foreach ($selectedProducts as $product) {
                $qty = rand(1, 10);
                $unitPrice = (float) $product->cost_price;
                // Harga beli bisa sedikit berbeda dari cost_price (±10%)
                $variation = $unitPrice * (rand(-10, 5) / 100);
                $unitPrice = max(1000, round(($unitPrice + $variation) / 100) * 100);
                $subtotal = $qty * $unitPrice;
                $totalAmount += $subtotal;

                $purchase->items()->create([
                    'product_id' => $product->id,
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ]);

                // Record stock movement
                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => 'in',
                    'qty' => $qty,
                    'reference' => $purchaseNumber,
                    'notes' => 'Pembelian dari ' . ($purchase->supplier_name ?: 'supplier'),
                    'user_id' => $owner->id,
                ]);
            }

            $purchase->update(['total_amount' => $totalAmount]);
        }
    }
}
