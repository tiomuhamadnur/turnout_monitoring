<script setup>
import { ref, reactive, onMounted, onBeforeUnmount, computed } from 'vue';
import dashboardService from '@/services/dashboard';
import { formatTimestamp } from '@/utils/date';
import { useAuthStore } from '@/stores/auth';
import { useThemeStore } from '@/stores/theme';
import { useRealtimeStore } from '@/stores/realtime';
import StationLiveCard from '@/components/StationLiveCard.vue';
import StationLiveModal from '@/components/StationLiveModal.vue';
import AlarmToastStack from '@/components/AlarmToastStack.vue';
import { primeAlarm } from '@/utils/alarmSound';
// vue-chartjs v5 exports component names without the "Chart" suffix (Bar, Line, etc.).
// Aliased here so the template can keep using <BarChart> / <LineChart>.
import { Bar as BarChart, Line as LineChart } from 'vue-chartjs';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    BarElement,
    Title,
    Tooltip,
    Legend,
} from 'chart.js';
import { subDays, format } from 'date-fns';

ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    BarElement,
    Title,
    Tooltip,
    Legend
);

const auth  = useAuthStore();
const theme = useThemeStore();
const rt    = useRealtimeStore();

// Chart.js doesn't auto-pick body text color from CSS, so we tie its tick/legend
// colour to the theme store. Switching dark/light or accent will repaint both
// charts automatically because the options below are computed refs.
const chartTextColor = computed(() => theme.mode === 'dark' ? '#e5e7eb' : '#1f2937');
const chartGridColor = 'rgba(127, 127, 127, 0.18)';

const overview      = ref({ turnouts: 0, nodes: 0, activeAlarms: 0, healthyNodes: 0 });
const recentEvents  = ref([]);
const turnoutStats  = ref([]);
const trendData     = ref({});

const dateRange = reactive({
    startDate: format(subDays(new Date(), 7), 'yyyy-MM-dd'),
    endDate:   format(new Date(), 'yyyy-MM-dd'),
});

// Modal: which station the user has zoomed into (null = closed).
const modalStation = ref(null);
const modalOpen    = ref(false);
function openStation(s) {
    // Pass the live snapshot — the modal will re-read it from store-bound refs
    // on next render via the reactive `rt.stationSummary`.
    modalStation.value = s;
    modalOpen.value    = true;
}
function closeStation() {
    modalOpen.value = false;
    // Defer clearing so the closing transition keeps content stable.
    setTimeout(() => { modalStation.value = null; }, 200);
}

// Reflect the modal's station from the live store (so its turnout grid
// updates in realtime while the modal is open).
const liveModalStation = computed(() => {
    if (!modalStation.value) return null;
    return rt.stationSummary.find(s => s.code === modalStation.value.code) || modalStation.value;
});

const turnoutChartData = computed(() => {
    if (!turnoutStats.value || turnoutStats.value.length === 0) {
        return {
            labels: [],
            datasets: [
                { label: 'NORMAL',  data: [], backgroundColor: '#10B981' },
                { label: 'REVERSE', data: [], backgroundColor: '#F59E0B' },
                { label: 'FAILURE', data: [], backgroundColor: '#EF4444' },
            ],
        };
    }
    return {
        labels: turnoutStats.value.map(s => s.code),
        datasets: [
            { label: 'NORMAL',  data: turnoutStats.value.map(s => s.normal_count  || 0), backgroundColor: '#10B981', borderRadius: 8 },
            { label: 'REVERSE', data: turnoutStats.value.map(s => s.reverse_count || 0), backgroundColor: '#F59E0B', borderRadius: 8 },
            { label: 'FAILURE', data: turnoutStats.value.map(s => s.failure_count || 0), backgroundColor: '#EF4444', borderRadius: 8 },
        ],
    };
});

const trendChartData = computed(() => {
    if (!trendData.value || !trendData.value.labels) {
        return { labels: [], datasets: [] };
    }
    return {
        labels: trendData.value.labels,
        datasets: [
            {
                label: 'Total Events',
                data: trendData.value.total || [],
                borderColor: '#2563EB',
                backgroundColor: 'rgba(37, 99, 235, 0.1)',
                fill: true, tension: 0.4,
                pointRadius: 5, pointBackgroundColor: '#2563EB',
                pointBorderColor: '#fff', pointBorderWidth: 2,
            },
            {
                label: 'Alarms',
                data: trendData.value.alarms || [],
                borderColor: '#EF4444',
                backgroundColor: 'rgba(239, 68, 68, 0.1)',
                fill: true, tension: 0.4,
                pointRadius: 5, pointBackgroundColor: '#EF4444',
                pointBorderColor: '#fff', pointBorderWidth: 2,
            },
        ],
    };
});

