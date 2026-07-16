<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $categories = Category::pluck('id', 'name');

        $products = [
            // === ATK ===
            ['category' => 'ATK', 'name' => 'Pulpen Pilot G1 Hitam', 'barcode' => '8991111110011', 'type' => 'barang', 'price' => 4000, 'wholesale_price' => 3500, 'wholesale_min_qty' => 12, 'cost_price' => 2500, 'stock' => 100, 'min_stock' => 20, 'unit' => 'pcs'],
            ['category' => 'ATK', 'name' => 'Pulpen Pilot G1 Biru', 'barcode' => '8991111110012', 'type' => 'barang', 'price' => 4000, 'wholesale_price' => 3500, 'wholesale_min_qty' => 12, 'cost_price' => 2500, 'stock' => 80, 'min_stock' => 20, 'unit' => 'pcs'],
            ['category' => 'ATK', 'name' => 'Pulpen Pilot G1 Merah', 'barcode' => '8991111110013', 'type' => 'barang', 'price' => 4000, 'wholesale_price' => 3500, 'wholesale_min_qty' => 12, 'cost_price' => 2500, 'stock' => 50, 'min_stock' => 20, 'unit' => 'pcs'],
            ['category' => 'ATK', 'name' => 'Pensil 2B Faber-Castell', 'barcode' => '8991111120011', 'type' => 'barang', 'price' => 3000, 'wholesale_price' => 2500, 'wholesale_min_qty' => 12, 'cost_price' => 1800, 'stock' => 120, 'min_stock' => 30, 'unit' => 'pcs'],
            ['category' => 'ATK', 'name' => 'Penghapus Staedtler', 'barcode' => '8991111120021', 'type' => 'barang', 'price' => 2500, 'wholesale_price' => 0, 'wholesale_min_qty' => 0, 'cost_price' => 1500, 'stock' => 60, 'min_stock' => 15, 'unit' => 'pcs'],
            ['category' => 'ATK', 'name' => 'Penggaris Besi 30cm', 'barcode' => '8991111120031', 'type' => 'barang', 'price' => 5000, 'wholesale_price' => 4500, 'wholesale_min_qty' => 10, 'cost_price' => 3000, 'stock' => 40, 'min_stock' => 10, 'unit' => 'pcs'],
            ['category' => 'ATK', 'name' => 'Tip-X Correction Pen', 'barcode' => '8991111130011', 'type' => 'barang', 'price' => 6000, 'wholesale_price' => 5500, 'wholesale_min_qty' => 10, 'cost_price' => 3500, 'stock' => 45, 'min_stock' => 10, 'unit' => 'pcs'],
            ['category' => 'ATK', 'name' => 'Spidol Snowman Hitam', 'barcode' => '8991111130021', 'type' => 'barang', 'price' => 5000, 'cost_price' => 3000, 'stock' => 50, 'min_stock' => 10, 'unit' => 'pcs'],
            ['category' => 'ATK', 'name' => 'Stabilo Warna Kuning', 'barcode' => '8991111130031', 'type' => 'barang', 'price' => 7000, 'cost_price' => 4500, 'stock' => 35, 'min_stock' => 10, 'unit' => 'pcs'],
            ['category' => 'ATK', 'name' => 'Map Plastik Transparan L', 'barcode' => '8991111140011', 'type' => 'barang', 'price' => 2000, 'cost_price' => 1200, 'stock' => 200, 'min_stock' => 50, 'unit' => 'pcs'],
            ['category' => 'ATK', 'name' => 'Map Kertas Folio Coklat', 'barcode' => '8991111140021', 'type' => 'barang', 'price' => 1500, 'cost_price' => 800, 'stock' => 150, 'min_stock' => 40, 'unit' => 'pcs'],
            ['category' => 'ATK', 'name' => 'Binder Clip Kecil (1 box)', 'barcode' => '8991111150011', 'type' => 'barang', 'price' => 5000, 'cost_price' => 3000, 'stock' => 30, 'min_stock' => 10, 'unit' => 'box'],
            ['category' => 'ATK', 'name' => 'Binder Clip Besar (1 box)', 'barcode' => '8991111150012', 'type' => 'barang', 'price' => 8000, 'cost_price' => 5000, 'stock' => 25, 'min_stock' => 10, 'unit' => 'box'],
            ['category' => 'ATK', 'name' => 'Stapler Kecil + Isi', 'barcode' => '8991111160011', 'type' => 'barang', 'price' => 15000, 'cost_price' => 9000, 'stock' => 20, 'min_stock' => 5, 'unit' => 'pcs'],
            ['category' => 'ATK', 'name' => 'Isi Staples No.10 (1 box)', 'barcode' => '8991111160021', 'type' => 'barang', 'price' => 3000, 'cost_price' => 1800, 'stock' => 50, 'min_stock' => 15, 'unit' => 'box'],
            ['category' => 'ATK', 'name' => 'Lem Kertas UHU Stick 8g', 'barcode' => '8991111170011', 'type' => 'barang', 'price' => 5000, 'cost_price' => 3200, 'stock' => 40, 'min_stock' => 10, 'unit' => 'pcs'],
            ['category' => 'ATK', 'name' => 'Selotip Bening Kecil', 'barcode' => '8991111170021', 'type' => 'barang', 'price' => 3000, 'cost_price' => 1800, 'stock' => 60, 'min_stock' => 15, 'unit' => 'pcs'],
            ['category' => 'ATK', 'name' => 'Gunting Kenko', 'barcode' => '8991111180011', 'type' => 'barang', 'price' => 12000, 'cost_price' => 7500, 'stock' => 15, 'min_stock' => 5, 'unit' => 'pcs'],
            ['category' => 'ATK', 'name' => 'Cutter Besar Kenko', 'barcode' => '8991111180021', 'type' => 'barang', 'price' => 10000, 'cost_price' => 6000, 'stock' => 15, 'min_stock' => 5, 'unit' => 'pcs'],

            // === Kertas ===
            ['category' => 'Kertas', 'name' => 'Kertas HVS A4 70gsm (1 rim)', 'barcode' => '8992222210011', 'type' => 'barang', 'price' => 45000, 'cost_price' => 35000, 'stock' => 25, 'min_stock' => 5, 'unit' => 'rim'],
            ['category' => 'Kertas', 'name' => 'Kertas HVS A4 80gsm (1 rim)', 'barcode' => '8992222210012', 'type' => 'barang', 'price' => 52000, 'cost_price' => 40000, 'stock' => 20, 'min_stock' => 5, 'unit' => 'rim'],
            ['category' => 'Kertas', 'name' => 'Kertas HVS F4 70gsm (1 rim)', 'barcode' => '8992222210021', 'type' => 'barang', 'price' => 48000, 'cost_price' => 37000, 'stock' => 15, 'min_stock' => 5, 'unit' => 'rim'],
            ['category' => 'Kertas', 'name' => 'Kertas HVS F4 80gsm (1 rim)', 'barcode' => '8992222210022', 'type' => 'barang', 'price' => 55000, 'cost_price' => 42000, 'stock' => 12, 'min_stock' => 5, 'unit' => 'rim'],
            ['category' => 'Kertas', 'name' => 'Kertas HVS A3 80gsm (1 rim)', 'barcode' => '8992222210031', 'type' => 'barang', 'price' => 95000, 'cost_price' => 75000, 'stock' => 8, 'min_stock' => 3, 'unit' => 'rim'],
            ['category' => 'Kertas', 'name' => 'Kertas Foto Glossy A4 (20 lbr)', 'barcode' => '8992222220011', 'type' => 'barang', 'price' => 25000, 'cost_price' => 16000, 'stock' => 15, 'min_stock' => 5, 'unit' => 'pak'],
            ['category' => 'Kertas', 'name' => 'Amplop Putih Polos (1 pak)', 'barcode' => '8992222230011', 'type' => 'barang', 'price' => 8000, 'cost_price' => 5000, 'stock' => 30, 'min_stock' => 10, 'unit' => 'pak'],
            ['category' => 'Kertas', 'name' => 'Amplop Coklat Folio (1 pak)', 'barcode' => '8992222230021', 'type' => 'barang', 'price' => 15000, 'cost_price' => 10000, 'stock' => 20, 'min_stock' => 5, 'unit' => 'pak'],

            // === Jasa Cetak ===
            ['category' => 'Jasa Cetak', 'name' => 'Cetak Foto 2x3 (per lembar)', 'barcode' => null, 'type' => 'jasa', 'price' => 1000, 'cost_price' => 400, 'stock' => 0, 'min_stock' => 0, 'unit' => 'lbr'],
            ['category' => 'Jasa Cetak', 'name' => 'Cetak Foto 3x4 (per lembar)', 'barcode' => null, 'type' => 'jasa', 'price' => 1500, 'cost_price' => 600, 'stock' => 0, 'min_stock' => 0, 'unit' => 'lbr'],
            ['category' => 'Jasa Cetak', 'name' => 'Cetak Foto 4x6 (per lembar)', 'barcode' => null, 'type' => 'jasa', 'price' => 2000, 'cost_price' => 800, 'stock' => 0, 'min_stock' => 0, 'unit' => 'lbr'],
            ['category' => 'Jasa Cetak', 'name' => 'Cetak Banner A3 (per lembar)', 'barcode' => null, 'type' => 'jasa', 'price' => 15000, 'cost_price' => 8000, 'stock' => 0, 'min_stock' => 0, 'unit' => 'lbr'],
            ['category' => 'Jasa Cetak', 'name' => 'Scan Dokumen (per halaman)', 'barcode' => null, 'type' => 'jasa', 'price' => 2000, 'cost_price' => 0, 'stock' => 0, 'min_stock' => 0, 'unit' => 'hal'],

            // === Jasa Jilid ===
            ['category' => 'Jasa Jilid', 'name' => 'Jilid Hardcover', 'barcode' => null, 'type' => 'jasa', 'price' => 25000, 'cost_price' => 12000, 'stock' => 0, 'min_stock' => 0, 'unit' => 'pcs'],
            ['category' => 'Jasa Jilid', 'name' => 'Jilid Soft Cover', 'barcode' => null, 'type' => 'jasa', 'price' => 15000, 'cost_price' => 7000, 'stock' => 0, 'min_stock' => 0, 'unit' => 'pcs'],

            // === Lainnya ===
            ['category' => 'Lainnya', 'name' => 'Tinta Printer Epson Hitam 100ml', 'barcode' => '8993333310011', 'type' => 'barang', 'price' => 25000, 'cost_price' => 15000, 'stock' => 10, 'min_stock' => 3, 'unit' => 'btl'],
            ['category' => 'Lainnya', 'name' => 'Tinta Printer Epson Cyan 100ml', 'barcode' => '8993333310012', 'type' => 'barang', 'price' => 25000, 'cost_price' => 15000, 'stock' => 8, 'min_stock' => 3, 'unit' => 'btl'],
            ['category' => 'Lainnya', 'name' => 'Tinta Printer Epson Magenta 100ml', 'barcode' => '8993333310013', 'type' => 'barang', 'price' => 25000, 'cost_price' => 15000, 'stock' => 8, 'min_stock' => 3, 'unit' => 'btl'],
            ['category' => 'Lainnya', 'name' => 'Tinta Printer Epson Yellow 100ml', 'barcode' => '8993333310014', 'type' => 'barang', 'price' => 25000, 'cost_price' => 15000, 'stock' => 8, 'min_stock' => 3, 'unit' => 'btl'],
            ['category' => 'Lainnya', 'name' => 'Plastik Laminating A4 (100 lbr)', 'barcode' => '8993333320011', 'type' => 'barang', 'price' => 35000, 'cost_price' => 22000, 'stock' => 10, 'min_stock' => 3, 'unit' => 'pak'],
            ['category' => 'Lainnya', 'name' => 'Spiral Jilid Plastik 10mm (50 pcs)', 'barcode' => '8993333330011', 'type' => 'barang', 'price' => 20000, 'cost_price' => 12000, 'stock' => 10, 'min_stock' => 3, 'unit' => 'pak'],
            ['category' => 'Lainnya', 'name' => 'CD-R Kosong GT-PRO', 'barcode' => '8993333340011', 'type' => 'barang', 'price' => 4000, 'cost_price' => 2500, 'stock' => 30, 'min_stock' => 10, 'unit' => 'pcs'],
            ['category' => 'Lainnya', 'name' => 'Flashdisk 8GB', 'barcode' => '8993333340021', 'type' => 'barang', 'price' => 35000, 'cost_price' => 22000, 'stock' => 10, 'min_stock' => 3, 'unit' => 'pcs'],
        ];

        foreach ($products as $item) {
            $product = Product::create([
                'branch_id' => 1,
                'category_id' => $categories[$item['category']],
                'name' => $item['name'],
                'barcode' => $item['barcode'],
                'type' => $item['type'],
                'cost_price' => $item['cost_price'],
                'stock' => $item['stock'],
                'min_stock' => $item['min_stock'],
            ]);

            $product->units()->create([
                'branch_id' => 1,
                'level' => 1,
                'unit_name' => strtoupper($item['unit']),
                'qty_per_previous' => 1,
                'base_multiplier' => 1,
                'price_h1' => $item['price'],
                'price_h2' => $item['wholesale_price'] ?? $item['price'],
                'price_h3' => $item['wholesale_price'] ?? $item['price'],
            ]);

            if (strtoupper($item['unit']) === 'PCS' && $item['type'] === 'barang') {
                $h1 = $item['price'];
                $h2 = isset($item['wholesale_price']) && $item['wholesale_price'] > 0 ? $item['wholesale_price'] : $item['price'];

                $product->units()->create([
                    'branch_id' => 1,
                    'level' => 2,
                    'unit_name' => 'PCK',
                    'qty_per_previous' => 12,
                    'base_multiplier' => 12,
                    'price_h1' => $h1 * 12 * 0.95,
                    'price_h2' => $h2 * 12 * 0.95,
                    'price_h3' => $h2 * 12 * 0.90,
                ]);

                $product->units()->create([
                    'branch_id' => 1,
                    'level' => 3,
                    'unit_name' => 'DOS',
                    'qty_per_previous' => 10,
                    'base_multiplier' => 120,
                    'price_h1' => $h1 * 120 * 0.90,
                    'price_h2' => $h2 * 120 * 0.90,
                    'price_h3' => $h2 * 120 * 0.85,
                ]);
            }
        }
    }
}
