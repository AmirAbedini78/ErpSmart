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
    public const STATUS_ROLLED_BACK = 'rolled_back';

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

    public function transitionTo(string $status, array $attributes = []): bool
    {
        return $this->fill(array_merge($attributes, ['status' => $status]))->save();
    }
}
