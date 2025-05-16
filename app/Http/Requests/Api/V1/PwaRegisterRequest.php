<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use App\Models\User; // To check for email uniqueness, excluding the current user
use Illuminate\Validation\Rule; // Import Rule

class PwaRegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * The user must be authenticated via Sanctum (e.g., with a token from validate-booking).
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userIdToIgnore = $this->user() ? $this->user()->id : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($userIdToIgnore),
            ],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            // 'device_name' => ['sometimes', 'string', 'max:255'], // Optional if a new token is issued
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
            'name.required' => 'Your name is required.',
            'email.required' => 'Email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'This email address is already taken by another account.',
            'password.required' => 'A password is required.',
            'password.confirmed' => 'Password confirmation does not match.',
        ];
    }
}
