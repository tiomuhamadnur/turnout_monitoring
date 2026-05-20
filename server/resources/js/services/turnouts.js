import api from './api';

export default {
    list:        (params = {}) => api.get('/api/turnouts', { params }),
    show:        (id)          => api.get(`/api/turnouts/${id}`),
    create:      (data)        => api.post('/api/turnouts', data),
    update:      (id, data)    => api.put(`/api/turnouts/${id}`, data),
    remove:      (id)          => api.delete(`/api/turnouts/${id}`),
    uploadPhoto: (id, file)    => {
        const body = new FormData();
        body.append('photo', file);
        return api.post(`/api/turnouts/${id}/photo`, body, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
    },
};
