<script setup>
import { computed } from 'vue';
import { RouterView } from 'vue-router';
import { useThemeStore } from '@/stores/theme';
import AppHeader from '@/components/AppHeader.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppFooter from '@/components/AppFooter.vue';

const theme = useThemeStore();
const layoutClass = computed(() => ({
    'app-layout': true,
    'is-collapsed': theme.sidebarCollapsed,
}));
</script>

<template>
    <div :class="layoutClass">
        <AppSidebar class="app-sidebar" />
        <AppHeader class="app-header" />
        <main class="app-main">
            <RouterView />
        </main>
        <AppFooter class="app-footer" />
    </div>
</template>
