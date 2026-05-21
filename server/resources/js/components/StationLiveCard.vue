<script setup>
import { computed } from 'vue';

const props = defineProps({
    summary: { type: Object, required: true },   // from useRealtimeStore().stationSummary
});

defineEmits(['open']);

const counts = computed(() => props.summary.counts);
</script>

<template>
    <div class="station-live-card"
         :class="{ 'has-failure': summary.hasFailure }"
         role="button" tabindex="0"
         @click="$emit('open', summary)"
         @keydown.enter.space.prevent="$emit('open', summary)">

        <div class="station-card-header">
            <div>
                <div class="station-code">{{ summary.code }}</div>
                <div class="station-name">{{ summary.name }}</div>
            </div>
            <div class="station-count-badge">
                {{ summary.turnoutCount }}
                <span class="small fw-normal opacity-75">turnouts</span>
            </div>
        </div>

        <!-- Per-turnout mini indicator strip. Tiny dot per turnout, color =
             current state. Gives an at-a-glance health view without opening
             the modal. -->
        <div class="indicator-strip" aria-label="Turnout indicators">
            <span v-for="t in summary.turnouts" :key="t.uuid"
                  class="dot"
                  :class="[`dot-${(t.state || 'unknown').toLowerCase()}`,
                           { 'dot-flash': t.state === 'FAILURE' }]"
                  :title="`${t.code}: ${t.state || 'UNKNOWN'}`"></span>
        </div>

        <div class="state-tally">
            <div class="tally tally-normal">
                <span class="tally-dot"></span>
                <span class="tally-num">{{ counts.NORMAL }}</span>
                <span class="tally-label">Normal</span>
            </div>
            <div class="tally tally-reverse">
                <span class="tally-dot"></span>
                <span class="tally-num">{{ counts.REVERSE }}</span>
                <span class="tally-label">Reverse</span>
            </div>
            <div class="tally tally-failure" :class="{ 'is-active': counts.FAILURE > 0 }">
                <span class="tally-dot"></span>
                <span class="tally-num">{{ counts.FAILURE }}</span>
                <span class="tally-label">Failure</span>
            </div>
            <div v-if="counts.UNKNOWN > 0" class="tally tally-unknown">
                <span class="tally-dot"></span>
                <span class="tally-num">{{ counts.UNKNOWN }}</span>
                <span class="tally-label">No data</span>
            </div>
        </div>

        <div class="station-card-footer">
            <span class="hint">
                <i class="bi bi-arrows-fullscreen me-1"></i>
                Click to view live layout
            </span>
        </div>
    </div>
</template>

<style scoped>
.station-live-card {
    background: var(--bs-card-bg, var(--bs-body-bg));
    border: 1px solid var(--bs-border-color);
    border-radius: 0.75rem;
    padding: 1rem 1.1rem 0.85rem;
    cursor: pointer;
    transition: transform 0.15s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    height: 100%;
    display: flex;
    flex-direction: column;
    gap: 0.8rem;
    position: relative;
    overflow: hidden;
}
.station-live-card:hover,
.station-live-card:focus-visible {
    transform: translateY(-2px);
    box-shadow: 0 10px 28px -14px rgba(0, 0, 0, 0.35);
    border-color: color-mix(in srgb, var(--app-accent) 60%, var(--bs-border-color));
    outline: none;
}
.station-live-card.has-failure {
    border-color: #EF4444;
    box-shadow: 0 0 0 1px #EF4444 inset, 0 8px 24px -12px rgba(239, 68, 68, 0.55);
    animation: card-failure-pulse 1.4s infinite;
}
@keyframes card-failure-pulse {
    0%, 100% { box-shadow: 0 0 0 1px #EF4444 inset, 0 0 0 0 rgba(239, 68, 68, 0.5); }
    50%      { box-shadow: 0 0 0 1px #EF4444 inset, 0 0 0 8px rgba(239, 68, 68, 0); }
}

.station-card-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.5rem;
}
.station-code {
    font-size: 1.35rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    line-height: 1;
}
.station-name {
    font-size: 0.78rem;
    color: var(--bs-secondary-color);
    margin-top: 0.15rem;
}
.station-count-badge {
    background: color-mix(in srgb, var(--app-accent) 12%, transparent);
    color: var(--app-accent);
    border-radius: 0.5rem;
    padding: 0.3rem 0.55rem;
    font-weight: 700;
    font-size: 1.1rem;
    line-height: 1;
    display: inline-flex;
    align-items: baseline;
    gap: 0.3rem;
}

.indicator-strip {
    display: flex;
    flex-wrap: wrap;
    gap: 0.32rem;
    padding: 0.4rem 0.55rem;
    background: color-mix(in srgb, var(--bs-body-color) 4%, transparent);
    border-radius: 0.45rem;
}
.dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #475569;
    box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.15) inset;
}
.dot-normal  { background: #10B981; }
.dot-reverse { background: #F59E0B; }
.dot-failure { background: #EF4444; }
.dot-unknown { background: #475569; }
.dot-flash   { animation: dot-flash 0.7s infinite alternate; }
@keyframes dot-flash {
    from { transform: scale(1);   box-shadow: 0 0 0 0   rgba(239, 68, 68, 0.7); }
    to   { transform: scale(1.2); box-shadow: 0 0 0 5px rgba(239, 68, 68, 0); }
}

.state-tally {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.4rem;
}
.tally {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.5rem;
    background: color-mix(in srgb, var(--bs-body-color) 4%, transparent);
    border-radius: 0.4rem;
    font-size: 0.78rem;
}
.tally-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #94a3b8;
    flex-shrink: 0;
}
.tally-num {
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}
.tally-label {
    color: var(--bs-secondary-color);
    font-size: 0.7rem;
}
.tally-normal  .tally-dot { background: #10B981; }
.tally-reverse .tally-dot { background: #F59E0B; }
.tally-failure .tally-dot { background: #EF4444; }
.tally-unknown .tally-dot { background: #475569; }
.tally-failure.is-active {
    background: color-mix(in srgb, #EF4444 14%, transparent);
    color: #EF4444;
}
.tally-failure.is-active .tally-label { color: #EF4444; }

.station-card-footer {
    margin-top: auto;
    display: flex;
    justify-content: flex-end;
}
.hint {
    font-size: 0.7rem;
    color: var(--bs-secondary-color);
}

@media (max-width: 480px) {
    .state-tally { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .station-code { font-size: 1.2rem; }
}
</style>
