<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BuilderDefinition extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_VALIDATING = 'validating';
    public const STATUS_VALIDATED = 'validated';
    public const STATUS_VALIDATION_FAILED = 'validation_failed';
    public const STATUS_PREVIEWING = 'previewing';
    public const STATUS_PREVIEWED = 'previewed';
    public const STATUS_PREVIEW_FAILED = 'preview_failed';
    public const STATUS_PUBLISH_PENDING = 'publish_pending';
    public const STATUS_PUBLISHING = 'publishing';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_PUBLISH_FAILED = 'publish_failed';
    public const STATUS_ARCHIVED = 'archived';
    public const STATUS_DISABLED = 'disabled';
    public const STATUS_UNINSTALL_PLANNED = 'uninstall_planned';
    public const STATUS_UNINSTALLING = 'uninstalling';
    public const STATUS_UNINSTALLED = 'uninstalled';
    public const STATUS_ROLLBACK_PLANNED = 'rollback_planned';
    public const STATUS_ROLLED_BACK = 'rolled_back';

    public const UNPUBLISHED_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_VALIDATED,
        self::STATUS_VALIDATION_FAILED,
        self::STATUS_PREVIEWED,
        self::STATUS_PREVIEW_FAILED,
        self::STATUS_ARCHIVED,
    ];

    public const RUNTIME_STATUSES = [
        self::STATUS_PUBLISHED,
        self::STATUS_DISABLED,
        self::STATUS_UNINSTALL_PLANNED,
        self::STATUS_UNINSTALLING,
        self::STATUS_UNINSTALLED,
        self::STATUS_ROLLBACK_PLANNED,
        self::STATUS_ROLLED_BACK,
    ];

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'module_name',
        'entity_name',
        'resource_name',
        'status',
        'schema_version',
        'definition_json',
        'checksum',
        'last_validation_report_json',
        'last_preview_manifest_json',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'schema_version' => 'integer',
        'definition_json' => 'array',
        'last_validation_report_json' => 'array',
        'last_preview_manifest_json' => 'array',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    public function versions(): HasMany
    {
        return $this->hasMany(BuilderDefinitionVersion::class);
    }

    public function previewRuns(): HasMany
    {
        return $this->hasMany(BuilderPreviewRun::class);
    }

    public function publishApprovalRequests(): HasMany
    {
        return $this->hasMany(BuilderPublishApprovalRequest::class);
    }

    public function publishAuditLogs(): HasMany
    {
        return $this->hasMany(BuilderPublishAuditLog::class);
    }

    public function transitionTo(string $status, array $attributes = []): bool
    {
        return $this->fill(array_merge($attributes, ['status' => $status]))->save();
    }

    public function isUnpublished(): bool
    {
        return in_array($this->status, self::UNPUBLISHED_STATUSES, true);
    }

    public function canBeArchived(): bool
    {
        return $this->isUnpublished() && $this->status !== self::STATUS_ARCHIVED;
    }

    public function canBeRestored(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    public function canBeDeletedAsDraft(): bool
    {
        return $this->isUnpublished();
    }
}
