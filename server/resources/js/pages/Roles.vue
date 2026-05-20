<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useAuthStore } from '@/stores/auth';
import rolesApi from '@/services/roles';
import BaseModal from '@/components/BaseModal.vue';

const auth = useAuthStore();

const roles = ref([]);
const permissionsByGroup = ref({});       // { users: [...], roles: [...], ... }
const loading = ref(false);
const fetchError = ref(null);

const modal = reactive({ open: false, mode: 'create', submitting: false, errors: {} });
const form = reactive({ id: null, name: '', permissions: [] });

const confirm = reactive({ open: false, target: null });

async function fetchRoles() {
    loading.value = true;
    fetchError.value = null;
    try {
        const { data } = await rolesApi.list({ per_page: 100 });
        roles.value = Array.isArray(data?.data) ? data.data : [];
        // Helpful while diagnosing: lets us see in the console exactly what
        // the API returned vs what the table renders.
        console.debug('[Roles] fetched', { count: roles.value.length, raw: data });
    } catch (e) {
        const status = e.response?.status ?? '(no response)';
        const body   = e.response?.data ?? e.message;
        fetchError.value = `Failed to load roles (status ${status}). See console for details.`;
        console.error('[Roles] fetch failed:', status, body, e);
        throw e;
    } finally {
        loading.value = false;
    }
}

async function fetchPermissions() {
    try {
        const { data } = await rolesApi.permissions();
        permissionsByGroup.value = data?.data ?? {};
    } catch (e) {
        console.error('[Roles] permissions fetch failed:', e.response?.status, e.response?.data, e);
    }
}

function openCreate() {
    Object.assign(form, { id: null, name: '', permissions: [] });
    modal.mode = 'create';
    modal.errors = {};
    modal.open = true;
}

function openEdit(role) {
    Object.assign(form, {
        id: role.id,
        name: role.name,
        permissions: [...(role.permissions ?? [])],
    });
    modal.mode = 'edit';
    modal.errors = {};
    modal.open = true;
}

function togglePerm(name) {
    const i = form.permissions.indexOf(name);
    if (i === -1) form.permissions.push(name);
    else form.permissions.splice(i, 1);
}

function toggleGroup(group, perms) {
    const allSelected = perms.every(p => form.permissions.includes(p));
    if (allSelected) {
        form.permissions = form.permissions.filter(p => !perms.includes(p));
    } else {
        const set = new Set([...form.permissions, ...perms]);
        form.permissions = [...set];
    }
}

async function submit() {
    modal.submitting = true;
    modal.errors = {};
    try {
        const payload = { permissions: form.permissions };
        if (modal.mode === 'create' || form.name) payload.name = form.name;

        if (modal.mode === 'create') {
            const { data } = await rolesApi.create(payload);
            // Optimistic insert, kept sorted by name to match server ordering.
            roles.value = [...roles.value, data.data]
                .sort((a, b) => a.name.localeCompare(b.name));
        } else {
            const { data } = await rolesApi.update(form.id, payload);
            const idx = roles.value.findIndex(r => r.id === form.id);
            if (idx !== -1) roles.value.splice(idx, 1, data.data);
        }

        modal.open = false;
    } catch (e) {
        if (e.response?.status === 422) {
            modal.errors = e.response.data.errors ?? {};
        } else {
            modal.errors = { _: [e.response?.data?.message ?? 'Failed to save role.'] };
        }
        return;
    } finally {
        modal.submitting = false;
    }

    // Background reconcile. A failed refresh must not look like a failed save —
    // the optimistic update above is the source of truth for the UI.
    fetchRoles().catch(err => console.warn('Roles refresh failed:', err));
}

function askDelete(role) {
    confirm.target = role;
    confirm.open = true;
}

async function doDelete() {
    if (!confirm.target) return;
    const id = confirm.target.id;
    try {
        await rolesApi.remove(id);
        // Optimistic removal.
        roles.value = roles.value.filter(r => r.id !== id);
        confirm.open = false;
        confirm.target = null;
    } catch (e) {
        alert(e.response?.data?.message ?? 'Delete failed.');
        return;
    }
    fetchRoles().catch(err => console.warn('Roles refresh failed:', err));
}

onMounted(async () => {
    // Don't let one failure cancel the other.
    await Promise.allSettled([fetchRoles(), fetchPermissions()]);
});
</script>

