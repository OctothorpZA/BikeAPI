<?php

namespace App\Actions\Jetstream;

use App\Models\Team; // Ensure Team model is imported
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Jetstream\Contracts\DeletesTeams;
use Laravel\Jetstream\Contracts\DeletesUsers;
use Illuminate\Support\Collection; // Ensure Collection is imported

class DeleteUser implements DeletesUsers
{
    /**
     * Create a new action instance.
     */
    public function __construct(protected DeletesTeams $deletesTeams) {}

    /**
     * Delete the given user.
     */
    public function delete(User $user): void
    {
        DB::transaction(function () use ($user) {
            $this->deleteTeams($user);
            $user->deleteProfilePhoto();
            $user->tokens->each->delete();
            $user->delete();
        });
    }

    /**
     * Delete the teams and team associations attached to the user.
     */
    protected function deleteTeams(User $user): void
    {
        $user->teams()->detach();

        /** @var Collection<int, Team> $ownedTeams */ // Explicitly type hint the collection
        $ownedTeams = $user->ownedTeams;

        $ownedTeams->each(function (Team $team) {
            $this->deletesTeams->delete($team);
        });
    }
}
