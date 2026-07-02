<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('builder_publish_executions', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->unsignedBigInteger('builder_definition_id');
            $table->unsignedBigInteger('builder_publish_approval_request_id')->nullable();
            $table->string('status')->index('bpe_status_idx');
            $table->string('candidate_id')->nullable()->index('bpe_candidate_idx');
            $table->string('definition_checksum')->nullable()->index('bpe_checksum_idx');
            $table->text('candidate_snapshot_path')->nullable();
            $table->json('preflight_report_json')->nullable();
            $table->text('rollback_manifest_path')->nullable();
            $table->text('staging_root')->nullable();
            $table->string('lock_key')->nullable();
            $table->string('lock_owner')->nullable();
            $table->unsignedBigInteger('requested_by_id')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('lock_acquired_at')->nullable();
            $table->timestamp('preflight_completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();

            $table->index('builder_definition_id', 'bpe_definition_idx');
            $table->foreign('builder_definition_id', 'bpe_definition_fk')
                ->references('id')->on('builder_definitions')->cascadeOnDelete();

            $table->index('builder_publish_approval_request_id', 'bpe_approval_idx');
            $table->foreign('builder_publish_approval_request_id', 'bpe_approval_fk')
                ->references('id')->on('builder_publish_approval_requests')->nullOnDelete();

            $table->index('requested_by_id', 'bpe_requested_by_idx');
            $table->foreign('requested_by_id', 'bpe_requested_by_fk')
                ->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('builder_publish_executions');
    }
};
