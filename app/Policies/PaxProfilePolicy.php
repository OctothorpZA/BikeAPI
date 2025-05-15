<?php

namespace App\Policies;

use App\Models\PaxProfile;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization; // Or use Response for more detailed responses

class PaxProfilePolicy
{
    use HandlesAuthorization; // Or use Response for more detailed responses

    /**
     * Perform pre-authorization checks.
     * Super Admins can do anything.
     */
    public function before(User $user, string $ability): bool|null
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }
        return null; // Let other policy methods decide
    }

     /**
     * Determine whether the user can view any pax profiles.
     * Staff, Supervisors, and Owners might need to view lists of pax profiles.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Owner', 'Supervisor', 'Staff']) || $user->can('view pax_profiles');
    }

    /**
     * Determine whether the user can view the pax profile.
     */
    public function view(User $user, PaxProfile $paxProfile): bool
    {
        // Staff can view pax profiles (e.g., when managing a rental).
        // Owners/Supervisors can view pax profiles within their scope.
        // A PWA user might be able to view their own PaxProfile if $paxProfile->user_id matches $user->id.
        if ($user->hasAnyRole(['Owner', 'Supervisor', 'Staff']) || $user->can('view pax_profiles')) {
            // Further scoping (e.g., is this pax profile related to a rental in their depot?)
            // would typically be handled in the controller or service layer query.
            // For now, if they have the general role/permission, allow.
            return true;
        }

        // Allow a PWA user to view their own profile
        if ($paxProfile->user_id && $user->id === $paxProfile->user_id) {
            return true;
        }

        return false;
    }

     /**
     * Determine whether the user can create pax profiles.
     * Staff would create these during a rental process.
     * PWA users might create their own profile during registration.
     */
    public function create(User $user): bool
    {
        // Staff can create pax profiles.
        // PWA users creating their own profile is usually handled by a registration endpoint,
        // but this permission could be used if staff create profiles for PWA users.
        return $user->hasAnyRole(['Owner', 'Supervisor', 'Staff']);
    }

    /**
     * Determine whether the user can update the pax profile.
     */
    public function update(User $user, PaxProfile $paxProfile): bool
    {
        // Staff, Supervisors, Owners might update profiles (e.g., correct details).
        if ($user->hasAnyRole(['Owner', 'Supervisor', 'Staff']) || $user->can('edit pax_profiles')) {
            // Add scoping if necessary (e.g., only for pax profiles related to their depot)
            return true;
        }

        // Allow a PWA user to update their own profile
        if ($paxProfile->user_id && $user->id === $paxProfile->user_id) {
            return true;
        }
        return false;
    }

    /**
     * Determine whether the user can delete the pax profile.
     * Deleting customer data should be restricted.
     */
    public function delete(User $user, PaxProfile $paxProfile): bool
    {
        // Typically only higher-level roles like Owner or Super Admin,
        // and with consideration for GDPR (anonymization might be preferred).
        if ($user->hasRole('Owner')) {
            // Add scoping logic if an Owner can only delete profiles linked to their depots/users.
            return true;
        }
        // Super Admin is handled by before()
        return false;
    }

    /**
     * Determine whether the user can restore the pax profile.
     */
    public function restore(User $user, PaxProfile $paxProfile): bool
    {
        if ($user->hasRole('Owner')) {
            return true;
        }
        // Super Admin is handled by before()
        return false;
    }

    /**
     * Determine whether the user can permanently delete the pax profile.
     */
    public function forceDelete(User $user, PaxProfile $paxProfile): bool
    {
        // Highly restricted, typically only Super Admin (handled by before())
        return false;
    }
}
