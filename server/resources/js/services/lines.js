import api from './api';

export default {
    list:   (params = {}) => api.get('/api/lines', { params }),
    show:   (id)          => api.get(`/api/lines/${id}`),
    create: (data)        => api.post('/api/lines', data),
    update: (id, data)    => api.put(`/api/lines/${id}`, data),
    remove: (id)          => api.delete(`/api/lines/${id}`),
};
