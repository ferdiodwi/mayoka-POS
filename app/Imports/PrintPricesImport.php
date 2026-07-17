<?php

namespace App\Imports;

use App\Models\PrintPrice;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\DB;

class PrintPricesImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows)
    {
        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                // Skip if essential data is missing
                if (empty($row['ukuran_kertas'])) {
                    continue;
                }

                $paperSize = trim($row['ukuran_kertas']);
                
                // Parse color
                $colorRaw = strtolower(trim($row['tinta'] ?? 'bw'));
                $colorType = (strpos($colorRaw, 'warna') !== false || $colorRaw === 'color') ? 'color' : 'bw';

                // Parse side
                $sideRaw = strtolower(trim($row['sisi'] ?? 'single'));
                $sideType = (strpos($sideRaw, 'bolak') !== false || $sideRaw === 'duplex') ? 'duplex' : 'single';

                $price = (float) ($row['harga_jual_per_lembar'] ?? 0);
                $cost = (float) ($row['hpp_per_lembar'] ?? 0);

                // Ensure it's a valid paper size based on enum
                if (!in_array($paperSize, ['A4', 'F4', 'A3', 'Kertas Sendiri'])) {
                    $paperSize = 'A4'; // default fallback
                }

                // Update or Create
                PrintPrice::updateOrCreate(
                    [
                        'paper_size' => $paperSize,
                        'color_type' => $colorType,
                        'side_type' => $sideType,
                    ],
                    [
                        'price_per_sheet' => $price,
                        'cost_per_sheet' => $cost,
                    ]
                );
            }
        });
    }
}
