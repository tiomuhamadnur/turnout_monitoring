<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import replayApi from '@/services/replay';
import TurnoutSvg from '@/components/TurnoutSvg.vue';
import { format, subHours } from 'date-fns';
import { formatTimestamp } from '@/utils/date';

/**
 * Historical replay UI.
 *
 *   1. Pick a station + time window.
 *   2. We fetch the seed state at `from` plus the full event stream.
 *   3. The scrubber drives `playheadIndex` — we fold events 0..playheadIndex
 *      onto the seed to compute the displayed state for each turnout.
 *   4. Play/pause walks the index forward at the chosen speed multiplier.
 *
 * Speed is "events per second" rather than wall-clock — much more useful
 * for sparse industrial data where a real-time replay would mostly be
 * watching nothing happen.
 */

const stations  = ref([]);
const loading   = ref(false);
const error     = ref(null);
const data      = ref(null);   // last timeline response

const filters = reactive({
    station_id: '',
    // Default: last 6h.
    from: format(subHours(new Date(), 6), "yyyy-MM-dd'T'HH:mm"),
    to:   format(new Date(),               "yyyy-MM-dd'T'HH:mm"),
});

// Playback state.
const playheadIndex = ref(0);    // 0..events.length
const playing       = ref(false);
const speed         = ref(4);    // events per second
let playTimer = null;

const events = computed(() => data.value?.events ?? []);
const turnouts = computed(() => data.value?.turnouts ?? []);
const seed = computed(() => data.value?.seed ?? []);

// Fold seed + events[0..playheadIndex) into a turnout_id -> state map.
const currentState = computed(() => {
    const map = {};
    for (const s of seed.value) {
        map[s.turnout_id] = {
            state:     s.state,
            channel_a: s.channel_a,
            channel_b: s.channel_b,
        };
    }
    const upto = Math.min(playheadIndex.value, events.value.length);
    for (let i = 0; i < upto; i++) {
        const e = events.value[i];
        map[e.turnout_id] = {
            state:     e.state,
            channel_a: e.channel_a,
            channel_b: e.channel_b,
        };
    }
    return map;
});

const currentTimestamp = computed(() => {
    if (events.value.length === 0) return data.value?.window?.from;
    const idx = Math.max(0, Math.min(playheadIndex.value - 1, events.value.length - 1));
    return events.value[idx]?.timestamp ?? data.value?.window?.from;
});

const progressPercent = computed(() => {
    const n = events.value.length;
    if (n === 0) return 0;
    return Math.round((playheadIndex.value / n) * 100);
});

const recentEvents = computed(() => {
    // Show last 8 events relative to the playhead so the operator can
    // narrate what just happened.
    const upto = Math.min(playheadIndex.value, events.value.length);
    const start = Math.max(0, upto - 8);
    return events.value.slice(start, upto).reverse();
});

async function loadStations() {
    const { data: resp } = await replayApi.stations();
    stations.value = resp.data ?? [];
    if (!filters.station_id && stations.value.length > 0) {
        filters.station_id = stations.value[0].id;
    }
}

async function loadTimeline() {
    stop();
    loading.value = true;
    error.value = null;
    data.value = null;
    playheadIndex.value = 0;
    try {
        const { data: resp } = await replayApi.timeline({
            station_id: filters.station_id,
            from: new Date(filters.from).toISOString(),
            to:   new Date(filters.to).toISOString(),
        });
        data.value = resp;
    } catch (e) {
        error.value = e?.response?.data?.message
                   || `Failed to load timeline (status ${e.response?.status ?? 'n/a'}).`;
    } finally {
        loading.value = false;
    }
}

function play() {
    if (events.value.length === 0) return;
    playing.value = true;
    // Loop: step `speed.value` events per second, so interval = 1000/speed.
    clearInterval(playTimer);
    playTimer = setInterval(() => {
        if (playheadIndex.value >= events.value.length) {
            stop();
            return;
        }
        playheadIndex.value++;
    }, Math.max(40, 1000 / Math.max(1, speed.value)));
}

function stop() {
    playing.value = false;
    clearInterval(playTimer);
    playTimer = null;
}

function toggle() {
    if (playing.value) stop();
    else play();
}

function rewind() {
    stop();
    playheadIndex.value = 0;
}

function stepBack() { playheadIndex.value = Math.max(0, playheadIndex.value - 1); }
function stepFwd()  { playheadIndex.value = Math.min(events.value.length, playheadIndex.value + 1); }

function onScrub(e) {
    playheadIndex.value = Number(e.target.value);
}

// Restart timer when speed changes mid-playback so it picks up immediately.
watch(speed, () => { if (playing.value) play(); });

onMounted(loadStations);
onBeforeUnmount(stop);
</script>

