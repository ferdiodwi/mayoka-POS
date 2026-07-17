<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\DB;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\Log;

class ProductsImport implements ToCollection, WithHeadingRow
{
    protected $userId;

    public function __construct($userId)
    {
        $this->userId = $userId;
    }

    public function collection(Collection $rows)
    {
        DB::transaction(function () use ($rows) {
            // Get current max product code to increment
            $maxCode = DB::table('products')->max(DB::raw('CAST(product_code AS UNSIGNED)'));
            $currentCode = $maxCode && (int)$maxCode >= 101 ? ((int)$maxCode + 1) : 101;

            foreach ($rows as $row) {
                // Skip if essential data is missing (Nama Produk is required)
                if (empty($row['nama_produk'])) {
                    continue;
                }

                // Handle Category
                $categoryName = trim($row['nama_kategori'] ?? 'Umum');
                $category = Category::firstOrCreate(['name' => $categoryName]);

                $type = isset($row['tipe']) && strtolower(trim($row['tipe'])) === 'jasa' ? 'jasa' : 'barang';
                $stock = $type === 'jasa' ? 0 : (int) ($row['stok'] ?? 0);
                $minStock = $type === 'jasa' ? 0 : (int) ($row['min_stok'] ?? 0);

                $barcode = isset($row['barcode']) && trim($row['barcode']) !== '' ? trim($row['barcode']) : null;

                // Check if barcode already exists to prevent duplicate entry error
                if ($barcode && Product::where('barcode', $barcode)->exists()) {
                    continue; // Skip this product if barcode already exists
                }

                // Create Product
                $product = Product::create([
                    'category_id' => $category->id,
                    'product_code' => (string) $currentCode++,
                    'name' => trim($row['nama_produk']),
                    'barcode' => $barcode,
                    'type' => $type,
                    'cost_price' => (float) ($row['harga_modal'] ?? 0),
                    'stock' => $stock,
                    'min_stock' => $minStock,
                    'is_active' => true,
                ]);

                // Create Units
                // Unit 1 (PCS)
                if (!empty($row['satuan_1']) && !empty($row['harga_s1'])) {
                    $product->units()->create([
                        'level' => 1,
                        'unit_name' => strtoupper(trim($row['satuan_1'])),
                        'qty_per_previous' => 1,
                        'base_multiplier' => 1,
                        'price_h1' => (float) $row['harga_s1'],
                        'price_h2' => (float) $row['harga_s1'],
                        'price_h3' => (float) $row['harga_s1'],
                    ]);
                } else {
                    // Fallback to default unit if nothing provided
                    $product->units()->create([
                        'level' => 1,
                        'unit_name' => 'PCS',
                        'qty_per_previous' => 1,
                        'base_multiplier' => 1,
                        'price_h1' => 0,
                        'price_h2' => 0,
                        'price_h3' => 0,
                    ]);
                }

                // Unit 2 (PCK)
                if (!empty($row['satuan_2']) && !empty($row['isi_s2']) && !empty($row['harga_s2'])) {
                    $qtyPerPrevious = (int) $row['isi_s2'];
                    $product->units()->create([
                        'level' => 2,
                        'unit_name' => strtoupper(trim($row['satuan_2'])),
                        'qty_per_previous' => $qtyPerPrevious,
                        'base_multiplier' => $qtyPerPrevious,
                        'price_h1' => (float) $row['harga_s2'],
                        'price_h2' => (float) $row['harga_s2'],
                        'price_h3' => (float) $row['harga_s2'],
                    ]);
                    
                    // Unit 3 (DOS)
                    if (!empty($row['satuan_3']) && !empty($row['isi_s3']) && !empty($row['harga_s3'])) {
                        $qtyPerPrevious3 = (int) $row['isi_s3'];
                        $product->units()->create([
                            'level' => 3,
                            'unit_name' => strtoupper(trim($row['satuan_3'])),
                            'qty_per_previous' => $qtyPerPrevious3,
                            'base_multiplier' => $qtyPerPrevious * $qtyPerPrevious3,
                            'price_h1' => (float) $row['harga_s3'],
                            'price_h2' => (float) $row['harga_s3'],
                            'price_h3' => (float) $row['harga_s3'],
                        ]);
                    }
                }

                // Record initial stock movement
                if ($type === 'barang' && $stock > 0) {
                    StockMovement::create([
                        'product_id' => $product->id,
                        'type' => 'in',
                        'qty' => $stock,
                        'reference' => 'Import Excel',
                        'notes' => 'Stok awal dari import masal Excel',
                        'user_id' => $this->userId,
                    ]);
                }
            }
        });
    }
}
