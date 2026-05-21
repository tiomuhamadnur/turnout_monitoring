// Demo data service for Dashboard presentation
const demoEvents = [];
for (let i = 0; i < 10; i++) {
    demoEvents.push({
        id: i + 1,
        timestamp: new Date(Date.now() - i * 60000).toISOString(),
        turnout_code: `W11${10 + i}`,
        node_id: i % 2 === 0 ? 'LBB-NODE-01' : 'BLM-NODE-01',
        state: i % 3 === 0 ? 'NORMAL' : (i % 3 === 1 ? 'REVERSE' : 'FAILURE'),
    });
}

// Generate realistic turnout statistics per code
const generateTurnoutStats = () => {
    const turnouts = [
        'W1110', 'W1111', 'W1112', 'W1113', 'W1114', // LBB
        'W1120', 'W1121', 'W1122', 'W1123', 'W1124', 'W1125', // BLM
        'W1130', 'W1131', 'W1132', 'W1133', // BHI
    ];
    
    return turnouts.map(code => ({
        code,
        normal_count: Math.floor(Math.random() * 80) + 20,
        reverse_count: Math.floor(Math.random() * 70) + 10,
        failure_count: Math.floor(Math.random() * 15) + 1,
    }));
};

// Generate trend data over last 7 days
const generateTrendData = () => {
    const labels = [];
    const total = [];
    const alarms = [];
    
    for (let i = 6; i >= 0; i--) {
        const date = new Date();
        date.setDate(date.getDate() - i);
        labels.push(date.toLocaleDateString('id-ID', { weekday: 'short', month: 'short', day: 'numeric' }));
        total.push(Math.floor(Math.random() * 80) + 40);
        alarms.push(Math.floor(Math.random() * 8) + 1);
    }
    
    return { labels, total, alarms };
};

export default {
    async getOverview() {
        return {
            turnouts: 15,
            nodes: 3,
            activeAlarms: 2,
            healthyNodes: 2,
        };
    },
    async getRecentEvents() {
        return demoEvents;
    },
    async getTurnoutStats(startDate, endDate) {
        // In real implementation, filter by date range from API
        return generateTurnoutStats();
    },
    async getTrendData(startDate, endDate) {
        // In real implementation, filter by date range from API
        return generateTrendData();
    },
};
