<?php

namespace App\Imports;

use App\Models\AddonService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\DB;

class AddonServicesImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows)
    {
        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                // Skip if essential data is missing
                if (empty($row['nama_jasa'])) {
                    continue;
                }

                $name = trim($row['nama_jasa']);
                $price = (float) ($row['harga'] ?? 0);
                
                // Parse boolean status
                $statusRaw = strtolower(trim($row['status_aktif'] ?? 'ya'));
                $isActive = in_array($statusRaw, ['1', 'ya', 'aktif', 'true', 'yes']);

                // Update or Create based on name
                AddonService::updateOrCreate(
                    [
                        'name' => $name,
                    ],
                    [
                        'price' => $price,
                        'is_active' => $isActive,
                    ]
                );
            }
        });
    }
}
