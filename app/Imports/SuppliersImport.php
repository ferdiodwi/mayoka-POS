<?php

namespace App\Imports;

use App\Models\Supplier;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class SuppliersImport implements ToModel, WithHeadingRow, WithValidation
{
    public function model(array $row)
    {
        // Try to find existing supplier by name to avoid duplicates
        $supplier = Supplier::where('name', $row['nama'])->first();

        if ($supplier) {
            $supplier->update([
                'phone' => $row['telepon'] ?? $supplier->phone,
                'address' => $row['alamat'] ?? $supplier->address,
                'notes' => $row['catatan'] ?? $supplier->notes,
                'is_active' => isset($row['aktif_10']) ? (bool) $row['aktif_10'] : $supplier->is_active,
            ]);
            return null; // Return null because we updated existing
        }

        // Generate code
        $lastSupplier = Supplier::withoutGlobalScope('branch')->orderBy('id', 'desc')->first();
        $nextId = $lastSupplier ? $lastSupplier->id + 1 : 1;
        $code = 'SUP-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

        return new Supplier([
            'code' => $code,
            'name' => $row['nama'],
            'phone' => $row['telepon'],
            'address' => $row['alamat'],
            'notes' => $row['catatan'],
            'is_active' => isset($row['aktif_10']) ? (bool) $row['aktif_10'] : true,
        ]);
    }

    public function rules(): array
    {
        return [
            'nama' => 'required|string|max:150',
            'telepon' => 'nullable|string|max:30',
        ];
    }
}
