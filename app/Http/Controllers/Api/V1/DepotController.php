<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PointOfInterest; // Your PointOfInterest model
use App\Http\Resources\DepotPublicResource; // The API Resource we just created
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection; // For collection responses

class DepotController extends Controller
{
    /**
     * Display a listing of publicly available depots.
     * This corresponds to GET /api/v1/public/depots
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function publicIndex(Request $request): AnonymousResourceCollection
    {
        // Fetch POIs that are:
        // 1. Category 'Depot'
        // 2. Active (is_active = true)
        // 3. Approved (is_approved = true) - Depots should always be approved
        $depots = PointOfInterest::where('category', PointOfInterest::CATEGORY_DEPOT)
                                 ->where('is_active', true)
                                 ->where('is_approved', true)
                                 // ->with('team') // Optionally load the associated Team model if needed by the resource
                                 ->orderBy('name')
                                 ->get();

        // Return a collection of resources
        return DepotPublicResource::collection($depots);
    }

    /**
     * Display a listing of depots for authenticated users.
     * This corresponds to GET /api/v1/depots
     * This might return more details than publicIndex.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        // For now, let's return the same data as publicIndex.
        // Later, you could add more details or filter based on the authenticated PWA user's context if needed.
        $depots = PointOfInterest::where('category', PointOfInterest::CATEGORY_DEPOT)
                                 ->where('is_active', true)
                                 ->where('is_approved', true)
                                 ->orderBy('name')
                                 ->get();

        // You might create a different resource (e.g., DepotAuthenticatedResource) if more data is needed.
        return DepotPublicResource::collection($depots);
    }

    // Other resourceful methods like show, store, update, destroy will be added as needed
    // For now, we focus on the blueprint's requirements.
    // public function show(PointOfInterest $pointOfInterest) // If depots are POIs
    // {
    //     // Ensure it's a depot and user can view it
    //     if ($pointOfInterest->category !== PointOfInterest::CATEGORY_DEPOT) {
    //         return response()->json(['message' => 'Not a depot.'], 404);
    //     }
    //     // Potentially use a different resource for detailed view
    //     return new DepotPublicResource($pointOfInterest);
    // }
}
