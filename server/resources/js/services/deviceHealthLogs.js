import api from './api';

export default {
    list: (params = {}) => api.get('/api/device-health-logs', { params }),
};
