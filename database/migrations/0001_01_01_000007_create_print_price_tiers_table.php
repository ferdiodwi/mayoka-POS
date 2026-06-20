<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_price_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('print_price_id')->constrained('print_prices')->cascadeOnDelete();
            $table->integer('min_qty');
            $table->decimal('price_per_sheet', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_price_tiers');
    }
};
