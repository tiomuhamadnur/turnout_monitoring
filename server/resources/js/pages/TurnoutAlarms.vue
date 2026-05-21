<script setup>
import { onMounted, reactive, ref } from 'vue';
import turnoutAlarmsApi from '@/services/turnoutAlarms';
import { formatTimestamp } from '@/utils/date';
import HistorianFilters from '@/components/HistorianFilters.vue';
import ExportButtons    from '@/components/ExportButtons.vue';

const rows = ref([]);
const loading = ref(false);
const error = ref(null);
const meta = reactive({ current_page: 1, last_page: 1, total: 0, per_page: 25 });
const filters = reactive({
    search: '',
    station_id: '',
    active: '',
    alarm_type: '',
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
        const { data } = await turnoutAlarmsApi.list(buildParams(page));
        rows.value = data.data ?? [];
        Object.assign(meta, data.meta ?? {});
    } catch (e) {
        error.value = `Failed to load turnout alarms (status ${e.response?.status ?? 'n/a'}).`;
    } finally {
        loading.value = false;
    }
}

function durationLabel(row) {
    if (!row.started_at) return '-';
    const start = new Date(row.started_at).getTime();
    const end   = row.ended_at ? new Date(row.ended_at).getTime() : Date.now();
    const sec   = Math.max(0, Math.round((end - start) / 1000));
    if (sec < 60)    return `${sec}s`;
    if (sec < 3600)  return `${Math.floor(sec / 60)}m ${sec % 60}s`;
    const h = Math.floor(sec / 3600);
    const m = Math.floor((sec % 3600) / 60);
    return `${h}h ${m}m`;
}

function onApply() { fetchRows(1); }
function onReset() { fetchRows(1); }

onMounted(() => fetchRows());
</script>

<template>
    <div>
        <div class="mb-3 d-flex align-items-start justify-content-between gap-3 flex-wrap">
            <div>
                <h1 class="h4 mb-0">Turnout Alarms</h1>
                <div class="small text-muted">Active and historical turnout failure alarms.</div>
            </div>
            <ExportButtons base-url="/api/exports/turnout-alarms" :params="filters" />
        </div>

        <div v-if="error" class="alert alert-danger py-2 small">{{ error }}</div>

        <HistorianFilters
            v-model="filters"
            :fields="['search', 'station', 'active', 'alarm_type', 'date_range']"
            @apply="onApply"
            @reset="onReset" />

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Station</th>
                                <th>Turnout</th>
                                <th>Node</th>
                                <th>Type</th>
                                <th>State</th>
                                <th>Started</th>
                                <th>Ended</th>
                                <th>Duration</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="loading"><td colspan="9" class="text-center text-muted py-4">Loading...</td></tr>
                            <tr v-else-if="rows.length === 0"><td colspan="9" class="text-center text-muted py-4">No turnout alarms.</td></tr>
                            <tr v-else v-for="row in rows" :key="row.id">
                                <td>
                                    <span class="badge" :class="row.is_active ? 'text-bg-danger' : 'text-bg-secondary'">
                                        {{ row.is_active ? 'ACTIVE' : 'RESOLVED' }}
                                    </span>
                                </td>
                                <td class="small">{{ row.turnout?.station?.code || '-' }}</td>
                                <td>
                                    <div class="fw-semibold">{{ row.turnout?.code || '-' }}</div>
                                    <div class="small text-muted">{{ row.turnout?.name }}</div>
                                </td>
                                <td class="small">{{ row.node?.node_id || '-' }}</td>
                                <td>{{ row.alarm_type }}</td>
                                <td>{{ row.state }}</td>
                                <td class="small">{{ formatTimestamp(row.started_at) }}</td>
                                <td class="small">{{ row.ended_at ? formatTimestamp(row.ended_at) : '-' }}</td>
                                <td class="small fw-semibold">{{ durationLabel(row) }}</td>
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
