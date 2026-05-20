<script setup>
import { useThemeStore } from '@/stores/theme';
import { useAuthStore } from '@/stores/auth';
import { useRouter } from 'vue-router';

const theme = useThemeStore();
const auth = useAuthStore();
const router = useRouter();

async function handleLogout() {
    await auth.logout();
    router.push({ name: 'login' });
}
</script>

<template>
    <header class="d-flex align-items-center px-3 border-bottom bg-body">
        <button class="btn btn-sm btn-outline-secondary me-3"
                type="button"
                @click="theme.toggleSidebar()"
                aria-label="Toggle sidebar">
            <i class="bi bi-list"></i>
        </button>

        <div class="fw-semibold">MRT Turnout Monitoring</div>

        <div class="ms-auto d-flex align-items-center gap-2">
            <button class="btn btn-sm btn-outline-secondary"
                    type="button"
                    @click="theme.toggleMode()"
                    :aria-label="theme.mode === 'dark' ? 'Switch to light' : 'Switch to dark'">
                <i :class="theme.mode === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars'"></i>
            </button>

            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle"
                        type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle me-1"></i>
                    {{ auth.user?.name ?? 'Guest' }}
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><button class="dropdown-item" type="button" @click="handleLogout">
                        <i class="bi bi-box-arrow-right me-2"></i>Logout
                    </button></li>
                </ul>
            </div>
        </div>
    </header>
</template>
