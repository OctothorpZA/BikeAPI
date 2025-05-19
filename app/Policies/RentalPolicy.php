<?php

namespace App\Policies;

use App\Models\Rental;
use App\Models\User;
use App\Models\Team; // Import Team model
use Illuminate\Auth\Access\HandlesAuthorization;

class RentalPolicy
{
    use HandlesAuthorization;

    /**
     * Perform pre-authorization checks.
     */
    public function before(User $user, string $ability)
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }
        return null;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Owner', 'Supervisor', 'Staff']) || $user->can('view rentals');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Rental $rental): bool
    {
        if ($user->hasRole('Owner')) {
            $startTeam = Team::find($rental->start_team_id);
            $endTeam = $rental->end_team_id ? Team::find($rental->end_team_id) : null;

            return ($startTeam && $user->ownsTeam($startTeam)) ||
                   ($endTeam && $user->ownsTeam($endTeam)) ||
                   ($startTeam && $user->belongsToTeam($startTeam)) || // Also allow if member of start team
                   ($endTeam && $user->belongsToTeam($endTeam));      // Or member of end team
        }

        if ($user->hasAnyRole(['Supervisor', 'Staff'])) {
            if (!$user->currentTeam) {
                return false;
            }
            return $rental->start_team_id == $user->currentTeam->id ||
                   ($rental->end_team_id && $rental->end_team_id == $user->currentTeam->id);
        }
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        if ($user->hasAnyRole(['Owner', 'Supervisor', 'Staff']) || $user->can('create rentals')) {
            // User must be part of at least one operational team to create a rental for that team
            return $user->allTeams()->where('personal_team', false)->isNotEmpty();
        }
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Rental $rental): bool
    {
        if ($user->hasRole('Owner') || $user->can('edit rentals')) {
            $startTeam = Team::find($rental->start_team_id);
            // Owners can update rentals if they own the starting depot or are a member of it.
            return $startTeam && ($user->ownsTeam($startTeam) || $user->belongsToTeam($startTeam));
        }

        if ($user->hasAnyRole(['Supervisor', 'Staff']) || $user->can('edit rentals')) {
            if (!$user->currentTeam) {
                return false;
            }
            // Supervisors/Staff can update rentals starting from their current depot.
            return $rental->start_team_id == $user->currentTeam->id;
        }
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Rental $rental): bool
    {
        // For cancelling, a specific 'cancel rentals' permission might be better.
        // This delete method is for soft deleting the record.
        if ($user->hasRole('Owner') || $user->can('cancel rentals')) { // Assuming 'cancel rentals' implies ability to delete/archive
            $startTeam = Team::find($rental->start_team_id);
            return $startTeam && ($user->ownsTeam($startTeam) || $user->belongsToTeam($startTeam));
        }

        if ($user->hasRole('Supervisor') || $user->can('cancel rentals')) {
            if (!$user->currentTeam) {
                return false;
            }
            return $rental->start_team_id == $user->currentTeam->id;
        }
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Rental $rental): bool
    {
        if ($user->hasRole('Owner')) {
            $startTeam = Team::find($rental->start_team_id);
            return $startTeam && ($user->ownsTeam($startTeam) || $user->belongsToTeam($startTeam));
        }
        if ($user->hasRole('Supervisor')) {
            if (!$user->currentTeam) {
                return false;
            }
            return $rental->start_team_id == $user->currentTeam->id;
        }
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Rental $rental): bool
    {
        return false; // Super Admin handled by before()
    }
}
