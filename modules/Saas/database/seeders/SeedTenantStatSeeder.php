<?php

namespace Modules\Saas\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SeedTenantStatSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('tenant_usages')->insert([
            [
                'tenant_id' => 1,
                'count' => '1',
                'model' => 'Modules\Contacts\Models',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
