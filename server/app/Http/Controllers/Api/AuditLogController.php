<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuditLogController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('audit_logs.view');

        $logs = AuditLog::query()
            ->with('user:id,name,email')
            ->when($request->integer('user_id'), fn ($q, $u) => $q->where('user_id', $u))
            ->when($request->string('action')->toString(), fn ($q, $a) => $q->where('action', $a))
            ->when($request->string('auditable_type')->toString(), function ($q, $t) {
                // Accept either short name (Turnout) or FQCN.
                $q->where(fn ($w) => $w->where('auditable_type', $t)
                                       ->orWhere('auditable_type', "App\\Models\\{$t}"));
            })
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 25));

        return AuditLogResource::collection($logs);
    }
}
