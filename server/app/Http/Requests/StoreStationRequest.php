<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('stations.manage');
    }

    public function rules(): array
    {
        return [
            'code'        => ['required', 'string', 'max:16', 'unique:stations,code'],
            'name'        => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'latitude'    => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'   => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
