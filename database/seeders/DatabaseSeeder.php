<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Disable foreign key constraints
        \DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Truncate tables in correct order (child first, then parent)
        \DB::table('car_models')->truncate();
        \DB::table('car_makers')->truncate();
        \DB::table('clients')->truncate();

        // Re-enable foreign key constraints
        \DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->call([
            StateSeeder::class,
            OrderTypeSeeder::class,
            OrderStatusSeeder::class,
            FeatureSeeder::class,
            PlanSeeder::class,
            PlanFeatureSeeder::class,
            CarMakerSeeder::class,
            CarModelSeeder::class,
            CategorySeeder::class,
            RolePermissionSeeder::class,
        ]);
    }
}
