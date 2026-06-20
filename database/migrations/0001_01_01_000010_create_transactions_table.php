<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 30)->unique();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('shift_id')->constrained('shifts');
            $table->decimal('subtotal', 14, 2);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('total', 14, 2);
            $table->enum('payment_method', ['cash', 'qris', 'transfer']);
            $table->decimal('cash_paid', 14, 2)->default(0);
            $table->decimal('cash_change', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('transaction_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->enum('item_type', ['print', 'product', 'addon']);
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('print_price_id')->nullable()->constrained('print_prices')->nullOnDelete();
            $table->foreignId('addon_service_id')->nullable()->constrained('addon_services')->nullOnDelete();
            $table->unsignedBigInteger('parent_item_id')->nullable();
            $table->string('description', 200);
            $table->integer('qty');
            $table->integer('returned_qty')->default(0);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('subtotal', 14, 2);
            $table->timestamps();

            $table->foreign('parent_item_id')->references('id')->on('transaction_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_items');
        Schema::dropIfExists('transactions');
    }
};
