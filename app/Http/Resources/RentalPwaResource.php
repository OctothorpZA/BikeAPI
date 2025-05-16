<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class RentalPwaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_code' => $this->booking_code,
            'status' => $this->status,
            'start_time' => $this->start_time ? Carbon::parse($this->start_time)->toIso8601String() : null,
            'expected_end_time' => $this->expected_end_time ? Carbon::parse($this->expected_end_time)->toIso8601String() : null,
            'end_time' => $this->end_time ? Carbon::parse($this->end_time)->toIso8601String() : null,
            'bike' => new BikePwaPublicResource($this->whenLoaded('bike')), // Assuming you'll create BikePwaPublicResource
            'pax_profile' => [ // Only include minimal, non-sensitive info if needed
                'first_name' => $this->whenLoaded('paxProfile', $this->paxProfile?->first_name),
            ],
            'start_depot' => new DepotPublicResource($this->whenLoaded('startTeamAsPoi')), // Assumes startTeamAsPoi relation
            'end_depot' => new DepotPublicResource($this->whenLoaded('endTeamAsPoi')),     // Assumes endTeamAsPoi relation
            'ship_departure' => $this->whenLoaded('shipDeparture', function () {
                return [
                    'ship_name' => $this->shipDeparture?->ship_name,
                    'departure_datetime' => $this->shipDeparture?->departure_datetime ? Carbon::parse($this->shipDeparture->departure_datetime)->toIso8601String() : null,
                    'final_boarding_datetime' => $this->shipDeparture?->final_boarding_datetime ? Carbon::parse($this->shipDeparture->final_boarding_datetime)->toIso8601String() : null,
                ];
            }),
            // Add any other relevant fields for the PWA user
            // 'rental_price' => $this->rental_price, // Maybe not for initial validation
            // 'payment_status' => $this->payment_status,
        ];
    }
}
