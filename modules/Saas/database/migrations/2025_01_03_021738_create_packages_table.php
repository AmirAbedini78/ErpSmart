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
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->decimal('base_price', 15, 3);
            $table->string('reocurring_period');
            $table->integer('trial_period')->default(0);
            $table->boolean('has_domain')->index()->default(true);
            $table->boolean('has_subdomain')->index()->default(true);
            $table->string('db_scheme');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
