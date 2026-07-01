<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('builder_definition_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('builder_definition_id')->constrained('builder_definitions')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('status')->index();
            $table->json('definition_json');
            $table->string('checksum')->nullable()->index();
            $table->json('validation_report_json')->nullable();
            $table->json('preview_manifest_json')->nullable();
            $table->json('diff_json')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['builder_definition_id', 'version'], 'bdv_definition_version_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('builder_definition_versions');
    }
};
