<script setup>
const year = new Date().getFullYear();
</script>

<template>
    <footer class="d-flex align-items-center justify-content-between px-3 small border-top bg-body text-muted">
        <span>&copy; {{ year }} MRT Turnout Monitoring</span>
        <span>v0.1.0 &middot; Phase 1</span>
    </footer>
</template>
