<script setup>
import { onMounted, reactive, ref } from 'vue';
import turnoutEventsApi from '@/services/turnoutEvents';
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
    state: '',
    from: '',
    to: '',
    transitions_only: false,
});

function stateBadgeClass(state) {
    if (!state) return 'text-bg-secondary';
    switch ((state || '').toUpperCase()) {
        case 'NORMAL':  return 'text-bg-success';
        case 'REVERSE': return 'text-bg-warning';
        case 'FAILURE': return 'text-bg-danger';
        default:        return 'text-bg-secondary';
    }
}

function buildParams(page) {
    const p = { page, per_page: meta.per_page };
    for (const [k, v] of Object.entries(filters)) {
        if (v === '' || v === false || v === null || v === undefined) continue;
        p[k] = v;
    }
    return p;
}

async function fetchRows(page = 1) {
    loading.value = true;
    error.value = null;
    try {
        const { data } = await turnoutEventsApi.list(buildParams(page));
        rows.value = data.data ?? [];
        Object.assign(meta, data.meta ?? {});
    } catch (e) {
        error.value = `Failed to load turnout events (status ${e.response?.status ?? 'n/a'}).`;
    } finally {
        loading.value = false;
    }
}

function onApply() { fetchRows(1); }
function onReset() { fetchRows(1); }

onMounted(() => fetchRows());
</script>

<template>
    <div>
        <div class="mb-3 d-flex align-items-start justify-content-between gap-3 flex-wrap">
            <div>
                <h1 class="h4 mb-0">Turnout Events</h1>
                <div class="small text-muted">Historian feed of turnout state changes.</div>
            </div>
            <ExportButtons base-url="/api/exports/turnout-events" :params="filters" />
        </div>

        <div v-if="error" class="alert alert-danger py-2 small">{{ error }}</div>

        <HistorianFilters
            v-model="filters"
            :fields="['search', 'station', 'state', 'date_range', 'transitions_only']"
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
                                <th>Turnout</th>
                                <th>Node</th>
                                <th>Channels</th>
                                <th>Previous</th>
                                <th></th>
                                <th>Current</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="loading"><td colspan="8" class="text-center text-muted py-4">Loading...</td></tr>
                            <tr v-else-if="rows.length === 0"><td colspan="8" class="text-center text-muted py-4">No turnout events.</td></tr>
                            <tr v-else v-for="row in rows" :key="row.id">
                                <td class="small">{{ formatTimestamp(row.source_timestamp) }}</td>
                                <td class="small">{{ row.turnout?.station?.code || '-' }}</td>
                                <td>
                                    <div class="fw-semibold">{{ row.turnout?.code || '-' }}</div>
                                    <div class="small text-muted">{{ row.turnout?.name }}</div>
                                </td>
                                <td class="small">{{ row.node?.node_id || '-' }}</td>
                                <td class="small">
                                    <span class="badge text-bg-secondary">N:{{ row.channel_a ? 1 : 0 }} / R:{{ row.channel_b ? 1 : 0 }}</span>
                                </td>
                                <td>
                                    <span v-if="row.previous_state" :class="['badge', stateBadgeClass(row.previous_state), 'opacity-50']">{{ row.previous_state }}</span>
                                    <span v-else class="text-muted">-</span>
                                </td>
                                <td class="text-center small text-muted">→</td>
                                <td>
                                    <span :class="['badge', stateBadgeClass(row.state)]">{{ row.state }}</span>
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
