<script setup>
import { onMounted, reactive, ref } from 'vue';
import auditLogsApi from '@/services/auditLogs';

const rows = ref([]);
const loading = ref(false);
const error = ref(null);
const meta = reactive({ current_page: 1, last_page: 1, total: 0, per_page: 20 });
const filters = reactive({ auditable_type: '', action: '' });

async function fetchRows(page = 1) {
    loading.value = true;
    error.value = null;
    try {
        const { data } = await auditLogsApi.list({
            page,
            per_page: meta.per_page,
            auditable_type: filters.auditable_type || undefined,
            action: filters.action || undefined,
        });
        rows.value = data.data ?? [];
        Object.assign(meta, data.meta ?? {});
    } catch (e) {
        error.value = `Failed to load audit logs (status ${e.response?.status ?? 'n/a'}).`;
    } finally {
        loading.value = false;
    }
}

onMounted(() => fetchRows());
</script>

<template>
    <div>
        <div class="mb-3">
            <h1 class="h4 mb-0">Audit Logs</h1>
            <div class="small text-muted">Readonly audit trail for Phase 2 CRUD activity.</div>
        </div>

        <div v-if="error" class="alert alert-danger py-2 small">{{ error }}</div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <select v-model="filters.auditable_type" class="form-select form-select-sm" style="max-width: 220px">
                        <option value="">All models</option>
                        <option value="Station">Station</option>
                        <option value="Node">Node</option>
                        <option value="Turnout">Turnout</option>
                    </select>
                    <select v-model="filters.action" class="form-select form-select-sm" style="max-width: 180px">
                        <option value="">All actions</option>
                        <option value="created">created</option>
                        <option value="updated">updated</option>
                        <option value="deleted">deleted</option>
                    </select>
                    <button class="btn btn-outline-secondary btn-sm" @click="fetchRows(1)"><i class="bi bi-search"></i></button>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>User</th>
                                <th>Model</th>
                                <th>Action</th>
                                <th>Record</th>
                                <th>IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="loading"><td colspan="6" class="text-center text-muted py-4">Loading...</td></tr>
                            <tr v-else-if="rows.length === 0"><td colspan="6" class="text-center text-muted py-4">No audit logs.</td></tr>
                            <tr v-else v-for="row in rows" :key="row.id">
                                <td class="small">{{ row.created_at }}</td>
                                <td>{{ row.user?.name || 'System' }}</td>
                                <td>{{ row.auditable_type }}</td>
                                <td><span class="badge text-bg-secondary text-capitalize">{{ row.action }}</span></td>
                                <td>#{{ row.auditable_id }}</td>
                                <td class="small text-muted">{{ row.ip_address || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>
