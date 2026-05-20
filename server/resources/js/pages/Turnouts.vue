<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useAuthStore } from '@/stores/auth';
import linesApi from '@/services/lines';
import turnoutsApi from '@/services/turnouts';
import stationsApi from '@/services/stations';
import BaseModal from '@/components/BaseModal.vue';

const auth = useAuthStore();
const rows = ref([]);
const stations = ref([]);
const lines = ref([]);
const loading = ref(false);
const search = ref('');
const stationFilter = ref('');
const lineFilter = ref('');
const error = ref(null);
const meta = reactive({ current_page: 1, last_page: 1, total: 0, per_page: 15 });
const modal = reactive({ open: false, mode: 'create', submitting: false, errors: {} });
const form = reactive({
    id: null,
    station_id: '',
    code: '',
    name: '',
    description: '',
    type: '1:10',
    direction: 'Right',
    line_id: '',
    chainage: '',
    latitude: '',
    longitude: '',
});
const currentPhotoUrl = ref('');
const photoPreviewUrl = ref('');
const photoFile = ref(null);
const confirm = reactive({ open: false, target: null });
const photoViewer = reactive({ open: false, url: '', code: '' });

async function fetchStations() {
    const { data } = await stationsApi.list({ per_page: 100 });
    stations.value = data.data ?? [];
}

async function fetchLines() {
    const { data } = await linesApi.list({ per_page: 100 });
    lines.value = data.data ?? [];
}

async function fetchRows(page = 1) {
    loading.value = true;
    error.value = null;
    try {
        const { data } = await turnoutsApi.list({
            page,
            per_page: meta.per_page,
            q: search.value || undefined,
            station_id: stationFilter.value || undefined,
            line_id: lineFilter.value || undefined,
        });
        rows.value = data.data ?? [];
        Object.assign(meta, data.meta ?? {});
    } catch (e) {
        error.value = `Failed to load turnouts (status ${e.response?.status ?? 'n/a'}).`;
    } finally {
        loading.value = false;
    }
}

function resetPhotoState() {
    photoFile.value = null;
    photoPreviewUrl.value = '';
}

function openCreate() {
    Object.assign(form, {
        id: null,
        station_id: stations.value[0]?.id ?? '',
        code: '',
        name: '',
        description: '',
        type: '1:10',
        direction: 'Right',
        line_id: lines.value[0]?.id ?? '',
        chainage: '',
        latitude: '',
        longitude: '',
    });
    currentPhotoUrl.value = '';
    resetPhotoState();
    modal.mode = 'create';
    modal.errors = {};
    modal.open = true;
}

function openEdit(row) {
    Object.assign(form, {
        id: row.id,
        station_id: row.station_id,
        code: row.code ?? '',
        name: row.name ?? '',
        description: row.description ?? '',
        type: row.type ?? '1:10',
        direction: row.direction ?? 'Right',
        line_id: row.line_id ?? '',
        chainage: row.chainage ?? '',
        latitude: row.latitude ?? '',
        longitude: row.longitude ?? '',
    });
    currentPhotoUrl.value = row.photo_url ?? '';
    resetPhotoState();
    modal.mode = 'edit';
    modal.errors = {};
    modal.open = true;
}

function handlePhotoChange(event) {
    photoFile.value = event.target.files?.[0] ?? null;
    photoPreviewUrl.value = photoFile.value ? URL.createObjectURL(photoFile.value) : '';
}

function payload() {
    return {
        station_id: Number(form.station_id),
        code: form.code,
        name: form.name,
        description: form.description || null,
        type: form.type || null,
        direction: form.direction || null,
        line_id: form.line_id === '' ? null : Number(form.line_id),
        chainage: form.chainage === '' ? null : Number(form.chainage),
        latitude: form.latitude === '' ? null : Number(form.latitude),
        longitude: form.longitude === '' ? null : Number(form.longitude),
    };
}

