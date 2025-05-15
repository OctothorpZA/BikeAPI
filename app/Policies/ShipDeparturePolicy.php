<?php

namespace App\Policies;

use App\Models\ShipDeparture;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ShipDeparturePolicy
{
    use HandlesAuthorization;

    /**
     * Perform pre-authorization checks.
     *
     * @param  \App\Models\User  $user
     * @param  string  $ability
     * @return void|bool
     */
    public function before(User $user, string $ability)
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }
        return null; // Important: return null to allow other policy methods to run
    }

    /**
     * Determine whether the user can view any models.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        // Owners, Supervisors, and Staff need to see a list of ship departures.
        if ($user->hasAnyRole(['Owner', 'Supervisor', 'Staff'])) {
            return true;
        }
        return false;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\ShipDeparture  $shipDeparture  (This parameter is not used in the current logic but is standard for the 'view' method)
     * @return bool
     */
    public function view(User $user, ShipDeparture $shipDeparture): bool
    {
        // If they can view any, they can view a specific one.
        // No specific team ownership for ship departures.
        if ($user->hasAnyRole(['Owner', 'Supervisor', 'Staff'])) {
            return true;
        }
        return false;
    }

    /**
     * Determine whether the user can create models.
     * As per clarification, Staff, Supervisors, and Owners can manage this data.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    public function create(User $user): bool
    {
        if ($user->hasAnyRole(['Owner', 'Supervisor', 'Staff'])) {
            return true;
        }
        return false;
    }

    /**
     * Determine whether the user can update the model.
     * As per clarification, Staff, Supervisors, and Owners can manage this data.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\ShipDeparture  $shipDeparture (This parameter is not used in the current logic but is standard for the 'update' method)
     * @return bool
     */
    public function update(User $user, ShipDeparture $shipDeparture): bool
    {
        if ($user->hasAnyRole(['Owner', 'Supervisor', 'Staff'])) {
            return true;
        }
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     * As per clarification, Staff, Supervisors, and Owners can manage this data.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\ShipDeparture  $shipDeparture
     * @return bool
     */
    public function delete(User $user, ShipDeparture $shipDeparture): bool
    {
        if ($user->hasAnyRole(['Owner', 'Supervisor', 'Staff'])) {
            // Reminder: Controller logic should check if deletion is safe
            // (e.g., no active/upcoming rentals linked).
            // Policy only determines if the role *can* perform the action.
            // Example check (conceptual, for controller):
            // if ($shipDeparture->rentals()->where('status', 'active')->orWhere('start_time', '>', now())->exists()) {
            //     // Abort deletion, return error message
            // }
            return true;
        }
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     * As per clarification, Staff, Supervisors, and Owners can manage this data.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\ShipDeparture  $shipDeparture (This parameter is not used in the current logic but is standard for the 'restore' method)
     * @return bool
     */
    public function restore(User $user, ShipDeparture $shipDeparture): bool
    {
        if ($user->hasAnyRole(['Owner', 'Supervisor', 'Staff'])) {
            return true;
        }
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\ShipDeparture  $shipDeparture (This parameter is not used in the current logic but is standard for the 'forceDelete' method)
     * @return bool
     */
    public function forceDelete(User $user, ShipDeparture $shipDeparture): bool
    {
        // Force delete is highly destructive. Typically only Super Admin (handled by before()).
        // Even Owners should be cautious. Let's keep this restricted.
        // If Owners need this, it can be specifically granted to the 'Owner' role here.
        return false;
    }

    /**
     * Determine whether the user can toggle the active status of a Ship Departure.
     * As per clarification, Staff, Supervisors, and Owners can manage this data.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\ShipDeparture  $shipDeparture (This parameter is not used in the current logic but is standard for this method)
     * @return bool
     */
    public function toggleActive(User $user, ShipDeparture $shipDeparture): bool
    {
        if ($user->hasAnyRole(['Owner', 'Supervisor', 'Staff'])) {
            return true;
        }
        return false;
    }
}
