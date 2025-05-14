<?php

namespace Database\Factories;

use App\Models\PaxProfile;
use App\Models\User; // Import User model for potential linking
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PaxProfile>
 */
class PaxProfileFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = PaxProfile::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Optionally link to an existing User (PWA account) or create one.
            // For demo data, you might want to create some profiles linked to users
            // and some unlinked.
            // 'user_id' => $this->faker->boolean(30) ? User::factory() : null,
            // For now, let's make it null by default and handle linking in the seeder if needed.
            'user_id' => null,

            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone_number' => $this->faker->optional()->phoneNumber(),
            'country_of_residence' => $this->faker->optional()->countryCode(), // ISO 3166-1 alpha-2

            // Be cautious with generating/storing fake passport numbers if not strictly needed for testing.
            'passport_number' => $this->faker->boolean(10) ? strtoupper($this->faker->bothify('??######')) : null,

            'date_of_birth' => $this->faker->optional()->date('Y-m-d', '2004-01-01'), // Adults mostly
            'notes' => $this->faker->boolean(15) ? $this->faker->paragraph() : null,
        ];
    }

    /**
     * Indicate that the pax profile should be linked to a new PWA user.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PaxProfile>
     */
    public function linkedToUser(): Factory
    {
        return $this->state(function (array $attributes) {
            // Create a user that is specifically for PWA (you might add a role or flag later)
            $user = User::factory()->create(); // Or User::factory()->pwaUser()->create(); if you add a state to UserFactory
            return [
                'user_id' => $user->id,
                'email' => $user->email, // Match the user's email
            ];
        });
    }
}
