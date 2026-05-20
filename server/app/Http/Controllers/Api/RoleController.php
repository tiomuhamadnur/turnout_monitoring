<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('roles.view');

        $roles = Role::query()
            ->with('permissions:id,name')
            ->orderBy('name')
            ->paginate($request->integer('per_page', 50));

        // Spatie's Role->users() relationship resolves the user model via
        // getModelForGuard() which returns NULL in some HTTP contexts (root
        // cause unclear; works under tinker). Avoid `withCount('users')` and
        // count straight from the morph pivot — same result, no breakage.
        $this->attachUserCounts($roles->getCollection());

        return RoleResource::collection($roles);
    }

    public function show(Role $role): RoleResource
    {
        $this->authorize('roles.view');

        $role->load('permissions:id,name');
        $this->attachUserCounts(collect([$role]));

        return new RoleResource($role);
    }

    private function attachUserCounts($roles): void
    {
        if ($roles->isEmpty()) {
            return;
        }

        $counts = DB::table(config('permission.table_names.model_has_roles'))
            ->where('model_type', User::class)
            ->whereIn('role_id', $roles->pluck('id'))
            ->select('role_id', DB::raw('count(*) as aggregate'))
            ->groupBy('role_id')
            ->pluck('aggregate', 'role_id');

        $roles->each(function ($role) use ($counts) {
            $role->users_count = (int) ($counts[$role->id] ?? 0);
        });
    }

    public function store(StoreRoleRequest $request): RoleResource
    {
        $data = $request->validated();
        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        if (! empty($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        return new RoleResource($role->load('permissions:id,name'));
    }

    public function update(UpdateRoleRequest $request, Role $role): RoleResource
    {
        $data = $request->validated();

        if (isset($data['name'])) {
            $role->name = $data['name'];
            $role->save();
        }
        if (array_key_exists('permissions', $data)) {
            $role->syncPermissions($data['permissions']);
        }

        return new RoleResource($role->load('permissions:id,name'));
    }

    public function destroy(Role $role): JsonResponse
    {
        $this->authorize('roles.delete');

        // Guard the super-admin role — losing it would lock the system out of
        // emergency access. is_super_admin column still provides Gate bypass
        // independently, but the role exists for assignment UX.
        if ($role->name === 'super-admin') {
            return response()->json(['message' => 'The super-admin role cannot be deleted.'], 422);
        }

        $role->delete();

        return response()->json(['status' => 'ok']);
    }
}
