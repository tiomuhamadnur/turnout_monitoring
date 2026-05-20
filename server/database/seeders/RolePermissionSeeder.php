<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Permissions are flat strings of the form "<resource>.<action>".
     * Future modules can register their own without touching this list.
     */
    private const PERMISSIONS = [
        // User & access control (Phase 1)
        'users.view', 'users.create', 'users.update', 'users.delete',
        'roles.view', 'roles.create', 'roles.update', 'roles.delete',
        'permissions.view',

        // Master data (Phase 2)
        'turnouts.view', 'turnouts.manage',
        'stations.view', 'stations.manage',
        'nodes.view',    'nodes.manage',

        // Runtime / historian (Phases 3-6)
        'alarms.view',
        'replay.view',
        'exports.use',

        // Settings & notifications (Phases 7-8)
        'notifications.manage',
        'settings.manage',
    ];

    /**
     * Default role -> permission map. super-admin has Gate::before bypass so
     * it's not enumerated here; the role still exists for assignment UX.
     */
    private const ROLE_MAP = [
        'admin' => [
            'users.view', 'users.create', 'users.update', 'users.delete',
            'roles.view', 'roles.create', 'roles.update', 'roles.delete',
            'permissions.view',
            'turnouts.view', 'turnouts.manage',
            'stations.view', 'stations.manage',
            'nodes.view',    'nodes.manage',
            'alarms.view', 'replay.view', 'exports.use',
            'notifications.manage', 'settings.manage',
        ],
        'operator' => [
            'turnouts.view', 'stations.view', 'nodes.view',
            'alarms.view', 'replay.view', 'exports.use',
        ],
        'viewer' => [
            'turnouts.view', 'stations.view', 'nodes.view', 'alarms.view',
        ],
    ];

    public function run(): void
    {
        Artisan::call('cache:forget', ['key' => 'spatie.permission.cache']);

        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // super-admin role exists for UX even though its permissions are
        // satisfied by the Gate::before bypass.
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

        foreach (self::ROLE_MAP as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($permissions);
        }
    }
}
