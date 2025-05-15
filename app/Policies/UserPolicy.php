<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Team; // Required for team context checks
use Illuminate\Auth\Access\HandlesAuthorization; // Or use Illuminate\Auth\Access\Response for Laravel 10+

class UserPolicy
{
    use HandlesAuthorization;

    // Role hierarchy (lower number means higher privilege)
    protected $roleHierarchy = [
        'Super Admin' => 1,
        'Owner' => 2,
        'Supervisor' => 3,
        'Staff' => 4,
        // Add PWA User role if they are managed here, e.g., 'PWA User' => 5
    ];

    /**
     * Perform pre-authorization checks.
     *
     * @param  \App\Models\User  $actingUser The user performing the action.
     * @param  string  $ability    The ability being checked.
     * @return void|bool
     */
    public function before(User $actingUser, string $ability)
    {
        if ($actingUser->hasRole('Super Admin')) {
            // Super Admins can do anything except be deleted/updated by non-SAs (handled in specific methods)
            if (in_array($ability, ['delete', 'forceDelete', 'update']) && $this->isTargetSuperAdmin($this->getTargetUserFromArguments(func_get_args()))) {
                 // If the target is SA, only SA can proceed.
                return $actingUser->hasRole('Super Admin') ? true : false;
            }
            return true;
        }
        return null; // Important: return null to allow other policy methods to run
    }

    /**
     * Helper to get the target user from policy method arguments.
     */
    private function getTargetUserFromArguments(array $args): ?User
    {
        // $args[0] is $actingUser, $args[1] is $ability (for before filter)
        // For other methods, $args[0] is $actingUser, $args[1] is $targetUser
        if (isset($args[2]) && $args[2] instanceof User) {
            return $args[2]; // For methods like view(User $actingUser, User $targetUser)
        }
        if (isset($args[1]) && $args[1] instanceof User) {
             return $args[1]; // For methods like view(User $actingUser, User $targetUser) when called without ability
        }
        return null;
    }

    private function isTargetSuperAdmin(?User $targetUser): bool
    {
        return $targetUser && $targetUser->hasRole('Super Admin');
    }


    /**
     * Determine whether the acting user can view any models.
     * (e.g., list staff users)
     *
     * @param  \App\Models\User  $actingUser
     * @return bool
     */
    public function viewAny(User $actingUser): bool
    {
        // Owners and Supervisors can list users (likely scoped in controller to their teams).
        return $actingUser->hasAnyRole(['Owner', 'Supervisor']);
    }

    /**
     * Determine whether the acting user can view the model.
     * (e.g., view a specific staff user's profile)
     *
     * @param  \App\Models\User  $actingUser
     * @param  \App\Models\User  $targetUser The user being viewed.
     * @return bool
     */
    public function view(User $actingUser, User $targetUser): bool
    {
        if ($this->isTargetSuperAdmin($targetUser) && !$actingUser->hasRole('Super Admin')) {
            return false; // Only SA can view SA profile details through user management
        }

        // Owners can view users if they are in any of the Owner's teams.
        if ($actingUser->hasRole('Owner')) {
            // Check if targetUser is a member of any team owned/managed by actingUser
            foreach ($actingUser->allTeams() as $team) {
                if ($targetUser->belongsToTeam($team)) {
                    return true;
                }
            }
        }

        // Supervisors can view users if they are in the Supervisor's current team.
        if ($actingUser->hasRole('Supervisor')) {
            if ($actingUser->currentTeam && $targetUser->belongsToTeam($actingUser->currentTeam)) {
                return true;
            }
        }

        // Users can typically view their own profile (though usually handled by Jetstream's profile routes)
        // If this policy is strictly for managing *other* users, this might not be needed here.
        // if ($actingUser->id === $targetUser->id) {
        //     return true;
        // }

        return false;
    }

    /**
     * Determine whether the acting user can create new users.
     * (e.g., create new staff accounts)
     *
     * @param  \App\Models\User  $actingUser
     * @return bool
     */
    public function create(User $actingUser): bool
    {
        // Owners can create Supervisors or Staff.
        // Supervisors can create Staff.
        return $actingUser->hasAnyRole(['Owner', 'Supervisor']);
    }

