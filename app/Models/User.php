<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail; // Uncomment if you implement email verification
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Jetstream\HasTeams;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles; // Spatie's HasRoles trait
use Lab404\Impersonate\Models\Impersonate; // <<<<<<<< 1. IMPORT THE TRAIT
use Illuminate\Database\Eloquent\Relations\HasMany;
// use Illuminate\Database\Eloquent\Relations\HasManyThrough; // Uncomment if used

/**
 * App\Models\User
 *
 * (Your existing PHPDoc block - it's good, ensure it's up-to-date if you use IDE helper,
 * or add the following lines manually if not using an IDE helper to regenerate it)
 * @method bool canImpersonate()
 * @method bool canBeImpersonated(?User $impersonator = null)
 * @property-read bool $is_impersonating
 * @property-read \App\Models\Team|null $currentTeam
 * @property-read string $profile_photo_url
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Team> $ownedTeams
 * @property-read int|null $owned_teams_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Team> $teams
 * @property-read int|null $teams_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PaxProfile> $paxProfiles
 * @property-read int|null $pax_profiles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Rental> $processedRentals
 * @property-read int|null $processed_rentals_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PointOfInterest> $createdPointsOfInterest
 * @property-read int|null $created_points_of_interest_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PointOfInterest> $approvedPointsOfInterest
 * @property-read int|null $approved_points_of_interest_count
 */
class User extends Authenticatable // Consider adding implements MustVerifyEmail if you enable email verification
{
    use HasApiTokens;
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use HasProfilePhoto;
    use HasTeams;
    use Notifiable;
    use TwoFactorAuthenticatable;
    use HasRoles;
    use Impersonate; // <<<<<<<< 2. USE THE TRAIT

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Get the user's initials.
     * Placeholder implementation.
     */
    public function initials(): string
    {
        if ($this->name) {
            $names = explode(' ', (string) $this->name);
            $initials = '';
            foreach ($names as $namePart) {
                if (!empty($namePart)) {
                    $initials .= strtoupper($namePart[0]);
                }
            }
            return $initials;
        }
        return 'XX'; // Default if name is not set
    }

    /**
     * Get the pax profiles associated with this user (if this user is a PWA customer).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\PaxProfile, \App\Models\User>
     */
    public function paxProfiles(): HasMany
    {
        return $this->hasMany(PaxProfile::class);
    }

    /**
     * Get the rentals processed by this user (if this user is a staff member).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\Rental, \App\Models\User>
     */
    public function processedRentals(): HasMany
    {
        return $this->hasMany(Rental::class, 'staff_user_id');
    }

    /**
     * Get the points of interest created by this user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\PointOfInterest, \App\Models\User>
     */
    public function createdPointsOfInterest(): HasMany
    {
        return $this->hasMany(PointOfInterest::class, 'created_by_user_id');
    }

    /**
     * Get the points of interest approved by this user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\PointOfInterest, \App\Models\User>
     */
    public function approvedPointsOfInterest(): HasMany
    {
        return $this->hasMany(PointOfInterest::class, 'approved_by_user_id');
    }

    // --- Laravel Impersonate Methods ---

    /**
     * Define who can impersonate other users.
     * Only users with the 'Super Admin' role can impersonate.
     *
     * @return bool
     */
    public function canImpersonate(): bool
    {
        return $this->hasRole('Super Admin');
    }

    /**
     * Define who can be impersonated.
     * Users with the 'Super Admin' role cannot be impersonated.
     *
     * @param \App\Models\User|null $impersonator The user attempting to impersonate.
     * @return bool
     */
    public function canBeImpersonated(?User $impersonator = null): bool
    {
        // Prevent Super Admins from being impersonated
        if ($this->hasRole('Super Admin')) {
            return false;
        }

        // Add any other logic here if needed, for example,
        // if $impersonator is not null, you could check if $impersonator has specific permissions
        // to impersonate this specific user or users with this user's role.
        // For now, simply not being a Super Admin is enough to be impersonable by an authorized impersonator.
        return true;
    }
}
