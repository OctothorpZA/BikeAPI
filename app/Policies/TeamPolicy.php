<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TeamPolicy
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
        return null; // Important to allow other methods to run
    }

    /**
     * Determine whether the user can view any models.
     * (e.g., list teams/depots in a dropdown)
     */
    public function viewAny(User $user): bool
    {
        // Allow all authenticated staff to see a list of teams/depots,
        // as they might need to select one or see information related to them.
        return $user->hasAnyRole(['Owner', 'Supervisor', 'Staff']);
    }

    /**
     * Determine whether the user can view the model.
     * (e.g., view details of a specific depot)
     */
    public function view(User $user, Team $team): bool
    {
        // User can view team details if they are a member of the team or own the team.
        if ($user->ownsTeam($team) || $user->belongsToTeam($team)) {
            return true;
        }
        return false;
    }

    /**
     * Determine whether the user can create new Teams (Depots).
     */
    public function create(User $user): bool
    {
        // Only Super Admins (via before()) and Owners can create new Depots.
        return $user->hasRole('Owner');
    }

    /**
     * Determine whether the user can update Team (Depot) details.
     */
    public function update(User $user, Team $team): bool
    {
        // Team owners can update.
        if ($user->ownsTeam($team)) {
            return true;
        }
        // Supervisors can update details of the Depot they are assigned to manage (their current team).
        if ($user->hasRole('Supervisor') && $user->belongsToTeam($team) && $user->currentTeam && $user->currentTeam->id === $team->id) {
            // Optional: Check for a specific Jetstream team permission if you use them granularly
            // e.g., return $user->hasTeamPermission($team, 'team:update');
            return true;
        }
        return false;
    }

    /**
     * Determine whether the user can add team members to the Team (Depot).
     * The $user is the one performing the action.
     */
    public function addTeamMember(User $user, Team $team): bool
    {
        // Team owners can add members.
        if ($user->ownsTeam($team)) {
            return true;
        }
        // Supervisors can add 'Staff' members to their current Depot.
        // The policy determines if they *can* add; the controller/UI would restrict *who* (e.g. only users with 'Staff' Spatie role)
        // and *what Jetstream team role* is assigned.
        if ($user->hasRole('Supervisor') && $user->belongsToTeam($team) && $user->currentTeam && $user->currentTeam->id === $team->id) {
            // Optional: Check for a specific Jetstream team permission
            // e.g., return $user->hasTeamPermission($team, 'member:add');
            return true;
        }
        return false;
    }

    /**
     * Determine whether the user can update team member permissions (Jetstream team roles) in the Team (Depot).
     * The $user is the one performing the action.
     */
    public function updateTeamMember(User $user, Team $team): bool
    {
        // Team owners can update member roles.
        if ($user->ownsTeam($team)) {
            return true;
        }
        // Supervisors might update roles of 'Staff' members in their current Depot.
        // They should not be able to change an Owner's role or another Supervisor's role.
        // This policy method determines if they *can generally* update roles.
        // The actual UI/controller should restrict *which roles* can be assigned to *which users*.
        if ($user->hasRole('Supervisor') && $user->belongsToTeam($team) && $user->currentTeam && $user->currentTeam->id === $team->id) {
            // Optional: Check for a specific Jetstream team permission
            // e.g., return $user->hasTeamPermission($team, 'member:update-role');
            return true;
        }
        return false;
    }

    /**
     * Determine whether the user can remove team members from the Team (Depot).
     * The $user is the one performing the action.
     * The $userToRemove is the one being removed.
     */
    public function removeTeamMember(User $user, Team $team, User $userToRemove): bool
    {
        // Team owners can remove members, but not themselves.
        if ($user->ownsTeam($team) && $user->id !== $userToRemove->id) {
            return true;
        }

        // Supervisors can remove 'Staff' members from their current Depot.
        // They should not be able to remove the Owner, other Supervisors, or themselves.
        if ($user->hasRole('Supervisor') &&
            $user->belongsToTeam($team) && // Supervisor must belong to the team
            $user->currentTeam && $user->currentTeam->id === $team->id && // Must be their current team
            $userToRemove->hasRole('Staff') && // Can only remove users with the 'Staff' Spatie role
            $user->id !== $userToRemove->id) { // Cannot remove self
            // Optional: Check for a specific Jetstream team permission
            // e.g., return $user->hasTeamPermission($team, 'member:remove');
            return true;
        }
        return false;
    }

    /**
     * Determine whether the user can delete the Team (Depot).
     */
    public function delete(User $user, Team $team): bool
    {
        // Only the team owner can delete the team.
        // Super Admin can also delete (handled by before()).
        return $user->ownsTeam($team);
    }

    /**
     * Determine whether the user can restore the model. (If using SoftDeletes on Teams)
     */
    public function restore(User $user, Team $team): bool
    {
        // Only the team owner can restore the team.
        // Super Admin can also restore (handled by before()).
        return $user->ownsTeam($team);
    }

    /**
     * Determine whether the user can permanently delete the model. (If using SoftDeletes on Teams)
     */
    public function forceDelete(User $user, Team $team): bool
    {
        // Highly destructive. Only Super Admin (via before()) or perhaps team owner with extreme caution.
        // For now, let's restrict to SA. If owner needs it, it can be $user->ownsTeam($team);
        return false;
    }
}
