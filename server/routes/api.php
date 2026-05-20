<?php

use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        $user = $request->user()->load('roles:id,name');

        return [
            'id'             => $user->id,
            'name'           => $user->name,
            'email'          => $user->email,
            'is_super_admin' => (bool) $user->is_super_admin,
            'roles'          => $user->roles->pluck('name'),
            'permissions'    => $user->getAllPermissions()->pluck('name'),
        ];
    });

    Route::apiResource('users', UserController::class);
    Route::apiResource('roles', RoleController::class);
    Route::get('permissions', [PermissionController::class, 'index']);
});
