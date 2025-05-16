<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class PaxProfileResource extends JsonResource
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
            // 'user_id' => $this->user_id, // ID of the linked PWA User account, usually not needed if user is parent resource
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone_number' => $this->phone_number,
            'country_of_residence' => $this->country_of_residence,
            // 'passport_number' => $this->passport_number, // Generally not for public API display
            'date_of_birth' => $this->date_of_birth ? Carbon::parse($this->date_of_birth)->toDateString() : null,
            'notes' => $this->notes,
            // 'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toIso8601String() : null,
            // 'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toIso8601String() : null,
        ];
    }
}
