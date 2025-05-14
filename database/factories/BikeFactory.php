<?php

namespace Database\Factories;

use App\Models\Bike;
use App\Models\Team; // Import the Team model
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str; // Import Str for bike_identifier

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Bike>
 */
class BikeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Bike::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $bikeTypes = ['standard', 'electric', 'mountain', 'kids', 'road', 'hybrid'];
        $bikeStatuses = ['available', 'rented', 'maintenance', 'unavailable'];

        return [
            // Assign to an existing Team (Depot).
            // Assumes you will have Teams created before Bikes when seeding.
            // If no teams exist, this will fail. Consider a fallback or ensure TeamFactory runs first.
            'team_id' => Team::factory(), // Or Team::inRandomOrder()->first()->id if you want to pick from existing

            'bike_identifier' => 'BIKE-' . strtoupper(Str::random(8)), // e.g., BIKE-A1B2C3D4
            'nickname' => $this->faker->boolean(70) ? $this->faker->firstName() . "'s " . $this->faker->colorName() : null, // e.g., "John's Red" or null
            'type' => $this->faker->randomElement($bikeTypes),
            'status' => $this->faker->randomElement($bikeStatuses),

            // Optional: Generate fake coordinates for some bikes
            'current_latitude' => $this->faker->boolean(30) ? $this->faker->latitude() : null,
            'current_longitude' => $this->faker->boolean(30) ? $this->faker->longitude() : null,

            'notes' => $this->faker->boolean(25) ? $this->faker->sentence() : null,
            // created_at and updated_at are handled by Eloquent automatically
            // deleted_at is handled by SoftDeletes trait if you use ->delete()
        ];
    }

    /**
     * Indicate that the bike is of type 'electric'.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Bike>
     */
    public function electric(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => 'electric',
            ];
        });
    }

    /**
     * Indicate that the bike is currently 'rented'.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Bike>
     */
    public function rented(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'rented',
            ];
        });
    }

    /**
     * Indicate that the bike is 'available'.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Bike>
     */
    public function available(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'available',
            ];
        });
    }

    /**
     * Indicate that the bike needs 'maintenance'.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Bike>
     */
    public function needsMaintenance(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'maintenance',
            ];
        });
    }
}
