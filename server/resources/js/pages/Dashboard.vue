<script setup>
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();

const stations = [
    { code: 'LBB', name: 'Lebak Bulus', turnouts: 5 },
    { code: 'BLM', name: 'Blok M',      turnouts: 6 },
    { code: 'BHI', name: 'Bundaran HI', turnouts: 4 },
];
</script>

<template>
    <div>
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h1 class="h4 mb-0">Dashboard</h1>
            <span class="text-muted small">Welcome, {{ auth.user?.name }}</span>
        </div>

        <div class="row g-3">
            <div v-for="s in stations" :key="s.code" class="col-12 col-md-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <div class="text-muted small">Station</div>
                                <div class="h5 mb-0">{{ s.code }}</div>
                                <div class="small">{{ s.name }}</div>
                            </div>
                            <div class="text-end">
                                <div class="text-muted small">Turnouts</div>
                                <div class="h3 mb-0 text-primary">{{ s.turnouts }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="alert alert-info mt-4 small">
            <i class="bi bi-info-circle me-1"></i>
            Phase 1 scaffolding. Real turnout state visualisation comes in Phase 4.
        </div>
    </div>
</template>
