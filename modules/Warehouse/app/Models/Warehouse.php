<?php

namespace Modules\Warehouse\Models;

use Modules\Core\Models\Model;
use Modules\Core\Resource\Resourceable;

class Warehouse extends Model
{
    use Resourceable;

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
