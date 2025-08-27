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
        Schema::create('merchant_credentials', function (Blueprint $table) {
            $table->id();
            $table->string('secret_key');
            $table->string('public_key');
            $table->string('webhook_key');
            $table->string('webhook_url')->nullable();
            $table->json('webhook_events')->nullable();
            $table->string('app_name');
            $table->string('app_logo');
            $table->enum('app_type',['production','development'])->default('development');
            $table->enum('status',['active','inactive','suspended'])->default('active');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchant_credentials');
    }
};
