<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Change enum to include 'voided'
        Schema::table('purchases', function (Blueprint $table) {
            $table->timestamp('voided_at')->nullable()->after('payment_status');
            $table->foreignId('voided_by')->nullable()->after('voided_at')->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable()->after('voided_by');
        });

        // Alter payment_status enum to add 'voided'
        DB::statement("ALTER TABLE purchases MODIFY COLUMN payment_status ENUM('paid', 'unpaid', 'voided') DEFAULT 'paid'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE purchases MODIFY COLUMN payment_status ENUM('paid', 'unpaid') DEFAULT 'paid'");

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropForeign(['voided_by']);
            $table->dropColumn(['voided_at', 'voided_by', 'void_reason']);
        });
    }
};
