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
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->datetime('started_at');
            $table->datetime('ended_at')->nullable();
            $table->decimal('cash_start', 12, 2)->default(0);
            $table->decimal('cash_end', 12, 2)->nullable();
            $table->decimal('cash_expected', 12, 2)->nullable();
            $table->decimal('cash_difference', 12, 2)->nullable();
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
