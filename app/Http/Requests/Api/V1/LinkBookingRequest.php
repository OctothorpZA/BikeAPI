<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Rental; // To potentially check if booking code exists

class LinkBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * User must be authenticated as a PWA user.
     */
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasRole('PWA User');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'booking_code' => [
                'required',
                'string',
                'min:6', // Adjust min/max based on your booking code format
                'max:10',
                // We'll do a more detailed check in the controller to provide specific feedback
                // Rule::exists('rentals', 'booking_code'),
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'booking_code.required' => 'A booking code is required to link a rental.',
            'booking_code.string' => 'The booking code must be a string.',
            'booking_code.min' => 'The booking code seems too short.',
            'booking_code.max' => 'The booking code seems too long.',
        ];
    }
}
