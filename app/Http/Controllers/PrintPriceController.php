<?php

namespace App\Http\Controllers;

use App\Models\PrintPrice;
use App\Imports\PrintPricesImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrintPriceController extends Controller
{
    public function index(): JsonResponse
    {
        $prices = PrintPrice::all()->map(function ($p) {
            $p->label = $p->label;
            return $p;
        });

        return response()->json(['print_prices' => $prices]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'paper_size' => 'required|in:A4,F4,A3,Kertas Sendiri',
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
            'print_price' => $price,
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
            'print_price' => $printPrice->fresh(),
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
            'paper_size' => 'required|in:A4,F4,A3,Kertas Sendiri',
            'color_type' => 'required|in:bw,color',
            'side_type' => 'required|in:single,duplex',
            'qty' => 'required|integer|min:1',
        ]);

        $printPrice = PrintPrice::where('paper_size', $request->paper_size)
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

    // Tiers removed

    public function downloadTemplate()
    {
        $headers = [
            'ukuran_kertas',
            'tinta',
            'sisi',
            'harga_jual_per_lembar',
            'hpp_per_lembar',
        ];

        $example1 = [
            'A4',
            'bw',
            'single',
            '500',
            '200',
        ];

        $example2 = [
            'A4',
            'color',
            'duplex',
            '1500',
            '500',
        ];

        $export = new class($headers, $example1, $example2) implements \Maatwebsite\Excel\Concerns\FromArray {
            protected $headers;
            protected $example1;
            protected $example2;

            public function __construct($headers, $example1, $example2)
            {
                $this->headers = $headers;
                $this->example1 = $example1;
                $this->example2 = $example2;
            }

            public function array(): array
            {
                return [
                    $this->headers,
                    $this->example1,
                    $this->example2
                ];
            }
        };

        return Excel::download($export, 'Template_Import_Harga_Cetak_MAYOKA.xlsx');
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:2048'
        ]);

        try {
            Excel::import(new PrintPricesImport(), $request->file('file'));
            return response()->json([
                'message' => 'Data harga cetak berhasil diimport.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat import: ' . $e->getMessage()
            ], 500);
        }
    }
}
