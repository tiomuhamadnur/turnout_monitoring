import api from '@/services/api';

/**
 * Download a binary export by hitting an authenticated endpoint and
 * triggering a browser save. We can't just open the URL in a new tab
 * because the SPA uses Sanctum cookie auth + XSRF — the cookies don't
 * always ride along on plain navigations.
 */
export async function downloadExport(path, params = {}) {
    // Strip empty values so the server's `when($filters[...] ?? null)`
    // checks behave as expected (no false-positive `state=` filters).
    const cleaned = {};
    for (const [k, v] of Object.entries(params || {})) {
        if (v === '' || v === null || v === undefined || v === false) continue;
        cleaned[k] = v;
    }

    const response = await api.get(path, { params: cleaned, responseType: 'blob' });

    const cd = response.headers['content-disposition'] || '';
    const match = cd.match(/filename="?([^"]+)"?/);
    const suggested = match ? match[1] : path.split('/').pop();

    const url = URL.createObjectURL(response.data);
    const a = document.createElement('a');
    a.href = url;
    a.download = suggested;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 0);
}