// Two independent computed refs so each chart instance gets its own options
// object — chart.js sometimes mutates options internally and sharing would
// cause one chart's tweaks to bleed into the other.
const makeChartOptions = () => ({
    responsive: true,
    maintainAspectRatio: true,
    plugins: {
        legend: { position: 'top', labels: { color: chartTextColor.value, font: { size: 12, weight: 500 } } },
        title:  { display: false },
    },
    scales: {
        y: { beginAtZero: true, ticks: { color: chartTextColor.value, font: { size: 11 } }, grid: { color: chartGridColor } },
        x: {                     ticks: { color: chartTextColor.value, font: { size: 11 } }, grid: { color: chartGridColor } },
    },
});

const turnoutChartOptions = computed(makeChartOptions);
const trendChartOptions   = computed(makeChartOptions);

async function load() {
    const o = await dashboardService.getOverview();
    overview.value = o;
    recentEvents.value = await dashboardService.getRecentEvents();

    const stats = await dashboardService.getTurnoutStats(dateRange.startDate, dateRange.endDate);
    turnoutStats.value = stats;

    const trend = await dashboardService.getTrendData(dateRange.startDate, dateRange.endDate);
    trendData.value = trend;
}

async function applyFilter() {
    await load();
}

onMounted(async () => {
    // Stats/charts first (these don't depend on realtime).
    await load();

    // Then live snapshot + Reverb. Errors are swallowed so the historian
    // section still renders even if the websocket back-end is offline.
    try {
        await rt.load();
        rt.connect();
    } catch (_) { /* shown via rt.loadError */ }

    // First click anywhere on the dashboard unlocks audio for later alarms.
    document.addEventListener('click', primeAlarm, { once: true });
});

onBeforeUnmount(() => {
    rt.disconnect();
});
</script>

