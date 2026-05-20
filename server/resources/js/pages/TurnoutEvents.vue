<script setup>
import { onMounted, reactive, ref } from 'vue';
import turnoutEventsApi from '@/services/turnoutEvents';

const rows = ref([]);
const loading = ref(false);
const error = ref(null);
const meta = reactive({ current_page: 1, last_page: 1, total: 0, per_page: 20 });
const filters = reactive({ state: '' });

function pad(n) { return n < 10 ? `0${n}` : `${n}`; }

function formatTimestamp(value) {
    if (!value) return '-';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return value;
    const Y = d.getFullYear();
    const M = pad(d.getMonth() + 1);
    const D = pad(d.getDate());
    const h = pad(d.getHours());
    const m = pad(d.getMinutes());
    const s = pad(d.getSeconds());
    return `${Y}-${M}-${D} ${h}:${m}:${s}`;
}

function stateBadgeClass(state) {
    if (!state) return 'text-bg-secondary';
    switch ((state || '').toUpperCase()) {
        case 'NORMAL': return 'text-bg-success';
        case 'REVERSE': return 'text-bg-warning';
        case 'FAILURE': return 'text-bg-danger';
        default: return 'text-bg-secondary';
    }
}

async function fetchRows(page = 1) {
    loading.value = true;
    error.value = null;
    try {
        const { data } = await turnoutEventsApi.list({
            page,
            per_page: meta.per_page,
            state: filters.state || undefined,
        });
        rows.value = data.data ?? [];
        Object.assign(meta, data.meta ?? {});
    } catch (e) {
        error.value = `Failed to load turnout events (status ${e.response?.status ?? 'n/a'}).`;
    } finally {
        loading.value = false;
    }
}

onMounted(() => fetchRows());
</script>

<template>
    <div>
        <div class="mb-3">
            <h1 class="h4 mb-0">Turnout Events</h1>
            <div class="small text-muted">Historian feed of turnout state changes.</div>
        </div>

        <div v-if="error" class="alert alert-danger py-2 small">{{ error }}</div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <select v-model="filters.state" class="form-select form-select-sm" style="max-width: 200px">
                        <option value="">All states</option>
                        <option value="NORMAL">NORMAL</option>
                        <option value="REVERSE">REVERSE</option>
                        <option value="FAILURE">FAILURE</option>
                    </select>
                    <button class="btn btn-outline-secondary btn-sm" @click="fetchRows(1)">
                        <i class="bi bi-search"></i>
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>Turnout</th>
                                <th>Node</th>
                                <th>Current State</th>
                                <th>Previous State</th>
                                <th>Channels</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="loading"><td colspan="6" class="text-center text-muted py-4">Loading...</td></tr>
                            <tr v-else-if="rows.length === 0"><td colspan="6" class="text-center text-muted py-4">No turnout events.</td></tr>
                            <tr v-else v-for="row in rows" :key="row.id">
                                <td class="small">{{ formatTimestamp(row.source_timestamp) }}</td>
                                <td>
                                    <div class="fw-semibold">{{ row.turnout?.code || '-' }}</div>
                                </td>
                                <td class="small">{{ row.node?.node_id || '-' }}</td>
                                <td>
                                    <span :class="['badge', stateBadgeClass(row.state)]">{{ row.state }}</span>
                                </td>
                                <td>
                                    <span v-if="row.previous_state" :class="['badge', stateBadgeClass(row.previous_state), 'small', 'opacity-50']">{{ row.previous_state }}</span>
                                    <span v-else class="text-muted">-</span>
                                </td>
                                <td class="small">{{ row.channel_a ? 'A:1' : 'A:0' }} / {{ row.channel_b ? 'B:1' : 'B:0' }}</td>
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
