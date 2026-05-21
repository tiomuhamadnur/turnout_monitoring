<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import notificationsApi from '@/services/notificationChannels';
import BaseModal from '@/components/BaseModal.vue';
import { formatTimestamp } from '@/utils/date';

const channels = ref([]);
const loading  = ref(false);
const error    = ref(null);

const editing  = ref(null);          // channel under edit, or null
const showForm = ref(false);
const saving   = ref(false);
const testingId = ref(null);
const testResult = ref(null);

const blank = () => ({
    type: 'webhook',
    name: '',
    is_enabled: true,
    triggers: ['alarm.raised'],
    config: {
        // shape mutates with `type` — bound via v-model + per-type template
        url: '',
        method: 'POST',
        timeout: 5,
        headers: {},
        recipients: [],
        subject_prefix: '[MRT Turnout]',
        provider_url: '',
        auth_header: '',
        to: [],
        field_to: 'to',
        field_text: 'message',
    },
});

const form = reactive(blank());

const triggerOptions = [
    { value: 'alarm.raised',  label: 'Alarm raised' },
    { value: 'alarm.cleared', label: 'Alarm cleared' },
    { value: 'test',          label: 'Manual test' },
];

const recipientsText = computed({
    get: () => (form.config.recipients || []).join(', '),
    set: (v) => { form.config.recipients = v.split(',').map(s => s.trim()).filter(Boolean); },
});
const toText = computed({
    get: () => (form.config.to || []).join(', '),
    set: (v) => { form.config.to = v.split(',').map(s => s.trim()).filter(Boolean); },
});
const headersText = computed({
    get: () => JSON.stringify(form.config.headers || {}, null, 2),
    set: (v) => {
        try { form.config.headers = JSON.parse(v || '{}'); }
        catch (_) { /* keep last valid */ }
    },
});

async function load() {
    loading.value = true; error.value = null;
    try {
        const { data } = await notificationsApi.list();
        channels.value = data.data ?? [];
    } catch (e) {
        error.value = `Failed to load notification channels (status ${e.response?.status ?? 'n/a'}).`;
    } finally {
        loading.value = false;
    }
}

function openCreate() {
    Object.assign(form, blank());
    editing.value = null;
    showForm.value = true;
}

function openEdit(channel) {
    editing.value = channel;
    Object.assign(form, blank(), channel, { config: { ...blank().config, ...(channel.config || {}) } });
    showForm.value = true;
}

function closeForm() {
    showForm.value = false;
    editing.value = null;
}

function payload() {
    // Send only the fields relevant to the chosen type so server validation
    // doesn't trip on stale UI state.
    const pickByType = {
        webhook:  ['url', 'method', 'headers', 'timeout'],
        email:    ['recipients', 'subject_prefix'],
        whatsapp: ['provider_url', 'auth_header', 'to', 'field_to', 'field_text'],
    };
    const cfg = {};
    for (const k of pickByType[form.type] || []) {
        if (form.config[k] !== undefined && form.config[k] !== '') cfg[k] = form.config[k];
    }
    return {
        type: form.type,
        name: form.name,
        is_enabled: !!form.is_enabled,
        triggers: form.triggers,
        config: cfg,
    };
}

async function save() {
    saving.value = true; error.value = null;
    try {
        if (editing.value) {
            await notificationsApi.update(editing.value.id, payload());
        } else {
            await notificationsApi.create(payload());
        }
        closeForm();
        await load();
    } catch (e) {
        error.value = e?.response?.data?.message
                   || `Save failed (status ${e.response?.status ?? 'n/a'}).`;
    } finally {
        saving.value = false;
    }
}

async function remove(channel) {
    if (!confirm(`Delete channel "${channel.name}"?`)) return;
    await notificationsApi.remove(channel.id);
    await load();
}

async function runTest(channel) {
    testingId.value = channel.id;
    testResult.value = null;
    try {
        const { data } = await notificationsApi.test(channel.id);
        testResult.value = { id: channel.id, ...data.data };
    } catch (e) {
        testResult.value = { id: channel.id, status: 'failed', summary: e?.response?.data?.message || e.message };
    } finally {
        testingId.value = null;
    }
}

onMounted(load);
</script>

