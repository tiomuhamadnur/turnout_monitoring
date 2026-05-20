<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('users.create');
    }

    public function rules(): array
    {
        return [
            'name'           => ['required', 'string', 'max:120'],
            'email'          => ['required', 'email', 'max:160', 'unique:users,email'],
            'password'       => ['required', 'string', Password::min(8)],
            'is_super_admin' => ['sometimes', 'boolean'],
            'roles'          => ['sometimes', 'array'],
            'roles.*'        => ['string', 'exists:roles,name'],
        ];
    }
}
