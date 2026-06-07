<?php

namespace Modules\Saas\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Core\Models\Model;

class TenantModule extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'is_enabled',
        'is_core',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'is_core' => 'boolean',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