<template>
    <div>
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h1 class="h4 mb-0">Role Management</h1>
            <button v-if="auth.can('roles.create')"
                    class="btn btn-primary btn-sm"
                    @click="openCreate">
                <i class="bi bi-plus-lg me-1"></i> New role
            </button>
        </div>

        <div v-if="fetchError" class="alert alert-danger d-flex align-items-center gap-2 py-2 small">
            <i class="bi bi-exclamation-triangle"></i>
            <span class="flex-grow-1">{{ fetchError }}</span>
            <button class="btn btn-sm btn-outline-danger" @click="fetchRoles()">Retry</button>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Permissions</th>
                                <th class="text-center">Users</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="loading">
                                <td colspan="4" class="text-center text-muted py-4">Loading…</td>
                            </tr>
                            <tr v-else-if="roles.length === 0">
                                <td colspan="4" class="text-center text-muted py-4">No roles.</td>
                            </tr>
                            <tr v-else v-for="r in roles" :key="r.id">
                                <td class="fw-medium">
                                    {{ r.name }}
                                    <span v-if="r.name === 'super-admin'"
                                          class="badge text-bg-warning ms-2 small">protected</span>
                                </td>
                                <td>
                                    <span v-if="(r.permissions ?? []).length === 0 && r.name !== 'super-admin'"
                                          class="text-muted small">—</span>
                                    <span v-else-if="r.name === 'super-admin'"
                                          class="text-muted small fst-italic">all (via gate bypass)</span>
                                    <template v-else>
                                        <span v-for="p in (r.permissions ?? []).slice(0, 6)" :key="p"
                                              class="badge text-bg-secondary me-1 mb-1">{{ p }}</span>
                                        <span v-if="(r.permissions ?? []).length > 6"
                                              class="badge text-bg-light text-muted">
                                            +{{ r.permissions.length - 6 }} more
                                        </span>
                                    </template>
                                </td>
                                <td class="text-center">{{ r.users_count ?? 0 }}</td>
                                <td class="text-end">
                                    <button v-if="auth.can('roles.update')"
                                            class="btn btn-sm btn-outline-secondary me-1"
                                            @click="openEdit(r)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button v-if="auth.can('roles.delete') && r.name !== 'super-admin'"
                                            class="btn btn-sm btn-outline-danger"
                                            @click="askDelete(r)">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <BaseModal :show="modal.open"
                   :title="modal.mode === 'create' ? 'New role' : `Edit role: ${form.name}`"
                   size="lg"
                   @close="modal.open = false">
            <form @submit.prevent="submit">
                <div v-if="modal.errors._" class="alert alert-danger py-2 small">{{ modal.errors._[0] }}</div>

                <div class="mb-3">
                    <label class="form-label small">Role name</label>
                    <input v-model="form.name"
                           class="form-control"
                           :class="{ 'is-invalid': modal.errors.name }"
                           :disabled="form.id && form.name === 'super-admin'" />
                    <div v-if="modal.errors.name" class="invalid-feedback">{{ modal.errors.name[0] }}</div>
                </div>

                <div class="mb-2 small text-muted">Permissions</div>
                <div v-for="(perms, group) in permissionsByGroup" :key="group"
                     class="border rounded p-2 mb-2">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="fw-semibold text-capitalize">{{ group }}</div>
                        <button type="button" class="btn btn-link btn-sm py-0"
                                @click="toggleGroup(group, perms)">
                            Toggle all
                        </button>
                    </div>
                    <div class="d-flex flex-wrap gap-3">
                        <div v-for="p in perms" :key="p" class="form-check">
                            <input :id="`p-${p}`" type="checkbox"
                                   class="form-check-input"
                                   :checked="form.permissions.includes(p)"
                                   @change="togglePerm(p)" />
                            <label :for="`p-${p}`" class="form-check-label small font-monospace">{{ p }}</label>
                        </div>
                    </div>
                </div>
            </form>

            <template #footer>
                <button class="btn btn-secondary" @click="modal.open = false">Cancel</button>
                <button class="btn btn-primary" :disabled="modal.submitting" @click="submit">
                    <span v-if="modal.submitting" class="spinner-border spinner-border-sm me-2"></span>
                    Save
                </button>
            </template>
        </BaseModal>

        <BaseModal :show="confirm.open" title="Delete role" size="sm" @close="confirm.open = false">
            <p class="mb-0">Delete role <strong>{{ confirm.target?.name }}</strong>?</p>
            <p v-if="confirm.target?.users_count" class="text-danger small mt-2 mb-0">
                {{ confirm.target.users_count }} user(s) currently have this role.
            </p>

            <template #footer>
                <button class="btn btn-secondary" @click="confirm.open = false">Cancel</button>
                <button class="btn btn-danger" @click="doDelete">Delete</button>
            </template>
        </BaseModal>
    </div>
</template>
