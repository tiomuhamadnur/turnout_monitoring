<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('users.update');
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id ?? $this->route('user');

        return [
            'name'           => ['sometimes', 'required', 'string', 'max:120'],
            'email'          => [
                'sometimes', 'required', 'email', 'max:160',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            // Password optional on update; empty string is treated as "no change".
            'password'       => ['nullable', 'string', Password::min(8)],
            'is_super_admin' => ['sometimes', 'boolean'],
            'roles'          => ['sometimes', 'array'],
            'roles.*'        => ['string', 'exists:roles,name'],
        ];
    }
}