async function submit() {
    modal.submitting = true;
    modal.errors = {};
    try {
        const response = modal.mode === 'create'
            ? await turnoutsApi.create(payload())
            : await turnoutsApi.update(form.id, payload());

        if (photoFile.value && auth.can('turnouts.manage')) {
            await turnoutsApi.uploadPhoto(response.data.data.id, photoFile.value);
        }

        modal.open = false;
        await fetchRows(meta.current_page);
    } catch (e) {
        modal.errors = e.response?.status === 422 ? (e.response.data.errors ?? {}) : { _: ['Failed to save turnout.'] };
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
        await turnoutsApi.remove(confirm.target.id);
        confirm.open = false;
        confirm.target = null;
        await fetchRows(meta.current_page);
    } catch (e) {
        alert(e.response?.data?.message ?? 'Delete failed.');
    }
}

function openPhotoViewer(row) {
    if (!row.photo_url) return;
    photoViewer.url = row.photo_url;
    photoViewer.code = row.code ?? '';
    photoViewer.open = true;
}

onMounted(async () => {
    await fetchStations();
    await fetchLines();
    await fetchRows();
});
</script>

<template>
    <div>
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h1 class="h4 mb-0">Turnout Management</h1>
                <div class="small text-muted">CRUD turnout assets and photo records.</div>
            </div>
            <button v-if="auth.can('turnouts.manage')" class="btn btn-primary btn-sm" @click="openCreate">
                <i class="bi bi-plus-lg me-1"></i> New turnout
            </button>
        </div>

        <div v-if="error" class="alert alert-danger py-2 small">{{ error }}</div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <input v-model="search" type="search" class="form-control form-control-sm" placeholder="Search code or name"
                           style="max-width: 320px" @keyup.enter="fetchRows(1)" />
                    <select v-model="stationFilter" class="form-select form-select-sm" style="max-width: 220px">
                        <option value="">All stations</option>
                        <option v-for="station in stations" :key="station.id" :value="station.id">{{ station.code }} - {{ station.name }}</option>
                    </select>
                    <select v-model="lineFilter" class="form-select form-select-sm" style="max-width: 220px">
                        <option value="">All lines</option>
                        <option v-for="line in lines" :key="line.id" :value="line.id">{{ line.code }} - {{ line.name }}</option>
                    </select>
                    <button class="btn btn-outline-secondary btn-sm" @click="fetchRows(1)"><i class="bi bi-search"></i></button>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Station</th>
                                <th>Type</th>
                                <th>Direction</th>
                                <th>Line</th>
                                <th>Chainage</th>
                                <th>Photo</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="loading"><td colspan="9" class="text-center text-muted py-4">Loading...</td></tr>
                            <tr v-else-if="rows.length === 0"><td colspan="9" class="text-center text-muted py-4">No turnouts.</td></tr>
                            <tr v-else v-for="row in rows" :key="row.id">
                                <td class="fw-semibold">{{ row.code }}</td>
                                <td>{{ row.name }}</td>
                                <td>{{ row.station?.code }} <span class="text-muted">{{ row.station?.name }}</span></td>
                                <td>{{ row.type || '-' }}</td>
                                <td>{{ row.direction || '-' }}</td>
                                <td>{{ row.line ? `${row.line.code} - ${row.line.name}` : '-' }}</td>
                                <td>{{ row.chainage ?? '-' }}</td>
                                <td>
                                    <button v-if="row.photo_url" type="button" class="btn btn-sm btn-outline-secondary" @click="openPhotoViewer(row)">
                                        <i class="bi bi-image"></i>
                                    </button>
                                    <span v-else class="small text-muted">-</span>
                                </td>
                                <td class="text-end">
                                    <button v-if="auth.can('turnouts.manage')" class="btn btn-sm btn-outline-secondary me-1" @click="openEdit(row)"><i class="bi bi-pencil"></i></button>
                                    <button v-if="auth.can('turnouts.manage')" class="btn btn-sm btn-outline-danger" @click="askDelete(row)"><i class="bi bi-trash"></i></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <BaseModal :show="modal.open" :title="modal.mode === 'create' ? 'New turnout' : 'Edit turnout'" size="xl" @close="modal.open = false">
            <form @submit.prevent="submit">
                <div v-if="modal.errors._" class="alert alert-danger py-2 small">{{ modal.errors._[0] }}</div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small">Station</label>
                        <select v-model="form.station_id" class="form-select" :class="{ 'is-invalid': modal.errors.station_id }">
                            <option value="">Select station</option>
                            <option v-for="station in stations" :key="station.id" :value="station.id">{{ station.code }} - {{ station.name }}</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Code</label>
                        <input v-model="form.code" class="form-control" :class="{ 'is-invalid': modal.errors.code }" />
                        <div v-if="modal.errors.code" class="invalid-feedback">{{ modal.errors.code[0] }}</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Name</label>
                        <input v-model="form.name" class="form-control" :class="{ 'is-invalid': modal.errors.name }" />
                        <div v-if="modal.errors.name" class="invalid-feedback">{{ modal.errors.name[0] }}</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Type</label>
                        <select v-model="form.type" class="form-select" :class="{ 'is-invalid': modal.errors.type }">
                            <option value="1:10">1:10</option>
                            <option value="1:8">1:8</option>
                        </select>
                        <div v-if="modal.errors.type" class="invalid-feedback">{{ modal.errors.type[0] }}</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Direction</label>
                        <select v-model="form.direction" class="form-select" :class="{ 'is-invalid': modal.errors.direction }">
                            <option value="Right">Right</option>
                            <option value="Left">Left</option>
                        </select>
                        <div v-if="modal.errors.direction" class="invalid-feedback">{{ modal.errors.direction[0] }}</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Line</label>
                        <select v-model="form.line_id" class="form-select" :class="{ 'is-invalid': modal.errors.line_id }">
                            <option value="">Select line</option>
                            <option v-for="line in lines" :key="line.id" :value="line.id">{{ line.code }} - {{ line.name }}</option>
                        </select>
                        <div v-if="modal.errors.line_id" class="invalid-feedback">{{ modal.errors.line_id[0] }}</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Chainage (meter)</label>
                        <input v-model="form.chainage" type="number" step="0.01" min="0" class="form-control" :class="{ 'is-invalid': modal.errors.chainage }" />
                        <div v-if="modal.errors.chainage" class="invalid-feedback">{{ modal.errors.chainage[0] }}</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Latitude</label>
                        <input v-model="form.latitude" type="number" step="0.0000001" class="form-control" />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Longitude</label>
                        <input v-model="form.longitude" type="number" step="0.0000001" class="form-control" />
                    </div>
                    <div class="col-12">
                        <label class="form-label small">Description</label>
                        <textarea v-model="form.description" rows="3" class="form-control"></textarea>
                    </div>
                    <div class="col-12" v-if="auth.can('turnouts.manage')">
                        <label class="form-label small">Photo</label>
                        <input type="file" class="form-control" accept=".jpg,.jpeg,.png,.webp,image/*" @change="handlePhotoChange" />
                        <div class="small text-muted mt-2">
                            <span v-if="photoFile">{{ photoFile.name }}</span>
                            <span v-else-if="currentPhotoUrl">Current photo attached.</span>
                            <span v-else>No photo uploaded.</span>
                        </div>
                        <a v-if="currentPhotoUrl" :href="currentPhotoUrl" target="_blank" class="small">Open current photo</a>
                        <div v-if="photoPreviewUrl" class="mt-3">
                            <div class="small text-muted mb-2">Preview before save</div>
                            <img :src="photoPreviewUrl" alt="Turnout preview" class="img-thumbnail" style="max-height: 220px" />
                        </div>
                    </div>
                </div>
            </form>
            <template #footer>
                <button class="btn btn-secondary" @click="modal.open = false">Cancel</button>
                <button class="btn btn-primary" :disabled="modal.submitting" @click="submit">Save</button>
            </template>
        </BaseModal>

        <BaseModal :show="confirm.open" title="Delete turnout" size="sm" @close="confirm.open = false">
            <p class="mb-0">Delete turnout <strong>{{ confirm.target?.code }}</strong>?</p>
            <template #footer>
                <button class="btn btn-secondary" @click="confirm.open = false">Cancel</button>
                <button class="btn btn-danger" @click="doDelete">Delete</button>
            </template>
        </BaseModal>

        <BaseModal :show="photoViewer.open" title="Turnout Photo" size="lg" @close="photoViewer.open = false">
            <div v-if="photoViewer.url" class="text-center">
                <img :src="photoViewer.url" :alt="photoViewer.code" class="img-fluid rounded border" style="max-height: 70vh" />
                <div class="mt-3 fw-semibold">{{ photoViewer.code || '-' }}</div>
            </div>
        </BaseModal>
    </div>
</template>
