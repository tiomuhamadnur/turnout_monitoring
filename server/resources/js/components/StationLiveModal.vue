<script setup>
import { computed } from 'vue';
import BaseModal from '@/components/BaseModal.vue';
import TurnoutSvg from '@/components/TurnoutSvg.vue';
import { formatTimestamp } from '@/utils/date';

const props = defineProps({
    show:    { type: Boolean, default: false },
    station: { type: Object,  default: null },   // { code, name, turnouts: [...] } | null
});
defineEmits(['close']);

const counts = computed(() => {
    const c = { NORMAL: 0, REVERSE: 0, FAILURE: 0, UNKNOWN: 0 };
    if (!props.station) return c;
    for (const t of props.station.turnouts) {
        const k = t.state && c[t.state] !== undefined ? t.state : 'UNKNOWN';
        c[k]++;
    }
    return c;
});

const hasFailure = computed(() => counts.value.FAILURE > 0);
</script>

<template>
    <BaseModal :show="show" size="xl" @close="$emit('close')">
        <template #header>
            <div class="d-flex align-items-center gap-3">
                <div class="station-modal-badge"
                     :class="{ 'has-failure': hasFailure }">
                    {{ station?.code || '—' }}
                </div>
                <div>
                    <div class="fw-bold">{{ station?.name || 'Station' }}</div>
                    <div class="small text-body-secondary">
                        {{ station?.turnouts?.length || 0 }} turnouts ·
                        Realtime via Reverb
                    </div>
                </div>
            </div>
        </template>

        <div v-if="station">
            <!-- State tally summary -->
            <div class="modal-tally mb-3">
                <div class="tally-pill tally-normal">
                    <span class="tally-dot"></span> NORMAL
                    <span class="tally-num">{{ counts.NORMAL }}</span>
                </div>
                <div class="tally-pill tally-reverse">
                    <span class="tally-dot"></span> REVERSE
                    <span class="tally-num">{{ counts.REVERSE }}</span>
                </div>
                <div class="tally-pill tally-failure" :class="{ 'is-active': counts.FAILURE > 0 }">
                    <span class="tally-dot"></span> FAILURE
                    <span class="tally-num">{{ counts.FAILURE }}</span>
                </div>
                <div v-if="counts.UNKNOWN > 0" class="tally-pill tally-unknown">
                    <span class="tally-dot"></span> NO DATA
                    <span class="tally-num">{{ counts.UNKNOWN }}</span>
                </div>
            </div>

            <!-- Turnout SVG grid -->
            <div class="turnout-grid">
                <div v-for="t in station.turnouts" :key="t.uuid" class="turnout-tile">
                    <TurnoutSvg :state="t.state"
                                :code="t.code"
                                :name="t.name"
                                :channel-a="t.channel_a"
                                :channel-b="t.channel_b" />
                    <div class="turnout-meta-extra small text-body-secondary text-center mt-1">
                        <div v-if="t.node_id">Node: <span class="fw-semibold">{{ t.node_id }}</span></div>
                        <div v-if="t.source_timestamp">
                            <i class="bi bi-clock"></i>
                            {{ formatTimestamp(t.source_timestamp) }}
                        </div>
                        <div v-if="t.has_active_alarm" class="text-danger fw-semibold mt-1">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>
                            Active alarm since {{ formatTimestamp(t.alarm_started_at) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div v-else class="text-center text-body-secondary py-4">
            No station selected.
        </div>

        <template #footer>
            <button class="btn btn-secondary" @click="$emit('close')">Close</button>
        </template>
    </BaseModal>
</template>

<style scoped>
.station-modal-badge {
    background: color-mix(in srgb, var(--app-accent) 18%, transparent);
    color: var(--app-accent);
    border: 2px solid color-mix(in srgb, var(--app-accent) 50%, transparent);
    width: 56px;
    height: 56px;
    border-radius: 0.65rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 1.2rem;
    letter-spacing: 0.05em;
}
.station-modal-badge.has-failure {
    background: color-mix(in srgb, #EF4444 18%, transparent);
    color: #EF4444;
    border-color: #EF4444;
}

.modal-tally {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}
.tally-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.3rem 0.7rem;
    border-radius: 999px;
    background: color-mix(in srgb, var(--bs-body-color) 5%, transparent);
    font-size: 0.78rem;
    font-weight: 600;
    letter-spacing: 0.05em;
}
.tally-pill .tally-num {
    background: rgba(0, 0, 0, 0.1);
    padding: 0.05rem 0.45rem;
    border-radius: 999px;
    font-variant-numeric: tabular-nums;
    margin-left: 0.15rem;
}
.tally-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #94a3b8;
}
.tally-normal  .tally-dot { background: #10B981; }
.tally-reverse .tally-dot { background: #F59E0B; }
.tally-failure .tally-dot { background: #EF4444; }
.tally-unknown .tally-dot { background: #475569; }
.tally-failure.is-active {
    background: color-mix(in srgb, #EF4444 14%, transparent);
    color: #EF4444;
}

.turnout-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 0.85rem;
}
.turnout-tile {
    display: flex;
    flex-direction: column;
}

@media (max-width: 576px) {
    .turnout-grid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); }
}
</style>