    /**
     * Determine whether the acting user can update the user model.
     * This includes changing profile details, and potentially roles or team assignments.
     *
     * @param  \App\Models\User  $actingUser
     * @param  \App\Models\User  $targetUser The user being updated.
     * @return bool
     */
    public function update(User $actingUser, User $targetUser): bool
    {
        if ($actingUser->id === $targetUser->id) {
            return false; // Users cannot update themselves via this policy (use Jetstream profile)
        }

        if ($this->isTargetSuperAdmin($targetUser) && !$actingUser->hasRole('Super Admin')) {
            return false; // Only SA can update SA
        }

        $actingUserRoleLevel = $this->getRoleLevel($actingUser);
        $targetUserRoleLevel = $this->getRoleLevel($targetUser);

        if ($targetUserRoleLevel <= $actingUserRoleLevel && !$actingUser->hasRole('Super Admin')) {
            return false; // Cannot update users of equal or higher role, unless SA
        }

        // Owners can update users in their teams (Supervisors, Staff).
        if ($actingUser->hasRole('Owner')) {
            foreach ($actingUser->allTeams() as $team) {
                if ($targetUser->belongsToTeam($team)) {
                    return true;
                }
            }
        }

        // Supervisors can update Staff users in their current team.
        if ($actingUser->hasRole('Supervisor')) {
            if ($actingUser->currentTeam &&
                $targetUser->belongsToTeam($actingUser->currentTeam) &&
                $targetUser->hasRole('Staff')) { // Explicitly check target is Staff
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the acting user can delete the user model.
     *
     * @param  \App\Models\User  $actingUser
     * @param  \App\Models\User  $targetUser The user being deleted.
     * @return bool
     */
    public function delete(User $actingUser, User $targetUser): bool
    {
        if ($actingUser->id === $targetUser->id) {
            return false; // Cannot delete self
        }

        if ($this->isTargetSuperAdmin($targetUser)) {
            return false; // SA cannot be deleted by anyone (even SA, via this policy - needs specific action)
        }

        $actingUserRoleLevel = $this->getRoleLevel($actingUser);
        $targetUserRoleLevel = $this->getRoleLevel($targetUser);

        if ($targetUserRoleLevel <= $actingUserRoleLevel && !$actingUser->hasRole('Super Admin')) {
            return false; // Cannot delete users of equal or higher role, unless SA
        }

        // Owners can delete users in their teams (Supervisors, Staff).
        if ($actingUser->hasRole('Owner')) {
            foreach ($actingUser->allTeams() as $team) {
                if ($targetUser->belongsToTeam($team)) {
                    return true;
                }
            }
        }

        // Supervisors can delete Staff users in their current team.
        if ($actingUser->hasRole('Supervisor')) {
            if ($actingUser->currentTeam &&
                $targetUser->belongsToTeam($actingUser->currentTeam) &&
                $targetUser->hasRole('Staff')) {
                return true;
            }
        }
        return false;
    }

    /**
     * Determine whether the acting user can restore the user model.
     *
     * @param  \App\Models\User  $actingUser
     * @param  \App\Models\User  $targetUser
     * @return bool
     */
    public function restore(User $actingUser, User $targetUser): bool
    {
        // Similar logic to delete, but SA might be able to restore anyone.
        // (Handled by before filter for SA)
        if ($this->isTargetSuperAdmin($targetUser) && !$actingUser->hasRole('Super Admin')) {
            return false;
        }

        $actingUserRoleLevel = $this->getRoleLevel($actingUser);
        $targetUserRoleLevel = $this->getRoleLevel($targetUser);

        if ($targetUserRoleLevel <= $actingUserRoleLevel && !$actingUser->hasRole('Super Admin')) {
            return false;
        }

        if ($actingUser->hasRole('Owner')) {
            foreach ($actingUser->allTeams() as $team) {
                if ($targetUser->belongsToTeam($team)) { // Check if was part of team might be complex if team membership was removed
                    return true;
                }
            }
        }

        if ($actingUser->hasRole('Supervisor')) {
            if ($actingUser->currentTeam &&
                $targetUser->hasRole('Staff') /* && was previously in this team */ ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Determine whether the acting user can permanently delete the user model.
     *
     * @param  \App\Models\User  $actingUser
     * @param  \App\Models\User  $targetUser
     * @return bool
     */
    public function forceDelete(User $actingUser, User $targetUser): bool
    {
        if ($actingUser->id === $targetUser->id) {
            return false;
        }
        if ($this->isTargetSuperAdmin($targetUser)) {
            return false; // SA cannot be force deleted by anyone (even SA, via this policy)
        }
        // This is extremely destructive. Usually only Super Admin (handled by `before`).
        // If Owners need this, it can be specifically granted here with extreme caution.
        return false;
    }

    /**
     * Determine whether the acting user can change the role of the target user.
     * This is a more granular check, often part of the 'update' logic.
     *
     * @param  \App\Models\User  $actingUser
     * @param  \App\Models\User  $targetUser
     * @param  string  $newRoleName The name of the role being assigned.
     * @return bool
     */
    public function changeRole(User $actingUser, User $targetUser, string $newRoleName): bool
    {
        if ($actingUser->id === $targetUser->id) {
            return false; // Cannot change own role via this method
        }
        if ($this->isTargetSuperAdmin($targetUser)) {
            return false; // Role of SA cannot be changed by this policy
        }

        $actingUserRoleLevel = $this->getRoleLevel($actingUser);
        $targetUserRoleLevel = $this->getRoleLevel($targetUser);
        $newRoleLevel = $this->roleHierarchy[$newRoleName] ?? 99; // Default to lowest if role not in hierarchy

        // Cannot manage users of equal or higher rank (unless SA)
        if ($targetUserRoleLevel <= $actingUserRoleLevel && !$actingUser->hasRole('Super Admin')) {
            return false;
        }
        // Cannot assign a role that is equal or higher than acting user's own role (unless SA)
        if ($newRoleLevel <= $actingUserRoleLevel && !$actingUser->hasRole('Super Admin')) {
            return false;
        }
        // Cannot assign Super Admin role (only SA can do this, perhaps through a different mechanism)
        if ($newRoleName === 'Super Admin' && !$actingUser->hasRole('Super Admin')) {
            return false;
        }


        // Owner can assign 'Supervisor' or 'Staff' to users in their teams.
        if ($actingUser->hasRole('Owner') && in_array($newRoleName, ['Supervisor', 'Staff'])) {
            foreach ($actingUser->allTeams() as $team) {
                if ($targetUser->belongsToTeam($team)) {
                    return true;
                }
            }
        }

        // Supervisor can assign 'Staff' to users in their current team.
        if ($actingUser->hasRole('Supervisor') && $newRoleName === 'Staff') {
            if ($actingUser->currentTeam && $targetUser->belongsToTeam($actingUser->currentTeam)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Helper function to get the hierarchical level of a user's highest role.
     *
     * @param  \App\Models\User  $user
     * @return int
     */
    protected function getRoleLevel(User $user): int
    {
        $level = 99; // Default to lowest if no mapped role found
        foreach ($this->roleHierarchy as $role => $value) {
            if ($user->hasRole($role)) {
                $level = min($level, $value);
            }
        }
        return $level;
    }
}
