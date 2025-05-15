<?php

namespace App\Policies;

use App\Models\Bike;
use App\Models\User;
use App\Models\Team; // Import Team model
use Illuminate\Auth\Access\HandlesAuthorization; // Or use Response for more detailed responses

class BikePolicy
{
    // If you want to use Response objects for more detailed denial messages:
    // use Illuminate\Auth\Access\Response;
    use HandlesAuthorization;


    /**
     * Perform pre-authorization checks.
     * Super Admins can do anything.
     *
     * @param  \App\Models\User  $user
     * @param  string  $ability
     * @return bool|null
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
     * Owners, Supervisors, and Staff should generally be able to view bikes.
     * Scoping to their specific teams will be handled by the controller/query.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Owner', 'Supervisor', 'Staff']);
    }

    /**
     * Determine whether the user can view the model.
     * Users can view a bike if they have a role that allows bike viewing
     * AND (if they are not a Super Admin) they belong to the bike's team
     * or, for Owners, if they own the bike's team.
     */
    public function view(User $user, Bike $bike): bool
    {
        if ($user->hasAnyRole(['Owner', 'Supervisor', 'Staff']) || $user->can('view bikes')) {
            // Super Admin check is handled by before()
            // For Owner: can view if they own the team or are a member of the bike's team.
            if ($user->hasRole('Owner')) {
                return $user->isOwnerOfTeam($bike->team) || $user->belongsToTeam($bike->team);
            }
            // For Supervisor/Staff: can view if they are a member of the bike's team.
            if ($user->hasAnyRole(['Supervisor', 'Staff'])) {
                return $user->belongsToTeam($bike->team);
            }
        }
        return false;
    }

    /**
     * Determine whether the user can create models.
     * Typically Supervisors and Owners, or users with the specific 'create bikes' permission.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['Owner', 'Supervisor']) || $user->can('create bikes');
    }

    /**
     * Determine whether the user can update the model.
     * Owners/Supervisors can update bikes in their teams.
     */
    public function update(User $user, Bike $bike): bool
    {
        if ($user->hasAnyRole(['Owner', 'Supervisor']) || $user->can('edit bikes')) {
            if ($user->hasRole('Owner')) {
                return $user->isOwnerOfTeam($bike->team) || $user->belongsToTeam($bike->team);
            }
            // Supervisor must belong to the bike's team
            return $user->belongsToTeam($bike->team);
        }
        return false;
    }

    /**
     * Determine whether the user can delete the model (soft delete).
     * Owners/Supervisors can delete/scrap bikes in their teams. Staff cannot.
     */
    public function delete(User $user, Bike $bike): bool
    {
        if ($user->hasAnyRole(['Owner', 'Supervisor']) || $user->can('delete bikes') || $user->can('scrap bikes')) {
             if ($user->hasRole('Owner')) {
                return $user->isOwnerOfTeam($bike->team) || $user->belongsToTeam($bike->team);
            }
            // Supervisor must belong to the bike's team
            return $user->belongsToTeam($bike->team);
        }
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     * Typically Supervisor and Owner roles.
     */
    public function restore(User $user, Bike $bike): bool
    {
        if ($user->hasAnyRole(['Owner', 'Supervisor'])) {
             if ($user->hasRole('Owner')) {
                return $user->isOwnerOfTeam($bike->team) || $user->belongsToTeam($bike->team);
            }
            // Supervisor must belong to the bike's team
            return $user->belongsToTeam($bike->team);
        }
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     * This is usually a more restricted action, Super Admin only by default via before().
     */
    public function forceDelete(User $user, Bike $bike): bool
    {
        // Super Admin is handled by before().
        // If you want Owners to be able to force delete, add specific logic:
        // if ($user->hasRole('Owner') && ($user->isOwnerOfTeam($bike->team) || $user->belongsToTeam($bike->team))) {
        //     return true; // Or check a specific 'force delete bikes' permission
        // }
        return false;
    }
}
