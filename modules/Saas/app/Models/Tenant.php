<?php

namespace Modules\Saas\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Modules\Contacts\Models\Contact;
use Modules\Core\Models\Model;
use Modules\Core\Resource\Resourceable;
use Modules\Saas\Enums\TenantDatabaseProvision;
use Modules\Saas\Models\Scopes\TenantScope;

class Tenant extends Model
{
    use HasFactory, Resourceable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'subdomain',
        'subdomain_url',
        'domain',
        'domain_url',
        'contact_id',
        'db_scheme',
        'package_id',
        'start_date',
        'expiry_date',
        'trial',
        'invoice_id',
        'is_active'
    ];

    protected $casts = [
        'first_login' => 'boolean',
        'is_active' => 'boolean',
        'trial' => 'boolean',
        'start_date' => 'datetime',
        'expiry_date' => 'datetime',
        'db_scheme' => TenantDatabaseProvision::class,
    ];

    protected $attributes = [
        'first_login' => true,
    ];

    protected $appends = [
        'login_url'
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'contact_id', 'id');
    }

    /**
     * Get the company industry
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'package_id', 'id');
    }

    public function database()
    {
        return $this->hasOne(TenantDatabase::class, 'tenant_id', 'id')->withoutGlobalScope(TenantScope::class);
    }

    public function modules()
    {
        return $this->hasMany(TenantModule::class);
    }

    public function tenantUsages()
    {
        return $this->hasMany(TenantUsage::class);
    }

    public function hasExpired()
    {
        if ($this->expiry_date == null) {
            return false;
        }

        $overDuesDays = (int) settings()->get('overdue_days');
        if ($overDuesDays && $overDuesDays > 0)
        {
            $this->expiry_date->addDays($overDuesDays)->lt(today());
        }

        return $this->expiry_date->lt(today());
    }

    public function getLoginUrlAttribute()
    {
        return $this->subdomain_url? $this->subdomain_url: $this->domain;
    }

    public static function UrlQueryExpression($as = null)
    {
        $expression = "CASE WHEN subdomain_url IS NOT NULL AND subdomain_url != '' THEN subdomain_url ELSE domain_url END";

        return DB::raw($as ? "$expression AS $as" : $expression);
    }
}
