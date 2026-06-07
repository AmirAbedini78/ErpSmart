<?php

namespace Modules\Saas\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Template extends Model
{
    use HasFactory;


    protected $fillable = ['uuid', 'name', 'path', 'index_file'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($template) {
            $template->uuid = (string) \Str::uuid();
        });
    }

}
