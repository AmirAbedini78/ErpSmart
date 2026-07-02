<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('builder_publish_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->unsignedBigInteger('builder_definition_id')->nullable();
            $table->unsignedBigInteger('builder_publish_approval_request_id')->nullable();
            $table->string('candidate_id')->nullable()->index('bpal_candidate_idx');
            $table->string('definition_checksum')->nullable()->index('bpal_checksum_idx');
            $table->string('event_type')->index('bpal_event_type_idx');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->json('payload_json')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('builder_definition_id', 'bpal_definition_idx');
            $table->foreign('builder_definition_id', 'bpal_definition_fk')
                ->references('id')->on('builder_definitions')->cascadeOnDelete();

            $table->index('builder_publish_approval_request_id', 'bpal_request_idx');
            $table->foreign('builder_publish_approval_request_id', 'bpal_request_fk')
                ->references('id')->on('builder_publish_approval_requests')->cascadeOnDelete();

            $table->index('actor_id', 'bpal_actor_idx');
            $table->foreign('actor_id', 'bpal_actor_fk')
                ->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('builder_publish_audit_logs');
    }
};
