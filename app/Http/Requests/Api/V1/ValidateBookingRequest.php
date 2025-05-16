<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidateBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * This is a public endpoint for initial validation, so always true.
     * Authorization for further actions will be based on the booking's validity.
     */
    public function authorize(): bool
    {
        return true;
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
                'min:6', // Assuming booking codes have a certain length
                'max:10',
                // Optional: Add a regex if your booking codes have a specific format
                // Rule::exists('rentals', 'booking_code'), // We'll do a custom check in the controller
                                                            // to provide more specific responses and handle
                                                            // user creation/linking logic.
            ],
            // You might also expect a device_name for Sanctum token issuance if needed
            // 'device_name' => ['sometimes', 'string', 'max:255'],
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
            'booking_code.required' => 'A booking code is required.',
            'booking_code.string' => 'The booking code must be a string.',
            'booking_code.min' => 'The booking code must be at least :min characters.',
            'booking_code.max' => 'The booking code must not exceed :max characters.',
        ];
    }
}
