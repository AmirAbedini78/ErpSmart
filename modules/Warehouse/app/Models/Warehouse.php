<?php

namespace Modules\Warehouse\Models;

use Modules\Core\Models\Model;

class Warehouse extends Model
{
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
}
