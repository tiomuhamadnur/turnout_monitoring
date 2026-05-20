<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useAuthStore } from '@/stores/auth';
import usersApi from '@/services/users';
import rolesApi from '@/services/roles';
import BaseModal from '@/components/BaseModal.vue';

const auth = useAuthStore();

const users = ref([]);
const roles = ref([]);
const meta = reactive({ current_page: 1, last_page: 1, total: 0, per_page: 15 });
const loading = ref(false);
const search = ref('');
const fetchError = ref(null);

const modal = reactive({ open: false, mode: 'create', submitting: false, errors: {} });
const form = reactive({ id: null, name: '', email: '', password: '', is_super_admin: false, roles: [] });

const confirm = reactive({ open: false, target: null });

async function fetchUsers(page = 1) {
    loading.value = true;
    fetchError.value = null;
    try {
        const { data } = await usersApi.list({ page, per_page: meta.per_page, q: search.value || undefined });
        users.value = Array.isArray(data?.data) ? data.data : [];
        Object.assign(meta, data.meta ?? {});
        console.debug('[Users] fetched', { count: users.value.length, raw: data });
    } catch (e) {
        const status = e.response?.status ?? '(no response)';
        fetchError.value = `Failed to load users (status ${status}). See console for details.`;
        console.error('[Users] fetch failed:', status, e.response?.data, e);
        throw e;
    } finally {
        loading.value = false;
    }
}

async function fetchRoles() {
    try {
        const { data } = await rolesApi.list({ per_page: 100 });
        roles.value = Array.isArray(data?.data) ? data.data : [];
    } catch (e) {
        console.error('[Users] roles fetch failed:', e.response?.status, e.response?.data, e);
    }
}

function openCreate() {
    Object.assign(form, { id: null, name: '', email: '', password: '', is_super_admin: false, roles: [] });
    modal.mode = 'create';
    modal.errors = {};
    modal.open = true;
}

function openEdit(user) {
    Object.assign(form, {
        id: user.id,
        name: user.name,
        email: user.email,
        password: '',
        is_super_admin: user.is_super_admin,
        roles: [...(user.roles ?? [])],
    });
    modal.mode = 'edit';
    modal.errors = {};
    modal.open = true;
}

async function submit() {
    modal.submitting = true;
    modal.errors = {};
    try {
        const payload = {
            name: form.name,
            email: form.email,
            is_super_admin: form.is_super_admin,
            roles: form.roles,
        };
        if (form.password) payload.password = form.password;

        if (modal.mode === 'create') {
            payload.password = form.password;
            const { data } = await usersApi.create(payload);
            // Optimistic insert so the new row is visible even if the
            // pagination refetch below races or fails.
            users.value = [data.data, ...users.value];
        } else {
            const { data } = await usersApi.update(form.id, payload);
            const idx = users.value.findIndex(u => u.id === form.id);
            if (idx !== -1) users.value.splice(idx, 1, data.data);
        }

        modal.open = false;
    } catch (e) {
        if (e.response?.status === 422) {
            modal.errors = e.response.data.errors ?? {};
        } else {
            modal.errors = { _: [e.response?.data?.message ?? 'Failed to save user.'] };
        }
        return;
    } finally {
        modal.submitting = false;
    }

    // Reconcile against server pagination. A failed refresh must not surface
    // as a save failure — the optimistic update above already updated the UI.
    fetchUsers(meta.current_page).catch(err => console.warn('Users refresh failed:', err));
}

function askDelete(user) {
    confirm.target = user;
    confirm.open = true;
}

async function doDelete() {
    if (!confirm.target) return;
    const id = confirm.target.id;
    try {
        await usersApi.remove(id);
        users.value = users.value.filter(u => u.id !== id);
        confirm.open = false;
        confirm.target = null;
    } catch (e) {
        alert(e.response?.data?.message ?? 'Delete failed.');
        return;
    }
    fetchUsers(meta.current_page).catch(err => console.warn('Users refresh failed:', err));
}

function toggleRole(name) {
    const i = form.roles.indexOf(name);
    if (i === -1) form.roles.push(name);
    else form.roles.splice(i, 1);
}

onMounted(async () => {
    await Promise.allSettled([fetchUsers(), fetchRoles()]);
});
</script>

