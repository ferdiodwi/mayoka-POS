<?php

namespace Database\Seeders;

use App\Models\PrintPrice;
use App\Models\PrintPriceTier;
use Illuminate\Database\Seeder;

class PrintPriceSeeder extends Seeder
{
    public function run(): void
    {
        $matrix = [
            // [paper_size, color_type, side_type, price, cost]
            ['A4', 'bw', 'single', 200, 80],
            ['A4', 'bw', 'duplex', 350, 150],
            ['A4', 'color', 'single', 1000, 400],
            ['A4', 'color', 'duplex', 1800, 750],
            ['F4', 'bw', 'single', 250, 90],
            ['F4', 'bw', 'duplex', 400, 170],
            ['F4', 'color', 'single', 1200, 450],
            ['F4', 'color', 'duplex', 2000, 850],
            ['A3', 'bw', 'single', 500, 150],
            ['A3', 'bw', 'duplex', 900, 280],
            ['A3', 'color', 'single', 2500, 900],
            ['A3', 'color', 'duplex', 4500, 1700],
        ];

        foreach ($matrix as [$size, $color, $side, $price, $cost]) {
            $pp = PrintPrice::create([
                'branch_id' => 1,
                'paper_size' => $size,
                'color_type' => $color,
                'side_type' => $side,
                'price_per_sheet' => $price,
                'cost_per_sheet' => $cost,
            ]);

            // Add sample tier for BW single-side
            if ($color === 'bw' && $side === 'single') {
                PrintPriceTier::create([
                    'branch_id' => 1,
                    'print_price_id' => $pp->id,
                    'min_qty' => 50,
                    'price_per_sheet' => $price - 50,
                ]);
                PrintPriceTier::create([
                    'branch_id' => 1,
                    'print_price_id' => $pp->id,
                    'min_qty' => 100,
                    'price_per_sheet' => $price - 80,
                ]);
            }
        }
    }
}
