<?php

namespace App\Providers;

use App\Listeners\SendAlarmNotifications;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Super-admin bypass: any Gate / $user->can() check returns true for
        // users flagged is_super_admin. Returning null lets ordinary policies
        // and Spatie permission checks continue for everyone else.
        Gate::before(function (User $user) {
            return $user->is_super_admin ? true : null;
        });

        // Phase 8: wire the alarm-broadcast events to the notification fan-out.
        Event::subscribe(SendAlarmNotifications::class);
    }
}
