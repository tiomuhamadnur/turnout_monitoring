import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const routes = [
    {
        path: '/login',
        name: 'login',
        component: () => import('@/pages/Login.vue'),
        meta: { layout: 'auth', guestOnly: true },
    },
    {
        path: '/',
        component: () => import('@/layouts/AppLayout.vue'),
        meta: { requiresAuth: true },
        children: [
            {
                path: '',
                name: 'dashboard',
                component: () => import('@/pages/Dashboard.vue'),
            },
            {
                path: 'users',
                name: 'users',
                component: () => import('@/pages/Users.vue'),
            },
            {
                path: 'roles',
                name: 'roles',
                component: () => import('@/pages/Roles.vue'),
            },
            {
                path: 'stations',
                name: 'stations',
                component: () => import('@/pages/Stations.vue'),
            },
            {
                path: 'lines',
                name: 'lines',
                component: () => import('@/pages/Lines.vue'),
            },
            {
                path: 'nodes',
                name: 'nodes',
                component: () => import('@/pages/Nodes.vue'),
            },
            {
                path: 'turnouts',
                name: 'turnouts',
                component: () => import('@/pages/Turnouts.vue'),
            },
            {
                path: 'turnout-events',
                name: 'turnout-events',
                component: () => import('@/pages/TurnoutEvents.vue'),
            },
            {
                path: 'turnout-alarms',
                name: 'turnout-alarms',
                component: () => import('@/pages/TurnoutAlarms.vue'),
            },
            {
                path: 'device-health-logs',
                name: 'device-health-logs',
                component: () => import('@/pages/DeviceHealthLogs.vue'),
            },
            {
                path: 'audit-logs',
                name: 'audit-logs',
                component: () => import('@/pages/AuditLogs.vue'),
            },
        ],
    },
    {
        path: '/:pathMatch(.*)*',
        name: 'not-found',
        component: () => import('@/pages/NotFound.vue'),
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();

    if (auth.user === null && to.meta.requiresAuth) {
        await auth.hydrate();
    }

    if (to.meta.requiresAuth && !auth.isAuthenticated) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }
    if (to.meta.guestOnly && auth.isAuthenticated) {
        return { name: 'dashboard' };
    }
});

export default router;
