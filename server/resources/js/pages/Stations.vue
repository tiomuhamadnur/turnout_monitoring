<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useAuthStore } from '@/stores/auth';
import stationsApi from '@/services/stations';
import BaseModal from '@/components/BaseModal.vue';

const auth = useAuthStore();
const rows = ref([]);
const loading = ref(false);
const search = ref('');
const error = ref(null);
const meta = reactive({ current_page: 1, last_page: 1, total: 0, per_page: 15 });
const modal = reactive({ open: false, mode: 'create', submitting: false, errors: {} });
const form = reactive({ id: null, code: '', name: '', description: '', latitude: '', longitude: '' });
const confirm = reactive({ open: false, target: null });

async function fetchRows(page = 1) {
    loading.value = true;
    error.value = null;
    try {
        const { data } = await stationsApi.list({ page, per_page: meta.per_page, q: search.value || undefined });
        rows.value = data.data ?? [];
        Object.assign(meta, data.meta ?? {});
    } catch (e) {
        error.value = `Failed to load stations (status ${e.response?.status ?? 'n/a'}).`;
    } finally {
        loading.value = false;
    }
}

function openCreate() {
    Object.assign(form, { id: null, code: '', name: '', description: '', latitude: '', longitude: '' });
    modal.mode = 'create';
    modal.errors = {};
    modal.open = true;
}

function openEdit(row) {
    Object.assign(form, {
        id: row.id,
        code: row.code ?? '',
        name: row.name ?? '',
        description: row.description ?? '',
        latitude: row.latitude ?? '',
        longitude: row.longitude ?? '',
    });
    modal.mode = 'edit';
    modal.errors = {};
    modal.open = true;
}

function payload() {
    return {
        code: form.code,
        name: form.name,
        description: form.description || null,
        latitude: form.latitude === '' ? null : Number(form.latitude),
        longitude: form.longitude === '' ? null : Number(form.longitude),
    };
}

async function submit() {
    modal.submitting = true;
    modal.errors = {};
    try {
        if (modal.mode === 'create') await stationsApi.create(payload());
        else await stationsApi.update(form.id, payload());
        modal.open = false;
        await fetchRows(meta.current_page);
    } catch (e) {
        modal.errors = e.response?.status === 422 ? (e.response.data.errors ?? {}) : { _: ['Failed to save station.'] };
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
        await stationsApi.remove(confirm.target.id);
        confirm.open = false;
        confirm.target = null;
        await fetchRows(meta.current_page);
    } catch (e) {
        alert(e.response?.data?.message ?? 'Delete failed.');
    }
}

onMounted(() => fetchRows());
</script>

<template>
    <div>
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h1 class="h4 mb-0">Station Management</h1>
                <div class="small text-muted">CRUD station and location master data.</div>
            </div>
            <button v-if="auth.can('stations.manage')" class="btn btn-primary btn-sm" @click="openCreate">
                <i class="bi bi-plus-lg me-1"></i> New station
            </button>
        </div>

        <div v-if="error" class="alert alert-danger py-2 small">{{ error }}</div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex gap-2 mb-3">
                    <input v-model="search" type="search" class="form-control form-control-sm" placeholder="Search code or name"
                           style="max-width: 320px" @keyup.enter="fetchRows(1)" />
                    <button class="btn btn-outline-secondary btn-sm" @click="fetchRows(1)"><i class="bi bi-search"></i></button>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th class="text-center">Nodes</th>
                                <th class="text-center">Turnouts</th>
                                <th>Coordinates</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="loading"><td colspan="6" class="text-center text-muted py-4">Loading...</td></tr>
                            <tr v-else-if="rows.length === 0"><td colspan="6" class="text-center text-muted py-4">No stations.</td></tr>
                            <tr v-else v-for="row in rows" :key="row.id">
                                <td class="fw-semibold">{{ row.code }}</td>
                                <td>{{ row.name }}</td>
                                <td class="text-center">{{ row.nodes_count ?? 0 }}</td>
                                <td class="text-center">{{ row.turnouts_count ?? 0 }}</td>
                                <td class="small text-muted">{{ row.latitude ?? '—' }}, {{ row.longitude ?? '—' }}</td>
                                <td class="text-end">
                                    <button v-if="auth.can('stations.manage')" class="btn btn-sm btn-outline-secondary me-1" @click="openEdit(row)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button v-if="auth.can('stations.manage')" class="btn btn-sm btn-outline-danger" @click="askDelete(row)">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <BaseModal :show="modal.open" :title="modal.mode === 'create' ? 'New station' : 'Edit station'" @close="modal.open = false">
            <form @submit.prevent="submit">
                <div v-if="modal.errors._" class="alert alert-danger py-2 small">{{ modal.errors._[0] }}</div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small">Code</label>
                        <input v-model="form.code" class="form-control" :class="{ 'is-invalid': modal.errors.code }" />
                        <div v-if="modal.errors.code" class="invalid-feedback">{{ modal.errors.code[0] }}</div>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label small">Name</label>
                        <input v-model="form.name" class="form-control" :class="{ 'is-invalid': modal.errors.name }" />
                        <div v-if="modal.errors.name" class="invalid-feedback">{{ modal.errors.name[0] }}</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Latitude</label>
                        <input v-model="form.latitude" type="number" step="0.0000001" class="form-control" :class="{ 'is-invalid': modal.errors.latitude }" />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Longitude</label>
                        <input v-model="form.longitude" type="number" step="0.0000001" class="form-control" :class="{ 'is-invalid': modal.errors.longitude }" />
                    </div>
                    <div class="col-12">
                        <label class="form-label small">Description</label>
                        <textarea v-model="form.description" rows="3" class="form-control" :class="{ 'is-invalid': modal.errors.description }"></textarea>
                    </div>
                </div>
            </form>
            <template #footer>
                <button class="btn btn-secondary" @click="modal.open = false">Cancel</button>
                <button class="btn btn-primary" :disabled="modal.submitting" @click="submit">Save</button>
            </template>
        </BaseModal>

        <BaseModal :show="confirm.open" title="Delete station" size="sm" @close="confirm.open = false">
            <p class="mb-0">Delete station <strong>{{ confirm.target?.code }}</strong>?</p>
            <template #footer>
                <button class="btn btn-secondary" @click="confirm.open = false">Cancel</button>
                <button class="btn btn-danger" @click="doDelete">Delete</button>
            </template>
        </BaseModal>
    </div>
</template>
