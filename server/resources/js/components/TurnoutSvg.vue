<script setup>
import { computed } from 'vue';

const props = defineProps({
    state:     { type: String, default: 'UNKNOWN' },   // NORMAL | REVERSE | FAILURE | UNKNOWN
    code:      { type: String, default: '' },
    name:      { type: String, default: '' },
    channelA:  { type: Boolean, default: false },
    channelB:  { type: Boolean, default: false },
    compact:   { type: Boolean, default: false },      // mini variant for cards
    showLabel: { type: Boolean, default: true },
});

// Color palette per BLUEPRINT:
//   NORMAL  = green   REVERSE = red (here amber for legibility)   FAILURE = flashing red
// We use bright greens/ambers for state and a dim grey for the inactive branch.
const palette = computed(() => {
    switch (props.state) {
        case 'NORMAL':  return { active: '#10B981', inactive: '#64748b' };
        case 'REVERSE': return { active: '#F59E0B', inactive: '#64748b' };
        case 'FAILURE': return { active: '#EF4444', inactive: '#7f1d1d' };
        default:        return { active: '#94a3b8', inactive: '#475569' };
    }
});

// Which leg of the SVG is "energised" — straight = NORMAL, diverging = REVERSE.
// FAILURE uses both legs in red and the whole graphic flashes.
const normalActive  = computed(() => props.state === 'NORMAL'  || props.state === 'FAILURE');
const reverseActive = computed(() => props.state === 'REVERSE' || props.state === 'FAILURE');

const stateLabel = computed(() => props.state || 'UNKNOWN');
const stateClass = computed(() => `turnout-${(props.state || 'unknown').toLowerCase()}`);
</script>

<template>
    <div class="turnout-svg" :class="[stateClass, { 'is-compact': compact }]">
        <svg viewBox="0 0 160 80" xmlns="http://www.w3.org/2000/svg" role="img"
             :aria-label="`Turnout ${code} ${stateLabel}`">
            <!-- Subtle background plate -->
            <rect x="0" y="0" width="160" height="80" rx="8"
                  fill="currentColor" fill-opacity="0.04" />

            <!-- Common stock rail -->
            <line x1="10" y1="40" x2="60" y2="40"
                  stroke="#94a3b8" stroke-width="6" stroke-linecap="round" />

            <!-- Straight (NORMAL) leg -->
            <line x1="60" y1="40" x2="150" y2="40"
                  :stroke="normalActive ? palette.active : palette.inactive"
                  :stroke-opacity="normalActive ? 1 : 0.45"
                  stroke-width="6" stroke-linecap="round"
                  class="leg leg-normal" />

            <!-- Diverging (REVERSE) leg -->
            <line x1="60" y1="40" x2="150" y2="10"
                  :stroke="reverseActive ? palette.active : palette.inactive"
                  :stroke-opacity="reverseActive ? 1 : 0.45"
                  stroke-width="6" stroke-linecap="round"
                  class="leg leg-reverse" />

            <!-- Pivot point (frog) -->
            <circle cx="60" cy="40" r="6"
                    :fill="palette.active" stroke="#0f172a" stroke-width="1.5" />

            <!-- Channel A / B indicator dots -->
            <g class="channel-dots">
                <circle cx="20" cy="70" r="4"
                        :fill="channelA ? '#10B981' : '#334155'"
                        :stroke="channelA ? '#10B981' : '#475569'" stroke-width="1" />
                <text x="28" y="73" font-size="9" fill="#94a3b8" font-family="ui-monospace, monospace">A</text>
                <circle cx="48" cy="70" r="4"
                        :fill="channelB ? '#F59E0B' : '#334155'"
                        :stroke="channelB ? '#F59E0B' : '#475569'" stroke-width="1" />
                <text x="56" y="73" font-size="9" fill="#94a3b8" font-family="ui-monospace, monospace">B</text>
            </g>
        </svg>
        <div v-if="showLabel" class="turnout-meta">
            <div class="turnout-code">{{ code }}</div>
            <div class="turnout-state-badge" :class="stateClass">{{ stateLabel }}</div>
        </div>
    </div>
</template>

<style scoped>
.turnout-svg {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.35rem;
    padding: 0.5rem;
    border-radius: 0.5rem;
    background: color-mix(in srgb, var(--bs-body-color) 4%, transparent);
    border: 1px solid var(--bs-border-color);
    transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.2s ease;
    color: var(--bs-body-color);
}
.turnout-svg.is-compact {
    padding: 0.25rem;
    gap: 0.15rem;
}
.turnout-svg svg {
    width: 100%;
    height: auto;
    max-width: 160px;
}
.is-compact svg { max-width: 80px; }

.turnout-meta {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.15rem;
    text-align: center;
}
.turnout-code {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 0.78rem;
    font-weight: 600;
    letter-spacing: 0.02em;
}
.is-compact .turnout-code { font-size: 0.65rem; }

.turnout-state-badge {
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    padding: 0.1rem 0.5rem;
    border-radius: 999px;
    text-transform: uppercase;
    background: #334155;
    color: #fff;
}
.turnout-state-badge.turnout-normal  { background: #10B981; }
.turnout-state-badge.turnout-reverse { background: #F59E0B; color: #1f2937; }
.turnout-state-badge.turnout-failure { background: #EF4444; }
.turnout-state-badge.turnout-unknown { background: #475569; }

/* FAILURE: pulse the whole tile + flash the legs (per BLUEPRINT). */
.turnout-svg.turnout-failure {
    background: color-mix(in srgb, #EF4444 14%, transparent);
    border-color: color-mix(in srgb, #EF4444 60%, transparent);
    animation: turnout-flash 1.1s infinite;
}
.turnout-svg.turnout-failure .leg {
    animation: leg-flash 0.55s infinite alternate;
}

@keyframes turnout-flash {
    0%, 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.45); }
    50%      { box-shadow: 0 0 0 6px rgba(239, 68, 68, 0); }
}
@keyframes leg-flash {
    from { stroke-opacity: 1; }
    to   { stroke-opacity: 0.35; }
}

/* Hover lift only when interactive (parent will set cursor). */
.turnout-svg:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 18px -8px rgba(0, 0, 0, 0.25);
}
</style>
