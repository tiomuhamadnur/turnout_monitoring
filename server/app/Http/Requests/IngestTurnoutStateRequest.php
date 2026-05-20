<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IngestTurnoutStateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'timestamp' => ['required', 'date'],
            'turnout_uuid' => ['nullable', 'uuid'],
            'turnout_code' => ['required_without:turnout_uuid', 'string', 'max:32'],
            'state' => ['required', 'string', 'in:NORMAL,REVERSE,FAILURE'],
            'channel_a' => ['required', 'boolean'],
            'channel_b' => ['required', 'boolean'],
            'node_id' => ['required', 'string', 'max:64'],
        ];
    }
}
