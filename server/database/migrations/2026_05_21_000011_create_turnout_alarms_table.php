<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('turnout_alarms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turnout_id')->constrained()->cascadeOnDelete();
            $table->foreignId('node_id')->nullable()->constrained('nodes')->nullOnDelete();
            $table->string('alarm_type', 32)->default('failure');
            $table->string('state', 16)->default('FAILURE');
            $table->boolean('is_active')->default(true);
            $table->timestampTz('started_at');
            $table->timestampTz('ended_at')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['turnout_id', 'is_active']);
            $table->index(['is_active', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turnout_alarms');
    }
};
