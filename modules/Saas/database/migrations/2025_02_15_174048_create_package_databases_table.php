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
        Schema::create('package_databases', function (Blueprint $table) {
            $table->id();
            $table->string('db_host');
            $table->string('db_port');
            $table->string('database');
            $table->string('db_username');
            $table->string('db_password');
            $table->foreignId('package_id')->constrained('packages')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('package_databases');
    }
};
