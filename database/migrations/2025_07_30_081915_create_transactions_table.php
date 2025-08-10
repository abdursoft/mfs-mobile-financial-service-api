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
        Schema::create('transactions', function (Blueprint $table) {
        $table->id();
        $table->foreignId('from_user_id')->nullable()->constrained('users')->onDelete('set null');
        $table->foreignId('to_user_id')->nullable()->constrained('users')->onDelete('set null');
        $table->foreignId('payment_id')->nullable()->constrained('payment_requests')->onDelete('set null');
        $table->decimal('amount', 12, 2);
        $table->enum('type', ['transfer', 'cash_in', 'cash_out', 'payment']);
        $table->string('status')->default('pending');
        $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
