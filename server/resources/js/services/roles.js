import api from './api';

export default {
    list:   (params = {}) => api.get('/api/roles', { params }),
    show:   (id)          => api.get(`/api/roles/${id}`),
    create: (data)        => api.post('/api/roles', data),
    update: (id, data)    => api.put(`/api/roles/${id}`, data),
    remove: (id)          => api.delete(`/api/roles/${id}`),

    permissions: () => api.get('/api/permissions'),
};
