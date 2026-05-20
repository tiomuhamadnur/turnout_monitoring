<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useAuthStore } from '@/stores/auth';
import nodesApi from '@/services/nodes';
import stationsApi from '@/services/stations';
import BaseModal from '@/components/BaseModal.vue';

const auth = useAuthStore();
const rows = ref([]);
const stations = ref([]);
const loading = ref(false);
const search = ref('');
const stationFilter = ref('');
const error = ref(null);
const meta = reactive({ current_page: 1, last_page: 1, total: 0, per_page: 15 });
const modal = reactive({ open: false, mode: 'create', submitting: false, errors: {} });
const form = reactive({ id: null, station_id: '', node_id: '', name: '', ip_address: '', mqtt_client_id: '', status: 'unknown', metadataText: '' });
const confirm = reactive({ open: false, target: null });

async function fetchStations() {
    const { data } = await stationsApi.list({ per_page: 100 });
    stations.value = data.data ?? [];
}

async function fetchRows(page = 1) {
    loading.value = true;
    error.value = null;
    try {
        const { data } = await nodesApi.list({
            page,
            per_page: meta.per_page,
            q: search.value || undefined,
            station_id: stationFilter.value || undefined,
        });
        rows.value = data.data ?? [];
        Object.assign(meta, data.meta ?? {});
    } catch (e) {
        error.value = `Failed to load nodes (status ${e.response?.status ?? 'n/a'}).`;
    } finally {
        loading.value = false;
    }
}

function openCreate() {
    Object.assign(form, { id: null, station_id: stations.value[0]?.id ?? '', node_id: '', name: '', ip_address: '', mqtt_client_id: '', status: 'unknown', metadataText: '' });
    modal.mode = 'create';
    modal.errors = {};
    modal.open = true;
}

function openEdit(row) {
    Object.assign(form, {
        id: row.id,
        station_id: row.station_id,
        node_id: row.node_id ?? '',
        name: row.name ?? '',
        ip_address: row.ip_address ?? '',
        mqtt_client_id: row.mqtt_client_id ?? '',
        status: row.status ?? 'unknown',
        metadataText: row.metadata ? JSON.stringify(row.metadata, null, 2) : '',
    });
    modal.mode = 'edit';
    modal.errors = {};
    modal.open = true;
}

function payload() {
    let metadata = null;
    if (form.metadataText.trim()) {
        try {
            metadata = JSON.parse(form.metadataText);
        } catch {
            modal.errors = { metadata: ['Metadata must be valid JSON.'] };
            return null;
        }
    }
    return {
        station_id: Number(form.station_id),
        node_id: form.node_id,
        name: form.name,
        ip_address: form.ip_address || null,
        mqtt_client_id: form.mqtt_client_id || null,
        status: form.status,
        metadata,
    };
}

async function submit() {
    modal.submitting = true;
    modal.errors = {};
    try {
        const body = payload();
        if (!body) return;
        if (modal.mode === 'create') await nodesApi.create(body);
        else await nodesApi.update(form.id, body);
        modal.open = false;
        await fetchRows(meta.current_page);
    } catch (e) {
        if (!modal.errors.metadata) modal.errors = e.response?.status === 422 ? (e.response.data.errors ?? {}) : { _: ['Failed to save node.'] };
    } finally {
        modal.submitting = false;
    }
}

function askDelete(row) {
    confirm.target = row;
    confirm.open = true;
}

async function doDelete() {
    if (!confirm.target) return;
    try {
        await nodesApi.remove(confirm.target.id);
        confirm.open = false;
        confirm.target = null;
        await fetchRows(meta.current_page);
    } catch (e) {
        alert(e.response?.data?.message ?? 'Delete failed.');
    }
}

onMounted(async () => {
    await fetchStations();
    await fetchRows();
});
</script>

