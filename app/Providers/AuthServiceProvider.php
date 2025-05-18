<?php

namespace App\Providers;

use App\Models\Bike;
use App\Policies\BikePolicy;
use App\Models\PaxProfile;
use App\Policies\PaxProfilePolicy;
use App\Models\Rental;
use App\Policies\RentalPolicy;
use App\Models\PointOfInterest;
use App\Policies\PointOfInterestPolicy;
use App\Models\ShipDeparture;
use App\Policies\ShipDeparturePolicy;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Models\Team; // Jetstream Team model
use App\Policies\TeamPolicy; // Jetstream's default TeamPolicy or your customized one
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate; // Ensure Gate is imported

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Bike::class => BikePolicy::class,
        PaxProfile::class => PaxProfilePolicy::class,
        Rental::class => RentalPolicy::class,
        PointOfInterest::class => PointOfInterestPolicy::class,
        ShipDeparture::class => ShipDeparturePolicy::class,
        User::class => UserPolicy::class,
        Team::class => TeamPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Gate for accessing the Depot Supervisor Management page
        // This checks if the user has the Spatie permission 'assign depot staff'.
        Gate::define('access-depot-supervisor-manager', function (User $user) {
            return $user->hasPermissionTo('assign depot staff', 'web');
        });

        // Gate for accessing the (upcoming) User Spatie Role Management page
        // This checks if the user has the Spatie permission 'assign spatie roles'.
        Gate::define('access-user-spatie-role-manager', function(User $user) {
            return $user->hasPermissionTo('assign spatie roles', 'web');
        });

        // The before() method in your individual policies (like TeamPolicy, UserPolicy)
        // is the correct place to handle Super Admin overrides for those specific models.
        // A global Gate::before() can be powerful but sometimes too broad.
        // Example:
        // Gate::before(function (User $user, string $ability) {
        //     if ($user->hasRole('Super Admin')) {
        //         return true;
        //     }
        //     return null;
        // });
        // Your policies already have `before` methods, which is good.
    }
}
