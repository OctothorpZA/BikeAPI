<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LinkBookingRequest;
use App\Http\Resources\RentalPwaResource; // We'll use the existing resource
use App\Models\Rental;
use App\Models\User;
use App\Models\PaxProfile;
use Illuminate\Http\Request; // For the myRentals method
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection; // For collection response
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str; // For Str::before/after if used in PaxProfile creation

class PwaUserController extends Controller
{
    /**
     * Link an existing booking/rental to the authenticated PWA user's account.
     * Corresponds to POST /api/v1/pwa/link-booking
     */
    public function linkBooking(LinkBookingRequest $request): JsonResponse
    {
        /** @var User $pwaUser */
        $pwaUser = $request->user();
        $bookingCode = $request->validated()['booking_code'];

        $rental = Rental::with('paxProfile')->where('booking_code', $bookingCode)->first();

        if (!$rental) {
            return response()->json(['message' => 'Booking code not found.'], 404);
        }

        if (!$rental->paxProfile) {
            Log::error("Booking code {$bookingCode} found, but it has no associated PaxProfile. Cannot link.");
            return response()->json(['message' => 'This booking cannot be linked at this time. Please contact support.'], 422);
        }

        if ($rental->paxProfile->user_id && $rental->paxProfile->user_id !== $pwaUser->id) {
            Log::warning("PWA User {$pwaUser->email} (ID: {$pwaUser->id}) attempted to link booking {$bookingCode}, but its PaxProfile (ID: {$rental->paxProfile->id}) is already linked to User ID: {$rental->paxProfile->user_id}.");
            return response()->json(['message' => 'This booking is already associated with another PWA account.'], 409); // 409 Conflict
        }

        if (is_null($rental->paxProfile->user_id)) {
            $rental->paxProfile->user_id = $pwaUser->id;
            if (empty($rental->paxProfile->email) && !empty($pwaUser->email)) {
                $rental->paxProfile->email = $pwaUser->email;
            }
            // Optionally sync names if pax profile names are generic
            // $rental->paxProfile->first_name = Str::before($pwaUser->name, ' ') ?: $pwaUser->name;
            // $rental->paxProfile->last_name = Str::after($pwaUser->name, ' ') ?: $this->faker()->lastName();

            $rental->paxProfile->save();
            Log::info("Booking {$bookingCode} (PaxProfile ID: {$rental->paxProfile->id}) successfully linked to PWA User {$pwaUser->email} (ID: {$pwaUser->id}).");
            return response()->json(['message' => 'Booking successfully linked to your account.']);
        } elseif ($rental->paxProfile->user_id === $pwaUser->id) {
            return response()->json(['message' => 'This booking is already linked to your account.']);
        }

        return response()->json(['message' => 'An unexpected error occurred while linking the booking.'], 500);
    }

    /**
     * Display a listing of the authenticated PWA user's rentals.
     * Corresponds to GET /api/v1/pwa/my-rentals
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function myRentals(Request $request): AnonymousResourceCollection
    {
        /** @var User $pwaUser */
        $pwaUser = $request->user();

        // Get IDs of PaxProfiles linked to this PWA user
        // Assumes 'paxProfiles' relationship exists on User model (public function paxProfiles(): HasMany)
        $paxProfileIds = $pwaUser->paxProfiles()->pluck('id');

        // Fetch rentals associated with these PaxProfiles
        $rentals = Rental::with([
                            'bike', // For BikePwaPublicResource
                            'paxProfile', // For context, though filtered by it
                            'shipDeparture',
                            'startTeam.depotPoi', // For DepotPublicResource via Team's depotPoi relation
                            'endTeam.depotPoi'    // For DepotPublicResource via Team's depotPoi relation
                        ])
                        ->whereIn('pax_profile_id', $paxProfileIds)
                        ->orderBy('start_time', 'desc') // Or by created_at, or preferred order
                        ->paginate(10); // Paginate for potentially long lists

        // Manually set startTeamAsPoi and endTeamAsPoi for the resource
        // This is necessary because the RentalPwaResource expects these dynamic properties.
        $rentals->getCollection()->transform(function ($rental) {
            // Ensure the Team model has the 'depotPoi' relationship defined
            $rental->startTeamAsPoi = $rental->startTeam?->depotPoi;
            $rental->endTeamAsPoi = $rental->endTeam?->depotPoi;
            return $rental;
        });

        return RentalPwaResource::collection($rentals);
    }

    // Helper for Faker instance - used in linkBooking if syncing names from PaxProfile
    private function faker()
    {
        return \Faker\Factory::create();
    }
}
