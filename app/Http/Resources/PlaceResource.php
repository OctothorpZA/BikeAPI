<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlaceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // This structure is a guess based on common Google Places API fields.
        // Adjust it based on what the actual API returns and what your PWA needs.
        return [
            'place_id' => $this['place_id'] ?? null, // Assuming the data passed is an array
            'name' => $this['name'] ?? 'Unknown Place',
            'address' => $this['vicinity'] ?? $this['formatted_address'] ?? null,
            'latitude' => $this['geometry']['location']['lat'] ?? null,
            'longitude' => $this['geometry']['location']['lng'] ?? null,
            'types' => $this['types'] ?? [],
            'rating' => $this['rating'] ?? null,
            'user_ratings_total' => $this['user_ratings_total'] ?? null,
            // 'icon' => $this['icon'] ?? null,
            // 'photos' => $this->when(isset($this['photos']), function () {
            //     return collect($this['photos'])->map(function ($photo) {
            //         // Construct photo URL if needed, requires API key and photo reference
            //         // return 'https_maps.googleapis.com/maps/api/place/photo?maxwidth=400&photoreference=' . $photo['photo_reference'] . '&key=YOUR_API_KEY';
            //         return $photo; // For now, just return the photo object
            //     });
            // }),
        ];
    }
}
