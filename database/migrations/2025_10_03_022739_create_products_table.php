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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type',['physical','digital','service']);
            $table->text('short_description')->nullable();
            $table->string('sku');
            $table->string('image');

            // relation with price table
            $table->string('price_id');
            $table->foreign('price_id')
                ->references('price_id')
                ->on('prices')
                ->cascadeOnDelete();

            // relation with merchant table
            $table->foreignId('merchant_app_id')->constrained('merchant_credentials')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
