<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IngestDeviceHealthRequest extends FormRequest
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
            'cpu_usage' => ['nullable', 'numeric', 'between:0,100'],
            'ram_usage' => ['nullable', 'numeric', 'between:0,100'],
            'disk_usage' => ['nullable', 'numeric', 'between:0,100'],
            'uptime_seconds' => ['nullable', 'integer', 'min:0'],
            'mqtt_status' => ['nullable', 'string', 'in:connected,disconnected,unknown'],
            'container_health' => ['nullable', 'array'],
        ];
    }
}