<template>
    <div class="d-flex flex-column gap-3">
        <!-- Page header -->
        <div class="d-flex align-items-end justify-content-between gap-3 flex-wrap">
            <div>
                <h1 class="h4 mb-1">Dashboard</h1>
                <p class="text-body-secondary mb-0 small">Monitoring Sistem Turnout MRT</p>
            </div>
            <div class="text-end small text-body-secondary">
                <div>Welcome, <span class="fw-semibold text-body">{{ auth.user?.name }}</span></div>
                <div>{{ new Date().toLocaleDateString('id-ID') }}</div>
            </div>
        </div>

        <!-- Realtime connection status -->
        <div v-if="rt.loadError" class="alert alert-warning d-flex align-items-center gap-2 py-2 px-3 mb-0 small">
            <i class="bi bi-wifi-off"></i>
            Live data unavailable: {{ rt.loadError }}
        </div>

        <!-- LIVE: Per-station cards (compact). Click → modal with SVG grid. -->
        <div class="d-flex align-items-center gap-2 mt-1">
            <h2 class="h6 mb-0">Live Stations</h2>
            <span class="live-pulse" :class="{ 'is-live': rt.connected }"
                  :title="rt.connected ? 'Realtime connected' : 'Offline'"></span>
            <span class="small text-body-secondary">
                {{ rt.connected ? 'Realtime' : 'Offline' }}
            </span>
            <span v-if="rt.activeAlarmCount > 0" class="badge text-bg-danger ms-2">
                {{ rt.activeAlarmCount }} active alarm{{ rt.activeAlarmCount > 1 ? 's' : '' }}
            </span>
        </div>
        <div class="row g-3">
            <div v-for="s in rt.stationSummary" :key="s.id" class="col-12 col-md-6 col-lg-4">
                <StationLiveCard :summary="s" @open="openStation" />
            </div>
            <div v-if="rt.loaded && rt.stationSummary.length === 0"
                 class="col-12 text-center text-body-secondary small py-3">
                No stations configured yet.
            </div>
        </div>

        <!-- Date range filter (uses accent color, theme-aware) -->
        <div class="card dashboard-accent-banner">
            <div class="card-body py-3">
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-4">
                        <label class="form-label small mb-1">Start Date</label>
                        <input v-model="dateRange.startDate" type="date" class="form-control form-control-sm" />
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small mb-1">End Date</label>
                        <input v-model="dateRange.endDate" type="date" class="form-control form-control-sm" />
                    </div>
                    <div class="col-12 col-md-4">
                        <button @click="applyFilter" class="btn btn-light btn-sm w-100">
                            <i class="bi bi-search me-1"></i> Filter
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="row g-3">
            <div class="col-6 col-md-3">
                <div class="card text-bg-success h-100">
                    <div class="card-body">
                        <div class="small opacity-75">Total Turnouts</div>
                        <div class="h3 fw-bold mb-0">{{ overview.turnouts }}</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card text-bg-info h-100">
                    <div class="card-body">
                        <div class="small opacity-75">Active Nodes</div>
                        <div class="h3 fw-bold mb-0">{{ overview.nodes }}</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card text-bg-warning h-100">
                    <div class="card-body">
                        <div class="small opacity-75">Active Alarms</div>
                        <div class="h3 fw-bold mb-0">{{ overview.activeAlarms }}</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card text-bg-primary h-100">
                    <div class="card-body">
                        <div class="small opacity-75">Healthy Nodes</div>
                        <div class="h3 fw-bold mb-0">{{ overview.healthyNodes }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="row g-3">
            <div class="col-12 col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex align-items-center justify-content-between bg-body-tertiary">
                        <h5 class="mb-0 fs-6 fw-semibold">Turnout State Distribution</h5>
                        <span class="badge text-bg-secondary small">Per Turnout</span>
                    </div>
                    <div class="card-body dashboard-chart-body">
                        <BarChart :data="turnoutChartData" :options="turnoutChartOptions" />
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex align-items-center justify-content-between bg-body-tertiary">
                        <h5 class="mb-0 fs-6 fw-semibold">Event Trend</h5>
                        <span class="badge text-bg-secondary small">Over Time</span>
                    </div>
                    <div class="card-body dashboard-chart-body">
                        <LineChart :data="trendChartData" :options="trendChartOptions" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Events -->
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between bg-body-tertiary">
                <h5 class="mb-0 fs-6 fw-semibold">Recent Turnout Events</h5>
                <span class="badge text-bg-secondary small">Latest 10</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="fw-semibold small">Time</th>
                                <th class="fw-semibold small">Turnout</th>
                                <th class="fw-semibold small">Node</th>
                                <th class="fw-semibold small">State</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="ev in recentEvents" :key="ev.id">
                                <td class="small text-body-secondary">{{ formatTimestamp(ev.timestamp) }}</td>
                                <td class="fw-semibold">{{ ev.turnout_code }}</td>
                                <td class="small text-body-secondary">{{ ev.node_id }}</td>
                                <td>
                                    <span class="badge"
                                          :class="ev.state === 'NORMAL' ? 'text-bg-success'
                                                : ev.state === 'REVERSE' ? 'text-bg-warning'
                                                : 'text-bg-danger'">
                                        {{ ev.state }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Live SVG modal (per station, opened from the cards above) -->
        <StationLiveModal :show="modalOpen" :station="liveModalStation" @close="closeStation" />

        <!-- Floating alarm popup stack (FAILURE events) -->
        <AlarmToastStack />
    </div>
</template>

<style scoped>
/* Accent-colored filter banner. Picks up --app-accent from the theme store
   so light/dark mode + accent color all flow through one variable. */
.dashboard-accent-banner {
    background: linear-gradient(
        135deg,
        var(--app-accent),
        color-mix(in srgb, var(--app-accent) 70%, #000)
    );
    border: none;
    color: #fff;
}
.dashboard-accent-banner .form-label {
    color: rgba(255, 255, 255, 0.9);
    font-weight: 500;
}

.dashboard-chart-body {
    min-height: 280px;
    position: relative;
}
.dashboard-chart-body :deep(canvas) {
    max-height: 280px;
}

/* Live connection dot. Grey when offline, green pulsing when connected. */
.live-pulse {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #94a3b8;
    display: inline-block;
}
.live-pulse.is-live {
    background: #10B981;
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.6);
    animation: live-pulse 1.6s infinite;
}
@keyframes live-pulse {
    0%   { box-shadow: 0 0 0 0   rgba(16, 185, 129, 0.6); }
    70%  { box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
    100% { box-shadow: 0 0 0 0   rgba(16, 185, 129, 0); }
}

@media (max-width: 768px) {
    .dashboard-chart-body { min-height: 220px; }
}
</style>
