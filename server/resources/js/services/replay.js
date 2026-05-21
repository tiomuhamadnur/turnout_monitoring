import api from './api';

export default {
    stations: () => api.get('/api/replay/stations'),
    timeline: (params) => api.get('/api/replay/timeline', { params }),
};
