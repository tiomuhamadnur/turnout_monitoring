<script setup>
import { RouterLink } from 'vue-router';
</script>

<template>
    <div class="d-flex align-items-center justify-content-center min-vh-100 text-center p-3">
        <div>
            <div class="display-1 text-muted">404</div>
            <p class="lead">This page does not exist.</p>
            <RouterLink to="/" class="btn btn-primary">
                <i class="bi bi-house me-1"></i> Back to dashboard
            </RouterLink>
        </div>
    </div>
</template>
