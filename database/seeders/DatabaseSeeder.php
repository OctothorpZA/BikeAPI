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
    // User::factory(10)->create(); // Example: Keep if you want 10 generic users

        // Call your new RolesPermissionsSeeder
        $this->call([
            RolesPermissionsSeeder::class,
            // You will add DemoDataSeeder::class here later
        ]);

        // Example: Create a specific user if needed after roles are set up
        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
    }
}
