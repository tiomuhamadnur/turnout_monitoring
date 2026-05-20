import { defineStore } from 'pinia';
import api, { ensureCsrfCookie } from '@/services/api';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        loading: false,
        error: null,
    }),

    getters: {
        isAuthenticated: (state) => state.user !== null,
        isSuperAdmin:    (state) => !!state.user?.is_super_admin,
        permissions:     (state) => state.user?.permissions ?? [],
    },

    actions: {
        can(permission) {
            if (!this.user) return false;
            if (this.user.is_super_admin) return true;
            return (this.user.permissions ?? []).includes(permission);
        },

        async hydrate() {
            try {
                const { data } = await api.get('/api/user');
                this.user = data;
            } catch {
                this.user = null;
            }
        },

        async login(email, password) {
            this.loading = true;
            this.error = null;
            try {
                await ensureCsrfCookie();
                await api.post('/login', { email, password });
                await this.hydrate();
                if (!this.user) {
                    // Login POST succeeded but the session cookie didn't stick
                    // (often SESSION_DOMAIN misconfig). Surface it rather than
                    // silently bouncing back to the login screen.
                    throw new Error('Logged in but session did not persist. Check SESSION_DOMAIN and SANCTUM_STATEFUL_DOMAINS in .env.');
                }
            } catch (e) {
                this.error = e.response?.data?.message ?? e.message ?? 'Login failed';
                throw e;
            } finally {
                this.loading = false;
            }
        },

        async logout() {
            try {
                await api.post('/logout');
            } finally {
                this.user = null;
            }
        },
    },
});