<template>
    <div>
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h1 class="h4 mb-0">Node Management</h1>
                <div class="small text-muted">CRUD monitoring node identity and assignment.</div>
            </div>
            <button v-if="auth.can('nodes.manage')" class="btn btn-primary btn-sm" @click="openCreate">
                <i class="bi bi-plus-lg me-1"></i> New node
            </button>
        </div>

        <div v-if="error" class="alert alert-danger py-2 small">{{ error }}</div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <input v-model="search" type="search" class="form-control form-control-sm" placeholder="Search node ID or name"
                           style="max-width: 320px" @keyup.enter="fetchRows(1)" />
                    <select v-model="stationFilter" class="form-select form-select-sm" style="max-width: 220px">
                        <option value="">All stations</option>
                        <option v-for="station in stations" :key="station.id" :value="station.id">{{ station.code }} - {{ station.name }}</option>
                    </select>
                    <button class="btn btn-outline-secondary btn-sm" @click="fetchRows(1)"><i class="bi bi-search"></i></button>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Node ID</th>
                                <th>Name</th>
                                <th>Station</th>
                                <th>IP</th>
                                <th>MQTT Client</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="loading"><td colspan="7" class="text-center text-muted py-4">Loading...</td></tr>
                            <tr v-else-if="rows.length === 0"><td colspan="7" class="text-center text-muted py-4">No nodes.</td></tr>
                            <tr v-else v-for="row in rows" :key="row.id">
                                <td class="fw-semibold">{{ row.node_id }}</td>
                                <td>{{ row.name }}</td>
                                <td>{{ row.station?.code }} <span class="text-muted">{{ row.station?.name }}</span></td>
                                <td>{{ row.ip_address || '—' }}</td>
                                <td>{{ row.mqtt_client_id || '—' }}</td>
                                <td><span class="badge text-capitalize" :class="row.status === 'online' ? 'text-bg-success' : row.status === 'offline' ? 'text-bg-danger' : 'text-bg-secondary'">{{ row.status }}</span></td>
                                <td class="text-end">
                                    <button v-if="auth.can('nodes.manage')" class="btn btn-sm btn-outline-secondary me-1" @click="openEdit(row)"><i class="bi bi-pencil"></i></button>
                                    <button v-if="auth.can('nodes.manage')" class="btn btn-sm btn-outline-danger" @click="askDelete(row)"><i class="bi bi-trash"></i></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <BaseModal :show="modal.open" :title="modal.mode === 'create' ? 'New node' : 'Edit node'" size="lg" @close="modal.open = false">
            <form @submit.prevent="submit">
                <div v-if="modal.errors._" class="alert alert-danger py-2 small">{{ modal.errors._[0] }}</div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small">Station</label>
                        <select v-model="form.station_id" class="form-select" :class="{ 'is-invalid': modal.errors.station_id }">
                            <option value="">Select station</option>
                            <option v-for="station in stations" :key="station.id" :value="station.id">{{ station.code }} - {{ station.name }}</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Node ID</label>
                        <input v-model="form.node_id" class="form-control" :class="{ 'is-invalid': modal.errors.node_id }" />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Name</label>
                        <input v-model="form.name" class="form-control" :class="{ 'is-invalid': modal.errors.name }" />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Status</label>
                        <select v-model="form.status" class="form-select">
                            <option value="unknown">unknown</option>
                            <option value="online">online</option>
                            <option value="offline">offline</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">IP Address</label>
                        <input v-model="form.ip_address" class="form-control" :class="{ 'is-invalid': modal.errors.ip_address }" />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">MQTT Client ID</label>
                        <input v-model="form.mqtt_client_id" class="form-control" :class="{ 'is-invalid': modal.errors.mqtt_client_id }" />
                    </div>
                    <div class="col-12">
                        <label class="form-label small">Metadata JSON</label>
                        <textarea v-model="form.metadataText" rows="5" class="form-control font-monospace" :class="{ 'is-invalid': modal.errors.metadata }"></textarea>
                        <div v-if="modal.errors.metadata" class="invalid-feedback">{{ modal.errors.metadata[0] }}</div>
                    </div>
                </div>
            </form>
            <template #footer>
                <button class="btn btn-secondary" @click="modal.open = false">Cancel</button>
                <button class="btn btn-primary" :disabled="modal.submitting" @click="submit">Save</button>
            </template>
        </BaseModal>

        <BaseModal :show="confirm.open" title="Delete node" size="sm" @close="confirm.open = false">
            <p class="mb-0">Delete node <strong>{{ confirm.target?.node_id }}</strong>?</p>
            <template #footer>
                <button class="btn btn-secondary" @click="confirm.open = false">Cancel</button>
                <button class="btn btn-danger" @click="doDelete">Delete</button>
            </template>
        </BaseModal>
    </div>
</template>
