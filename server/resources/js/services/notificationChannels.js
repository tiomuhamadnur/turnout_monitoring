import api from './api';

export default {
    list:   ()              => api.get('/api/notification-channels'),
    create: (data)          => api.post('/api/notification-channels', data),
    update: (id, data)      => api.put(`/api/notification-channels/${id}`, data),
    remove: (id)            => api.delete(`/api/notification-channels/${id}`),
    test:   (id)            => api.post(`/api/notification-channels/${id}/test`),
    logs:   (params = {})   => api.get('/api/notification-logs', { params }),
};
