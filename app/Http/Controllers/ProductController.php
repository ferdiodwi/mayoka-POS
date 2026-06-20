<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::with('category:id,name');

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
                  ->orWhere('barcode', 'like', "%{$s}%");
            });
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
            ->with('category:id,name')
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                      ->orWhere('barcode', $q);
            })
            ->limit(20)
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
            'price' => 'required|numeric|min:0',
            'cost_price' => 'required|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'min_stock' => 'nullable|integer|min:0',
            'unit' => 'required|string|max:20',
        ]);

        // For jasa type, stock is always 0
        if ($validated['type'] === 'jasa') {
            $validated['stock'] = 0;
            $validated['min_stock'] = 0;
        }

        $product = DB::transaction(function () use ($validated, $request) {
            $product = Product::create($validated);

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
            'product' => $product->load('category:id,name'),
        ], 201);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:150',
            'barcode' => ['nullable', 'string', 'max:50', Rule::unique('products', 'barcode')->ignore($product->id)],
            'type' => 'required|in:barang,jasa',
            'price' => 'required|numeric|min:0',
            'cost_price' => 'required|numeric|min:0',
            'min_stock' => 'nullable|integer|min:0',
            'unit' => 'required|string|max:20',
            'is_active' => 'boolean',
        ]);

        $product->update($validated);

        return response()->json([
            'message' => 'Produk berhasil diperbarui.',
            'product' => $product->fresh()->load('category:id,name'),
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

        return response()->json([
            'message' => 'Stok berhasil disesuaikan.',
            'product' => $product->fresh(),
        ]);
    }
}
