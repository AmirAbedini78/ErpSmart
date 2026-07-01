<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuilderDefinitionVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'builder_definition_id',
        'version',
        'status',
        'definition_json',
        'checksum',
        'validation_report_json',
        'preview_manifest_json',
        'diff_json',
        'created_by',
    ];

    protected $casts = [
        'builder_definition_id' => 'integer',
        'version' => 'integer',
        'definition_json' => 'array',
        'validation_report_json' => 'array',
        'preview_manifest_json' => 'array',
        'diff_json' => 'array',
        'created_by' => 'integer',
    ];

    public function definition(): BelongsTo
    {
        return $this->belongsTo(BuilderDefinition::class, 'builder_definition_id');
    }
}
