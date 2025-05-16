<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ValidateBookingRequest;
use App\Http\Resources\RentalPwaResource;
use App\Models\Rental;
use App\Models\User; // For PWA User creation/linking
use App\Models\PaxProfile; // To link rental to PaxProfile and potentially User
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class RentalController extends Controller
{
    /**
     * Validate a booking code and issue a token for the PWA user.
     * Corresponds to POST /api/v1/rentals/validate-booking
     *
     * @param ValidateBookingRequest $request
     * @return JsonResponse
     */
    public function validateBookingAndIssueToken(ValidateBookingRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $bookingCode = $validated['booking_code'];
        // $deviceName = $request->input('device_name', 'PWA Device'); // Get device name for token

        $rental = Rental::with([
                            'bike', // Eager load bike
                            'paxProfile.user', // Eager load pax profile and its associated user (PWA user)
                            'shipDeparture',
                            'startTeam.depotPoi', // Assumes Team model has a 'depotPoi' relation to its PointOfInterest
                            'endTeam.depotPoi'    // Same for endTeam
                        ])
                        ->where('booking_code', $bookingCode)
                        ->first();

        if (!$rental) {
            return response()->json(['message' => 'Invalid booking code.'], 404);
        }

        // --- PWA User & Token Logic ---
        $pwaUser = null;
        if ($rental->paxProfile && $rental->paxProfile->user) {
            // A PWA User is already linked to this PaxProfile
            $pwaUser = $rental->paxProfile->user;
        } elseif ($rental->paxProfile) {
            // No PWA User linked to PaxProfile, try to find one by PaxProfile's email
            // or create a new PWA User based on PaxProfile details.
            // This is a simplified PWA user creation.
            // PWA users should ideally not have staff Spatie roles.
            if ($rental->paxProfile->email) {
                $pwaUser = User::where('email', $rental->paxProfile->email)->first();
                if (!$pwaUser) {
                    $pwaUser = User::create([
                        'name' => $rental->paxProfile->first_name . ' ' . $rental->paxProfile->last_name,
                        'email' => $rental->paxProfile->email,
                        'password' => Hash::make(Str::random(16)), // Random password
                        'email_verified_at' => now(), // Assume verified via booking
                    ]);
                    // IMPORTANT: Ensure PWA users do NOT get staff roles by default from UserFactory.
                    // If UserFactory assigns a default role, remove it here or have a 'PWA User' role.
                    // $pwaUser->syncRoles([]); // Example: remove all roles
                    // Or assign a specific 'PWA User' Spatie role if you have one.
                }
                // Link this PWA user to the PaxProfile
                $rental->paxProfile->update(['user_id' => $pwaUser->id]);
            } else {
                // Cannot create/link PWA user without an email on PaxProfile
                // For now, proceed without a token if no email. PWA can prompt for full registration later.
                // Or return an error if PWA user/token is mandatory.
                // Let's assume for now we proceed and token is optional if user can't be identified/created.
                Log::warning("Cannot create/link PWA user for booking {$bookingCode} due to missing email on PaxProfile.");
            }
        } else {
            // No PaxProfile associated with the rental, cannot determine/create PWA user.
            Log::warning("No PaxProfile found for booking {$bookingCode}, cannot issue PWA user token.");
        }

        $token = null;
        if ($pwaUser) {
            // Revoke old tokens and issue a new one
            // $pwaUser->tokens()->delete(); // Optional: revoke all old tokens
            $deviceName = $request->input('device_name', 'PWA-' . Str::kebab($pwaUser->name) . '-' . now()->timestamp);
            $token = $pwaUser->createToken($deviceName)->plainTextToken;
        }

        // Prepare rental data for response, including transforming team to POI for depots
        // We need to manually add startTeamAsPoi and endTeamAsPoi if not directly on rental model
        // This assumes Team model has a 'depotPoi' relationship to its PointOfInterest record
        $rental->startTeamAsPoi = $rental->startTeam?->depotPoi;
        $rental->endTeamAsPoi = $rental->endTeam?->depotPoi;


        return response()->json([
            'message' => 'Booking validated successfully.',
            'rental' => new RentalPwaResource($rental),
            'token_type' => $token ? 'Bearer' : null,
            'access_token' => $token,
            'pwa_user_id' => $pwaUser?->id, // Send PWA user ID if available
        ]);
    }

    // ... other RentalController methods
}
