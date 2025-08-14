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
        Schema::create('transaction_limits', function (Blueprint $table) {
            $table->id();
            $table->decimal('daily_send_money_limit', 8, 2)->default(25000.00);
            $table->decimal('daily_cash_in_limit', 8, 2)->default(100000.00);
            $table->decimal('daily_cash_out_limit', 8, 2)->default(25000.00);
            $table->decimal('daily_payment_limit', 8, 2)->default(25000.00);
            $table->decimal('monthly_send_money_limit', 9, 2)->default(1000000.00);
            $table->decimal('monthly_cash_in_limit', 10, 2)->default(10000000.00);
            $table->decimal('monthly_cash_out_limit', 9, 2)->default(1000000.00);
            $table->decimal('monthly_payment_limit', 9, 2)->default(1000000.00);
            $table->decimal('send_money_max', 8, 2)->default(15000.00);
            $table->decimal('cash_out_max', 8, 2)->default(20000.00);
            $table->decimal('payment_max', 8, 2)->default(20000.00);
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_limits');
    }
};
