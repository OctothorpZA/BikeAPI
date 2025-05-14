<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Team>
 */
class TeamFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Team::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // By default, create a non-personal team (a Depot)
            // The name can be more descriptive for a depot.
            'name' => $this->faker->city() . ' Depot',
            'user_id' => User::factory(), // Assigns a new user as the owner by default
            'personal_team' => false, // Depots are not personal teams
        ];
    }

    /**
     * Indicate that the team is a personal team.
     *
     * @param  \App\Models\User  $user The user who owns this personal team.
     * @return static
     */
    public function personalTeam(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $user->name.'\'s Team',
            'user_id' => $user->id,
            'personal_team' => true,
        ]);
    }

    /**
     * Configure the model factory.
     *
     * @return $this
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Team $team) {
            if (!$team->personal_team) {
                // If it's a Depot, automatically create a PointOfInterest entry for it.
                // The POI factory will use the team's name and set category to 'Depot'.
                \App\Models\PointOfInterest::factory()->isDepot($team)->create([
                    'created_by_user_id' => $team->user_id, // Owner of the team creates the POI
                    'approved_by_user_id' => $team->user_id, // Owner also approves it
                ]);
            }
        });
    }
}
