<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepotPublicResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // This resource is for PointOfInterest models that are Depots
        return [
            'id' => $this->id, // POI ID
            'name' => $this->name,
            'latitude' => (float) $this->latitude, // Ensure float for JSON
            'longitude' => (float) $this->longitude, // Ensure float for JSON
            'address_line_1' => $this->address_line_1,
            'city' => $this->city,
            // Add any other publicly relevant fields from the PointOfInterest model
            // e.g., 'postal_code', 'description' (if short and public-friendly)
            // 'team_id' => $this->team_id, // The actual Team ID this POI represents
        ];
    }
}
