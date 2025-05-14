<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\ShipDeparture
 *
 * @property int $id
 * @property string $ship_name
 * @property string|null $cruise_line_name
 * @property string $departure_port_name
 * @property string|null $arrival_port_name
 * @property \Illuminate\Support\Carbon $departure_datetime
 * @property \Illuminate\Support\Carbon|null $expected_arrival_datetime_at_port
 * @property \Illuminate\Support\Carbon|null $final_boarding_datetime
 * @property string|null $voyage_number
 * @property string|null $notes
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Rental> $rentals
 * @property-read int|null $rentals_count
 * @method static \Database\Factories\ShipDepartureFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|ShipDeparture newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ShipDeparture newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ShipDeparture onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|ShipDeparture query()
 * @method static \Illuminate\Database\Eloquent\Builder|ShipDeparture whereArrivalPortName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ShipDeparture whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ShipDeparture whereCruiseLineName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ShipDeparture whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ShipDeparture whereDepartureDatetime($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ShipDeparture whereDeparturePortName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ShipDeparture whereExpectedArrivalDatetimeAtPort($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ShipDeparture whereFinalBoardingDatetime($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ShipDeparture whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ShipDeparture whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ShipDeparture whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ShipDeparture whereShipName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ShipDeparture whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ShipDeparture whereVoyageNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ShipDeparture withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|ShipDeparture withoutTrashed()
 * @mixin \Eloquent
 */
class ShipDeparture extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'ship_name',
        'cruise_line_name',
        'departure_port_name',
        'arrival_port_name',
        'departure_datetime',
        'expected_arrival_datetime_at_port',
        'final_boarding_datetime',
        'voyage_number',
        'notes',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'departure_datetime' => 'datetime',
        'expected_arrival_datetime_at_port' => 'datetime',
        'final_boarding_datetime' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Get the rentals associated with this ship departure.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\Rental, \App\Models\ShipDeparture>
     */
    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }
}
