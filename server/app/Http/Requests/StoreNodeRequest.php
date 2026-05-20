<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreNodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('nodes.manage');
    }

    public function rules(): array
    {
        return [
            'station_id'     => ['required', 'integer', 'exists:stations,id'],
            'node_id'        => ['required', 'string', 'max:64', 'unique:nodes,node_id'],
            'name'           => ['required', 'string', 'max:160'],
            'ip_address'     => ['nullable', 'ip'],
            'mqtt_client_id' => ['nullable', 'string', 'max:128'],
            'status'         => ['nullable', 'string', 'in:online,offline,unknown'],
            'metadata'       => ['nullable', 'array'],
        ];
    }
}
