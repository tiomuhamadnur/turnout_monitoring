<script setup>
import { RouterLink } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useThemeStore } from '@/stores/theme';

const auth = useAuthStore();
const theme = useThemeStore();

const mainItems = [
    { to: '/',                    icon: 'bi-speedometer2',   label: 'Dashboard' },
    { to: '/turnout-events',      icon: 'bi-activity',       label: 'Turnout Events' },
    { to: '/turnout-alarms',      icon: 'bi-bell',           label: 'Turnout Alarms' },
    { to: '/device-health-logs',  icon: 'bi-cpu',            label: 'Health Logs' },
    { to: '/audit-logs',          icon: 'bi-journal-text',   label: 'Audit Logs' },
    { to: '/replay',              icon: 'bi-clock-history',  label: 'Replay' },
];

const masterItems = [
    { to: '/stations',  icon: 'bi-geo-alt',        label: 'Stations', permission: 'stations.view' },
    { to: '/lines',     icon: 'bi-signpost-split', label: 'Lines',    permission: 'lines.view' },
    { to: '/nodes',     icon: 'bi-hdd-network',    label: 'Nodes',    permission: 'nodes.view' },
    { to: '/turnouts',  icon: 'bi-diagram-3',      label: 'Turnouts', permission: 'turnouts.view' },
    { to: '/users',     icon: 'bi-people',         label: 'Users',    permission: 'users.view' },
    { to: '/roles',     icon: 'bi-shield-lock',    label: 'Roles',    permission: 'roles.view' },
    { to: '/settings',  icon: 'bi-gear',           label: 'Settings', permission: 'settings.manage' },
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
                <li v-if="!theme.sidebarCollapsed" class="px-3 pt-2 pb-1">
                    <div class="sidebar-section-label">Main Features</div>
                </li>
                <li v-for="item in mainItems" :key="`main-${item.to}`">
                    <RouterLink :to="item.to"
                                class="d-flex align-items-center gap-2 px-3 py-2 text-decoration-none text-body"
                                exact-active-class="active bg-primary-subtle text-primary fw-medium">
                        <i class="bi" :class="item.icon"></i>
                        <span v-if="!theme.sidebarCollapsed">{{ item.label }}</span>
                    </RouterLink>
                </li>
                <li v-if="!theme.sidebarCollapsed" class="px-3 pt-3 pb-1">
                    <div class="sidebar-section-label">Master Data</div>
                </li>
                <li v-for="item in masterItems.filter(item => auth.can(item.permission))" :key="`master-${item.to}`">
                    <RouterLink :to="item.to"
                                class="d-flex align-items-center gap-2 px-3 py-2 text-decoration-none text-body"
                                exact-active-class="active bg-primary-subtle text-primary fw-medium">
                        <i class="bi" :class="item.icon"></i>
                        <span v-if="!theme.sidebarCollapsed">{{ item.label }}</span>
                    </RouterLink>
                </li>
            </ul>
        </nav>
    </aside>
</template>

<style scoped>
.sidebar-section-label {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: var(--bs-secondary-color);
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    user-select: none;
}

.sidebar-section-label::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--bs-border-color);
    opacity: 0.9;
}
</style>
