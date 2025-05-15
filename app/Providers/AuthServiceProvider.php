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
use Illuminate\Support\Facades\Gate; // Uncomment if you use Gate directly for non-model specific permissions

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy', // Default Laravel example
        Bike::class => BikePolicy::class,
        PaxProfile::class => PaxProfilePolicy::class,
        Rental::class => RentalPolicy::class,
        PointOfInterest::class => PointOfInterestPolicy::class,
        ShipDeparture::class => ShipDeparturePolicy::class,
        User::class => UserPolicy::class,
        Team::class => TeamPolicy::class, // Registering Jetstream's TeamPolicy (ensure it's in App\Policies)
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Example of defining a Gate for a specific ability (non-model related)
        // Gate::define('view-admin-dashboard', function (User $user) {
        //     return $user->hasRole('Super Admin') || $user->hasRole('Owner');
        // });

        // Implicitly grant "Super Admin" all permissions.
        // This is often done in the `before` method of individual policies,
        // but can also be done globally here if preferred, though policy-level `before` is more common.
        // Gate::before(function ($user, $ability) {
        //     return $user->hasRole('Super Admin') ? true : null;
        // });
    }
}
