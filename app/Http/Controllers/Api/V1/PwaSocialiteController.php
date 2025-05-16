<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Exception;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;

class PwaSocialiteController extends Controller
{
    public function redirectToProvider(string $provider)
    {
        if (!in_array($provider, ['google'])) {
            return response()->json(['message' => 'Unsupported login provider.'], 422);
        }
        try {
            $redirectUrl = route('auth.pwa.social.callback', ['provider' => $provider], true); // Absolute URL
            Log::info("PWA Socialite: Redirecting to Google. Telling Google to callback to: " . $redirectUrl);

            return Socialite::driver($provider)
                ->redirectUrl($redirectUrl)
                ->redirect();

        } catch (Exception $e) {
            Log::error("PWA Socialite redirect error for provider {$provider}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'Could not redirect to login provider. Please try again.'], 500);
        }
    }

    public function handleProviderCallback(string $provider, Request $request)
    {
        if (!in_array($provider, ['google'])) {
            $pwaErrorUrl = env('PWA_APP_URL', 'http://localhost:3000') . '/auth/social-callback-error?error=unsupported_provider';
            return redirect($pwaErrorUrl);
        }

        // This is the crucial part for session state in callback
        $redirectUrl = route('auth.pwa.social.callback', ['provider' => $provider], true);
        Log::info("PWA Socialite: Handling callback from Google. Expected callback URL for Socialite user() method: " . $redirectUrl);

        try {
            // Pass the same redirectUrl to the user() method that was used for the initial redirect.
            $socialUser = Socialite::driver($provider)->redirectUrl($redirectUrl)->user();
        } catch (Exception $e) {
            Log::error("PWA Socialite callback error for provider {$provider}: " . $e->getMessage(), [
                'exception' => $e,
                'request_all' => $request->all(),
                'session_id' => session()->getId(), // Log session ID
                'session_all' => session()->all() // Log all session data (be careful with sensitive data in logs)
            ]);
            $pwaErrorUrl = env('PWA_APP_URL', 'http://localhost:3000') . '/auth/social-callback-error?error=provider_login_failed';
            return redirect($pwaErrorUrl);
        }

        if (empty($socialUser->getEmail())) {
            Log::warning("PWA Socialite: No email address provided by {$provider}. Token: " . ($socialUser->token ?? 'N/A'));
            $pwaErrorUrl = env('PWA_APP_URL', 'http://localhost:3000') . '/auth/social-callback-error?error=no_email_provided';
            return redirect($pwaErrorUrl);
        }

        $userEmail = $socialUser->getEmail();

        try {
            $user = DB::transaction(function () use ($socialUser, $userEmail, $provider) {
                $existingUser = User::where('email', $userEmail)->first();
                $providerIdField = $provider . '_id';

                if ($existingUser) {
                    if (!$existingUser->hasRole('PWA User')) {
                        $pwaRole = Role::firstOrCreate(['name' => 'PWA User', 'guard_name' => 'web']);
                        $existingUser->assignRole($pwaRole);
                    }
                    if (empty($existingUser->{$providerIdField})) {
                        $existingUser->forceFill([$providerIdField => $socialUser->getId()])->save();
                    }
                    $this->ensureUserHasTeam($existingUser);
                    return $existingUser;
                } else {
                    $newUser = User::create([
                        'name' => $socialUser->getName() ?: $socialUser->getNickname() ?: 'PWA User',
                        'email' => $userEmail,
                        'password' => Hash::make(Str::random(24)),
                        $providerIdField => $socialUser->getId(),
                        'email_verified_at' => now(),
                    ]);
                    $pwaRole = Role::firstOrCreate(['name' => 'PWA User', 'guard_name' => 'web']);
                    $newUser->assignRole($pwaRole);
                    $this->ensureUserHasTeam($newUser);
                    Log::info("New PWA User {$newUser->email} created via {$provider} SSO.");
                    return $newUser->fresh();
                }
            });

            $pwaDeviceName = 'PWA-SSO-' . $provider . '-' . Str::kebab($user->name) . '-' . now()->timestamp;
            $token = $user->createToken($pwaDeviceName)->plainTextToken;

            $pwaCallbackUrl = env('PWA_APP_URL', 'http://localhost:3000') . '/auth/social-callback';
            return redirect()->to($pwaCallbackUrl . '?token=' . $token . '&user_id=' . $user->id . '&name=' . urlencode($user->name) . '&email=' . urlencode($user->email));

        } catch (Exception $e) {
            Log::error("PWA Socialite user processing error for {$userEmail} with {$provider}: " . $e->getMessage(), ['exception' => $e]);
            $pwaErrorUrl = env('PWA_APP_URL', 'http://localhost:3000') . '/auth/social-callback-error?error=processing_failed';
            return redirect($pwaErrorUrl);
        }
    }

    protected function ensureUserHasTeam(User $user): void
    {
        if (Features::hasTeamFeatures()) {
            $personalTeam = $user->ownedTeams()->where('personal_team', true)->first();
            if (!$personalTeam) {
                $team = $user->ownedTeams()->save(Team::forceCreate([
                    'user_id' => $user->id,
                    'name' => explode(' ', $user->name, 2)[0] . "'s Team",
                    'personal_team' => true,
                ]));
                $user->forceFill(['current_team_id' => $team->id])->save();
                Log::info("Personal team created for PWA user {$user->email} during SSO.");
            } elseif (is_null($user->current_team_id) || $user->current_team_id !== $personalTeam->id) {
                 $user->forceFill(['current_team_id' => $personalTeam->id])->save(); // Use forceFill for direct update
                 Log::info("Switched PWA user {$user->email} to existing personal team ID {$personalTeam->id} during SSO.");
            }
        }
    }
}
