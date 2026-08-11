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
        Schema::table('print_prices', function (Blueprint $table) {
            $table->dropUnique(['paper_size', 'color_type', 'side_type']);
            $table->enum('type', ['print', 'fotocopy'])->default('print')->after('id');
            $table->unique(['type', 'paper_size', 'color_type', 'side_type'], 'print_prices_unique_combo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('print_prices', function (Blueprint $table) {
            $table->dropUnique('print_prices_unique_combo');
            $table->dropColumn('type');
            $table->unique(['paper_size', 'color_type', 'side_type']);
        });
    }
};
