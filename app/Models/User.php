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
use Lab404\Impersonate\Models\Impersonate;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
    use Impersonate;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id', // <-- ADD THIS LINE
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
     */
    public function paxProfiles(): HasMany
    {
        return $this->hasMany(PaxProfile::class);
    }

    /**
     * Get the rentals processed by this user (if this user is a staff member).
     */
    public function processedRentals(): HasMany
    {
        return $this->hasMany(Rental::class, 'staff_user_id');
    }

    /**
     * Get the points of interest created by this user.
     */
    public function createdPointsOfInterest(): HasMany
    {
        return $this->hasMany(PointOfInterest::class, 'created_by_user_id');
    }

    /**
     * Get the points of interest approved by this user.
     */
    public function approvedPointsOfInterest(): HasMany
    {
        return $this->hasMany(PointOfInterest::class, 'approved_by_user_id');
    }

    // --- Laravel Impersonate Methods ---

    public function canImpersonate(): bool
    {
        return $this->hasRole('Super Admin');
    }

    public function canBeImpersonated(?User $impersonator = null): bool
    {
        if ($this->hasRole('Super Admin')) {
            return false;
        }
        return true;
    }
}
