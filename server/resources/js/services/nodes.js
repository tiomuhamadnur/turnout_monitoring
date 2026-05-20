import api from './api';

export default {
    list:   (params = {}) => api.get('/api/nodes', { params }),
    show:   (id)          => api.get(`/api/nodes/${id}`),
    create: (data)        => api.post('/api/nodes', data),
    update: (id, data)    => api.put(`/api/nodes/${id}`, data),
    remove: (id)          => api.delete(`/api/nodes/${id}`),
};
