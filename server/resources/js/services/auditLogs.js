import api from './api';

export default {
    list: (params = {}) => api.get('/api/audit-logs', { params }),
};
