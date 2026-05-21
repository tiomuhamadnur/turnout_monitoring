<script setup>
import { onMounted, reactive, ref } from 'vue';
import deviceHealthLogsApi from '@/services/deviceHealthLogs';
import { formatTimestamp } from '@/utils/date';
import HistorianFilters from '@/components/HistorianFilters.vue';
import ExportButtons    from '@/components/ExportButtons.vue';

const rows = ref([]);
const loading = ref(false);
const error = ref(null);
const meta = reactive({ current_page: 1, last_page: 1, total: 0, per_page: 25 });
const filters = reactive({
    station_id: '',
    mqtt_status: '',
    from: '',
    to: '',
});

function buildParams(page) {
    const p = { page, per_page: meta.per_page };
    for (const [k, v] of Object.entries(filters)) {
        if (v === '' || v === null || v === undefined) continue;
        p[k] = v;
    }
    return p;
}

async function fetchRows(page = 1) {
    loading.value = true;
    error.value = null;
    try {
        const { data } = await deviceHealthLogsApi.list(buildParams(page));
        rows.value = data.data ?? [];
        Object.assign(meta, data.meta ?? {});
    } catch (e) {
        error.value = `Failed to load device health logs (status ${e.response?.status ?? 'n/a'}).`;
    } finally {
        loading.value = false;
    }
}

function uptimeLabel(seconds) {
    if (seconds === null || seconds === undefined) return '-';
    const sec = Number(seconds);
    if (sec < 60)    return `${sec}s`;
    if (sec < 3600)  return `${Math.floor(sec / 60)}m`;
    if (sec < 86400) return `${Math.floor(sec / 3600)}h ${Math.floor((sec % 3600) / 60)}m`;
    return `${Math.floor(sec / 86400)}d ${Math.floor((sec % 86400) / 3600)}h`;
}

function pctClass(p) {
    if (p === null || p === undefined) return 'text-muted';
    if (p >= 90) return 'text-danger fw-semibold';
    if (p >= 75) return 'text-warning';
    return '';
}

function onApply() { fetchRows(1); }
function onReset() { fetchRows(1); }

onMounted(() => fetchRows());
</script>

<template>
    <div>
        <div class="mb-3 d-flex align-items-start justify-content-between gap-3 flex-wrap">
            <div>
                <h1 class="h4 mb-0">Device Health Logs</h1>
                <div class="small text-muted">CPU, RAM, disk, uptime, and MQTT health snapshots per node.</div>
            </div>
            <ExportButtons base-url="/api/exports/device-health" :params="filters" />
        </div>

        <div v-if="error" class="alert alert-danger py-2 small">{{ error }}</div>

        <HistorianFilters
            v-model="filters"
            :fields="['station', 'mqtt_status', 'date_range']"
            @apply="onApply"
            @reset="onReset" />

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>Station</th>
                                <th>Node</th>
                                <th>CPU</th>
                                <th>RAM</th>
                                <th>Disk</th>
                                <th>Uptime</th>
                                <th>MQTT</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="loading"><td colspan="8" class="text-center text-muted py-4">Loading...</td></tr>
                            <tr v-else-if="rows.length === 0"><td colspan="8" class="text-center text-muted py-4">No device health logs.</td></tr>
                            <tr v-else v-for="row in rows" :key="row.id">
                                <td class="small">{{ formatTimestamp(row.source_timestamp) }}</td>
                                <td class="small">{{ row.node?.station?.code || '-' }}</td>
                                <td>
                                    <div class="fw-semibold">{{ row.node?.node_id || '-' }}</div>
                                </td>
                                <td :class="pctClass(row.cpu_usage)">{{ row.cpu_usage ?? '-' }}<span v-if="row.cpu_usage !== null">%</span></td>
                                <td :class="pctClass(row.ram_usage)">{{ row.ram_usage ?? '-' }}<span v-if="row.ram_usage !== null">%</span></td>
                                <td :class="pctClass(row.disk_usage)">{{ row.disk_usage ?? '-' }}<span v-if="row.disk_usage !== null">%</span></td>
                                <td class="small">{{ uptimeLabel(row.uptime_seconds) }}</td>
                                <td>
                                    <span class="badge text-capitalize" :class="row.mqtt_status === 'connected' ? 'text-bg-success' : 'text-bg-secondary'">
                                        {{ row.mqtt_status }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="meta.last_page > 1" class="d-flex align-items-center justify-content-between small text-muted">
                    <div>Page {{ meta.current_page }} of {{ meta.last_page }} · {{ meta.total }} total</div>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-secondary" :disabled="meta.current_page <= 1" @click="fetchRows(meta.current_page - 1)">Prev</button>
                        <button class="btn btn-outline-secondary" :disabled="meta.current_page >= meta.last_page" @click="fetchRows(meta.current_page + 1)">Next</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
