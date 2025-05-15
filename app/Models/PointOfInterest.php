<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\PointOfInterest
 *
 * @property int $id
 * @property int|null $team_id
 * @property string $name
 * @property string $category // Ensure this is in $fillable
 * @property string|null $description
 * @property float $latitude
 * @property float $longitude
 * @property string|null $address_line_1
 * @property string|null $address_line_2
 * @property string|null $city
 * @property string|null $state_province
 * @property string|null $postal_code
 * @property string|null $country_code
 * @property string|null $phone_number
 * @property string|null $website_url
 * @property string|null $primary_image_url
 * @property bool $is_approved
 * @property bool $is_active
 * @property int|null $created_by_user_id
 * @property int|null $approved_by_user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\User|null $approvedByUser
 * @property-read \App\Models\User|null $createdByUser
 * @property-read \App\Models\Team|null $team
 * @method static \Database\Factories\PointOfInterestFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|PointOfInterest newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PointOfInterest newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PointOfInterest onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|PointOfInterest query()
 * @method static \Illuminate\Database\Eloquent\Builder|PointOfInterest whereAddressLine1($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PointOfInterest whereAddressLine2($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PointOfInterest whereApprovedByUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PointOfInterest whereCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PointOfInterest whereCity($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PointOfInterest whereCountryCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PointOfInterest whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PointOfInterest whereCreatedByUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PointOfInterest whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PointOfInterest whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PointOfInterest whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PointOfInterest whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PointOfInterest whereIsApproved($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PointOfInterest whereLatitude($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PointOfInterest whereLongitude($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PointOfInterest whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PointOfInterest wherePhoneNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PointOfInterest wherePostalCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PointOfInterest wherePrimaryImageUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PointOfInterest whereStateProvince($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PointOfInterest whereTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PointOfInterest whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PointOfInterest whereWebsiteUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PointOfInterest withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|PointOfInterest withoutTrashed()
 * @mixin \Eloquent
 */
class PointOfInterest extends Model
{
    use HasFactory, SoftDeletes;

    // Define constants for category types for easier reference and consistency
    public const CATEGORY_DEPOT = 'Depot';
    public const CATEGORY_STAFF_PICK = 'Staff Pick';
    public const CATEGORY_GENERAL = 'General'; // Your default in the migration
    // Add more as needed, e.g., 'API Sourced', 'Landmark', 'Attraction' etc.


    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'team_id',
        'name',
        'category', // This was already in your $fillable, which is correct!
        'description',
        'latitude',
        'longitude',
        'address_line_1',
        'address_line_2',
        'city',
        'state_province',
        'postal_code',
        'country_code',
        'phone_number',
        'website_url',
        'primary_image_url',
        'is_approved',
        'is_active',
        'created_by_user_id',
        'approved_by_user_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'is_approved' => 'boolean',
        'is_active' => 'boolean',
        'team_id' => 'integer', // Your existing cast
        'created_by_user_id' => 'integer', // Your existing cast
        'approved_by_user_id' => 'integer', // Your existing cast
        'created_at' => 'datetime', // Adding this for consistency, though often default
        'updated_at' => 'datetime', // Adding this for consistency, though often default
        'deleted_at' => 'datetime', // Standard for SoftDeletes
    ];

    /**
     * Get the team (Depot) associated with this POI, if it is a Depot.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Team, \App\Models\PointOfInterest>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user who created this POI.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, \App\Models\PointOfInterest>
     */
    public function createdByUser(): BelongsTo // Using your preferred relationship name
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Get the user who approved this POI.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, \App\Models\PointOfInterest>
     */
    public function approvedByUser(): BelongsTo // Using your preferred relationship name
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    // Example Scope: Get only active POIs
    // public function scopeActive($query)
    // {
    //     return $query->where('is_active', true);
    // }

    // Example Scope: Get only approved POIs
    // public function scopeApproved($query)
    // {
    //     return $query->where('is_approved', true);
    // }
}
