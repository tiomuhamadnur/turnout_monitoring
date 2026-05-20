<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('lines.manage');
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:16', 'unique:lines,code'],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
