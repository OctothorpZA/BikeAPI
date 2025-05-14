<?php

namespace Database\Factories;

use App\Models\PointOfInterest;
use App\Models\Team; // For potentially linking a POI as a Depot
use App\Models\User; // For created_by_user_id and approved_by_user_id
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PointOfInterest>
 */
class PointOfInterestFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = PointOfInterest::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categories = ['Depot', 'Cafe', 'Landmark', 'Viewpoint', 'Repair Station', 'Restaurant', 'Museum', 'Park'];
        $isDepot = $this->faker->boolean(20); // 20% chance this POI is a Depot

        $teamId = null;
        if ($isDepot) {
            // If it's a Depot, ensure a Team exists or create one.
            // For simplicity here, we'll try to get a random one or create one.
            // In a seeder, you'd likely create a Team first, then its corresponding POI.
            $team = Team::inRandomOrder()->first() ?? Team::factory()->create();
            $teamId = $team->id;
        }

        return [
            'team_id' => $teamId,
            'name' => $isDepot ? ($team->name ?? 'Depot ' . $this->faker->citySuffix) : $this->faker->company . ' ' . $this->faker->companySuffix,
            'category' => $isDepot ? 'Depot' : $this->faker->randomElement($categories),
            'description' => $this->faker->optional()->paragraph(2),
            'latitude' => $this->faker->latitude(),
            'longitude' => $this->faker->longitude(),
            'address_line_1' => $this->faker->streetAddress(),
            'address_line_2' => $this->faker->optional()->secondaryAddress(),
            'city' => $this->faker->city(),
            'state_province' => $this->faker->optional()->state(),
            'postal_code' => $this->faker->postcode(),
            'country_code' => $this->faker->countryCode(),
            'phone_number' => $this->faker->optional()->phoneNumber(),
            'website_url' => $this->faker->optional()->url(),
            'primary_image_url' => $this->faker->optional(0.5)->imageUrl(640, 480, 'business'), // 50% chance of having an image
            'is_approved' => $this->faker->boolean(80), // 80% are pre-approved for demo data
            'is_active' => $this->faker->boolean(95),   // 95% are active

            // Assign to a staff/supervisor user. Ensure UserFactory handles roles or select appropriate users in seeder.
            'created_by_user_id' => User::factory(), // Or User::inRandomOrder()->first()?->id,
            'approved_by_user_id' => $this->faker->boolean(70) ? (User::factory()) : null, // 70% of approved ones are approved by someone
        ];
    }

    /**
     * Indicate that the point of interest is a Depot.
     *
     * @param \App\Models\Team|null $team The team to associate as the depot.
     * @return \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PointOfInterest>
     */
    public function isDepot(Team $team = null): Factory
    {
        return $this->state(function (array $attributes) use ($team) {
            $team = $team ?? Team::factory()->create();
            return [
                'team_id' => $team->id,
                'name' => $team->name, // Use the team's name for the POI name
                'category' => 'Depot',
                'is_approved' => true,
                'is_active' => true,
            ];
        });
    }

    /**
     * Indicate that the point of interest is approved.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PointOfInterest>
     */
    public function approved(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'is_approved' => true,
                'approved_by_user_id' => $attributes['approved_by_user_id'] ?? User::factory(), // Ensure an approver if not set
            ];
        });
    }
}
