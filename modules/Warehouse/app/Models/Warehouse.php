<?php

namespace Modules\Warehouse\Models;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Modules\Core\Common\Timeline\HasTimeline;
use Modules\Core\Models\Model;
use Modules\Core\Resource\Resourceable;
use Modules\Notes\Models\Note;

class Warehouse extends Model
{
    use HasTimeline,
        Resourceable;

    protected $table = 'warehouses';

    protected $attributes = [
        'is_active' => true,
    ];

    protected $fillable = [
        'name',
        'code',
        'description',
        'is_active',
        'import_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'import_id' => 'integer',
    ];

    /**
     * Notes attached to this warehouse.
     *
     * Concord/ErpSmart stores note relations through the shared "noteables"
     * morph pivot. Keeping the relationship on the domain model makes the
     * generic resource timeline endpoint able to serve /api/warehouses/{id}/notes.
     */
    public function notes(): MorphToMany
    {
        return $this->morphToMany(Note::class, 'noteable')->withTimestamps();
    }

    /**
     * Normalize boolean values coming from Resource forms, API requests, and CSV imports.
     *
     * The Core importer may receive values such as "true", "false", "1", "0",
     * "yes", "no", "on", and "off" from CSV files. MySQL boolean columns are
     * tiny integers, so keeping this normalization close to the model prevents
     * import/update failures like "Incorrect integer value".
     */
    public function setIsActiveAttribute(mixed $value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['is_active'] = true;

            return;
        }

        $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        $this->attributes['is_active'] = $normalized ?? (bool) $value;
    }
}
