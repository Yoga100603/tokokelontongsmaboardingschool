<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** SEED_DEMO=0 di .env mematikan data demo. */
    public function run(): void
    {
        if (env('SEED_DEMO', '1') !== '0') {
            $this->call(DemoSeeder::class);
        }
    }
}
