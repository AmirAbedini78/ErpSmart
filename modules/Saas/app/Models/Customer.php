<?php

namespace Modules\Saas\Models;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Modules\Core\Concerns\HasUuid;
use Modules\Core\Contracts\HasNotificationsSettings;
use Modules\Core\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Customer extends Model implements HasNotificationsSettings
{
    use HasFactory, Notifiable, HasUuid;

    protected $table = 'contacts';

    protected $guarded = [
        'created_by',
        'created_at',
        'updated_at',
        'owner_assigned_date',
        'next_activity_id',
        'uuid',
    ];

    /**
     * Raw concat attributes for query
     *
     * @param  array  $attributes
     * @param  string  $separator
     * @return \Illuminate\Database\Query\Expression|null
     */
    public static function nameQueryExpression($as = null)
    {
        $driver = (new static)->getConnection()->getDriverName();

        return match ($driver) {
            'mysql', 'pgsql', 'mariadb' => DB::raw('RTRIM(CONCAT(first_name, \' \', COALESCE(last_name, \'\')))'.($as ? ' as '.$as : '')),
            'sqlite' => DB::raw('RTRIM(first_name || \' \' || last_name)'.($as ? ' as '.$as : '')),
            default => throw new \Exception('Unsupported driver: '.$driver),
        };
    }

    /**
     * Get all of the companies that are associated with the contact
     */
    public function companies(): MorphToMany
    {
        return $this->morphedByMany(\Modules\Contacts\Models\Company::class, 'contactable', 'contactables', 'contact_id', 'contactable_id')
            ->withTimestamps()
            ->orderBy('contactables.created_at');
    }

    public function getNotificationsPreferences(string $key): array
    {
        return [
            "mail" => true,
            "database" => false,
            "broadcast" => false
        ];
    }
}
