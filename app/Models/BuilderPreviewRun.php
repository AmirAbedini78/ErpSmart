<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuilderPreviewRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'builder_definition_id',
        'status',
        'preview_path',
        'manifest_json',
        'output_text',
        'error_text',
        'started_at',
        'finished_at',
        'created_by',
    ];

    protected $casts = [
        'builder_definition_id' => 'integer',
        'manifest_json' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'created_by' => 'integer',
    ];

    public function definition(): BelongsTo
    {
        return $this->belongsTo(BuilderDefinition::class, 'builder_definition_id');
    }
}
