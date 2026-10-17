<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesSeeder::class,
            ReferenceDataSeeder::class,
            CatalogSeeder::class,
            ContentSeeder::class,
            StaffSeeder::class,
        ]);

        if (! app()->isProduction() || env('SEED_DEMO_DATA', false)) {
            $this->call(DemoSeeder::class);
        }
    }
}
