<?php

namespace App\Http\Controllers;

use App\Models\AddonService;
use App\Imports\AddonServicesImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class AddonServiceController extends Controller
{
    public function index(): JsonResponse
    {
        $addons = AddonService::orderBy('name')->get();
        return response()->json(['addon_services' => $addons]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
        ]);

        $addon = AddonService::create($validated);

        return response()->json([
            'message' => 'Jasa tambahan berhasil dibuat.',
            'addon_service' => $addon,
        ], 201);
    }

    public function update(Request $request, AddonService $addonService): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $addonService->update($validated);

        return response()->json([
            'message' => 'Jasa tambahan berhasil diperbarui.',
            'addon_service' => $addonService,
        ]);
    }

    public function destroy(AddonService $addonService): JsonResponse
    {
        $addonService->delete();
        return response()->json(['message' => 'Jasa tambahan berhasil dihapus.']);
    }

    public function downloadTemplate()
    {
        $headers = [
            'nama_jasa',
            'harga',
            'status_aktif',
        ];

        $example1 = [
            'Jilid Spiral',
            '5000',
            'ya',
        ];

        $example2 = [
            'Laminating',
            '3000',
            'ya',
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

        return Excel::download($export, 'Template_Import_Addon_MAYOKA.xlsx');
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:2048'
        ]);

        try {
            Excel::import(new AddonServicesImport(), $request->file('file'));
            return response()->json([
                'message' => 'Data jasa tambahan berhasil diimport.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat import: ' . $e->getMessage()
            ], 500);
        }
    }
}
