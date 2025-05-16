<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon; // For date formatting

class PwaUserResource extends JsonResource
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
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at ? Carbon::parse($this->email_verified_at)->toIso8601String() : null,
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toIso8601String() : null,
            'roles' => $this->whenLoaded('roles', $this->getRoleNames()), // Spatie roles
            // Example: Eager load paxProfiles if a PWA user has one primary profile
            'pax_profile' => new PaxProfileResource($this->whenLoaded('paxProfiles', $this->paxProfiles->first())),
            // Add other PWA user-specific, non-sensitive data
        ];
    }
}
