<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\Bike
 *
 * @property int $id
 * @property int $team_id
 * @property string $bike_identifier
 * @property string|null $nickname
 * @property string $type
 * @property string $status
 * @property float|null $current_latitude
 * @property float|null $current_longitude
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Team $team
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Rental> $rentals
 * @property-read int|null $rentals_count
 * @method static \Database\Factories\BikeFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Bike newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Bike newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Bike onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Bike query()
 * @method static \Illuminate\Database\Eloquent\Builder|Bike whereBikeIdentifier($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Bike whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Bike whereCurrentLatitude($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Bike whereCurrentLongitude($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Bike whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Bike whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Bike whereNickname($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Bike whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Bike whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Bike whereTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Bike whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Bike whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Bike withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Bike withoutTrashed()
 * @mixin \Eloquent
 */
class Bike extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'team_id',
        'bike_identifier',
        'nickname',
        'type',
        'status',
        'current_latitude',
        'current_longitude',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'current_latitude' => 'decimal:7', // Matches the decimal(10,7) in migration
        'current_longitude' => 'decimal:7',// Matches the decimal(10,7) in migration
        'team_id' => 'integer',
    ];

    /**
     * Get the team (depot) that this bike belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Team, \App\Models\Bike>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the rentals associated with this bike.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\Rental, \App\Models\Bike>
     */
    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }

    // You can add scopes here if needed, for example:
    // public function scopeAvailable($query)
    // {
    //     return $query->where('status', 'available');
    // }

    // public function scopeOfType($query, $type)
    // {
    //     return $query->where('type', $type);
    // }
}
