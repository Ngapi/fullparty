<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        $hasPassword = filled($this->user()?->getAuthPassword());

        return [
            'current_password' => [
                Rule::requiredIf($hasPassword),
                'nullable',
                'string',
                ...($hasPassword ? ['current_password'] : []),
            ],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}
