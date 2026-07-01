<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('builder_preview_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('builder_definition_id')->constrained('builder_definitions')->cascadeOnDelete();
            $table->string('status')->index();
            $table->string('preview_path')->nullable();
            $table->json('manifest_json')->nullable();
            $table->longText('output_text')->nullable();
            $table->longText('error_text')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('builder_preview_runs');
    }
};
