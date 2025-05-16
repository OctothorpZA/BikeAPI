<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password; // For password rules

class PwaLoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * This is a public login endpoint.
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
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'], // Consider Password::defaults() if you have specific rules
            'device_name' => ['required', 'string', 'max:255'], // Device name for Sanctum token
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
            'email.required' => 'Email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'password.required' => 'Password is required.',
            'device_name.required' => 'Device name is required for token generation.',
        ];
    }
}
