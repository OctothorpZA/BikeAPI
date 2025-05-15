<?php

namespace App\Providers;

use App\Models\Bike; // Import the Bike model
use App\Policies\BikePolicy; // Import the BikePolicy
// Jetstream models and policies are usually auto-discovered or registered by Jetstream itself
// use App\Models\Team;
// use App\Policies\TeamPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // Team::class => TeamPolicy::class, // Jetstream usually handles its own policy registration
        Bike::class => BikePolicy::class,   // Our new BikePolicy
        PaxProfile::class => PaxProfilePolicy::class,
        // We will add more policies here:
        // Rental::class => RentalPolicy::class,
        // PointOfInterest::class => PointOfInterestPolicy::class,
        // ShipDeparture::class => ShipDeparturePolicy::class,
        // User::class => UserPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // The registerPolicies() method is called automatically by Laravel.
        // You don't need to call it explicitly here unless you have a very old version
        // or specific reason.

        // Define Gates or other auth logic here if needed.
        // Gate::before(function ($user, $ability) {
        //     return $user->hasRole('Super Admin') ? true : null;
        // });
    }
}
