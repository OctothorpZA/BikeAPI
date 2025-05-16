<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BikePwaPublicResource extends JsonResource
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
            'bike_identifier' => $this->bike_identifier,
            'nickname' => $this->nickname,
            'type' => $this->type,
            // Add other fields you want to expose for the bike in the PWA context
            // 'status' => $this->status, // Maybe not public for all PWA views
        ];
    }
}
