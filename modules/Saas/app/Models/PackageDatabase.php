<?php

namespace Modules\Saas\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Core\Models\Model;

class PackageDatabase extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'package_id',
        'db_host',
        'db_port',
        'database',
        'db_username',
        'db_password',
    ];
}
