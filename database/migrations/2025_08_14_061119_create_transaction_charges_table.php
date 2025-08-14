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
        Schema::create('transaction_charges', function (Blueprint $table) {
            $table->id();
            $table->decimal('send_money_percentage')->default('0');
            $table->decimal('cash_in_percentage')->default('0');
            $table->decimal('cash_out_percentage')->default('7.5');
            $table->decimal('payment_percentage')->default('0.33');
            $table->decimal('send_money_fixed')->default('0');
            $table->decimal('send_money_max')->default('0');
            $table->string('description')->nullable();
            $table->string('currency')->default('BDT');
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
        Schema::dropIfExists('transaction_charges');
    }
};
