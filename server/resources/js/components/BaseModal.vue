<script setup>
import { watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: '' },
    size: { type: String, default: '' },         // '', 'sm', 'lg', 'xl'
    closeOnBackdrop: { type: Boolean, default: true },
});
const emit = defineEmits(['close']);

// Block body scroll while a modal is open. Bootstrap normally does this via
// its JS Modal class; we own the open/close lifecycle here, so do it manually.
watch(() => props.show, (open) => {
    document.body.classList.toggle('modal-open', open);
}, { immediate: true });
</script>

<template>
    <Teleport to="body">
        <transition name="fade">
            <div v-if="show" class="modal-backdrop fade show" @click="closeOnBackdrop && emit('close')"></div>
        </transition>
        <transition name="fade">
            <div v-if="show" class="modal fade show d-block" tabindex="-1" role="dialog" @keydown.esc="emit('close')">
                <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"
                     :class="size ? `modal-${size}` : ''">
                    <div class="modal-content">
                        <div v-if="title || $slots.header" class="modal-header">
                            <h5 class="modal-title">
                                <slot name="header">{{ title }}</slot>
                            </h5>
                            <button type="button" class="btn-close" aria-label="Close" @click="emit('close')"></button>
                        </div>
                        <div class="modal-body">
                            <slot />
                        </div>
                        <div v-if="$slots.footer" class="modal-footer">
                            <slot name="footer" />
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </Teleport>
</template>
