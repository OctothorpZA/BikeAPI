<?php

namespace App\Policies;

use App\Models\PointOfInterest;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PointOfInterestPolicy
{
    use HandlesAuthorization;

    /**
     * Perform pre-authorization checks.
     */
    public function before(User $user, string $ability): ?bool
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
        return $user->hasAnyRole(['Owner', 'Supervisor', 'Staff']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PointOfInterest $pointOfInterest): bool
    {
        if ($pointOfInterest->is_approved && $pointOfInterest->is_active) {
            return $user->hasAnyRole(['Owner', 'Supervisor', 'Staff']);
        }

        // Creator can always view their own submission.
        if ($pointOfInterest->created_by_user_id === $user->id) {
            return true;
        }

        // For unapproved or inactive POIs:
        if ($user->hasRole('Owner')) {
            if ($pointOfInterest->category === PointOfInterest::CATEGORY_DEPOT && $pointOfInterest->team_id && $user->allTeams()->pluck('id')->contains($pointOfInterest->team_id)) {
                return true; // Owner can view their team's Depots (even if inactive/unapproved by some chance)
            }
            // Owner can view any non-Depot POI (staff picks, general) regardless of team or approval status for management purposes.
            if ($pointOfInterest->category !== PointOfInterest::CATEGORY_DEPOT) {
                 return true;
            }
             // Owner can view Depots associated with their teams
            if ($pointOfInterest->category === PointOfInterest::CATEGORY_DEPOT && $pointOfInterest->team_id && $user->allTeams()->pluck('id')->contains($pointOfInterest->team_id)) {
                return true;
            }
        }

        if ($user->hasRole('Supervisor')) {
            // Supervisor can view their team's Depots (even if inactive/unapproved by some chance)
            if ($pointOfInterest->category === PointOfInterest::CATEGORY_DEPOT && $pointOfInterest->team_id && $user->currentTeam && $pointOfInterest->team_id == $user->currentTeam->id) {
                return true;
            }
            // Supervisor can view unapproved staff picks within their team or general unapproved staff picks they can approve.
            if ($pointOfInterest->category === PointOfInterest::CATEGORY_STAFF_PICK && !$pointOfInterest->is_approved) {
                if (is_null($pointOfInterest->team_id)) return true; // General staff pick
                if ($pointOfInterest->team_id && $user->currentTeam && $pointOfInterest->team_id == $user->currentTeam->id) return true; // Team specific staff pick
            }
        }
        return false;
    }

    /**
     * Determine whether the user can create Point Of Interest records.
     * NOTE: This method determines if a user can INITIATE creation.
     * The CONTROLLER'S store() method MUST enforce:
     * - Only Owners/Super Admins can create POIs of category 'Depot'.
     * - 'Depot' POIs are created as is_approved = true and linked to a team.
     * - Staff/Supervisors creating POIs should result in 'Staff Pick' (or similar)
     * category records, marked as is_approved = false, and can be team_id specific or general.
     */
    public function create(User $user): bool
    {
        // All staff types can initiate creation (suggest a 'Staff Pick').
        // Owners/SA can also initiate creation (for 'Depots' or 'Staff Picks').
        return $user->hasAnyRole(['Owner', 'Supervisor', 'Staff']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PointOfInterest $pointOfInterest): bool
    {
        // Rule: Only Owners/SA can create/approve new Depots.
        // Implies only Owner/SA can *update* a POI that IS a Depot.
        if ($pointOfInterest->category === PointOfInterest::CATEGORY_DEPOT) {
            if ($user->hasRole('Owner') && $pointOfInterest->team_id && $user->allTeams()->pluck('id')->contains($pointOfInterest->team_id)) {
                return true; // SA handled by before()
            }
            return false; // Supervisors cannot update Depots.
        }

        // For 'Staff Pick' (or other non-Depot categories):
        // Creator can update their own *unapproved* 'Staff Pick'.
        if ($pointOfInterest->category !== PointOfInterest::CATEGORY_DEPOT && !$pointOfInterest->is_approved && $pointOfInterest->created_by_user_id === $user->id) {
            return true;
        }

        // Supervisor & Owner (and SA) can update any 'Staff Pick' (approved or unapproved),
        // respecting team scope if the Staff Pick is team-specific.
        if ($pointOfInterest->category !== PointOfInterest::CATEGORY_DEPOT) {
            if ($user->hasAnyRole(['Owner', 'Supervisor'])) {
                if (is_null($pointOfInterest->team_id)) { // General Staff Pick
                    return true;
                }
                // Team-specific Staff Pick
                if ($pointOfInterest->team_id) {
                    if ($user->hasRole('Owner') && $user->allTeams()->pluck('id')->contains($pointOfInterest->team_id)) return true;
                    if ($user->hasRole('Supervisor') && $user->currentTeam && $pointOfInterest->team_id == $user->currentTeam->id) return true;
                }
            }
        }
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PointOfInterest $pointOfInterest): bool
    {
        // Only Owner/SA can delete Depots.
        if ($pointOfInterest->category === PointOfInterest::CATEGORY_DEPOT) {
            if ($user->hasRole('Owner') && $pointOfInterest->team_id && $user->allTeams()->pluck('id')->contains($pointOfInterest->team_id)) {
                return true; // SA handled by before()
            }
            return false; // Supervisors cannot delete Depots.
        }

        // For 'Staff Pick' (or other non-Depot categories):
        // Creator (if Staff role) can delete their own *unapproved* 'Staff Pick'.
        if ($pointOfInterest->category !== PointOfInterest::CATEGORY_DEPOT &&
            !$pointOfInterest->is_approved &&
            $pointOfInterest->created_by_user_id === $user->id &&
            $user->hasRole('Staff')) {
            return true;
        }

        // Supervisor & Owner (and SA) can delete any 'Staff Pick' (approved or unapproved),
        // respecting team scope if the Staff Pick is team-specific.
        if ($pointOfInterest->category !== PointOfInterest::CATEGORY_DEPOT) {
            if ($user->hasAnyRole(['Owner', 'Supervisor'])) {
                if (is_null($pointOfInterest->team_id)) { // General Staff Pick
                    return true;
                }
                // Team-specific Staff Pick
                if ($pointOfInterest->team_id) {
                    if ($user->hasRole('Owner') && $user->allTeams()->pluck('id')->contains($pointOfInterest->team_id)) return true;
                    if ($user->hasRole('Supervisor') && $user->currentTeam && $pointOfInterest->team_id == $user->currentTeam->id) return true;
                }
            }
        }
        return false;
    }

    /**
     * Determine whether the user can approve or reject a Point Of Interest.
     * This is primarily for 'Staff Pick' category POIs.
     */
    public function approve(User $user, PointOfInterest $pointOfInterest): bool
    {
        if ($pointOfInterest->is_approved) {
            return false; // Already approved
        }

        // Depots should be created as approved by Owner/SA.
        // If a Depot somehow becomes unapproved, only Owner/SA could re-approve it.
        if ($pointOfInterest->category === PointOfInterest::CATEGORY_DEPOT) {
            return $user->hasRole('Owner') && $pointOfInterest->team_id && $user->allTeams()->pluck('id')->contains($pointOfInterest->team_id);
            // SA handled by before()
        }

        // Rule: "supervisor role and up can approve staff picks"
        // This applies to POIs that are NOT Depots.
        if ($pointOfInterest->category !== PointOfInterest::CATEGORY_DEPOT) { // e.g., 'Staff Pick', 'General'
            if ($user->hasAnyRole(['Owner', 'Supervisor'])) { // SA handled by before()
                // If the staff pick is tied to a team, ensure approver has scope for that team
                if ($pointOfInterest->team_id) {
                    if ($user->hasRole('Owner') && $user->allTeams()->pluck('id')->contains($pointOfInterest->team_id)) return true;
                    if ($user->hasRole('Supervisor') && $user->currentTeam && $pointOfInterest->team_id == $user->currentTeam->id) return true;
                    return false; // No scope for this team-specific staff pick
                }
                return true; // General staff pick (not tied to a team)
            }
        }
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, PointOfInterest $pointOfInterest): bool
    {
        // Mirror delete permissions for simplicity
        if ($pointOfInterest->category === PointOfInterest::CATEGORY_DEPOT) {
            if ($user->hasRole('Owner') && $pointOfInterest->team_id && $user->allTeams()->pluck('id')->contains($pointOfInterest->team_id)) {
                return true;
            }
            return false;
        }

        if ($pointOfInterest->category !== PointOfInterest::CATEGORY_DEPOT) {
            if ($user->hasAnyRole(['Owner', 'Supervisor'])) {
                if (is_null($pointOfInterest->team_id)) return true;
                if ($pointOfInterest->team_id) {
                    if ($user->hasRole('Owner') && $user->allTeams()->pluck('id')->contains($pointOfInterest->team_id)) return true;
                    if ($user->hasRole('Supervisor') && $user->currentTeam && $pointOfInterest->team_id == $user->currentTeam->id) return true;
                }
            }
        }
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, PointOfInterest $pointOfInterest): bool
    {
        return false; // SA only (via before)
    }

    /**
     * Determine whether the user can toggle the active status of a Point Of Interest.
     */
    public function toggleActive(User $user, PointOfInterest $pointOfInterest): bool
    {
        if (!$pointOfInterest->is_approved) {
            return false; // Can only toggle active for approved POIs
        }

        // For Depots: Only Owner/SA
        if ($pointOfInterest->category === PointOfInterest::CATEGORY_DEPOT) {
            if ($user->hasRole('Owner') && $pointOfInterest->team_id && $user->allTeams()->pluck('id')->contains($pointOfInterest->team_id)) {
                return true; // SA handled by before()
            }
            return false;
        }

        // For Staff Picks (or other non-Depot, approved POIs): Supervisor/Owner/SA
        if ($pointOfInterest->category !== PointOfInterest::CATEGORY_DEPOT) {
            if ($user->hasAnyRole(['Owner', 'Supervisor'])) {
                if (is_null($pointOfInterest->team_id)) return true; // General
                if ($pointOfInterest->team_id) { // Team-specific staff pick
                    if ($user->hasRole('Owner') && $user->allTeams()->pluck('id')->contains($pointOfInterest->team_id)) return true;
                    if ($user->hasRole('Supervisor') && $user->currentTeam && $pointOfInterest->team_id == $user->currentTeam->id) return true;
                }
            }
        }
        return false;
    }
}
