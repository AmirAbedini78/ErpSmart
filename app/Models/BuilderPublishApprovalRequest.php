<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BuilderPublishApprovalRequest extends Model
{
    use HasFactory;

    public const STATUS_REQUESTED = 'requested';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_REVOKED = 'revoked';
    public const STATUS_INVALIDATED = 'invalidated';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'uuid',
        'builder_definition_id',
        'status',
        'candidate_id',
        'candidate_snapshot_path',
        'candidate_root',
        'definition_checksum',
        'requested_by_id',
        'approved_by_id',
        'rejected_by_id',
        'revoked_by_id',
        'requested_at',
        'approved_at',
        'rejected_at',
        'revoked_at',
        'expires_at',
        'invalidated_at',
        'invalidation_reason',
        'decision_note',
        'snapshot_json',
        'metadata_json',
    ];

    protected $casts = [
        'builder_definition_id' => 'integer',
        'requested_by_id' => 'integer',
        'approved_by_id' => 'integer',
        'rejected_by_id' => 'integer',
        'revoked_by_id' => 'integer',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'revoked_at' => 'datetime',
        'expires_at' => 'datetime',
        'invalidated_at' => 'datetime',
        'snapshot_json' => 'array',
        'metadata_json' => 'array',
    ];

    public function definition(): BelongsTo
    {
        return $this->belongsTo(BuilderDefinition::class, 'builder_definition_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(BuilderPublishAuditLog::class, 'builder_publish_approval_request_id');
    }

    public function canApprove(): bool
    {
        return $this->status === self::STATUS_REQUESTED;
    }

    public function canReject(): bool
    {
        return $this->status === self::STATUS_REQUESTED;
    }

    public function canRevoke(): bool
    {
        return in_array($this->status, [self::STATUS_REQUESTED, self::STATUS_APPROVED], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_REJECTED,
            self::STATUS_REVOKED,
            self::STATUS_INVALIDATED,
            self::STATUS_EXPIRED,
        ], true);
    }

    public function isChecksumCurrent(BuilderDefinition $definition): bool
    {
        return $this->definition_checksum === $definition->checksum;
    }
}
