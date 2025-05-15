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
        // User can view team details if they are a member of the team.
        // This is Jetstream's default and generally makes sense.
        // An Owner might also view any team they own, even if not explicitly a "member" with a specific role.
        if ($user->ownsTeam($team) || $user->belongsToTeam($team)) {
            return true;
        }
        // If an Owner should see all Depots regardless of direct membership (e.g. for an overview)
        // this might be true, but typically viewing details implies some relationship.
        // Let's stick to Jetstream's default + owner check for now.
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
        // Supervisors can update details of the Depot they are assigned to manage.
        // This assumes a Supervisor is a member of the team with a specific role (e.g., 'supervisor')
        // or their currentTeam is this team.
        if ($user->hasRole('Supervisor') && $user->belongsToTeam($team) && $user->currentTeam && $user->currentTeam->id === $team->id) {
            // Further check: Ensure they have a Jetstream team role that permits updates (e.g., 'admin' or 'editor' for the team)
            // return $user->hasTeamPermission($team, 'update'); // Jetstream's team permission check
            return true; // Simplified for now: if they are a supervisor and it's their current team.
        }
        return false;
    }

    /**
     * Determine whether the user can add team members to the Team (Depot).
     */
    public function addTeamMember(User $user, Team $team): bool
    {
        // Team owners can add members.
        if ($user->ownsTeam($team)) {
            return true;
        }
        // Supervisors can add 'Staff' members to their current Depot.
        if ($user->hasRole('Supervisor') && $user->belongsToTeam($team) && $user->currentTeam && $user->currentTeam->id === $team->id) {
            // They should only be able to add users with the 'Staff' Spatie role,
            // and assign them a 'member' or 'staff' Jetstream team role.
            // The policy checks if they *can* add, controller validates *who* and *what role*.
            // return $user->hasTeamPermission($team, 'addTeamMember');
             return true; // Simplified for now
        }
        return false;
    }

    /**
     * Determine whether the user can update team member permissions (Jetstream team roles) in the Team (Depot).
     */
    public function updateTeamMember(User $user, Team $team): bool
    {
        // Team owners can update member roles.
        if ($user->ownsTeam($team)) {
            return true;
        }
        // Supervisors might update roles of 'Staff' members in their current Depot.
        // (e.g., from a 'viewer' Jetstream role to an 'editor' Jetstream role if you have such distinctions).
        // They should not be able to change an Owner's role or another Supervisor's role.
        if ($user->hasRole('Supervisor') && $user->belongsToTeam($team) && $user->currentTeam && $user->currentTeam->id === $team->id) {
            // Policy checks if they *can* update, controller validates *whose role* and *to what*.
            // return $user->hasTeamPermission($team, 'updateTeamMember');
            return true; // Simplified for now
        }
        return false;
    }

    /**
     * Determine whether the user can remove team members from the Team (Depot).
     */
    public function removeTeamMember(User $user, Team $team, User $userToRemove): bool
    {
        // Team owners can remove members, but not themselves.
        if ($user->ownsTeam($team) && $user->id !== $userToRemove->id) {
            return true;
        }
        // Supervisors can remove 'Staff' members from their current Depot.
        // They should not be able to remove the Owner or other Supervisors.
        if ($user->hasRole('Supervisor') &&
            $user->belongsToTeam($team) &&
            $user->currentTeam && $user->currentTeam->id === $team->id &&
            $userToRemove->hasRole('Staff') && // Can only remove Staff
            $user->id !== $userToRemove->id) { // Cannot remove self
            // return $user->hasTeamPermission($team, 'removeTeamMember');
            return true; // Simplified for now
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
     * Determine whether the user can manage team (Depot) settings.
     * This could be a general permission for various settings pages.
     */
    // public function manageSettings(User $user, Team $team): bool
    // {
    //     if ($user->ownsTeam($team)) {
    //         return true;
    //     }
    //     if ($user->hasRole('Supervisor') && $user->belongsToTeam($team) && $user->currentTeam && $user->currentTeam->id === $team->id) {
    //         return $user->hasTeamPermission($team, 'manage:settings'); // Example Jetstream permission
    //     }
    //     return false;
    // }
}
