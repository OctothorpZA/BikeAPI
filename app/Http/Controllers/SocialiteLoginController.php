<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Team;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Exception;
use Laravel\Jetstream\Features; // For checking if team features are enabled

class SocialiteLoginController extends Controller
{
    /**
     * Redirect the user to the provider's authentication page.
     */
    public function redirectToProvider(string $provider)
    {
        if (!in_array($provider, ['google'])) {
            return redirect('/login')->with('error', 'Unsupported login provider.');
        }
        try {
            return Socialite::driver($provider)->redirect();
        } catch (Exception $e) {
            Log::error("Socialite redirect error for provider {$provider}: " . $e->getMessage());
            return redirect('/login')->with('error', 'Could not redirect to login provider. Please try again.');
        }
    }

    /**
     * Obtain the user information from the provider.
     */
    public function handleProviderCallback(string $provider)
    {
        if (!in_array($provider, ['google'])) {
            return redirect('/login')->with('error', 'Unsupported login provider.');
        }

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (Exception $e) {
            Log::error("Socialite callback error for provider {$provider}: " . $e->getMessage());
            return redirect('/login')->with('error', 'Login failed with the provider. Please try again.');
        }

        $userEmail = $socialUser->getEmail();
        if (empty($userEmail)) {
            return redirect('/login')->with('error', 'No email address was provided by the login provider. Cannot log in.');
        }

        $user = User::where('email', $userEmail)->first();

        if (!$user) {
            Log::warning("Unauthorized SSO attempt: Email {$userEmail} not found in the system (pre-provisioned accounts only).");
            return redirect('/login')->with('error', 'Access denied. Your account must be pre-registered by an administrator.');
        }

        // At this point, $user is an existing user from the database.
        DB::transaction(function () use ($socialUser, $user) {
            if (empty($user->google_id)) {
                $user->forceFill(['google_id' => $socialUser->getId()])->save();
            }

            if (Features::hasTeamFeatures()) {
                // Check if the user has a current_team_id and if that team exists.
                $currentTeamExists = false;
                if ($user->current_team_id) {
                    $currentTeam = Team::find($user->current_team_id);
                    if ($currentTeam) {
                        $currentTeamExists = true;
                        // Ensure the relation is loaded if needed, though switchTeam will handle it.
                        // $user->setRelation('currentTeam', $currentTeam);
                         Log::info("User {$user->email} has existing current_team_id: {$user->current_team_id} which points to team '{$currentTeam->name}'.");
                    } else {
                        Log::warning("User {$user->email} has current_team_id: {$user->current_team_id}, but team not found. Will attempt to set personal team.");
                    }
                }

                if (!$currentTeamExists) {
                    Log::info("User {$user->email} has no valid current team. Attempting to set/create personal team.");
                    $personalTeam = $user->ownedTeams()->where('personal_team', true)->first();

                    if (!$personalTeam) {
                        Log::info("No existing personal team found for {$user->email}. Creating one.");
                        // Create a personal team using the method that also sets current_team_id
                        $personalTeam = $this->createPersonalTeam($user);
                    }

                    if ($personalTeam) {
                        // Use Jetstream's switchTeam method to ensure current_team_id is set
                        // and any other necessary session/state updates occur.
                        // This method also saves the user model.
                        if ($user->current_team_id != $personalTeam->id) {
                           Log::info("Switching user {$user->email} to personal team ID: {$personalTeam->id}");
                           $user->switchTeam($personalTeam);
                        } else {
                           Log::info("User {$user->email} is already on their personal team ID: {$personalTeam->id}");
                           // Ensure the relation is loaded if switchTeam wasn't called
                           $user->load('currentTeam');
                        }
                    } else {
                        Log::error("Could not create or find a personal team for existing SSO user: {$user->email}. current_team_id might remain null.");
                    }
                }
            }
        });

        // Log in a fresh instance of the user to ensure all updates are reflected.
        Auth::login($user->fresh(), true);

        return redirect()->intended(config('fortify.home'));
    }

    /**
     * Create a personal team for the user if team features are enabled.
     * This method now ensures the user's current_team_id is updated.
     *
     * @param  \App\Models\User  $user
     * @return \App\Models\Team|null The created or existing personal team, or null if team features are disabled.
     */
    protected function createPersonalTeam(User $user): ?Team
    {
        if (!Features::hasTeamFeatures()) {
            return null;
        }

        // Double check if they already own a personal team to prevent duplicates if called multiple times.
        $existingPersonalTeam = $user->ownedTeams()->where('personal_team', true)->first();
        if ($existingPersonalTeam) {
            Log::info("User {$user->email} already has a personal team: ID {$existingPersonalTeam->id} during createPersonalTeam call.");
            // Ensure current_team_id is set to this existing personal team if not already
            if ($user->current_team_id !== $existingPersonalTeam->id) {
                 $user->forceFill(['current_team_id' => $existingPersonalTeam->id])->save();
                 $user->refresh(); // Refresh to load the currentTeam relation
            }
            return $existingPersonalTeam;
        }

        $team = $user->ownedTeams()->save(Team::forceCreate([
            'user_id' => $user->id,
            'name' => explode(' ', $user->name, 2)[0] . "'s Team", // Jetstream's default naming
            'personal_team' => true,
        ]));

        // Update current_team_id directly on the user model and save.
        // Using switchTeam here is also an option but forceFill is direct.
        $user->forceFill(['current_team_id' => $team->id])->save();
        Log::info("Personal team created for user {$user->email}. ID: {$team->id}. current_team_id set.");

        return $team;
    }
}
