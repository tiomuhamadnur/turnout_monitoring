<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('nodes.manage');
    }

    public function rules(): array
    {
        $id = $this->route('node')?->id ?? $this->route('node');

        return [
            'station_id'     => ['sometimes', 'required', 'integer', 'exists:stations,id'],
            'node_id'        => ['sometimes', 'required', 'string', 'max:64', Rule::unique('nodes', 'node_id')->ignore($id)],
            'name'           => ['sometimes', 'required', 'string', 'max:160'],
            'ip_address'     => ['nullable', 'ip'],
            'mqtt_client_id' => ['nullable', 'string', 'max:128'],
            'status'         => ['nullable', 'string', 'in:online,offline,unknown'],
            'metadata'       => ['nullable', 'array'],
        ];
    }
}
