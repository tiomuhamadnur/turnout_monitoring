<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-channel notification config rows. Each row is one delivery target
 * (one webhook URL, one email list, one WhatsApp number/token bundle).
 * Operators can add/disable/test channels independently in the Settings
 * UI without touching .env.
 *
 *   type      = 'webhook' | 'email' | 'whatsapp'   (extensible)
 *   config    = channel-specific JSON, validated at delivery time
 *   triggers  = JSON list of event names the channel cares about
 *               (e.g. ["alarm.raised", "alarm.cleared"])
 *
 * notification_logs records each delivery attempt for the operator's
 * troubleshooting view.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('notification_channels', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32);
            $table->string('name');
            $table->boolean('is_enabled')->default(true);
            $table->json('config');
            $table->json('triggers')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'is_enabled']);
        });

        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->nullable()->constrained('notification_channels')->nullOnDelete();
            $table->string('event', 64);
            $table->string('status', 16);  // 'sent' | 'failed' | 'skipped'
            $table->string('summary')->nullable();
            $table->json('payload')->nullable();
            $table->json('response')->nullable();
            $table->timestamp('sent_at')->useCurrent();
            $table->timestamps();

            $table->index(['event', 'sent_at']);
            $table->index(['status', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('notification_channels');
    }
};