<template>
    <div class="d-flex flex-column gap-3">
        <div class="d-flex align-items-end justify-content-between gap-3 flex-wrap">
            <div>
                <h1 class="h4 mb-1">Settings · Notifications</h1>
                <p class="text-body-secondary mb-0 small">
                    Webhook / Email / WhatsApp channels for turnout alarm events.
                </p>
            </div>
            <button class="btn btn-primary btn-sm" @click="openCreate">
                <i class="bi bi-plus-lg me-1"></i> New channel
            </button>
        </div>

        <div v-if="error" class="alert alert-danger py-2 small mb-0">{{ error }}</div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Triggers</th>
                                <th>Last sent</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="loading"><td colspan="6" class="text-center text-muted py-4">Loading...</td></tr>
                            <tr v-else-if="channels.length === 0"><td colspan="6" class="text-center text-muted py-4">No channels configured.</td></tr>
                            <tr v-else v-for="c in channels" :key="c.id">
                                <td>
                                    <span class="badge" :class="c.is_enabled ? 'text-bg-success' : 'text-bg-secondary'">
                                        {{ c.is_enabled ? 'ENABLED' : 'DISABLED' }}
                                    </span>
                                </td>
                                <td class="fw-semibold">{{ c.name }}</td>
                                <td><span class="badge text-bg-info text-uppercase">{{ c.type }}</span></td>
                                <td class="small">
                                    <span v-for="t in (c.triggers && c.triggers.length ? c.triggers : ['(all)'])"
                                          :key="t"
                                          class="badge text-bg-light border me-1">{{ t }}</span>
                                </td>
                                <td class="small text-body-secondary">{{ c.last_sent_at ? formatTimestamp(c.last_sent_at) : '—' }}</td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-secondary me-1"
                                            :disabled="testingId === c.id"
                                            @click="runTest(c)">
                                        <span v-if="testingId === c.id" class="spinner-border spinner-border-sm"></span>
                                        <i v-else class="bi bi-send"></i>
                                        Test
                                    </button>
                                    <button class="btn btn-sm btn-outline-primary me-1" @click="openEdit(c)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" @click="remove(c)">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div v-if="testResult" class="alert"
             :class="testResult.status === 'sent' ? 'alert-success' : testResult.status === 'skipped' ? 'alert-warning' : 'alert-danger'">
            <strong>Test result:</strong>
            {{ testResult.status.toUpperCase() }} — {{ testResult.summary }}
        </div>

        <BaseModal :show="showForm" :title="editing ? 'Edit channel' : 'New channel'" size="lg" @close="closeForm">
            <div class="d-flex flex-column gap-3">
                <div class="row g-2">
                    <div class="col-12 col-md-4">
                        <label class="form-label small mb-1">Type</label>
                        <select v-model="form.type" class="form-select form-select-sm" :disabled="!!editing">
                            <option value="webhook">Webhook</option>
                            <option value="email">Email</option>
                            <option value="whatsapp">WhatsApp</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label small mb-1">Name</label>
                        <input v-model="form.name" type="text" class="form-control form-control-sm" placeholder="Ops Slack hook" />
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-check form-switch small mt-4">
                            <input v-model="form.is_enabled" type="checkbox" class="form-check-input" />
                            <span class="form-check-label">Enabled</span>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="form-label small mb-1">Triggers</label>
                    <div class="d-flex flex-wrap gap-3">
                        <label v-for="t in triggerOptions" :key="t.value" class="form-check small">
                            <input type="checkbox" class="form-check-input"
                                   :value="t.value"
                                   :checked="form.triggers.includes(t.value)"
                                   @change="(e) => {
                                       if (e.target.checked) form.triggers = [...new Set([...form.triggers, t.value])];
                                       else form.triggers = form.triggers.filter(x => x !== t.value);
                                   }" />
                            <span class="form-check-label">{{ t.label }}</span>
                        </label>
                    </div>
                    <div class="small text-body-secondary">Empty = subscribe to all events.</div>
                </div>

                <!-- WEBHOOK -->
                <template v-if="form.type === 'webhook'">
                    <div>
                        <label class="form-label small mb-1">URL</label>
                        <input v-model="form.config.url" type="url" class="form-control form-control-sm" placeholder="https://hooks.example.com/abc" />
                    </div>
                    <div class="row g-2">
                        <div class="col-6 col-md-4">
                            <label class="form-label small mb-1">Method</label>
                            <select v-model="form.config.method" class="form-select form-select-sm">
                                <option>POST</option>
                                <option>PUT</option>
                                <option>PATCH</option>
                                <option>GET</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-4">
                            <label class="form-label small mb-1">Timeout (s)</label>
                            <input v-model.number="form.config.timeout" type="number" min="1" max="30" class="form-control form-control-sm" />
                        </div>
                    </div>
                    <div>
                        <label class="form-label small mb-1">Headers (JSON)</label>
                        <textarea v-model="headersText" rows="4" class="form-control form-control-sm font-monospace small"></textarea>
                    </div>
                </template>

                <!-- EMAIL -->
                <template v-else-if="form.type === 'email'">
                    <div>
                        <label class="form-label small mb-1">Recipients (comma separated)</label>
                        <input v-model="recipientsText" type="text" class="form-control form-control-sm"
                               placeholder="ops@example.com, oncall@example.com" />
                    </div>
                    <div>
                        <label class="form-label small mb-1">Subject prefix</label>
                        <input v-model="form.config.subject_prefix" type="text" class="form-control form-control-sm" />
                    </div>
                </template>

                <!-- WHATSAPP -->
                <template v-else-if="form.type === 'whatsapp'">
                    <div>
                        <label class="form-label small mb-1">Provider URL</label>
                        <input v-model="form.config.provider_url" type="url" class="form-control form-control-sm"
                               placeholder="https://waha.example/api/sendText" />
                    </div>
                    <div>
                        <label class="form-label small mb-1">Auth header (optional)</label>
                        <input v-model="form.config.auth_header" type="text" class="form-control form-control-sm"
                               placeholder="Bearer xxxxxxx" />
                    </div>
                    <div>
                        <label class="form-label small mb-1">Recipients (comma separated WA numbers)</label>
                        <input v-model="toText" type="text" class="form-control form-control-sm"
                               placeholder="6281xxxxxxxx, 6281yyyyyyyy" />
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small mb-1">"to" field name</label>
                            <input v-model="form.config.field_to" type="text" class="form-control form-control-sm" />
                        </div>
                        <div class="col-6">
                            <label class="form-label small mb-1">"text" field name</label>
                            <input v-model="form.config.field_text" type="text" class="form-control form-control-sm" />
                        </div>
                    </div>
                </template>
            </div>

            <template #footer>
                <button class="btn btn-secondary" @click="closeForm">Cancel</button>
                <button class="btn btn-primary" :disabled="saving" @click="save">
                    <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                    Save
                </button>
            </template>
        </BaseModal>
    </div>
</template>
