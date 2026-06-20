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
        if (! Schema::hasColumn('warehouses', 'import_id')) {
            Schema::table('warehouses', function (Blueprint $table) {
                $table->unsignedBigInteger('import_id')->nullable()->after('is_active')->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('warehouses', 'import_id')) {
            Schema::table('warehouses', function (Blueprint $table) {
                $table->dropColumn('import_id');
            });
        }
    }
};
