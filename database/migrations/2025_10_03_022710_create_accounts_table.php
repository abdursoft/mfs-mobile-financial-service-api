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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('act_id');
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone');
            $table->string('country');
            $table->string('province')->nullable();
            $table->string('state')->nullable();
            $table->string('district');
            $table->string('zipcode');
            $table->string('village')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
