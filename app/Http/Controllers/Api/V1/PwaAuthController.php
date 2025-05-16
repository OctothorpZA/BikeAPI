<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PwaLoginRequest;
use App\Http\Requests\Api\V1\PwaRegisterRequest;
use App\Http\Resources\PwaUserResource;
use App\Models\User;
use App\Models\PaxProfile;
use Illuminate\Http\Request; // <-- Added for logout method
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class PwaAuthController extends Controller
{
    /**
     * Handle a login request for PWA users.
     */
    public function login(PwaLoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');
        $user = User::where('email', $credentials['email'])->first();

        if (!$user) {
            Log::info("PWA Login attempt: User not found for email {$credentials['email']}.");
            throw ValidationException::withMessages(['email' => [__('auth.failed')]]);
        }
        if (!$user->hasRole('PWA User')) {
             Log::warning("PWA Login attempt: User {$user->email} does not have 'PWA User' role.");
             throw ValidationException::withMessages(['email' => [__('auth.failed')]]);
        }
        if (!Auth::attempt($credentials)) {
            Log::info("PWA Login attempt: Auth::attempt failed for user {$user->email}.");
            throw ValidationException::withMessages(['email' => [__('auth.failed')]]);
        }
        $authenticatedUser = User::where('email', $credentials['email'])->first();
        if (!$authenticatedUser) {
             Log::error("PWA Login: Auth::attempt succeeded but could not re-fetch user {$credentials['email']}.");
             return response()->json(['message' => 'Authentication error during user retrieval.'], 500);
        }
        $token = $authenticatedUser->createToken($request->device_name)->plainTextToken;
        Log::info("PWA User {$authenticatedUser->email} logged in successfully. Token created: {$request->device_name}");

        return response()->json([
            'message' => 'PWA user logged in successfully.',
            'user' => new PwaUserResource($authenticatedUser->loadMissing('roles', 'paxProfiles')),
            'token_type' => 'Bearer',
            'access_token' => $token,
        ]);
    }

    /**
     * Register/Update a PWA user with full account details (name, email, password).
     */
    public function registerUserFromPwa(PwaRegisterRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->forceFill([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        if (!$user->hasRole('PWA User')) {
            $pwaRole = Role::firstOrCreate(['name' => 'PWA User', 'guard_name' => 'web']);
            $user->syncRoles([$pwaRole->name]);
        }

        $paxProfile = $user->paxProfiles()->first();
        if ($paxProfile) {
            $paxProfileDataToUpdate = [];
            if (empty($paxProfile->email) && $request->email) {
                $paxProfileDataToUpdate['email'] = $request->email;
            }
            $paxProfileDataToUpdate['first_name'] = Str::before($request->name, ' ') ?: $request->name;
            $paxProfileDataToUpdate['last_name'] = Str::after($request->name, ' ') ?: $this->faker()->lastName;

            if(!empty($paxProfileDataToUpdate)){
                $paxProfile->update($paxProfileDataToUpdate);
            }
        } elseif ($request->email) {
            PaxProfile::create([
                'user_id' => $user->id,
                'email' => $request->email,
                'first_name' => Str::before($request->name, ' ') ?: $request->name,
                'last_name' => Str::after($request->name, ' ') ?: $this->faker()->lastName,
            ]);
        }

        Log::info("PWA User {$user->email} completed full registration/update.");

        return response()->json([
            'message' => 'PWA user account updated successfully.',
            'user' => new PwaUserResource($user->fresh()->loadMissing('roles', 'paxProfiles')),
        ], 200);
    }

    /**
     * Log the PWA user out (Invalidate the current token).
     * Corresponds to POST /api/v1/pwa/logout
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user) {
            $user->currentAccessToken()->delete();
            Log::info("PWA User {$user->email} logged out. Token revoked.");
            return response()->json(['message' => 'PWA user logged out successfully.']);
        }

        return response()->json(['message' => 'No authenticated user to log out.'], 400);
    }

    // Helper for Faker instance - used in PaxProfile creation fallback
    private function faker()
    {
        return \Faker\Factory::create();
    }
}
