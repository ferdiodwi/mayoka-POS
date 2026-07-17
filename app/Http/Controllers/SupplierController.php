<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\SuppliersImport;

class SupplierController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 20);
        $search = $request->get('search');

        $query = Supplier::query()->orderBy('name');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($perPage === 'all') {
            return response()->json(['suppliers' => $query->get()]);
        }

        return response()->json($query->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:150',
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $lastSupplier = Supplier::withoutGlobalScope('branch')->orderBy('id', 'desc')->first();
        $nextId = $lastSupplier ? $lastSupplier->id + 1 : 1;
        $code = 'SUP-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

        $supplier = Supplier::create([
            'code' => $code,
            'name' => $request->name,
            'phone' => $request->phone,
            'address' => $request->address,
            'notes' => $request->notes,
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json([
            'message' => 'Supplier berhasil ditambahkan.',
            'supplier' => $supplier,
        ], 201);
    }

    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:150',
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $supplier->update($request->only(['name', 'phone', 'address', 'notes', 'is_active']));

        return response()->json([
            'message' => 'Supplier berhasil diperbarui.',
            'supplier' => $supplier,
        ]);
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        // Check if supplier is used in purchases
        if ($supplier->purchases()->exists()) {
            // Soft delete / disable instead of hard delete
            $supplier->update(['is_active' => false]);
            return response()->json(['message' => 'Supplier memiliki riwayat pembelian dan telah dinonaktifkan.']);
        }

        $supplier->delete();
        return response()->json(['message' => 'Supplier berhasil dihapus.']);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:2048',
        ]);

        try {
            Excel::import(new SuppliersImport, $request->file('file'));
            return response()->json(['message' => 'Data supplier berhasil diimport.']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal mengimport data: ' . $e->getMessage()], 422);
        }
    }

    public function downloadTemplate()
    {
        $headers = [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
        
        $path = public_path('templates/suppliers_template.xlsx');
        if (!file_exists($path)) {
            // Create empty excel file using Maatwebsite Excel if template doesn't exist
            return Excel::download(new class implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithHeadings {
                public function array(): array {
                    return [
                        ['Supplier A', '08123456789', 'Jl. Contoh No 1', 'Supplier Kertas', '1'],
                    ];
                }
                public function headings(): array {
                    return ['NAMA', 'TELEPON', 'ALAMAT', 'CATATAN', 'AKTIF (1/0)'];
                }
            }, 'suppliers_template.xlsx');
        }

        return response()->download($path, 'suppliers_template.xlsx', $headers);
    }
}
