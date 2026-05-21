<script setup>
import { useRealtimeStore } from '@/stores/realtime';
import { formatTimestamp } from '@/utils/date';

const rt = useRealtimeStore();
</script>

<template>
    <Teleport to="body">
        <div class="alarm-stack" v-if="rt.alarms.length > 0" role="alert" aria-live="assertive">
            <div class="alarm-stack-header">
                <div class="d-flex align-items-center gap-2">
                    <span class="alarm-pulse"></span>
                    <span class="fw-bold text-uppercase small">
                        Turnout Alarm ({{ rt.alarms.length }})
                    </span>
                </div>
                <button v-if="rt.alarms.length > 1"
                        class="btn btn-sm btn-outline-light"
                        @click="rt.dismissAllAlarms()">
                    Dismiss all
                </button>
            </div>

            <transition-group name="alarm-pop" tag="div" class="alarm-list">
                <div v-for="a in rt.alarms" :key="a.id" class="alarm-card">
                    <div class="alarm-icon">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                    </div>
                    <div class="alarm-body">
                        <div class="alarm-title">
                            <strong>{{ a.turnout_code }}</strong>
                            <span class="text-secondary"> · {{ a.station_code }}</span>
                            <span v-if="a.seeded" class="badge text-bg-secondary ms-2 small">existing</span>
                        </div>
                        <div class="alarm-sub">
                            {{ a.turnout_name || 'Turnout' }} ·
                            <span class="text-danger fw-semibold">FAILURE</span>
                        </div>
                        <div class="alarm-time">
                            <i class="bi bi-clock me-1"></i>
                            since {{ formatTimestamp(a.started_at) }}
                        </div>
                    </div>
                    <button class="btn btn-sm btn-link text-decoration-none p-1"
                            aria-label="Dismiss"
                            @click="rt.dismissAlarm(a.id)">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </transition-group>
        </div>
    </Teleport>
</template>

<style scoped>
.alarm-stack {
    position: fixed;
    top: 1rem;
    right: 1rem;
    z-index: 1080;
    width: min(380px, calc(100vw - 2rem));
    background: #1f2937;
    color: #fff;
    border-radius: 0.65rem;
    box-shadow: 0 18px 42px -16px rgba(0, 0, 0, 0.6),
                0 0 0 1px rgba(239, 68, 68, 0.6);
    overflow: hidden;
    animation: stack-pulse 1.6s infinite;
}
@keyframes stack-pulse {
    0%, 100% { box-shadow: 0 18px 42px -16px rgba(0, 0, 0, 0.6), 0 0 0 1px rgba(239, 68, 68, 0.6); }
    50%      { box-shadow: 0 18px 42px -16px rgba(0, 0, 0, 0.6), 0 0 0 4px rgba(239, 68, 68, 0.15); }
}
.alarm-stack-header {
    background: linear-gradient(90deg, #dc2626, #991b1b);
    padding: 0.55rem 0.85rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}
.alarm-pulse {
    width: 10px;
    height: 10px;
    background: #fff;
    border-radius: 50%;
    animation: pulse-dot 1s infinite;
}
@keyframes pulse-dot {
    0%, 100% { opacity: 1;   transform: scale(1); }
    50%      { opacity: 0.5; transform: scale(1.3); }
}

.alarm-list {
    max-height: 60vh;
    overflow-y: auto;
}
.alarm-card {
    display: flex;
    align-items: flex-start;
    gap: 0.6rem;
    padding: 0.7rem 0.85rem;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
}
.alarm-card:first-child { border-top: none; }

.alarm-icon {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: rgba(239, 68, 68, 0.18);
    color: #fca5a5;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.alarm-body { flex: 1; min-width: 0; }
.alarm-title {
    font-size: 0.95rem;
    line-height: 1.2;
}
.alarm-sub {
    font-size: 0.78rem;
    color: rgba(255, 255, 255, 0.75);
    margin-top: 0.1rem;
}
.alarm-time {
    font-size: 0.72rem;
    color: rgba(255, 255, 255, 0.55);
    margin-top: 0.2rem;
}
.btn-link {
    color: rgba(255, 255, 255, 0.7) !important;
}
.btn-link:hover { color: #fff !important; }

.alarm-pop-enter-active,
.alarm-pop-leave-active { transition: all 0.25s ease; }
.alarm-pop-enter-from   { opacity: 0; transform: translateX(20px); }
.alarm-pop-leave-to     { opacity: 0; transform: translateX(20px); }
</style>
