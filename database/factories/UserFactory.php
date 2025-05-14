<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role; // Import the Spatie Role model

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'remember_token' => Str::random(10),
            'profile_photo_path' => null,
            // current_team_id will be set via the configure() method or withPersonalTeam()
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user should have a personal team.
     * This is called by the configure method by default.
     */
    public function withPersonalTeam(?callable $callback = null): static
    {
        if (! Features::hasTeamFeatures()) {
            return $this->state([]);
        }

        return $this->has(
            Team::factory()
                ->state(function (array $attributes, User $user) {
                    // This creates the personal team and sets its user_id to this user.
                    return [
                        'name' => $user->name.'\'s Team',
                        'user_id' => $user->id,
                        'personal_team' => true,
                    ];
                })
                ->when(is_callable($callback), $callback),
            'ownedTeams' // This is the relationship Jetstream uses for teams owned by the user
        );
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            if (Features::hasTeamFeatures()) {
                // Ensure a personal team exists or create one
                $personalTeam = $user->personalTeam();
                if (!$personalTeam) {
                    $personalTeam = Team::forceCreate([
                        'user_id' => $user->id,
                        'name' => explode(' ', $user->name, 2)[0]."'s Team",
                        'personal_team' => true,
                    ]);
                }
                // Switch to the personal team and save current_team_id
                $user->switchTeam($personalTeam);
            }

            // Assign a default role if no specific role state was used during factory call.
            // This ensures every user created by the factory (without a specific role state) gets a default role.
            if ($user->roles->isEmpty()) {
                // Ensure the 'Staff' role exists (create if not, though seeder is better for this)
                $defaultRole = Role::firstOrCreate(['name' => 'Staff', 'guard_name' => 'web']);
                $user->assignRole($defaultRole);
            }
        });
    }

    /**
     * Assign the 'Super Admin' role to the user.
     */
    public function superAdmin(): static
    {
        return $this->afterCreating(function (User $user) {
            $role = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
            $user->assignRole($role);
        });
    }

    /**
     * Assign the 'Owner' role to the user.
     */
    public function owner(): static
    {
        return $this->afterCreating(function (User $user) {
            $role = Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
            $user->assignRole($role);
        });
    }

    /**
     * Assign the 'Supervisor' role to the user.
     */
    public function supervisor(): static
    {
        return $this->afterCreating(function (User $user) {
            $role = Role::firstOrCreate(['name' => 'Supervisor', 'guard_name' => 'web']);
            $user->assignRole($role);
        });
    }

    /**
     * Assign the 'Staff' role to the user.
     */
    public function staff(): static
    {
        return $this->afterCreating(function (User $user) {
            $role = Role::firstOrCreate(['name' => 'Staff', 'guard_name' => 'web']);
            $user->assignRole($role);
        });
    }

    /**
     * Indicate that the user is a PWA customer.
     * This state might not assign a Spatie role, or assign a specific 'PWA User' role.
     */
    public function pwaUser(): static
    {
        return $this->state(function (array $attributes) {
            // PWA users might not need a personal Jetstream team by default,
            // or their team setup might be different.
            // For now, this state doesn't modify team creation.
            return [];
        })->afterCreating(function(User $user){
            // If you have a specific 'PWA User' role:
            // $role = Role::firstOrCreate(['name' => 'PWA User', 'guard_name' => 'web']);
            // $user->assignRole($role);

            // If PWA users don't get a default Spatie role via the factory,
            // ensure the default role assignment in configure() is appropriate or conditional.
            // For now, the configure() method assigns 'Staff' if no other role is present.
            // You might want to adjust that if creating PWA users directly with the factory.
        });
    }
}
