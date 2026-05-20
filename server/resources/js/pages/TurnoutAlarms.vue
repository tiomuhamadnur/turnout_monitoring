<script setup>
import { onMounted, reactive, ref } from 'vue';
import turnoutAlarmsApi from '@/services/turnoutAlarms';

const rows = ref([]);
const loading = ref(false);
const error = ref(null);
const meta = reactive({ current_page: 1, last_page: 1, total: 0, per_page: 20 });
const filters = reactive({ active: '' });

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

async function fetchRows(page = 1) {
    loading.value = true;
    error.value = null;
    try {
        const params = {
            page,
            per_page: meta.per_page,
        };
        if (filters.active !== '') params.active = filters.active;

        const { data } = await turnoutAlarmsApi.list(params);
        rows.value = data.data ?? [];
        Object.assign(meta, data.meta ?? {});
    } catch (e) {
        error.value = `Failed to load turnout alarms (status ${e.response?.status ?? 'n/a'}).`;
    } finally {
        loading.value = false;
    }
}

onMounted(() => fetchRows());
</script>

<template>
    <div>
        <div class="mb-3">
            <h1 class="h4 mb-0">Turnout Alarms</h1>
            <div class="small text-muted">Active and historical turnout failure alarms.</div>
        </div>

        <div v-if="error" class="alert alert-danger py-2 small">{{ error }}</div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <select v-model="filters.active" class="form-select form-select-sm" style="max-width: 220px">
                        <option value="">All alarms</option>
                        <option :value="true">Active only</option>
                        <option :value="false">Resolved only</option>
                    </select>
                    <button class="btn btn-outline-secondary btn-sm" @click="fetchRows(1)">
                        <i class="bi bi-search"></i>
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Turnout</th>
                                <th>Node</th>
                                <th>Type</th>
                                <th>State</th>
                                <th>Started</th>
                                <th>Ended</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="loading"><td colspan="7" class="text-center text-muted py-4">Loading...</td></tr>
                            <tr v-else-if="rows.length === 0"><td colspan="7" class="text-center text-muted py-4">No turnout alarms.</td></tr>
                            <tr v-else v-for="row in rows" :key="row.id">
                                <td>
                                    <span class="badge" :class="row.is_active ? 'text-bg-danger' : 'text-bg-secondary'">
                                        {{ row.is_active ? 'ACTIVE' : 'RESOLVED' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ row.turnout?.code || '-' }}</div>
                                </td>
                                <td class="small">{{ row.node?.node_id || '-' }}</td>
                                <td>{{ row.alarm_type }}</td>
                                <td>{{ row.state }}</td>
                                <td class="small">{{ formatTimestamp(row.started_at) }}</td>
                                <td class="small">{{ row.ended_at ? formatTimestamp(row.ended_at) : '-' }}</td>
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
