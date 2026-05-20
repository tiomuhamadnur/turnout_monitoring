import api from './api';

export default {
    list:   (params = {}) => api.get('/api/stations', { params }),
    show:   (id)          => api.get(`/api/stations/${id}`),
    create: (data)        => api.post('/api/stations', data),
    update: (id, data)    => api.put(`/api/stations/${id}`, data),
    remove: (id)          => api.delete(`/api/stations/${id}`),
};
