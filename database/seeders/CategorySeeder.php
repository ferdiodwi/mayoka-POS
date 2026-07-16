<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = ['ATK', 'Kertas', 'Jasa Cetak', 'Jasa Jilid', 'Lainnya'];

        foreach ($categories as $name) {
            Category::create(['name' => $name, 'branch_id' => 1]);
        }
    }
}
