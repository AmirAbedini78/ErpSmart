<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('builder_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->nullable()->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('module_name')->nullable()->index();
            $table->string('entity_name')->nullable()->index();
            $table->string('resource_name')->nullable()->index();
            $table->string('status')->default('draft')->index();
            $table->unsignedInteger('schema_version')->default(1);
            $table->json('definition_json');
            $table->string('checksum')->nullable()->index();
            $table->json('last_validation_report_json')->nullable();
            $table->json('last_preview_manifest_json')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('builder_definitions');
    }
};
