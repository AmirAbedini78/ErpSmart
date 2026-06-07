<?php

namespace Modules\Saas\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SeedPackagesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('packages')->insert([
            [
                'name' => 'Pacakage',
                'description' => 'This is a basic package with essential features.',
                'base_price' => 99.99,
                'reocurring_period' => 'monthly',
                'trial_period' => 14,
                'has_domain' => true,
                'has_subdomain' => true,
                'db_scheme' => Str::random(10), // generating a random db scheme name
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
