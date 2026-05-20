<script setup>
import { ref } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useThemeStore } from '@/stores/theme';

const router = useRouter();
const route = useRoute();
const auth = useAuthStore();
const theme = useThemeStore();

const email = ref('');
const password = ref('');

async function submit() {
    try {
        await auth.login(email.value, password.value);
        router.push(route.query.redirect?.toString() ?? '/');
    } catch {
        // error message rendered from auth.error
    }
}
</script>

<template>
    <div class="d-flex align-items-center justify-content-center min-vh-100 p-3">
        <div class="card shadow-sm" style="max-width: 420px; width: 100%;">
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <i class="bi bi-train-front-fill text-primary fs-1"></i>
                    <h1 class="h4 mt-2 mb-1">MRT Turnout Monitoring</h1>
                    <p class="text-muted small mb-0">Sign in to continue</p>
                </div>

                <form @submit.prevent="submit" novalidate>
                    <div v-if="auth.error" class="alert alert-danger py-2 small">
                        {{ auth.error }}
                    </div>

                    <div class="mb-3">
                        <label class="form-label small">Email</label>
                        <input v-model="email"
                               type="email"
                               class="form-control"
                               required
                               autocomplete="email"
                               autofocus />
                    </div>

                    <div class="mb-3">
                        <label class="form-label small">Password</label>
                        <input v-model="password"
                               type="password"
                               class="form-control"
                               required
                               autocomplete="current-password" />
                    </div>

                    <button class="btn btn-primary w-100"
                            type="submit"
                            :disabled="auth.loading">
                        <span v-if="auth.loading" class="spinner-border spinner-border-sm me-2"></span>
                        Sign in
                    </button>
                </form>

                <div class="text-center mt-3">
                    <button class="btn btn-link btn-sm text-decoration-none"
                            @click="theme.toggleMode()">
                        <i :class="theme.mode === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars'"></i>
                        Toggle theme
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
