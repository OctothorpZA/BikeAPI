<?php

namespace App\Actions\Jetstream;

use App\Models\Team; // Ensure Team model is imported if not already
use Laravel\Jetstream\Contracts\DeletesTeams;

class DeleteTeam implements DeletesTeams
{
    /**
     * Delete the given team.
     */
    public function delete(Team $team): void
    {
        // This is Jetstream's default behavior for deleting a team.
        // It handles dissociating users, deleting invitations, etc., then deletes the team.
        $team->purge();
    }
}
