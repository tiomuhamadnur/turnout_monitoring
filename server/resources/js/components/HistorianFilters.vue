<script setup>
import { onMounted, ref, reactive, watch } from 'vue';
import stationsApi from '@/services/stations';
import nodesApi    from '@/services/nodes';

/**
 * Shared filter strip used by the historian pages (TurnoutEvents,
 * TurnoutAlarms, DeviceHealthLogs). Each page picks which fields to
 * expose via the `fields` prop — keeps one place to evolve filter UI.
 *
 * Emits `apply` with the filter payload whenever the user clicks "Filter".
 * The parent owns the page=1 reset and the fetch call so filters can stay
 * dumb / shareable.
 */
const props = defineProps({
    modelValue: { type: Object, default: () => ({}) },
    fields: {
        type: Array,
        // any of: 'station', 'state', 'active', 'alarm_type', 'mqtt_status',
        //         'transitions_only', 'search', 'date_range'
        default: () => ['search', 'date_range'],
    },
});
const emit = defineEmits(['update:modelValue', 'apply', 'reset']);

const stations = ref([]);
const local = reactive({ ...props.modelValue });

// Re-sync if the parent ever overwrites modelValue.
watch(() => props.modelValue, (v) => {
    Object.assign(local, v);
}, { deep: true });

onMounted(async () => {
    if (props.fields.includes('station')) {
        try {
            const { data } = await stationsApi.list({ per_page: 100 });
            stations.value = data.data ?? data ?? [];
        } catch (_) { stations.value = []; }
    }
});

function apply() {
    emit('update:modelValue', { ...local });
    emit('apply', { ...local });
}

function reset() {
    for (const key of Object.keys(local)) local[key] = '';
    emit('update:modelValue', { ...local });
    emit('reset', { ...local });
}

function has(field) { return props.fields.includes(field); }
</script>

<template>
    <div class="historian-filters card mb-3">
        <div class="card-body py-3">
            <div class="row g-2 align-items-end">
                <div v-if="has('search')" class="col-12 col-md-3">
                    <label class="form-label small mb-1">Search code / name</label>
                    <input v-model="local.search" type="text"
                           class="form-control form-control-sm"
                           placeholder="W1110, Turnout A..."
                           @keydown.enter="apply" />
                </div>

                <div v-if="has('station')" class="col-6 col-md-2">
                    <label class="form-label small mb-1">Station</label>
                    <select v-model="local.station_id" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option v-for="s in stations" :key="s.id" :value="s.id">{{ s.code }}</option>
                    </select>
                </div>

                <div v-if="has('state')" class="col-6 col-md-2">
                    <label class="form-label small mb-1">State</label>
                    <select v-model="local.state" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="NORMAL">NORMAL</option>
                        <option value="REVERSE">REVERSE</option>
                        <option value="FAILURE">FAILURE</option>
                    </select>
                </div>

                <div v-if="has('active')" class="col-6 col-md-2">
                    <label class="form-label small mb-1">Status</label>
                    <select v-model="local.active" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option :value="true">Active</option>
                        <option :value="false">Resolved</option>
                    </select>
                </div>

                <div v-if="has('alarm_type')" class="col-6 col-md-2">
                    <label class="form-label small mb-1">Alarm type</label>
                    <select v-model="local.alarm_type" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="failure">Failure</option>
                    </select>
                </div>

                <div v-if="has('mqtt_status')" class="col-6 col-md-2">
                    <label class="form-label small mb-1">MQTT</label>
                    <select v-model="local.mqtt_status" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="connected">connected</option>
                        <option value="disconnected">disconnected</option>
                        <option value="unknown">unknown</option>
                    </select>
                </div>

                <div v-if="has('date_range')" class="col-6 col-md-2">
                    <label class="form-label small mb-1">From</label>
                    <input v-model="local.from" type="date" class="form-control form-control-sm" />
                </div>
                <div v-if="has('date_range')" class="col-6 col-md-2">
                    <label class="form-label small mb-1">To</label>
                    <input v-model="local.to" type="date" class="form-control form-control-sm" />
                </div>

                <div v-if="has('transitions_only')" class="col-6 col-md-2">
                    <label class="form-check form-switch small mt-3 mb-1">
                        <input v-model="local.transitions_only" type="checkbox" class="form-check-input" />
                        <span class="form-check-label">Transitions only</span>
                    </label>
                </div>

                <div class="col-12 col-md-2 d-flex gap-1">
                    <button class="btn btn-primary btn-sm flex-grow-1" @click="apply">
                        <i class="bi bi-funnel-fill me-1"></i> Filter
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" @click="reset" title="Reset">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </button>
                </div>
            </div>
            <div v-if="$slots.extra" class="mt-2">
                <slot name="extra" />
            </div>
        </div>
    </div>
</template>
