import { defineStore } from 'pinia';

const STORAGE_KEY = 'mrt.theme';

// Theme persisted in localStorage. Per BLUEPRINT.md theme persistence is
// per-user; this client-side store is the local fallback until the backend
// user_preferences endpoint is wired up.
export const useThemeStore = defineStore('theme', {
    state: () => ({
        mode: 'light',         // 'light' | 'dark'
        accent: '#0a6cff',
        sidebarCollapsed: false,
    }),

    actions: {
        init() {
            const saved = localStorage.getItem(STORAGE_KEY);
            if (saved) {
                try { Object.assign(this.$state, JSON.parse(saved)); } catch {}
            }
            this.apply();
        },

        apply() {
            document.documentElement.setAttribute('data-bs-theme', this.mode);
            document.documentElement.style.setProperty('--app-accent', this.accent);
            localStorage.setItem(STORAGE_KEY, JSON.stringify(this.$state));
        },

        setMode(mode) { this.mode = mode; this.apply(); },
        toggleMode()   { this.setMode(this.mode === 'dark' ? 'light' : 'dark'); },
        setAccent(c)   { this.accent = c; this.apply(); },
        toggleSidebar(){ this.sidebarCollapsed = !this.sidebarCollapsed; this.apply(); },
    },
});
