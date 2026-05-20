<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('lines.manage');
    }

    public function rules(): array
    {
        $id = $this->route('line')?->id ?? $this->route('line');

        return [
            'code' => ['sometimes', 'required', 'string', 'max:16', Rule::unique('lines', 'code')->ignore($id)],
            'name' => ['sometimes', 'required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
