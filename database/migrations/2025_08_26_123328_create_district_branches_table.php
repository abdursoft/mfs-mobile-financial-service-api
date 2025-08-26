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
        Schema::create('district_branches', function (Blueprint $table) {
            $table->id();
            $table->string('branch_name');
            $table->string('branch_slug');
            $table->string('branch_code');
            $table->string('swift_code')->nullable();
            $table->string('routing_number')->nullable();
            $table->string('email')->nullable();
            $table->string('fax')->nullable();
            $table->string('telephone')->nullable();
            $table->longText('address')->nullable();
            $table->foreignId('bank_id')->constrained('banks')->cascadeOnDelete();
            $table->foreignId('bank_district_id')->constrained('bank_districts')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('district_branches');
    }
};
