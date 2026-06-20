<?php

namespace Database\Seeders;

use App\Models\AddonService;
use Illuminate\Database\Seeder;

class AddonServiceSeeder extends Seeder
{
    public function run(): void
    {
        $addons = [
            ['name' => 'Jilid Lakban', 'price' => 3000],
            ['name' => 'Jilid Mika', 'price' => 5000],
            ['name' => 'Jilid Spiral', 'price' => 8000],
            ['name' => 'Laminating A4', 'price' => 3000],
            ['name' => 'Laminating F4', 'price' => 4000],
            ['name' => 'Laminating A3', 'price' => 6000],
        ];

        foreach ($addons as $addon) {
            AddonService::create($addon);
        }
    }
}
