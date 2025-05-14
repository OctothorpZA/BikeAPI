<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\Rental
 *
 * @property int $id
 * @property int $pax_profile_id
 * @property int $bike_id
 * @property int|null $staff_user_id
 * @property int $start_team_id
 * @property int|null $end_team_id
 * @property int|null $ship_departure_id
 * @property string $booking_code
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $start_time
 * @property \Illuminate\Support\Carbon|null $end_time
 * @property \Illuminate\Support\Carbon|null $expected_end_time
 * @property float|null $rental_price
 * @property string $payment_status
 * @property string|null $payment_method
 * @property string|null $transaction_id
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Bike $bike
 * @property-read \App\Models\Team|null $endTeam
 * @property-read \App\Models\PaxProfile $paxProfile
 * @property-read \App\Models\ShipDeparture|null $shipDeparture
 * @property-read \App\Models\User|null $staffUser
 * @property-read \App\Models\Team $startTeam
 * @method static \Database\Factories\RentalFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Rental newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Rental newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Rental onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Rental query()
 * @method static \Illuminate\Database\Eloquent\Builder|Rental whereBikeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Rental whereBookingCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Rental whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Rental whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Rental whereEndTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Rental whereEndTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Rental whereExpectedEndTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Rental whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Rental whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Rental wherePaxProfileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Rental wherePaymentMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Rental wherePaymentStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Rental whereRentalPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Rental whereShipDepartureId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Rental whereStaffUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Rental whereStartTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Rental whereStartTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Rental whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Rental whereTransactionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Rental whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Rental withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Rental withoutTrashed()
 * @mixin \Eloquent
 */
class Rental extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'pax_profile_id',
        'bike_id',
        'staff_user_id',
        'start_team_id',
        'end_team_id',
        'ship_departure_id',
        'booking_code',
        'status',
        'start_time',
        'end_time',
        'expected_end_time',
        'rental_price',
        'payment_status',
        'payment_method',
        'transaction_id',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'expected_end_time' => 'datetime',
        'rental_price' => 'decimal:2',
        'pax_profile_id' => 'integer',
        'bike_id' => 'integer',
        'staff_user_id' => 'integer',
        'start_team_id' => 'integer',
        'end_team_id' => 'integer',
        'ship_departure_id' => 'integer',
    ];

    /**
     * Get the passenger profile associated with the rental.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\PaxProfile, \App\Models\Rental>
     */
    public function paxProfile(): BelongsTo
    {
        return $this->belongsTo(PaxProfile::class);
    }

    /**
     * Get the bike associated with the rental.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Bike, \App\Models\Rental>
     */
    public function bike(): BelongsTo
    {
        return $this->belongsTo(Bike::class);
    }

    /**
     * Get the staff member who processed the rental.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, \App\Models\Rental>
     */
    public function staffUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }

    /**
     * Get the depot (team) where the rental started.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Team, \App\Models\Rental>
     */
    public function startTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'start_team_id');
    }

    /**
     * Get the depot (team) where the rental ended.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Team, \App\Models\Rental>
     */
    public function endTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'end_team_id');
    }

    /**
     * Get the ship departure associated with the rental (if any).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\ShipDeparture, \App\Models\Rental>
     */
    public function shipDeparture(): BelongsTo
    {
        return $this->belongsTo(ShipDeparture::class);
    }

    // Example Scope: Get active rentals
    // public function scopeActive($query)
    // {
    //     return $query->where('status', 'active');
    // }
}
