<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->constrained()->cascadeOnDelete();
            $table->string('node_id', 64)->unique();   // e.g. LBB-NODE-01
            $table->string('name', 160);
            $table->string('ip_address', 45)->nullable();
            $table->string('mqtt_client_id', 128)->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->string('status', 16)->default('unknown');  // online / offline / unknown
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nodes');
    }
};
