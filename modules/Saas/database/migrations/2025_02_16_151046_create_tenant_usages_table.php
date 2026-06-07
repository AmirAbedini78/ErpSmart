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
        Schema::create('tenant_usages', function (Blueprint $table) {
            $table->id();
            $table->integer(column: 'count')->nullable();
            $table->string('model')->nullable();
            $table->foreignId(column: 'tenant_id')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'model']);
            $table->index(['tenant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_usages');
    }
};
