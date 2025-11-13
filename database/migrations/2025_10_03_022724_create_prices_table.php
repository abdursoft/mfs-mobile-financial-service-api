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
        Schema::create('prices', function (Blueprint $table) {
            $table->id();
            $table->string('price_id');
            $table->decimal('amount');
            $table->enum('cycle',['once','daily','weekly','monthly','quarterly','yearly']);
            $table->enum('currency',['USD','BDT','INR','YEN','CAD','PKR','DNG','EUR','GBP','AUD','SAR','QAR'])->default('BDT');
            $table->foreignId('merchant_app_id')->constrained('merchant_credentials')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prices');
    }
};
