<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DeviceHealthLogController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\HistorianController;
use App\Http\Controllers\Api\InternalTelemetryController;
use App\Http\Controllers\Api\NotificationChannelController;
use App\Http\Controllers\Api\ReplayController;
use App\Http\Controllers\Api\LineController;
use App\Http\Controllers\Api\NodeController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\StationController;
use App\Http\Controllers\Api\TurnoutAlarmController;
use App\Http\Controllers\Api\TurnoutController;
use App\Http\Controllers\Api\TurnoutEventController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // Current user + permissions for the SPA.
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

    // Access control (Phase 1).
    Route::apiResource('users', UserController::class);
    Route::apiResource('roles', RoleController::class);
    Route::get('permissions', [PermissionController::class, 'index']);

    // Master data (Phase 2).
    Route::apiResource('stations', StationController::class);
    Route::apiResource('lines',    LineController::class);
    Route::apiResource('nodes',    NodeController::class);
    Route::apiResource('turnouts', TurnoutController::class);
    Route::get('turnouts/{turnout}/photo', [TurnoutController::class, 'photo'])->name('turnouts.photo.show');
    Route::post('turnouts/{turnout}/photo', [TurnoutController::class, 'uploadPhoto']);

    // Audit trail (Phase 2).
    Route::get('audit-logs', [AuditLogController::class, 'index']);
    Route::get('turnout-events', [TurnoutEventController::class, 'index']);
    Route::get('turnout-alarms', [TurnoutAlarmController::class, 'index']);
    Route::get('device-health-logs', [DeviceHealthLogController::class, 'index']);

    // Realtime dashboard snapshot (Phase 4).
    Route::get('dashboard/live', [DashboardController::class, 'live']);

    // Historian aggregates (Phase 5).
    Route::get('historian/state-duration', [HistorianController::class, 'stateDuration']);
    Route::get('historian/communication',  [HistorianController::class, 'communication']);
    Route::get('historian/alarm-summary',  [HistorianController::class, 'alarmSummary']);

    // Replay engine (Phase 6).
    Route::get('replay/stations', [ReplayController::class, 'stations']);
    Route::get('replay/timeline', [ReplayController::class, 'timeline']);

    // Exports (Phase 7).
    Route::get('exports/turnout-events.xlsx',  [ExportController::class, 'turnoutEventsExcel']);
    Route::get('exports/turnout-events.pdf',   [ExportController::class, 'turnoutEventsPdf']);
    Route::get('exports/turnout-alarms.xlsx',  [ExportController::class, 'turnoutAlarmsExcel']);
    Route::get('exports/turnout-alarms.pdf',   [ExportController::class, 'turnoutAlarmsPdf']);
    Route::get('exports/device-health.xlsx',   [ExportController::class, 'deviceHealthExcel']);
    Route::get('exports/device-health.pdf',    [ExportController::class, 'deviceHealthPdf']);

    // Notifications (Phase 8).
    Route::apiResource('notification-channels', NotificationChannelController::class)
        ->parameters(['notification-channels' => 'notificationChannel']);
    Route::post('notification-channels/{notificationChannel}/test', [NotificationChannelController::class, 'test']);
    Route::get('notification-logs', [NotificationChannelController::class, 'logs']);
});

Route::prefix('internal/telemetry')->group(function () {
    Route::post('state', [InternalTelemetryController::class, 'ingestState']);
    Route::post('heartbeat', [InternalTelemetryController::class, 'ingestHeartbeat']);
    Route::post('health', [InternalTelemetryController::class, 'ingestHealth']);
});
