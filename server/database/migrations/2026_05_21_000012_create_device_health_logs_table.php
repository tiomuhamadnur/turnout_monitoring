<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('device_health_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('node_id')->constrained('nodes')->cascadeOnDelete();
            $table->decimal('cpu_usage', 5, 2)->nullable();
            $table->decimal('ram_usage', 5, 2)->nullable();
            $table->decimal('disk_usage', 5, 2)->nullable();
            $table->unsignedBigInteger('uptime_seconds')->nullable();
            $table->string('mqtt_status', 16)->default('unknown');
            $table->json('container_health')->nullable();
            $table->timestampTz('source_timestamp');
            $table->timestamp('received_at')->useCurrent();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['node_id', 'source_timestamp']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_health_logs');
    }
};
