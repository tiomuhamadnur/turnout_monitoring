export function pad(n) {
    return n < 10 ? `0${n}` : `${n}`;
}

export function formatTimestamp(value) {
    if (!value) return '-';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return value;
    const Y = d.getFullYear();
    const M = pad(d.getMonth() + 1);
    const D = pad(d.getDate());
    const h = pad(d.getHours());
    const m = pad(d.getMinutes());
    const s = pad(d.getSeconds());
    return `${Y}-${M}-${D} ${h}:${m}:${s}`;
}
