<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\PaxProfile
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $first_name
 * @property string $last_name
 * @property string|null $email
 * @property string|null $phone_number
 * @property string|null $country_of_residence
 * @property string|null $passport_number
 * @property \Illuminate\Support\Carbon|null $date_of_birth
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\User|null $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Rental> $rentals
 * @property-read int|null $rentals_count
 * @method static \Database\Factories\PaxProfileFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|PaxProfile newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PaxProfile newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PaxProfile onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|PaxProfile query()
 * @method static \Illuminate\Database\Eloquent\Builder|PaxProfile whereCountryOfResidence($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaxProfile whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaxProfile whereDateOfBirth($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaxProfile whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaxProfile whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaxProfile whereFirstName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaxProfile whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaxProfile whereLastName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaxProfile whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaxProfile wherePassportNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaxProfile wherePhoneNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaxProfile whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaxProfile whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaxProfile withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|PaxProfile withoutTrashed()
 * @mixin \Eloquent
 */
class PaxProfile extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'email',
        'phone_number',
        'country_of_residence',
        'passport_number', // Remember to handle this sensitive data appropriately
        'date_of_birth',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date_of_birth' => 'date',
        'user_id' => 'integer',
    ];

    /**
     * Get the PWA user account associated with this pax profile (if any).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, \App\Models\PaxProfile>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the rentals associated with this pax profile.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\Rental, \App\Models\PaxProfile>
     */
    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }

    /**
     * Get the full name of the passenger.
     *
     * @return string
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
