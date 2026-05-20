<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IngestHeartbeatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'timestamp' => ['required', 'date'],
            'node_id' => ['required', 'string', 'max:64'],
            'mqtt_status' => ['nullable', 'string', 'in:connected,disconnected,unknown'],
            'status' => ['nullable', 'string', 'in:online,offline,unknown'],
            'ip_address' => ['nullable', 'ip'],
        ];
    }
}
