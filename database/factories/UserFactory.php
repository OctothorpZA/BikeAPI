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
            'google_id' => null, // Default google_id to null
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
     */
    public function withPersonalTeam(?callable $callback = null): static
    {
        if (! Features::hasTeamFeatures()) {
            return $this->state([]);
        }

        return $this->has(
            Team::factory()
                ->state(function (array $attributes, User $user) {
                    return [
                        'name' => $user->name.'\'s Team',
                        'user_id' => $user->id,
                        'personal_team' => true,
                    ];
                })
                ->when(is_callable($callback), $callback),
            'ownedTeams'
        );
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            if (Features::hasTeamFeatures()) {
                $personalTeam = $user->personalTeam();
                if (!$personalTeam) {
                    $personalTeam = Team::forceCreate([
                        'user_id' => $user->id,
                        'name' => explode(' ', $user->name, 2)[0]."'s Team",
                        'personal_team' => true,
                    ]);
                }
                $user->switchTeam($personalTeam);
            }

            // Assign a default 'Staff' role ONLY IF no other roles have been assigned.
            if ($user->roles->isEmpty()) {
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
            $user->syncRoles(['Super Admin']);
        });
    }

    /**
     * Assign the 'Owner' role to the user.
     */
    public function owner(): static
    {
        return $this->afterCreating(function (User $user) {
            $user->syncRoles(['Owner']);
        });
    }

    /**
     * Assign the 'Supervisor' role to the user.
     */
    public function supervisor(): static
    {
        return $this->afterCreating(function (User $user) {
            $user->syncRoles(['Supervisor']);
        });
    }

    /**
     * Assign the 'Staff' role to the user.
     */
    public function staff(): static
    {
        return $this->afterCreating(function (User $user) {
            $user->syncRoles(['Staff']);
        });
    }

    /**
     * Indicate that the user is a PWA customer.
     * Assigns the 'PWA User' role and ensures no other staff roles.
     */
    public function pwaUser(): static
    {
        return $this->afterCreating(function(User $user){
            $role = Role::firstOrCreate(['name' => 'PWA User', 'guard_name' => 'web']);
            $user->syncRoles([$role->name]); // Use syncRoles to ensure ONLY PWA User role
        });
    }
}
