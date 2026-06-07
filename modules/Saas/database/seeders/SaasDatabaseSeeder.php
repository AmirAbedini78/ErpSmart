<?php

namespace Modules\Saas\Database\Seeders;

use Illuminate\Database\Seeder;

class SaasDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(SeedPackagesSeeder::class);
        $this->call(SeedPagesSeeder::class);
        $this->call(SeedTenantStatSeeder::class);

    }
}
