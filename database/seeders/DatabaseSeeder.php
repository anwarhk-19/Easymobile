<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** Production-safe base data. Sample marketplace data is added only outside production. */
    public function run(): void
    {
        $this->call([RolesAndPermissionsSeeder::class, CatalogueSeeder::class, PlatformSeeder::class]);

        if (! app()->isProduction()) {
            $this->call(SampleMarketplaceSeeder::class);
        }
    }
}