<template>
    <div class="d-flex flex-column gap-3">
        <div>
            <h1 class="h4 mb-1">Replay</h1>
            <p class="text-body-secondary mb-0 small">
                Reconstruct historical turnout state across a time window.
            </p>
        </div>

        <!-- Filter / load bar -->
        <div class="card">
            <div class="card-body py-3">
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-3">
                        <label class="form-label small mb-1">Station</label>
                        <select v-model="filters.station_id" class="form-select form-select-sm">
                            <option value="">Select station…</option>
                            <option v-for="s in stations" :key="s.id" :value="s.id">
                                {{ s.code }} — {{ s.name }} ({{ s.turnouts.length }})
                            </option>
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small mb-1">From</label>
                        <input v-model="filters.from" type="datetime-local" class="form-control form-control-sm" />
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small mb-1">To</label>
                        <input v-model="filters.to" type="datetime-local" class="form-control form-control-sm" />
                    </div>
                    <div class="col-12 col-md-3">
                        <button class="btn btn-primary btn-sm w-100"
                                :disabled="!filters.station_id || loading"
                                @click="loadTimeline">
                            <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>
                            <i v-else class="bi bi-play-circle me-1"></i>
                            Load Timeline
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="error" class="alert alert-danger py-2 small mb-0">{{ error }}</div>

        <!-- Timeline + transport -->
        <div v-if="data" class="card">
            <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <strong>{{ data.station.code }}</strong>
                    <span class="text-body-secondary"> · {{ data.station.name }}</span>
                </div>
                <div class="small text-body-secondary">
                    <i class="bi bi-clock-history me-1"></i>
                    {{ data.event_count }} events
                    <span v-if="data.truncated" class="badge text-bg-warning ms-2">truncated</span>
                </div>
            </div>
            <div class="card-body">

                <!-- Transport -->
                <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
                    <button class="btn btn-outline-secondary btn-sm" @click="rewind" title="Rewind">
                        <i class="bi bi-skip-backward-fill"></i>
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" @click="stepBack" title="Step back">
                        <i class="bi bi-chevron-left"></i>
                    </button>
                    <button class="btn btn-primary btn-sm" @click="toggle">
                        <i :class="playing ? 'bi bi-pause-fill' : 'bi bi-play-fill'" class="me-1"></i>
                        {{ playing ? 'Pause' : 'Play' }}
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" @click="stepFwd" title="Step forward">
                        <i class="bi bi-chevron-right"></i>
                    </button>

                    <div class="vr mx-2"></div>

                    <label class="small text-body-secondary me-1">Speed</label>
                    <select v-model.number="speed" class="form-select form-select-sm" style="width: auto">
                        <option :value="1">1× (1 ev/s)</option>
                        <option :value="4">4×</option>
                        <option :value="10">10×</option>
                        <option :value="25">25×</option>
                        <option :value="60">60×</option>
                    </select>

                    <div class="ms-auto small text-body-secondary">
                        <i class="bi bi-clock me-1"></i>
                        {{ formatTimestamp(currentTimestamp) }}
                    </div>
                </div>

                <!-- Scrubber -->
                <div class="mb-3">
                    <input type="range"
                           class="form-range"
                           :min="0"
                           :max="events.length"
                           :value="playheadIndex"
                           @input="onScrub" />
                    <div class="d-flex justify-content-between small text-body-secondary">
                        <span>{{ formatTimestamp(data.window.from) }}</span>
                        <span>Event {{ playheadIndex }} / {{ events.length }} ({{ progressPercent }}%)</span>
                        <span>{{ formatTimestamp(data.window.to) }}</span>
                    </div>
                </div>

                <!-- Turnout grid (live re-renders as scrubber moves) -->
                <div v-if="turnouts.length > 0" class="replay-grid">
                    <div v-for="t in turnouts" :key="t.uuid">
                        <TurnoutSvg
                            :code="t.code"
                            :name="t.name"
                            :state="currentState[t.id]?.state || 'UNKNOWN'"
                            :channel-a="!!currentState[t.id]?.channel_a"
                            :channel-b="!!currentState[t.id]?.channel_b" />
                    </div>
                </div>
                <div v-else class="text-center text-body-secondary py-4">
                    No turnouts at this station.
                </div>

                <!-- Recent event ribbon -->
                <div v-if="recentEvents.length > 0" class="mt-4">
                    <h6 class="small text-uppercase text-body-secondary fw-semibold">Just happened</h6>
                    <ul class="list-unstyled mb-0 small">
                        <li v-for="e in recentEvents" :key="e.id" class="d-flex gap-2 py-1 border-bottom">
                            <span class="text-body-secondary" style="min-width: 165px">
                                {{ formatTimestamp(e.timestamp) }}
                            </span>
                            <span class="fw-semibold" style="min-width: 80px">{{ e.turnout_code }}</span>
                            <span class="text-body-secondary">
                                {{ e.previous_state || '—' }} → {{ e.state }}
                            </span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div v-else-if="!loading" class="text-center text-body-secondary small py-3">
            Pick a station + window above to load the timeline.
        </div>
    </div>
</template>

<style scoped>
.replay-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 0.85rem;
}
@media (max-width: 576px) {
    .replay-grid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); }
}
</style>
