<?php

namespace App\Http\Controllers;

use App\Models\PrintPrice;
use App\Models\PrintPriceTier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrintPriceController extends Controller
{
    public function index(): JsonResponse
    {
        $prices = PrintPrice::with('tiers')->get()->map(function ($p) {
            $p->label = $p->label;
            return $p;
        });

        return response()->json(['print_prices' => $prices]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'paper_size' => 'required|in:A4,F4,A3',
            'color_type' => 'required|in:bw,color',
            'side_type' => 'required|in:single,duplex',
            'price_per_sheet' => 'required|numeric|min:0',
            'cost_per_sheet' => 'required|numeric|min:0',
        ]);

        // Check unique combination
        $exists = PrintPrice::where('paper_size', $validated['paper_size'])
            ->where('color_type', $validated['color_type'])
            ->where('side_type', $validated['side_type'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Kombinasi harga cetak ini sudah ada.',
            ], 422);
        }

        $price = PrintPrice::create($validated);

        return response()->json([
            'message' => 'Harga cetak berhasil ditambahkan.',
            'print_price' => $price->load('tiers'),
        ], 201);
    }

    public function update(Request $request, PrintPrice $printPrice): JsonResponse
    {
        $validated = $request->validate([
            'price_per_sheet' => 'required|numeric|min:0',
            'cost_per_sheet' => 'required|numeric|min:0',
        ]);

        $printPrice->update($validated);

        return response()->json([
            'message' => 'Harga cetak berhasil diperbarui.',
            'print_price' => $printPrice->fresh()->load('tiers'),
        ]);
    }

    public function destroy(PrintPrice $printPrice): JsonResponse
    {
        $printPrice->delete();
        return response()->json(['message' => 'Harga cetak berhasil dihapus.']);
    }

    /**
     * Calculate price for POS — returns effective price for a given combination and qty.
     */
    public function calculate(Request $request): JsonResponse
    {
        $request->validate([
            'paper_size' => 'required|in:A4,F4,A3',
            'color_type' => 'required|in:bw,color',
            'side_type' => 'required|in:single,duplex',
            'qty' => 'required|integer|min:1',
        ]);

        $printPrice = PrintPrice::with('tiers')
            ->where('paper_size', $request->paper_size)
            ->where('color_type', $request->color_type)
            ->where('side_type', $request->side_type)
            ->first();

        if (!$printPrice) {
            return response()->json(['message' => 'Kombinasi harga tidak ditemukan.'], 404);
        }

        $effectivePrice = $printPrice->getPriceForQty($request->qty);
        $subtotal = $effectivePrice * $request->qty;

        return response()->json([
            'print_price' => $printPrice,
            'effective_price_per_sheet' => $effectivePrice,
            'qty' => $request->qty,
            'subtotal' => number_format($subtotal, 2, '.', ''),
        ]);
    }

    // --- Tier Management ---

    public function storeTier(Request $request, PrintPrice $printPrice): JsonResponse
    {
        $validated = $request->validate([
            'min_qty' => 'required|integer|min:1',
            'price_per_sheet' => 'required|numeric|min:0',
        ]);

        $validated['print_price_id'] = $printPrice->id;
        $tier = PrintPriceTier::create($validated);

        return response()->json([
            'message' => 'Tier harga berhasil ditambahkan.',
            'tier' => $tier,
        ], 201);
    }

    public function updateTier(Request $request, PrintPriceTier $tier): JsonResponse
    {
        $validated = $request->validate([
            'min_qty' => 'required|integer|min:1',
            'price_per_sheet' => 'required|numeric|min:0',
        ]);

        $tier->update($validated);

        return response()->json([
            'message' => 'Tier harga berhasil diperbarui.',
            'tier' => $tier,
        ]);
    }

    public function destroyTier(PrintPriceTier $tier): JsonResponse
    {
        $tier->delete();
        return response()->json(['message' => 'Tier harga berhasil dihapus.']);
    }
}
