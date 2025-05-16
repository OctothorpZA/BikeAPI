<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http; // For making HTTP requests to Google Places API
use Illuminate\Support\Facades\Log;
use App\Http\Resources\PlaceResource; // The API Resource we just created
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GooglePlacesController extends Controller
{
    /**
     * Fetch nearby places from Google Places API.
     * Corresponds to GET /api/v1/google-places/nearby
     *
     * Requires query parameters: latitude, longitude
     * Optional: radius (meters, default 5000), type (e.g., restaurant, cafe)
     *
     * @param Request $request
     * @return AnonymousResourceCollection|JsonResponse
     */
    public function nearby(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius' => ['sometimes', 'integer', 'min:50', 'max:50000'], // Max radius for Nearby Search is 50,000 meters
            'type' => ['sometimes', 'string', 'max:100'], // e.g., 'restaurant', 'cafe', 'tourist_attraction'
            // 'keyword' => ['sometimes', 'string', 'max:100'], // Optional keyword search
        ]);

        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');
        $radius = $request->input('radius', 5000); // Default to 5km
        $type = $request->input('type');
        // $keyword = $request->input('keyword');

        $apiKey = env('GOOGLE_PLACES_API_KEY');

        if (empty($apiKey)) {
            Log::error('Google Places API key is not configured.');
            // Return placeholder data if API key is missing, for development
            $placeholderData = [
                ['place_id' => 'placeholder_1', 'name' => 'Placeholder Cafe', 'vicinity' => '123 Main St', 'geometry' => ['location' => ['lat' => $latitude, 'lng' => $longitude]], 'types' => ['cafe', 'food']],
                ['place_id' => 'placeholder_2', 'name' => 'Placeholder Park', 'vicinity' => '456 Oak Ave', 'geometry' => ['location' => ['lat' => $latitude + 0.01, 'lng' => $longitude + 0.01]], 'types' => ['park']],
            ];
            return PlaceResource::collection(collect($placeholderData));
            // Or return an error:
            // return response()->json(['message' => 'Service not configured.'], 503);
        }

        // --- Actual Google Places API Call (Example) ---
        /*
        $url = "https://maps.googleapis.com/maps/api/place/nearbysearch/json";
        $parameters = [
            'location' => "{$latitude},{$longitude}",
            'radius' => $radius,
            'key' => $apiKey,
        ];
        if ($type) {
            $parameters['type'] = $type;
        }
        // if ($keyword) {
        //     $parameters['keyword'] = $keyword;
        // }

        try {
            $response = Http::timeout(10)->get($url, $parameters);

            if ($response->successful()) {
                $places = $response->json('results', []); // Get the 'results' array
                return PlaceResource::collection(collect($places));
            } else {
                Log::error('Google Places API request failed.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'url' => $url,
                    'params' => $parameters (except API key for logging)
                ]);
                return response()->json(['message' => 'Could not fetch nearby places.', 'error' => $response->json('error_message', 'Unknown error')], $response->status());
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Google Places API connection error: ' . $e->getMessage());
            return response()->json(['message' => 'Could not connect to places service.'], 504); // Gateway Timeout
        } catch (Exception $e) {
            Log::error('Error fetching Google Places: ' . $e->getMessage());
            return response()->json(['message' => 'An unexpected error occurred while fetching places.'], 500);
        }
        */

        // For now, returning placeholder data as API key is not set up for actual calls yet.
        // Remove this placeholder block once you have a GOOGLE_PLACES_API_KEY and want to make live calls.
        $placeholderData = [
            ['place_id' => 'fake_id_123', 'name' => 'Demo Cafe Nearby', 'vicinity' => '123 Demo St', 'geometry' => ['location' => ['lat' => (float)$latitude + 0.001, 'lng' => (float)$longitude + 0.001]], 'types' => ['cafe', 'food'], 'rating' => 4.5, 'user_ratings_total' => 150],
            ['place_id' => 'fake_id_456', 'name' => 'Demo Park Central', 'vicinity' => '456 Park Ave', 'geometry' => ['location' => ['lat' => (float)$latitude - 0.002, 'lng' => (float)$longitude - 0.002]], 'types' => ['park', 'tourist_attraction'], 'rating' => 4.2, 'user_ratings_total' => 80],
        ];
        return PlaceResource::collection(collect($placeholderData));
    }
}
