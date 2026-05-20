<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('users.view');

        $users = User::query()
            ->with('roles:id,name')
            ->when($request->string('q')->toString(), function ($q, $term) {
                $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")
                                       ->orWhere('email', 'like', "%{$term}%"));
            })
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return UserResource::collection($users);
    }

    public function show(User $user): UserResource
    {
        $this->authorize('users.view');

        return new UserResource($user->load('roles:id,name'));
    }

    public function store(StoreUserRequest $request): UserResource
    {
        $data = $request->validated();
        $user = User::create([
            'name'              => $data['name'],
            'email'             => $data['email'],
            'password'          => Hash::make($data['password']),
            'is_super_admin'    => $data['is_super_admin'] ?? false,
            'email_verified_at' => now(),
        ]);

        if (! empty($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        return new UserResource($user->load('roles:id,name'));
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $data = $request->validated();

        $user->fill(collect($data)->except(['password', 'roles'])->all());
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();

        if (array_key_exists('roles', $data)) {
            $user->syncRoles($data['roles']);
        }

        return new UserResource($user->load('roles:id,name'));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('users.delete');

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 422);
        }

        $user->delete();

        return response()->json(['status' => 'ok']);
    }
}
