<?php

namespace Modules\Saas\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Core\Models\Model;

class SubscriptionHistory extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = ['tenant_id', 'start_date', 'expiry_date', 'package_id', 'payment_amount', 'trial'];

    protected $casts = [
        'trial' => 'boolean',
        'start_date' => 'datetime',
        'expiry_date' => 'datetime',
    ];
}