<template>
    <div>
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h1 class="h4 mb-0">User Management</h1>
            <button v-if="auth.can('users.create')"
                    class="btn btn-primary btn-sm"
                    @click="openCreate">
                <i class="bi bi-plus-lg me-1"></i> New user
            </button>
        </div>

        <div v-if="fetchError" class="alert alert-danger d-flex align-items-center gap-2 py-2 small">
            <i class="bi bi-exclamation-triangle"></i>
            <span class="flex-grow-1">{{ fetchError }}</span>
            <button class="btn btn-sm btn-outline-danger" @click="fetchUsers(1)">Retry</button>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex mb-3 gap-2">
                    <input v-model="search"
                           type="search"
                           class="form-control form-control-sm"
                           placeholder="Search name or email"
                           style="max-width: 320px"
                           @keyup.enter="fetchUsers(1)" />
                    <button class="btn btn-outline-secondary btn-sm" @click="fetchUsers(1)">
                        <i class="bi bi-search"></i>
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Roles</th>
                                <th>Super admin</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="loading">
                                <td colspan="5" class="text-center text-muted py-4">Loading…</td>
                            </tr>
                            <tr v-else-if="users.length === 0">
                                <td colspan="5" class="text-center text-muted py-4">No users.</td>
                            </tr>
                            <tr v-else v-for="u in users" :key="u.id">
                                <td class="fw-medium">{{ u.name }}</td>
                                <td>{{ u.email }}</td>
                                <td>
                                    <span v-for="r in (u.roles ?? [])" :key="r"
                                          class="badge text-bg-secondary me-1">{{ r }}</span>
                                </td>
                                <td>
                                    <span v-if="u.is_super_admin" class="badge text-bg-warning">
                                        <i class="bi bi-shield-fill-check me-1"></i> Yes
                                    </span>
                                    <span v-else class="text-muted small">—</span>
                                </td>
                                <td class="text-end">
                                    <button v-if="auth.can('users.update')"
                                            class="btn btn-sm btn-outline-secondary me-1"
                                            @click="openEdit(u)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button v-if="auth.can('users.delete') && u.id !== auth.user?.id"
                                            class="btn btn-sm btn-outline-danger"
                                            @click="askDelete(u)">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="meta.last_page > 1" class="d-flex align-items-center justify-content-between small text-muted">
                    <div>Page {{ meta.current_page }} of {{ meta.last_page }} &middot; {{ meta.total }} total</div>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-secondary"
                                :disabled="meta.current_page <= 1"
                                @click="fetchUsers(meta.current_page - 1)">Prev</button>
                        <button class="btn btn-outline-secondary"
                                :disabled="meta.current_page >= meta.last_page"
                                @click="fetchUsers(meta.current_page + 1)">Next</button>
                    </div>
                </div>
            </div>
        </div>

        <BaseModal :show="modal.open" :title="modal.mode === 'create' ? 'New user' : 'Edit user'"
                   @close="modal.open = false">
            <form @submit.prevent="submit">
                <div v-if="modal.errors._" class="alert alert-danger py-2 small">{{ modal.errors._[0] }}</div>

                <div class="mb-3">
                    <label class="form-label small">Name</label>
                    <input v-model="form.name" class="form-control" :class="{ 'is-invalid': modal.errors.name }" />
                    <div v-if="modal.errors.name" class="invalid-feedback">{{ modal.errors.name[0] }}</div>
                </div>

                <div class="mb-3">
                    <label class="form-label small">Email</label>
                    <input v-model="form.email" type="email" class="form-control" :class="{ 'is-invalid': modal.errors.email }" />
                    <div v-if="modal.errors.email" class="invalid-feedback">{{ modal.errors.email[0] }}</div>
                </div>

                <div class="mb-3">
                    <label class="form-label small">
                        Password
                        <span v-if="modal.mode === 'edit'" class="text-muted">(leave blank to keep current)</span>
                    </label>
                    <input v-model="form.password" type="password" class="form-control"
                           :class="{ 'is-invalid': modal.errors.password }"
                           :required="modal.mode === 'create'" />
                    <div v-if="modal.errors.password" class="invalid-feedback">{{ modal.errors.password[0] }}</div>
                </div>

                <div class="mb-3">
                    <label class="form-label small">Roles</label>
                    <div class="d-flex flex-wrap gap-2">
                        <div v-for="r in roles" :key="r.id" class="form-check">
                            <input :id="`role-${r.id}`" type="checkbox"
                                   class="form-check-input"
                                   :checked="form.roles.includes(r.name)"
                                   @change="toggleRole(r.name)" />
                            <label :for="`role-${r.id}`" class="form-check-label small">{{ r.name }}</label>
                        </div>
                    </div>
                </div>

                <div class="form-check mb-3">
                    <input id="is_super" v-model="form.is_super_admin" type="checkbox" class="form-check-input" />
                    <label for="is_super" class="form-check-label small">Grant super-admin (bypass all permission checks)</label>
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

        <BaseModal :show="confirm.open" title="Delete user" size="sm" @close="confirm.open = false">
            <p class="mb-0">Delete user <strong>{{ confirm.target?.name }}</strong>?</p>
            <p class="text-muted small mt-2 mb-0">This cannot be undone.</p>

            <template #footer>
                <button class="btn btn-secondary" @click="confirm.open = false">Cancel</button>
                <button class="btn btn-danger" @click="doDelete">Delete</button>
            </template>
        </BaseModal>
    </div>
</template>
