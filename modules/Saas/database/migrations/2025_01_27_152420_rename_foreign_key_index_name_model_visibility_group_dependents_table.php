<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('model_visibility_group_dependents', function (Blueprint $table) {
            $table->dropForeign('visibility_group_id_foreign');

            $table->foreign('model_visibility_group_id', DB::getTablePrefix().'visibility_group_id_foreign')
                ->references('id')
                ->on('model_visibility_groups')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('model_visibility_group_dependents', function (Blueprint $table) {
            $table->dropForeign(DB::getTablePrefix().'visibility_group_id_foreign');

            $table->foreign('model_visibility_group_id', 'visibility_group_id_foreign')
                ->references('id')
                ->on('model_visibility_groups')
                ->cascadeOnDelete();
        });
    }
};
