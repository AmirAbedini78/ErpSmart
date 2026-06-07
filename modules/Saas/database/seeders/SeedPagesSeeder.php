<?php

namespace Modules\Saas\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SeedPagesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('pages')->insert([
            [
                'name' => 'Landing Page',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Pro Package',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Enterprise Package',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
