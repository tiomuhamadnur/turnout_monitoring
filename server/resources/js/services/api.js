import axios from 'axios';

// Axios instance configured for Laravel Sanctum SPA cookie auth.
// Requests are credentialed; CSRF token is read from XSRF-TOKEN cookie.
const api = axios.create({
    baseURL: '/',
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

// Call once before the first state-changing request so Laravel can set the
// XSRF-TOKEN cookie. Safe to call multiple times.
export async function ensureCsrfCookie() {
    await api.get('/sanctum/csrf-cookie');
}

export default api;
