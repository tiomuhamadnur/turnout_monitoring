<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Drop into any model whose CRUD actions should land in `audit_logs`.
 * Skips entries when there is no authenticated user (e.g. seeders, console
 * commands) so the seed run doesn't dump noise.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created (fn (Model $m) => static::writeAuditEntry($m, 'created'));
        static::updated (fn (Model $m) => static::writeAuditEntry($m, 'updated'));
        static::deleted (fn (Model $m) => static::writeAuditEntry($m, 'deleted'));
    }

    protected static function writeAuditEntry(Model $model, string $action): void
    {
        $request = request();
        $userId  = $request?->user()?->id;

        // Skip non-HTTP context (seeders/console). The audit log is meant to
        // capture human actions; framework-driven changes go elsewhere.
        if ($userId === null) {
            return;
        }

        $changes = match ($action) {
            'updated' => [
                'before' => array_intersect_key($model->getOriginal(), $model->getChanges()),
                'after'  => $model->getChanges(),
            ],
            'created' => ['after'  => static::sanitize($model->getAttributes())],
            'deleted' => ['before' => static::sanitize($model->getOriginal())],
            default   => null,
        };

        AuditLog::create([
            'user_id'        => $userId,
            'action'         => $action,
            'auditable_type' => $model::class,
            'auditable_id'   => $model->getKey(),
            'changes'        => $changes,
            'ip_address'     => $request?->ip(),
            'user_agent'     => substr((string) $request?->userAgent(), 0, 255) ?: null,
            'created_at'     => now(),
        ]);
    }

    /**
     * Drop fields that should never make it into the audit trail.
     */
    protected static function sanitize(array $attributes): array
    {
        return collect($attributes)
            ->except(['password', 'remember_token'])
            ->all();
    }
}
