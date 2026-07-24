<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\StockMovement;
use App\Imports\ProductsImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['category:id,name', 'units']);

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->boolean('low_stock')) {
            $query->lowStock();
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('product_code', 'like', "%{$s}%")
                  ->orWhere('barcode', 'like', "%{$s}%");
            });
        }

        if ($request->get('per_page') === 'all') {
            return response()->json($query->orderBy('name')->get());
        }

        $products = $query->orderBy('name')->paginate(20);

        return response()->json($products);
    }

    /**
     * Quick search for POS (name or barcode).
     */
    public function search(Request $request): JsonResponse
    {
        $q = $request->get('q', '');
        if (strlen($q) < 1) {
            return response()->json(['products' => []]);
        }

        $products = Product::active()
            ->with(['category:id,name', 'units'])
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                      ->orWhere('product_code', $q)
                      ->orWhere('barcode', $q);
            })
            ->limit(20)
            ->get();

        return response()->json(['products' => $products]);
    }

    /**
     * Full product catalog for POS client-side cache.
     * Returns all active products with category and units.
     */
    public function catalog(): JsonResponse
    {
        $products = Product::active()
            ->with(['category:id,name', 'units'])
            ->orderBy('name')
            ->get();

        return response()->json(['products' => $products]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:150',
            'barcode' => 'nullable|string|max:50|unique:products,barcode',
            'type' => 'required|in:barang,jasa',
            'cost_price' => 'required|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'min_stock' => 'nullable|integer|min:0',
            'units' => 'required|array|min:1',
            'units.*.level' => 'required|integer',
            'units.*.unit_name' => 'required|string|max:20',
            'units.*.qty_per_previous' => 'required|integer|min:1',
            'units.*.base_multiplier' => 'required|integer|min:1',
            'units.*.price_h1' => 'required|numeric|min:0',
            'units.*.price_h2' => 'nullable|numeric|min:0',
            'units.*.price_h3' => 'nullable|numeric|min:0',
        ]);

        // For jasa type, stock is always 0
        if ($validated['type'] === 'jasa') {
            $validated['stock'] = 0;
            $validated['min_stock'] = 0;
        }

        $product = DB::transaction(function () use ($validated, $request) {
            // Extract product fields only
            $productData = collect($validated)->except('units')->toArray();
            
            // Auto generate product code
            $maxCode = DB::table('products')->max(DB::raw('CAST(product_code AS UNSIGNED)'));
            $productData['product_code'] = $maxCode && (int)$maxCode >= 101 ? ((int)$maxCode + 1) : 101;
            
            $product = Product::create($productData);

            // Create units
            foreach ($validated['units'] as $unitData) {
                $product->units()->create($unitData);
            }

            // Record initial stock movement if type is barang and stock > 0
            if ($product->type === 'barang' && $product->stock > 0) {
                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => 'in',
                    'qty' => $product->stock,
                    'reference' => 'Stok awal',
                    'notes' => 'Stok awal saat produk dibuat',
                    'user_id' => $request->user()->id,
                ]);
            }

            return $product;
        });

        return response()->json([
            'message' => 'Produk berhasil dibuat.',
            'product' => $product->load(['category:id,name', 'units']),
        ], 201);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:150',
            'barcode' => ['nullable', 'string', 'max:50', Rule::unique('products', 'barcode')->ignore($product->id)],
            'type' => 'required|in:barang,jasa',
            'cost_price' => 'required|numeric|min:0',
            'min_stock' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'units' => 'required|array|min:1',
            'units.*.level' => 'required|integer',
            'units.*.unit_name' => 'required|string|max:20',
            'units.*.qty_per_previous' => 'required|integer|min:1',
            'units.*.base_multiplier' => 'required|integer|min:1',
            'units.*.price_h1' => 'required|numeric|min:0',
            'units.*.price_h2' => 'nullable|numeric|min:0',
            'units.*.price_h3' => 'nullable|numeric|min:0',
        ]);

        DB::transaction(function () use ($product, $validated) {
            $productData = collect($validated)->except('units')->toArray();
            $productData['has_hpp_warning'] = false;
            $product->update($productData);

            // Sync units (delete existing, recreate)
            // Or better, delete and insert to ensure clean state
            $product->units()->delete();
            foreach ($validated['units'] as $unitData) {
                $product->units()->create($unitData);
            }
        });

        event(new \App\Events\DashboardUpdated());

        return response()->json([
            'message' => 'Produk berhasil diperbarui.',
            'product' => $product->fresh()->load(['category:id,name', 'units']),
        ]);
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->update(['is_active' => false]);
        return response()->json(['message' => 'Produk berhasil dinonaktifkan.']);
    }

    /**
     * Manual stock adjustment (owner only).
     */
    public function stockAdjust(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'qty' => 'required|integer',
            'notes' => 'required|string|max:255',
        ]);

        if ($product->type !== 'barang') {
            return response()->json(['message' => 'Adjustment stok hanya untuk tipe barang.'], 422);
        }

        DB::transaction(function () use ($request, $product) {
            $product->increment('stock', $request->qty);

            StockMovement::create([
                'product_id' => $product->id,
                'type' => 'adjustment',
                'qty' => $request->qty,
                'reference' => 'Manual adjustment',
                'notes' => $request->notes,
                'user_id' => $request->user()->id,
            ]);
        });

        event(new \App\Events\DashboardUpdated());
        event(new \App\Events\ProductStockUpdated($product->id, $product->fresh()->stock));

        return response()->json([
            'message' => 'Stok berhasil disesuaikan.',
            'product' => $product->fresh(),
        ]);
    }

    public function downloadTemplate()
    {
        $headers = [
            'Nama Kategori', 'Nama Produk', 'Barcode', 'Tipe (barang/jasa)', 
            'Harga Modal', 'Stok', 'Min Stok', 
            'Satuan 1', 'Harga S1', 
            'Satuan 2', 'Isi S2', 'Harga S2', 
            'Satuan 3', 'Isi S3', 'Harga S3'
        ];

        $example = [
            'ATK', 'Pulpen Pilot G1', '89912345', 'barang', 
            '2000', '100', '10', 
            'PCS', '3000', 
            'PCK', '12', '35000', 
            'DOS', '12', '400000'
        ];

        $export = new class($headers, $example) implements \Maatwebsite\Excel\Concerns\FromArray {
            protected $headers;
            protected $example;

            public function __construct($headers, $example)
            {
                $this->headers = $headers;
                $this->example = $example;
            }

            public function array(): array
            {
                return [
                    $this->headers,
                    $this->example
                ];
            }
        };

        return Excel::download($export, 'Template_Import_Produk_MAYOKA.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120', // 5MB max
        ]);

        try {
            Excel::import(new ProductsImport($request->user()->id), $request->file('file'));
            
            return response()->json([
                'message' => 'Data produk berhasil diimport.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat import: ' . $e->getMessage(),
            ], 500);
        }
    }
}
