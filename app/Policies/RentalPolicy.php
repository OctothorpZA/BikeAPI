<?php

namespace App\Policies;

use App\Models\Rental;
use App\Models\User;
// use App\Models\Team; // Team model is not directly used in this version of the policy methods
use Illuminate\Auth\Access\HandlesAuthorization; // Or use Illuminate\Auth\Access\Response for Laravel 10+ style responses

class RentalPolicy
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
    }

    /**
     * Determine whether the user can view any models.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        // Super Admin: Handled by before()
        // Owner: Can view rentals associated with any of their depots (teams).
        // Supervisor: Can view rentals associated with their assigned depot (current team).
        // Staff: Can view rentals associated with their current depot (current team).
        // This is a general permission; actual data fetching should be scoped in controllers/Livewire components.
        if ($user->hasAnyRole(['Owner', 'Supervisor', 'Staff'])) {
            return true;
        }
        return false;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Rental  $rental
     * @return bool
     */
    public function view(User $user, Rental $rental): bool
    {
        // Super Admin: Handled by before()
        // Owner: Can view if the rental's start_team_id or end_team_id (if set) belongs to one of their teams.
        if ($user->hasRole('Owner')) {
            // Ensure the user model has a method like `allTeams()` that returns a collection of their teams.
            // This could be $user->teams or $user->ownedTeams depending on Jetstream setup and your logic.
            // For this example, assuming allTeams() gives all teams they are a member of with sufficient rights.
            $teamIds = $user->allTeams()->pluck('id');
            return $teamIds->contains($rental->start_team_id) || ($rental->end_team_id && $teamIds->contains($rental->end_team_id));
        }

        // Supervisor or Staff: Can view if the rental's start_team_id or end_team_id (if set) is their current_team_id.
        if ($user->hasAnyRole(['Supervisor', 'Staff'])) {
            if (!$user->currentTeam) { // Check if currentTeam is set
                return false;
            }
            return $rental->start_team_id == $user->currentTeam->id ||
                   ($rental->end_team_id && $rental->end_team_id == $user->currentTeam->id);
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    public function create(User $user): bool
    {
        // Super Admin: Handled by before()
        // Owner, Supervisor, Staff: Can create if they are part of at least one team (depot).
        // The actual team for creation will be determined by the request data and authorized there.
        if ($user->hasAnyRole(['Owner', 'Supervisor', 'Staff'])) {
            return $user->currentTeam !== null || $user->allTeams()->count() > 0;
        }
        return false;
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Rental  $rental
     * @return bool
     */
    public function update(User $user, Rental $rental): bool
    {
        // Super Admin: Handled by before()
        // Owner: Can update if the rental's start_team_id belongs to one of their teams.
        if ($user->hasRole('Owner')) {
            return $user->allTeams()->pluck('id')->contains($rental->start_team_id);
        }

        // Supervisor or Staff: Can update if the rental's start_team_id is their current_team_id.
        // Further restrictions (e.g., on specific fields or rental statuses) might be needed in Form Requests or service layers.
        if ($user->hasAnyRole(['Supervisor', 'Staff'])) {
            if (!$user->currentTeam) {
                return false;
            }
            return $rental->start_team_id == $user->currentTeam->id;
        }
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Rental  $rental
     * @return bool
     */
    public function delete(User $user, Rental $rental): bool
    {
        // Super Admin: Handled by before()
        // Owner: Can delete rentals where the start_team_id belongs to one of their teams.
        if ($user->hasRole('Owner')) {
            return $user->allTeams()->pluck('id')->contains($rental->start_team_id);
        }

        // Supervisor: Can delete rentals where the start_team_id is their current_team_id.
        // Consider adding status checks, e.g., can only delete 'pending' rentals.
        if ($user->hasRole('Supervisor')) {
            if (!$user->currentTeam) {
                return false;
            }
            return $rental->start_team_id == $user->currentTeam->id;
        }

        // Staff: Generally cannot delete.
        // Example: Allow staff to delete only their own 'pending_payment' rentals within their current team.
        // if ($user->hasRole('Staff') &&
        //     $rental->status === 'pending_payment' &&
        //     $rental->staff_user_id === $user->id && // Assuming staff_user_id links to the creator
        //     $user->currentTeam && $rental->start_team_id == $user->currentTeam->id) {
        //    return true;
        // }
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Rental  $rental
     * @return bool
     */
    public function restore(User $user, Rental $rental): bool
    {
        // Super Admin: Handled by before()
        // Owner: Can restore rentals where the start_team_id belongs to one of their teams.
        if ($user->hasRole('Owner')) {
            return $user->allTeams()->pluck('id')->contains($rental->start_team_id);
        }

        // Supervisor: Can restore rentals where the start_team_id is their current_team_id.
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
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Rental  $rental
     * @return bool
     */
    public function forceDelete(User $user, Rental $rental): bool
    {
        // Super Admin: Handled by before()
        // Owner, Supervisor, Staff: Generally should not be able to force delete.
        // This is a destructive action usually reserved for Super Admins.
        return false;
    }

    // --- Custom Policy Methods (Examples) ---

    /**
     * Determine whether the user can finalize a rental (e.g., process payment, mark as complete).
     * This might involve checking if the rental is at the start or end depot of the user.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Rental  $rental
     * @return bool
     */
    // public function finalizeRental(User $user, Rental $rental): bool
    // {
    //     if ($user->hasAnyRole(['Supervisor', 'Staff'])) {
    //         if (!$user->currentTeam) {
    //             return false;
    //         }
    //         return $rental->start_team_id == $user->currentTeam->id ||
    //                ($rental->end_team_id && $rental->end_team_id == $user->currentTeam->id);
    //     }
    //     return false;
    // }
}
