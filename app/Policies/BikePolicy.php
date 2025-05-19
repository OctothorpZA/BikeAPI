<?php

namespace App\Policies;

use App\Models\Bike;
use App\Models\User;
use App\Models\Team; // Import Team model
use Illuminate\Auth\Access\HandlesAuthorization;

class BikePolicy
{
    use HandlesAuthorization;

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
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Owner', 'Supervisor', 'Staff']) || $user->can('view bikes');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Bike $bike): bool
    {
        if ($user->hasAnyRole(['Owner', 'Supervisor', 'Staff']) || $user->can('view bikes')) {
            if ($user->hasRole('Owner')) {
                // Use ownsTeam() instead of isOwnerOfTeam()
                return $user->ownsTeam($bike->team) || $user->belongsToTeam($bike->team);
            }
            if ($user->hasAnyRole(['Supervisor', 'Staff'])) {
                return $user->belongsToTeam($bike->team);
            }
        }
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['Owner', 'Supervisor']) || $user->can('create bikes');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Bike $bike): bool
    {
        if ($user->hasAnyRole(['Owner', 'Supervisor']) || $user->can('edit bikes')) {
            if ($user->hasRole('Owner')) {
                // Use ownsTeam() instead of isOwnerOfTeam()
                return $user->ownsTeam($bike->team) || $user->belongsToTeam($bike->team);
            }
            return $user->belongsToTeam($bike->team);
        }
        return false;
    }

    /**
     * Determine whether the user can delete the model (soft delete).
     */
    public function delete(User $user, Bike $bike): bool
    {
        if ($user->hasAnyRole(['Owner', 'Supervisor']) || $user->can('delete bikes') || $user->can('scrap bikes')) {
             if ($user->hasRole('Owner')) {
                // Use ownsTeam() instead of isOwnerOfTeam()
                return $user->ownsTeam($bike->team) || $user->belongsToTeam($bike->team);
            }
            return $user->belongsToTeam($bike->team);
        }
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Bike $bike): bool
    {
        if ($user->hasAnyRole(['Owner', 'Supervisor'])) {
             if ($user->hasRole('Owner')) {
                // Use ownsTeam() instead of isOwnerOfTeam()
                return $user->ownsTeam($bike->team) || $user->belongsToTeam($bike->team);
            }
            return $user->belongsToTeam($bike->team);
        }
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Bike $bike): bool
    {
        // Example if Owners could force delete bikes they own:
        // if ($user->hasRole('Owner') && $user->ownsTeam($bike->team)) {
        //     return true;
        // }
        return false; // Super Admin handled by before()
    }
}
