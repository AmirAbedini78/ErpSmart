<?php

namespace Modules\Saas\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Core\Models\Model;
use Modules\Core\Resource\Resourceable;

class Quota extends Model
{
    use HasFactory,Resourceable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = ['name', 'description', 'models'];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'models' => 'array',
    ];

    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(Package::class, 'package_quotas')->withPivot('limit')->withTimestamps();
    }

    public function getModelsDisplayAttribute()
    {
        $models = json_decode($this->attributes['models']) ?? [];
        $modelStr = '';

        foreach ($models as $index => $model) {
            $modelName = explode('\\', $model);
            if ($index == 0) {
                $modelStr .= $modelName[count($modelName) - 1];
            } else {
                $modelStr .= ', '.$modelName[count($modelName) - 1];
            }
        }

        return $modelStr;
    }
}
