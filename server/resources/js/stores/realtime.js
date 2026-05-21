import { defineStore } from 'pinia';
import api from '@/services/api';
import { getEcho, disconnectEcho } from '@/services/echo';
import { playAlarm } from '@/utils/alarmSound';

/**
 * Holds the live snapshot of every station + turnout the dashboard cares
 * about, plus an in-memory queue of unacknowledged alarms used to drive
 * the popup component. Hydrated once via /api/dashboard/live, then kept
 * fresh by Reverb broadcasts.
 *
 * Channels subscribed: `turnouts.global` + one per station from the live
 * snapshot's `broadcast.channels` list — the server controls the list so
 * we don't hardcode station codes here.
 */
export const useRealtimeStore = defineStore('realtime', {
    state: () => ({
        stations: [],            // [{ id, code, name, turnouts: [...] }]
        turnoutsByUuid: {},      // uuid -> turnout snapshot (mutated by broadcasts)
        alarms: [],              // active alarm popups [{ id, turnout_code, station_code, ... }]
        connected: false,
        loaded: false,
        loadError: null,
        broadcastChannels: [],
        _subscriptions: [],
    }),

    getters: {
        /** Compact per-station summary used by StationLiveCard. */
        stationSummary: (state) => state.stations.map(s => {
            const counts = { NORMAL: 0, REVERSE: 0, FAILURE: 0, UNKNOWN: 0 };
            for (const t of s.turnouts) {
                const key = t.state && counts[t.state] !== undefined ? t.state : 'UNKNOWN';
                counts[key]++;
            }
            return {
                id: s.id,
                code: s.code,
                name: s.name,
                turnoutCount: s.turnouts.length,
                counts,
                hasFailure: counts.FAILURE > 0,
                turnouts: s.turnouts,
            };
        }),

        activeAlarmCount: (state) => state.alarms.length,
    },

    actions: {
        async load() {
            this.loadError = null;
            try {
                const { data } = await api.get('/api/dashboard/live');
                this.stations = data.stations || [];
                this.broadcastChannels = data.broadcast?.channels || [];
                this._rebuildIndex();
                this._seedAlarms();
                this.loaded = true;
            } catch (err) {
                this.loadError = err?.response?.data?.message || err?.message || 'Failed to load live snapshot';
                throw err;
            }
        },

        connect() {
            if (this._subscriptions.length > 0) return;

            const echo = getEcho();
            // Connection lifecycle (Reverb uses pusher protocol).
            const connector = echo.connector?.pusher?.connection;
            if (connector) {
                connector.bind('connected',    () => { this.connected = true; });
                connector.bind('disconnected', () => { this.connected = false; });
                connector.bind('unavailable',  () => { this.connected = false; });
            }

            for (const name of this.broadcastChannels) {
                const channel = echo.private(name);
                channel.listen('.turnout.state.updated', (payload) => this._applyStateUpdate(payload));
                channel.listen('.turnout.alarm.raised',  (payload) => this._applyAlarmRaised(payload));
                channel.listen('.turnout.alarm.cleared', (payload) => this._applyAlarmCleared(payload));
                this._subscriptions.push(name);
            }
        },

        disconnect() {
            const echo = getEcho();
            for (const name of this._subscriptions) {
                echo.leave(name);
            }
            this._subscriptions = [];
            disconnectEcho();
            this.connected = false;
        },

        dismissAlarm(alarmId) {
            this.alarms = this.alarms.filter(a => a.id !== alarmId);
        },

        dismissAllAlarms() {
            this.alarms = [];
        },

        _rebuildIndex() {
            const idx = {};
            for (const s of this.stations) {
                for (const t of s.turnouts) {
                    idx[t.uuid] = t;
                }
            }
            this.turnoutsByUuid = idx;
        },

        _seedAlarms() {
            // Pre-populate the popup queue with alarms that are still active
            // at snapshot time, so a refresh doesn't hide ongoing failures.
            this.alarms = [];
            for (const s of this.stations) {
                for (const t of s.turnouts) {
                    if (t.has_active_alarm) {
                        this.alarms.push({
                            id: `seed-${t.uuid}`,
                            turnout_uuid: t.uuid,
                            turnout_code: t.code,
                            turnout_name: t.name,
                            station_code: s.code,
                            station_name: s.name,
                            started_at: t.alarm_started_at,
                            seeded: true,
                        });
                    }
                }
            }
        },

        _applyStateUpdate(p) {
            const t = this.turnoutsByUuid[p.turnout_uuid];
            if (!t) return;
            t.state = p.state;
            t.channel_a = !!p.channel_a;
            t.channel_b = !!p.channel_b;
            t.node_id = p.node_id;
            t.source_timestamp = p.source_timestamp;
            // Note: has_active_alarm is owned by raised/cleared events, not state updates.
        },

        _applyAlarmRaised(p) {
            const t = this.turnoutsByUuid[p.turnout_uuid];
            if (t) {
                t.has_active_alarm = true;
                t.alarm_started_at = p.started_at;
            }
            // De-dup against seeded alarm (if any) for the same turnout.
            this.alarms = this.alarms.filter(a => a.turnout_uuid !== p.turnout_uuid);
            this.alarms.push({
                id: `alarm-${p.alarm_id}`,
                alarm_id: p.alarm_id,
                turnout_uuid: p.turnout_uuid,
                turnout_code: p.turnout_code,
                turnout_name: p.turnout_name,
                station_code: p.station_code,
                station_name: p.station_name,
                started_at: p.started_at,
            });
            playAlarm();
        },

        _applyAlarmCleared(p) {
            const t = this.turnoutsByUuid[p.turnout_uuid];
            if (t) {
                t.has_active_alarm = false;
                t.alarm_started_at = null;
            }
            // Drop any popup tied to this turnout (seeded or live).
            this.alarms = this.alarms.filter(
                a => a.turnout_uuid !== p.turnout_uuid && a.alarm_id !== p.alarm_id
            );
        },
    },
});
