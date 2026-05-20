<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('turnout_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turnout_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('node_id')->nullable()->constrained('nodes')->nullOnDelete();
            $table->string('state', 16);
            $table->boolean('channel_a')->default(false);
            $table->boolean('channel_b')->default(false);
            $table->timestampTz('source_timestamp');
            $table->timestamp('received_at')->useCurrent();
            $table->timestamps();

            $table->index(['state', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turnout_states');
    }
};
