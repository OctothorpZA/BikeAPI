<?php

namespace Database\Seeders;

use App\Models\User; // Keep if you have default user creation here
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Call the Roles and Permissions Seeder first
        $this->call(RolesPermissionsSeeder::class);

        // Conditionally call the DemoDataSeeder for non-production environments
        if (!app()->environment('production')) {
            $this->command->info('Non-production environment detected, running DemoDataSeeder.');
            $this->call(DemoDataSeeder::class);
        } else {
            $this->command->info('Production environment detected, skipping DemoDataSeeder.');
        }

        // You can call other seeders here if needed, for example:
        // User::factory(10)->create(); // If you want some generic users
        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
    }
}
