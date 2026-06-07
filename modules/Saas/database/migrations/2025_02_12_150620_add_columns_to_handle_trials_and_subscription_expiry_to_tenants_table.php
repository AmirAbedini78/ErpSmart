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
        Schema::table('tenants', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable();
            $table->boolean('trial')->default(false);
            $table->date('start_date')->nullable();
            $table->date('expiry_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('trial');
            $table->dropColumn('expiry_date');
            $table->dropColumn('start_date');
            $table->dropColumn('invoice_id');
        });
    }
};
