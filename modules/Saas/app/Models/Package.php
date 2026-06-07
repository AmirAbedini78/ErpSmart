<?php

namespace Modules\Saas\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Billable\Models\Product;
use Modules\Core\Models\Model;
use Modules\Core\Resource\Resourceable;

class Package extends Model
{
    use HasFactory, Resourceable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = ['name', 'description', 'base_price', 'reocurring_period', 'trial_period', 'db_scheme', 'product_id'];

    public function quotas(): BelongsToMany
    {
        return $this->belongsToMany(Quota::class, 'package_quotas')->withPivot('limit')->withTimestamps();
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class, 'package_id', 'id');
    }
}
