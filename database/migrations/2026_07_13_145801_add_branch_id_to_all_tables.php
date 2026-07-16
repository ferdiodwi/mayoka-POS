<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = [
            'users',
            'categories',
            'products',
            'print_prices',
            'print_price_tiers',
            'addon_services',
            'stock_movements',
            'transactions',
            'purchases',
            'expenses',
            'shifts',
            'customers',
            'returns',
            'product_units',
        ];

        // 1. Tambahkan kolom secara nullable
        foreach ($tables as $tableName) {
            if (\Illuminate\Support\Facades\Schema::hasTable($tableName)) {
                \Illuminate\Support\Facades\Schema::table($tableName, function (Blueprint $table) {
                    $table->foreignId('branch_id')->nullable()->constrained('branches')->onDelete('cascade');
                });
            }
        }

        // 2. Buat data cabang pusat bawaan
        $branchId = \Illuminate\Support\Facades\DB::table('branches')->insertGetId([
            'name' => 'ATK',
            'address' => 'JL. JEMBER DESA TAMAN',
            'phone' => '082 234 278 798',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3. Update semua data agar masuk ke cabang pusat
        foreach ($tables as $tableName) {
            if (\Illuminate\Support\Facades\Schema::hasTable($tableName)) {
                \Illuminate\Support\Facades\DB::table($tableName)->update(['branch_id' => $branchId]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'users',
            'categories',
            'products',
            'print_prices',
            'print_price_tiers',
            'addon_services',
            'stock_movements',
            'transactions',
            'purchases',
            'expenses',
            'shifts',
            'customers',
            'returns',
            'product_units',
        ];

        foreach ($tables as $tableName) {
            if (\Illuminate\Support\Facades\Schema::hasTable($tableName)) {
                \Illuminate\Support\Facades\Schema::table($tableName, function (Blueprint $table) {
                    $table->dropForeign(['branch_id']);
                    $table->dropColumn('branch_id');
                });
            }
        }
    }
};
