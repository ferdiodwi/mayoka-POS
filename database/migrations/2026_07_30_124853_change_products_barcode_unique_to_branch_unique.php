<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Drop the global unique constraint on barcode alone
            $table->dropUnique('products_barcode_unique');

            // Add a new unique constraint that is unique per branch
            // So the same barcode can exist in different branches
            $table->unique(['barcode', 'branch_id'], 'products_barcode_branch_unique');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_barcode_branch_unique');
            $table->unique('barcode');
        });
    }
};
