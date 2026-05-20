<script setup>
import { RouterLink } from 'vue-router';
import { useThemeStore } from '@/stores/theme';

const theme = useThemeStore();

const items = [
    { to: '/',          icon: 'bi-speedometer2',     label: 'Dashboard' },
    { to: '/turnouts',  icon: 'bi-diagram-3',        label: 'Turnouts' },
    { to: '/stations',  icon: 'bi-geo-alt',          label: 'Stations' },
    { to: '/nodes',     icon: 'bi-hdd-network',      label: 'Nodes' },
    { to: '/replay',    icon: 'bi-clock-history',    label: 'Replay' },
    { to: '/alarms',    icon: 'bi-bell',             label: 'Alarms' },
    { to: '/users',     icon: 'bi-people',           label: 'Users' },
    { to: '/roles',     icon: 'bi-shield-lock',      label: 'Roles' },
    { to: '/settings',  icon: 'bi-gear',             label: 'Settings' },
];
</script>

<template>
    <aside class="d-flex flex-column border-end bg-body-tertiary"
           :class="{ 'is-collapsed': theme.sidebarCollapsed }">
        <div class="d-flex align-items-center justify-content-center py-3 fw-semibold">
            <i class="bi bi-train-front-fill me-2 text-primary"></i>
            <span v-if="!theme.sidebarCollapsed">MRT MONITOR</span>
        </div>
        <nav class="flex-grow-1 overflow-auto">
            <ul class="list-unstyled m-0 p-0">
                <li v-for="item in items" :key="item.to">
                    <RouterLink :to="item.to"
                                class="d-flex align-items-center gap-2 px-3 py-2 text-decoration-none text-body"
                                active-class="active bg-primary-subtle text-primary fw-medium">
                        <i class="bi" :class="item.icon"></i>
                        <span v-if="!theme.sidebarCollapsed">{{ item.label }}</span>
                    </RouterLink>
                </li>
            </ul>
        </nav>
    </aside>
</template>
