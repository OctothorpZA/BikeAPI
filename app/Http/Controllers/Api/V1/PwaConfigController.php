<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PwaConfigController extends Controller
{
    /**
     * Display the PWA configuration.
     * Corresponds to GET /api/v1/pwa/config
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request): JsonResponse
    {
        // IMPORTANT: Only include public, non-sensitive keys here.
        // API keys for frontend services (like Google Maps JavaScript API) are okay,
        // but backend-only secret keys should NEVER be exposed.

        $config = [
            'appName' => config('app.name', 'Dock & Ride'),

            // Key for PWA frontend Google Maps JavaScript API display
            // Ensure this key is restricted in Google Cloud Console to your PWA's domain(s).
            'googleMapsApiKey_PWA' => env('GOOGLE_MAPS_JS_API_KEY_FOR_PWA'),

            // Configuration for Laravel Echo client (Reverb)
            // These are typically set as VITE_ variables for frontend build,
            // but providing them here can be an alternative if PWA can't access Vite env vars post-build.
            'reverb' => [
                'appKey' => env('VITE_REVERB_APP_KEY'),
                'host' => env('VITE_REVERB_HOST'), // e.g., localhost or your app's domain
                'port' => (int) env('VITE_REVERB_PORT', 8080),
                'scheme' => env('VITE_REVERB_SCHEME', 'http'), // 'http' or 'https'
                'encrypted' => env('VITE_REVERB_SCHEME') === 'https', // For Echo's 'encrypted' option
                // 'cluster' => env('VITE_PUSHER_APP_CLUSTER', 'mt1'), // Default, Reverb doesn't strictly use cluster but Echo client might expect it
            ],

            // Example feature flags
            'featureFlags' => [
                'chatEnabled' => (bool) env('PWA_FEATURE_CHAT_ENABLED', false),
                'notificationsEnabled' => (bool) env('PWA_FEATURE_NOTIFICATIONS_ENABLED', true),
                // Add other PWA-specific feature flags as needed
            ],

            // Other public configuration your PWA might need
            // 'contactEmail' => 'support@dockandride.com',
            // 'maxRentalDurationHours' => 8,
            // 'currencySymbol' => '$',
        ];

        // Ensure sensitive keys are not accidentally exposed if not set in .env
        if (empty($config['googleMapsApiKey_PWA'])) {
            // Log a warning or handle appropriately if this key is critical for PWA
            // For now, we'll allow it to be null if not set.
            // Log::warning('GOOGLE_MAPS_JS_API_KEY_FOR_PWA is not set in .env for PWA config endpoint.');
        }


        return response()->json($config);
    }
}
