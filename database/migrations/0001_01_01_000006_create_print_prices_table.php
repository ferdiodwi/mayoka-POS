<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_prices', function (Blueprint $table) {
            $table->id();
            $table->enum('paper_size', ['A4', 'F4', 'A3']);
            $table->enum('color_type', ['bw', 'color']);
            $table->enum('side_type', ['single', 'duplex']);
            $table->decimal('price_per_sheet', 10, 2);
            $table->decimal('cost_per_sheet', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['paper_size', 'color_type', 'side_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_prices');
    }
};
