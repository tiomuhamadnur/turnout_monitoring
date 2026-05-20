<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->string('mqtt_status', 16)->default('unknown')->after('status');
            $table->timestamp('last_health_at')->nullable()->after('last_heartbeat_at');
        });
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->dropColumn(['mqtt_status', 'last_health_at']);
        });
    }
};
