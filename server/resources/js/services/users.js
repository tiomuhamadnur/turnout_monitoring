import api from './api';

export default {
    list:   (params = {}) => api.get('/api/users', { params }),
    show:   (id)          => api.get(`/api/users/${id}`),
    create: (data)        => api.post('/api/users', data),
    update: (id, data)    => api.put(`/api/users/${id}`, data),
    remove: (id)          => api.delete(`/api/users/${id}`),
};
