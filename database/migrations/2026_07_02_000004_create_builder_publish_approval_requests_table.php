<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('builder_publish_approval_requests', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->unsignedBigInteger('builder_definition_id');
            $table->string('status')->index('bpar_status_idx');
            $table->string('candidate_id')->index('bpar_candidate_idx');
            $table->text('candidate_snapshot_path');
            $table->text('candidate_root')->nullable();
            $table->string('definition_checksum')->nullable()->index('bpar_checksum_idx');
            $table->unsignedBigInteger('requested_by_id')->nullable();
            $table->unsignedBigInteger('approved_by_id')->nullable();
            $table->unsignedBigInteger('rejected_by_id')->nullable();
            $table->unsignedBigInteger('revoked_by_id')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('invalidated_at')->nullable();
            $table->text('invalidation_reason')->nullable();
            $table->text('decision_note')->nullable();
            $table->json('snapshot_json')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();

            $table->index('builder_definition_id', 'bpar_definition_idx');
            $table->foreign('builder_definition_id', 'bpar_definition_fk')
                ->references('id')->on('builder_definitions')->cascadeOnDelete();

            $table->index('requested_by_id', 'bpar_requested_by_idx');
            $table->foreign('requested_by_id', 'bpar_requested_by_fk')
                ->references('id')->on('users')->nullOnDelete();

            $table->index('approved_by_id', 'bpar_approved_by_idx');
            $table->foreign('approved_by_id', 'bpar_approved_by_fk')
                ->references('id')->on('users')->nullOnDelete();

            $table->index('rejected_by_id', 'bpar_rejected_by_idx');
            $table->foreign('rejected_by_id', 'bpar_rejected_by_fk')
                ->references('id')->on('users')->nullOnDelete();

            $table->index('revoked_by_id', 'bpar_revoked_by_idx');
            $table->foreign('revoked_by_id', 'bpar_revoked_by_fk')
                ->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('builder_publish_approval_requests');
    }
};
