import api from './api';

export default {
    list: (params = {}) => api.get('/api/turnout-alarms', { params }),
};
