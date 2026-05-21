<script setup>
import { ref } from 'vue';
import { downloadExport } from '@/utils/download';

/**
 * Two-button group rendered next to historian filters. Re-uses whatever
 * filter object the parent page already has, so Browse and Export always
 * agree on what they're showing.
 */
const props = defineProps({
    baseUrl: { type: String, required: true },   // '/api/exports/turnout-events'
    params:  { type: Object, default: () => ({}) },
});

const busy = ref(null);     // 'xlsx' | 'pdf' | null
const error = ref(null);

async function download(kind) {
    busy.value = kind;
    error.value = null;
    try {
        await downloadExport(`${props.baseUrl}.${kind}`, props.params);
    } catch (e) {
        error.value = e?.response?.data?.message
                   || `Export failed (status ${e.response?.status ?? 'n/a'}).`;
    } finally {
        busy.value = null;
    }
}
</script>

<template>
    <div class="d-inline-flex flex-column align-items-end gap-1">
        <div class="btn-group btn-group-sm">
            <button class="btn btn-outline-success" :disabled="busy" @click="download('xlsx')">
                <span v-if="busy === 'xlsx'" class="spinner-border spinner-border-sm me-1"></span>
                <i v-else class="bi bi-file-earmark-excel me-1"></i>
                Excel
            </button>
            <button class="btn btn-outline-danger" :disabled="busy" @click="download('pdf')">
                <span v-if="busy === 'pdf'" class="spinner-border spinner-border-sm me-1"></span>
                <i v-else class="bi bi-file-earmark-pdf me-1"></i>
                PDF
            </button>
        </div>
        <div v-if="error" class="small text-danger">{{ error }}</div>
    </div>
</template>
