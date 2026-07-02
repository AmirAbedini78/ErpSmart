<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuilderPublishExecution extends Model
{
    use HasFactory;

    public const STATUS_REQUESTED = 'requested';
    public const STATUS_LOCK_ACQUIRED = 'lock_acquired';
    public const STATUS_PREFLIGHT_FAILED = 'preflight_failed';
    public const STATUS_PREFLIGHT_PASSED = 'preflight_passed';
    public const STATUS_STAGING_VALIDATED = 'staging_validated';
    public const STATUS_STAGING_VALIDATION_FAILED = 'staging_validation_failed';
    public const STATUS_RUNTIME_WRITE_PLANNED = 'runtime_write_planned';
    public const STATUS_RUNTIME_WRITE_PLAN_BLOCKED = 'runtime_write_plan_blocked';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'uuid',
        'builder_definition_id',
        'builder_publish_approval_request_id',
        'status',
        'candidate_id',
        'definition_checksum',
        'candidate_snapshot_path',
        'preflight_report_json',
        'rollback_manifest_path',
        'staging_root',
        'lock_key',
        'lock_owner',
        'requested_by_id',
        'started_at',
        'lock_acquired_at',
        'preflight_completed_at',
        'failed_at',
        'cancelled_at',
        'failure_reason',
        'metadata_json',
    ];

    protected $casts = [
        'builder_definition_id' => 'integer',
        'builder_publish_approval_request_id' => 'integer',
        'requested_by_id' => 'integer',
        'started_at' => 'datetime',
        'lock_acquired_at' => 'datetime',
        'preflight_completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'preflight_report_json' => 'array',
        'metadata_json' => 'array',
    ];

    public function definition(): BelongsTo
    {
        return $this->belongsTo(BuilderDefinition::class, 'builder_definition_id');
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(BuilderPublishApprovalRequest::class, 'builder_publish_approval_request_id');
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_PREFLIGHT_FAILED,
            self::STATUS_PREFLIGHT_PASSED,
            self::STATUS_STAGING_VALIDATED,
            self::STATUS_STAGING_VALIDATION_FAILED,
            self::STATUS_RUNTIME_WRITE_PLANNED,
            self::STATUS_RUNTIME_WRITE_PLAN_BLOCKED,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
        ], true);
    }

    public function hasRuntimeWrites(): bool
    {
        return false;
    }
}
