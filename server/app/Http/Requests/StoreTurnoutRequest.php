<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTurnoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('turnouts.manage');
    }

    public function rules(): array
    {
        return [
            'station_id'   => ['required', 'integer', 'exists:stations,id'],
            'code'         => ['required', 'string', 'max:32', 'unique:turnouts,code'],
            'name'         => ['required', 'string', 'max:160'],
            'description'  => ['nullable', 'string', 'max:2000'],
            'type'         => ['nullable', 'string', 'in:1:10,1:8'],
            'direction'    => ['nullable', 'string', 'in:Right,Left'],
            'line_id'      => ['nullable', 'integer', 'exists:lines,id'],
            'chainage'     => ['nullable', 'numeric', 'min:0'],
            'latitude'     => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'    => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
