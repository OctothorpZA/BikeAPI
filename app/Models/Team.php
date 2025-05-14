<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Jetstream\Events\TeamCreated;
use Laravel\Jetstream\Events\TeamDeleted;
use Laravel\Jetstream\Events\TeamUpdated;
use Laravel\Jetstream\Team as JetstreamTeam;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne; // For the POI relationship if a Team is one POI
use App\Models\User; // For owner relationship
use Laravel\Jetstream\Jetstream; // For userModel
use Laravel\Jetstream\TeamInvitation; // For teamInvitations relationship

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
 * @property-read \App\Models\PointOfInterest|null $pointOfInterest
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
        'user_id', // Ensure user_id is fillable if you create teams programmatically assigning an owner
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
     * A Depot has many bikes.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\Bike, \App\Models\Team>
     */
    public function bikes(): HasMany
    {
        return $this->hasMany(Bike::class);
    }

    /**
     * Get the rentals that started at this team (Depot).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\Rental, \App\Models\Team>
     */
    public function rentalsStartedHere(): HasMany
    {
        return $this->hasMany(Rental::class, 'start_team_id');
    }

    /**
     * Get the rentals that ended at this team (Depot).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\Rental, \App\Models\Team>
     */
    public function rentalsEndedHere(): HasMany
    {
        return $this->hasMany(Rental::class, 'end_team_id');
    }

    /**
     * Get the Point of Interest record for this team (Depot).
     * A Depot is a type of Point of Interest.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne<\App\Models\PointOfInterest, \App\Models\Team>
     */
    public function pointOfInterest(): HasOne
    {
        return $this->hasOne(PointOfInterest::class);
    }

    // Jetstream's default relationships are inherited from JetstreamTeam:
    // - owner(): BelongsTo (User that owns the team)
    // - users(): BelongsToMany (Users that are members of the team)
    // - teamInvitations(): HasMany (Invitations for this team)
    // We ensure PHPDocs are clear for these if needed for PHPStan.

    /**
     * Get the owner of the team.
     * Overriding to ensure precise PHPDoc for PHPStan if needed.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, \App\Models\Team>
     */
    public function owner(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Jetstream::userModel(), 'user_id');
    }

    /**
     * Get all of the users that belong to the team.
     * Overriding to ensure precise PHPDoc for PHPStan if needed.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\App\Models\User>
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
     * Overriding to ensure precise PHPDoc for PHPStan if needed.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\Laravel\Jetstream\TeamInvitation, \App\Models\Team>
     */
    public function teamInvitations(): HasMany
    {
        return $this->hasMany(Jetstream::teamInvitationModel());
    }
}
