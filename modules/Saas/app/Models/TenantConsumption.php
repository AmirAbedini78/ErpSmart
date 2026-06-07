<?php

namespace Modules\Saas\Models;

use Modules\Core\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Saas\Database\factories\TenantConsumptionFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;


class TenantConsumption extends Model
{
    use HasFactory;

    protected $table = 'tenant_consumptions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = ['count', 'tenant_id', 'model'];

    public function tenant():BelongsTo       {
        return $this->belongsTo(Tenant::class);
    }

}
