<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationChannel;
use App\Models\NotificationLog;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * CRUD + test endpoint for notification channels. The schema is JSON
 * (the `config` blob varies per driver), but we still validate the
 * shape per type to give operators useful errors instead of silent
 * 'skipped' delivery rows.
 */
class NotificationChannelController extends Controller
{
    public function __construct(private readonly NotificationDispatcher $dispatcher) {}

    public function index(Request $request): array
    {
        $this->authorize('notifications.view');

        return [
            'data' => NotificationChannel::query()
                ->orderBy('type')
                ->orderBy('name')
                ->get()
                ->map(fn ($c) => $this->serialize($c))
                ->values()
                ->all(),
        ];
    }

    public function store(Request $request): array
    {
        $this->authorize('notifications.manage');
        $data = $this->validatePayload($request);

        $channel = NotificationChannel::create($data);
        return ['data' => $this->serialize($channel)];
    }

    public function update(Request $request, NotificationChannel $notificationChannel): array
    {
        $this->authorize('notifications.manage');
        $data = $this->validatePayload($request, $notificationChannel);

        $notificationChannel->update($data);
        return ['data' => $this->serialize($notificationChannel->fresh())];
    }

    public function destroy(NotificationChannel $notificationChannel): array
    {
        $this->authorize('notifications.manage');
        $notificationChannel->delete();
        return ['data' => ['deleted' => true]];
    }

    public function test(NotificationChannel $notificationChannel): array
    {
        $this->authorize('notifications.manage');
        $log = $this->dispatcher->testChannel($notificationChannel);

        return [
            'data' => [
                'status'  => $log->status,
                'summary' => $log->summary,
                'sent_at' => $log->sent_at?->toIso8601String(),
            ],
        ];
    }

    public function logs(Request $request): array
    {
        $this->authorize('notifications.view');

        $logs = NotificationLog::query()
            ->with('channel:id,name,type')
            ->when($request->integer('channel_id'),     fn ($q, $id) => $q->where('channel_id', $id))
            ->when($request->string('event')->toString(),  fn ($q, $e) => $q->where('event', $e))
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('sent_at')
            ->paginate($request->integer('per_page', 25));

        return $logs->toArray();
    }

    private function validatePayload(Request $request, ?NotificationChannel $existing = null): array
    {
        $rules = [
            'type'        => ['required', Rule::in(['webhook', 'email', 'whatsapp'])],
            'name'        => ['required', 'string', 'max:120'],
            'is_enabled'  => ['boolean'],
            'config'      => ['required', 'array'],
            'triggers'    => ['nullable', 'array'],
            'triggers.*'  => ['string'],
        ];

        // Per-type config rules.
        $type = $request->input('type', $existing?->type);
        if ($type === 'webhook') {
            $rules['config.url']     = ['required', 'url'];
            $rules['config.method']  = ['nullable', 'string', Rule::in(['POST','PUT','PATCH','GET'])];
            $rules['config.headers'] = ['nullable', 'array'];
            $rules['config.timeout'] = ['nullable', 'integer', 'min:1', 'max:30'];
        } elseif ($type === 'email') {
            $rules['config.recipients']     = ['required', 'array', 'min:1'];
            $rules['config.recipients.*']   = ['email'];
            $rules['config.subject_prefix'] = ['nullable', 'string', 'max:80'];
        } elseif ($type === 'whatsapp') {
            $rules['config.provider_url'] = ['required', 'url'];
            $rules['config.auth_header']  = ['nullable', 'string'];
            $rules['config.to']           = ['required', 'array', 'min:1'];
            $rules['config.to.*']         = ['string'];
            $rules['config.field_to']     = ['nullable', 'string'];
            $rules['config.field_text']   = ['nullable', 'string'];
        }

        return $request->validate($rules);
    }

    private function serialize(NotificationChannel $c): array
    {
        return [
            'id'           => $c->id,
            'type'         => $c->type,
            'name'         => $c->name,
            'is_enabled'   => (bool) $c->is_enabled,
            'config'       => $c->config,
            'triggers'     => $c->triggers ?: [],
            'last_sent_at' => $c->last_sent_at?->toIso8601String(),
            'created_at'   => $c->created_at?->toIso8601String(),
            'updated_at'   => $c->updated_at?->toIso8601String(),
        ];
    }
}
