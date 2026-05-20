<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('turnout_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turnout_id')->constrained()->cascadeOnDelete();
            $table->foreignId('node_id')->nullable()->constrained('nodes')->nullOnDelete();
            $table->string('event_type', 32)->default('state');
            $table->string('state', 16);
            $table->string('previous_state', 16)->nullable();
            $table->boolean('channel_a')->default(false);
            $table->boolean('channel_b')->default(false);
            $table->boolean('is_transition')->default(false);
            $table->timestampTz('source_timestamp');
            $table->timestamp('received_at')->useCurrent();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['turnout_id', 'source_timestamp']);
            $table->index(['state', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turnout_events');
    }
};
