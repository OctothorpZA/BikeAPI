<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Jetstream\Events\TeamCreated;
use Laravel\Jetstream\Events\TeamDeleted;
use Laravel\Jetstream\Events\TeamUpdated;
use Laravel\Jetstream\Team as JetstreamTeam;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;    // For the POI relationship
use App\Models\PointOfInterest;                     // Import PointOfInterest
use Laravel\Jetstream\Jetstream;                    // For userModel
// TeamInvitation is usually handled by Jetstream's base Team model, but explicit import is fine if needed.
// use Laravel\Jetstream\TeamInvitation;

/**
 * App\Models\Team
 *
 * @property int $id
 * @property int $user_id Owner of the team
 * @property string $name
 * @property bool $personal_team
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $owner
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Jetstream\TeamInvitation> $teamInvitations
 * @property-read int|null $team_invitations_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Bike> $bikes
 * @property-read int|null $bikes_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Rental> $rentalsStartedHere
 * @property-read int|null $rentals_started_here_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Rental> $rentalsEndedHere
 * @property-read int|null $rentals_ended_here_count
 * @property-read \App\Models\PointOfInterest|null $pointOfInterest // Your existing generic POI link
 * @property-read \App\Models\PointOfInterest|null $depotPoi // New specific POI link for the Depot itself
 * @method static \Database\Factories\TeamFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Team newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Team newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Team query()
 * @method static \Illuminate\Database\Eloquent\Builder|Team whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Team whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Team whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Team wherePersonalTeam($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Team whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Team whereUserId($value)
 * @mixin \Eloquent
 */
class Team extends JetstreamTeam
{
    /** @use HasFactory<\Database\Factories\TeamFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'personal_team',
        'user_id', // Ensure user_id is fillable
    ];

    /**
     * The event map for the model.
     *
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'created' => TeamCreated::class,
        'updated' => TeamUpdated::class,
        'deleted' => TeamDeleted::class,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'personal_team' => 'boolean',
        ];
    }

    /**
     * Get the bikes associated with this team (Depot).
     */
    public function bikes(): HasMany
    {
        return $this->hasMany(Bike::class);
    }

    /**
     * Get the rentals that started at this team (Depot).
     */
    public function rentalsStartedHere(): HasMany
    {
        return $this->hasMany(Rental::class, 'start_team_id');
    }

    /**
     * Get the rentals that ended at this team (Depot).
     */
    public function rentalsEndedHere(): HasMany
    {
        return $this->hasMany(Rental::class, 'end_team_id');
    }

    /**
     * Get the generic Point of Interest record for this team (if any).
     * This was your original relationship.
     */
    public function pointOfInterest(): HasOne
    {
        // This assumes 'team_id' is the foreign key in 'points_of_interest' table
        // and there's no specific category filter for this generic relationship.
        return $this->hasOne(PointOfInterest::class, 'team_id');
    }

    /**
     * Get the specific Point of Interest record that IS this Depot.
     * A Team (Depot) should have one PointOfInterest record where the category is 'Depot'.
     */
    public function depotPoi(): HasOne
    {
        return $this->hasOne(PointOfInterest::class, 'team_id') // Foreign key in points_of_interest table
                    ->where('category', PointOfInterest::CATEGORY_DEPOT); // Filter by category
    }


    // --- Jetstream Inherited/Overridden Relationships for Clarity ---
    // Your existing owner(), users(), teamInvitations() methods are good.
    // I'm including them as they were in your uploaded file for completeness.

    /**
     * Get the owner of the team.
     */
    public function owner(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Jetstream::userModel(), 'user_id');
    }

    /**
     * Get all of the users that belong to the team.
     */
    public function users(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Jetstream::userModel(), Jetstream::membershipModel())
                    ->withPivot('role')
                    ->withTimestamps()
                    ->as('membership');
    }

    /**
     * Get all of the pending user invitations for the team.
     */
    public function teamInvitations(): HasMany
    {
        return $this->hasMany(Jetstream::teamInvitationModel());
    }
}
