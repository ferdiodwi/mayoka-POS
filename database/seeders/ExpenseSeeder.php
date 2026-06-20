<?php

namespace Database\Seeders;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\Seeder;

class ExpenseSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::where('role', 'owner')->first();
        if (!$owner)
            return;

        $expenses = [
            // Bulan ini
            ['days_ago' => 2, 'category' => 'listrik', 'amount' => 450000, 'description' => 'Bayar listrik bulan Juni 2026'],
            ['days_ago' => 3, 'category' => 'operasional', 'amount' => 50000, 'description' => 'Beli tisu dan sabun cuci tangan'],
            ['days_ago' => 5, 'category' => 'bahan_baku', 'amount' => 150000, 'description' => 'Beli tinta refill printer Canon'],
            ['days_ago' => 7, 'category' => 'operasional', 'amount' => 25000, 'description' => 'Parkir dan transportasi ambil barang'],
            ['days_ago' => 10, 'category' => 'operasional', 'amount' => 35000, 'description' => 'Beli air galon dan snack toko'],
            ['days_ago' => 15, 'category' => 'gaji', 'amount' => 1800000, 'description' => 'Gaji kasir (setengah bulan)'],
            ['days_ago' => 18, 'category' => 'lainnya', 'amount' => 75000, 'description' => 'Service mesin fotokopi (minor)'],

            // Bulan lalu
            ['days_ago' => 32, 'category' => 'sewa', 'amount' => 2500000, 'description' => 'Sewa ruko bulan Mei 2026'],
            ['days_ago' => 33, 'category' => 'listrik', 'amount' => 420000, 'description' => 'Bayar listrik bulan Mei 2026'],
            ['days_ago' => 35, 'category' => 'gaji', 'amount' => 3500000, 'description' => 'Gaji kasir bulan Mei (full)'],
            ['days_ago' => 38, 'category' => 'operasional', 'amount' => 80000, 'description' => 'Pembelian alat kebersihan toko'],
            ['days_ago' => 40, 'category' => 'bahan_baku', 'amount' => 200000, 'description' => 'Beli tinta refill 4 warna'],
            ['days_ago' => 45, 'category' => 'lainnya', 'amount' => 350000, 'description' => 'Ganti roller mesin laminating'],
        ];

        foreach ($expenses as $item) {
            Expense::create([
                'user_id' => $owner->id,
                'expense_date' => now()->subDays($item['days_ago'])->toDateString(),
                'category' => $item['category'],
                'amount' => $item['amount'],
                'description' => $item['description'],
                'notes' => null,
            ]);
        }
    }
}
