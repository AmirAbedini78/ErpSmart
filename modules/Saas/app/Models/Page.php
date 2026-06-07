<?php

namespace Modules\Saas\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Core\Models\Model;
use Modules\Core\Resource\Resourceable;

class Page extends Model
{
    use HasFactory,Resourceable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = ['name', 'html','css', 'status','template_id','slug'];
}
