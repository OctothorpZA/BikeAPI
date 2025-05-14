<?php

namespace App\Actions\Jetstream;

use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Laravel\Jetstream\Contracts\RemovesTeamMembers;
use Laravel\Jetstream\Events\TeamMemberRemoved;

class RemoveTeamMember implements RemovesTeamMembers
{
    /**
     * Remove the team member from the given team.
     */
    public function remove(User $user, Team $team, User $teamMember): void
    {
        $this->authorize($user, $team, $teamMember);

        // This was the method call that PHPStan reported as undefined.
        // It should match the actual method name defined below.
        $this->ensureTeamMemberIsNotOwner($teamMember, $team);

        $team->removeUser($teamMember);

        TeamMemberRemoved::dispatch($team, $teamMember);
    }

    /**
     * Authorize that the user can remove the team member.
     */
    protected function authorize(User $user, Team $team, User $teamMember): void
    {
        if (! Gate::forUser($user)->check('removeTeamMember', $team) &&
            $user->id !== $teamMember->id) {
            throw new AuthorizationException;
        }
    }

    /**
     * Ensure that the team member is not the owner of the team.
     */
    protected function ensureTeamMemberIsNotOwner(User $teamMember, Team $team): void
    {
        if ($team->owner && $teamMember->id === $team->owner->id) {
            throw ValidationException::withMessages([
                'team' => [__('The team owner cannot be removed from the team.')],
            ])->errorBag('removeTeamMember');
        }
    }
}
