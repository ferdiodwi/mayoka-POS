<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First migrate existing product prices to product_units
        $products = \Illuminate\Support\Facades\DB::table('products')->get();
        foreach ($products as $p) {
            \Illuminate\Support\Facades\DB::table('product_units')->insert([
                'product_id' => $p->id,
                'level' => 1,
                'unit_name' => $p->unit ?? 'PCS',
                'qty_per_previous' => 1,
                'base_multiplier' => 1,
                'price_h1' => $p->price ?? 0,
                'price_h2' => $p->wholesale_price ?? 0,
                'price_h3' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['price', 'wholesale_price', 'wholesale_min_qty', 'unit']);
        });

        Schema::table('transaction_items', function (Blueprint $table) {
            $table->string('unit_name', 20)->default('PCS')->after('product_id');
            $table->integer('base_multiplier')->default(1)->after('unit_name');
        });

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->string('unit_name', 20)->default('PCS')->after('product_id');
            $table->integer('base_multiplier')->default(1)->after('unit_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('wholesale_price', 12, 2)->default(0);
            $table->integer('wholesale_min_qty')->default(0);
            $table->string('unit', 20)->default('pcs');
        });

        Schema::table('transaction_items', function (Blueprint $table) {
            $table->dropColumn(['unit_name', 'base_multiplier']);
        });

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropColumn(['unit_name', 'base_multiplier']);
        });
    }
};
