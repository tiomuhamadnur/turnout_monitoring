<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Turnout telemetry channels. Authenticated SPA users subscribe to the
| station-scoped private channels listed below; Reverb authorises them via
| the Sanctum-protected /broadcasting/auth endpoint.
|
*/

Broadcast::channel('turnouts.station.{stationCode}', function ($user, string $stationCode) {
    // Any authenticated user may observe turnout telemetry for any station.
    // Permission-scoped subscriptions can be added later without changing the
    // channel name (return $user->can("stations.view.$stationCode") etc.).
    return (bool) $user;
});

Broadcast::channel('turnouts.global', function ($user) {
    return (bool) $user;
});
