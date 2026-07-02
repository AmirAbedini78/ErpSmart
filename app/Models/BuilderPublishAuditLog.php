<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuilderPublishAuditLog extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'uuid',
        'builder_definition_id',
        'builder_publish_approval_request_id',
        'candidate_id',
        'definition_checksum',
        'event_type',
        'actor_id',
        'payload_json',
        'created_at',
    ];

    protected $casts = [
        'builder_definition_id' => 'integer',
        'builder_publish_approval_request_id' => 'integer',
        'actor_id' => 'integer',
        'payload_json' => 'array',
        'created_at' => 'datetime',
    ];

    public function definition(): BelongsTo
    {
        return $this->belongsTo(BuilderDefinition::class, 'builder_definition_id');
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(BuilderPublishApprovalRequest::class, 'builder_publish_approval_request_id');
    }
}
