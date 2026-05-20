<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('permissions.view');

        // Permissions are seeded as a fixed flat list. We expose them grouped
        // by the prefix before the dot (users / roles / turnouts / ...) to
        // simplify the role-edit checkbox grid in the UI.
        $grouped = Permission::orderBy('name')->pluck('name')
            ->groupBy(fn ($name) => explode('.', $name)[0] ?? 'misc');

        return response()->json(['data' => $grouped]);
    }
}
