<script setup>
import { onMounted, reactive, ref } from 'vue';
import deviceHealthLogsApi from '@/services/deviceHealthLogs';

const rows = ref([]);
const loading = ref(false);
const error = ref(null);
const meta = reactive({ current_page: 1, last_page: 1, total: 0, per_page: 20 });

async function fetchRows(page = 1) {
    loading.value = true;
    error.value = null;
    try {
        const { data } = await deviceHealthLogsApi.list({
            page,
            per_page: meta.per_page,
        });
        rows.value = data.data ?? [];
        Object.assign(meta, data.meta ?? {});
    } catch (e) {
        error.value = `Failed to load device health logs (status ${e.response?.status ?? 'n/a'}).`;
    } finally {
        loading.value = false;
    }
}

onMounted(() => fetchRows());
</script>

<template>
    <div>
        <div class="mb-3">
            <h1 class="h4 mb-0">Device Health Logs</h1>
            <div class="small text-muted">CPU, RAM, disk, uptime, and MQTT health snapshots per node.</div>
        </div>

        <div v-if="error" class="alert alert-danger py-2 small">{{ error }}</div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Node</th>
                                <th>CPU</th>
                                <th>RAM</th>
                                <th>Disk</th>
                                <th>Uptime</th>
                                <th>MQTT</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="loading"><td colspan="7" class="text-center text-muted py-4">Loading...</td></tr>
                            <tr v-else-if="rows.length === 0"><td colspan="7" class="text-center text-muted py-4">No device health logs.</td></tr>
                            <tr v-else v-for="row in rows" :key="row.id">
                                <td class="small">{{ row.source_timestamp }}</td>
                                <td>
                                    <div class="fw-semibold">{{ row.node?.node_id || '-' }}</div>
                                    <div class="small text-muted">{{ row.node?.name || '-' }}</div>
                                </td>
                                <td>{{ row.cpu_usage ?? '-' }}<span v-if="row.cpu_usage !== null">%</span></td>
                                <td>{{ row.ram_usage ?? '-' }}<span v-if="row.ram_usage !== null">%</span></td>
                                <td>{{ row.disk_usage ?? '-' }}<span v-if="row.disk_usage !== null">%</span></td>
                                <td>{{ row.uptime_seconds ?? '-' }}</td>
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
