<?php

namespace Modules\Saas\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Core\Models\Model;

// use Modules\Saas\Database\factories\TenantModuleFactory;

class PageModule extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = ['name', 'markdown', 'status'];
}
