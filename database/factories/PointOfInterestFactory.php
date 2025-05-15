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
        // Using constants from the PointOfInterest model for categories
        $categories = [
            PointOfInterest::CATEGORY_DEPOT, // Use constant
            'Cafe', 'Landmark', 'Viewpoint', 'Repair Station', 'Restaurant', 'Museum', 'Park',
            PointOfInterest::CATEGORY_STAFF_PICK, // Use constant
            PointOfInterest::CATEGORY_GENERAL,    // Use constant
        ];
        // Remove duplicates if any were added manually and also exist as constants
        $categories = array_unique($categories);


        // In a general definition, creating a 'Depot' might be less common.
        // The 'isDepot' state is specifically for creating Depot POIs.
        // Let's assume default POIs created by this factory are not depots unless specified by a state.
        $isDepotCategory = false; // Default to not being a depot
        $selectedCategory = $this->faker->randomElement(array_filter($categories, fn($cat) => $cat !== PointOfInterest::CATEGORY_DEPOT));


        $teamId = null;
        // The following logic for creating a team if $isDepot is true is generally
        // better handled by the specific seeder logic or the isDepot() state.
        // For a generic POI, team_id is often null or set explicitly.
        // if ($isDepotCategory) {
        //     $team = Team::inRandomOrder()->first() ?? Team::factory()->create();
        //     $teamId = $team->id;
        // }

        return [
            'team_id' => $teamId, // Usually null for generic POIs, or set by a state like isDepot()
            'name' => $this->faker->company,
            'category' => $selectedCategory,
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
            'primary_image_url' => $this->faker->optional(0.3)->imageUrl(640, 480, 'business'), // 30% chance
            'is_approved' => $this->faker->boolean(70), // 70% are pre-approved for demo data
            'is_active' => $this->faker->boolean(90),   // 90% are active

            // It's better to assign existing users in the seeder rather than creating new ones here.
            // These will be overridden by the TeamFactory when creating Depot POIs.
            // For other POIs, you'd pass these in the seeder: PointOfInterest::factory()->create(['created_by_user_id' => $someStaffUser->id])
            'created_by_user_id' => null, // Default to null, set explicitly in seeder/other factories
            'approved_by_user_id' => null, // Default to null, set explicitly in seeder/other factories
        ];
    }

    /**
     * Indicate that the point of interest is a Depot.
     * This state is used by TeamFactory.
     *
     * @param \App\Models\Team $team The team to associate as the depot.
     * @return \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PointOfInterest>
     */
    public function isDepot(Team $team): Factory // Team should not be nullable if this state guarantees a Depot
    {
        return $this->state(function (array $attributes) use ($team) {
            return [
                'team_id' => $team->id,
                'name' => $team->name . ' Depot', // Make it clear it's the Depot POI
                'category' => PointOfInterest::CATEGORY_DEPOT, // Use constant
                'is_approved' => true,
                'is_active' => true,
                'description' => $attributes['description'] ?? 'Official depot location for ' . $team->name . '.',
                'latitude' => $attributes['latitude'] ?? $this->faker->latitude(), // Ensure these have values
                'longitude' => $attributes['longitude'] ?? $this->faker->longitude(),
                // created_by_user_id and approved_by_user_id are set by TeamFactory's configure method
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
            // Attempt to get an existing Supervisor or Owner to be the approver
            $approver = User::role(['Supervisor', 'Owner', 'Super Admin'])->inRandomOrder()->first();
            return [
                'is_approved' => true,
                'approved_by_user_id' => $attributes['approved_by_user_id'] ?? $approver?->id, // Use existing admin/supervisor if possible
            ];
        });
    }

     /**
     * Indicate that the point of interest is a Staff Pick.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PointOfInterest>
     */
    public function staffPick(): Factory
    {
        // Attempt to get an existing Staff member to be the creator
        $creator = User::role('Staff')->inRandomOrder()->first();
        return $this->state(function (array $attributes) use ($creator) {
            return [
                'category' => PointOfInterest::CATEGORY_STAFF_PICK,
                'created_by_user_id' => $attributes['created_by_user_id'] ?? $creator?->id,
                'is_approved' => false, // Staff picks start as unapproved by default
                'is_active' => true,    // But they can be active to be reviewed
            ];
        });
    }
}
