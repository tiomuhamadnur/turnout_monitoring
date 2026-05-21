"""
Top-level orchestrator. Pulls the input reader, state machines, MQTT
publisher, SQLite cache, and heartbeat service together into one polling
loop plus one daemon thread.

Lifecycle:
  start()      → connect MQTT, start heartbeat thread, return.
  run_forever()→ poll loop until stop() (handled by main.py signal handler).
"""

from __future__ import annotations

import logging
import signal
import threading
import time
from datetime import datetime, timezone
from typing import Dict

from app.config import Settings
from app.heartbeat import HeartbeatService
from app.historian import SqliteCache
from app.input import (
    InputReader,
    ModbusInputReader,
    SimulatorInputReader,
)
from app.mqtt import state_topic
from app.mqtt.publisher import MqttPublisher
from app.state_machine import TurnoutState, TurnoutStateMachine


log = logging.getLogger(__name__)


def _now_iso() -> str:
    return datetime.now(timezone.utc).astimezone().isoformat()


def _build_input(settings: Settings) -> InputReader:
    driver = settings.input_driver
    if driver == "simulator":
        log.info("Using simulator input driver with %d turnouts", len(settings.simulator_turnouts))
        return SimulatorInputReader(settings.simulator_turnouts)
    if driver == "modbus":
        host = settings.__dict__.get("modbus_host") or "127.0.0.1"
        log.info("Using modbus input driver (host=%s)", host)
        return ModbusInputReader(host=host)
    raise ValueError(f"Unknown INPUT_DRIVER={driver!r}")


class Runner:
    def __init__(self, settings: Settings) -> None:
        self._settings = settings
        self._stop = threading.Event()

        self._input = _build_input(settings)
        self._publisher = MqttPublisher(
            client_id   = settings.node_id,
            host        = settings.mqtt_host,
            port        = settings.mqtt_port,
            username    = settings.mqtt_username,
            password    = settings.mqtt_password,
            keepalive   = settings.mqtt_keepalive,
            qos         = settings.mqtt_qos,
        )
        self._cache = SqliteCache(settings.sqlite_path, settings.sqlite_retain_events)

        self._state_machines: Dict[str, TurnoutStateMachine] = {
            code: TurnoutStateMachine(
                code=code,
                failure_debounce_seconds   = settings.failure_debounce_seconds,
                keepalive_interval_seconds = settings.state_publish_interval_seconds,
            )
            for code in self._input.turnout_codes()
        }

        self._heartbeat = HeartbeatService(
            settings   = settings,
            publisher  = self._publisher,
            cache_record = self._cache.record,
        )

    # ---- lifecycle ----------------------------------------------------

    def start(self) -> None:
        self._publisher.start()
        self._heartbeat.start()
        # Wire SIGINT/SIGTERM here so signal handlers run on the main thread.
        for sig in (signal.SIGINT, signal.SIGTERM):
            try:
                signal.signal(sig, self._on_signal)
            except ValueError:
                # Not all platforms (e.g. non-main threads on Windows) support this.
                pass

    def stop(self) -> None:
        self._stop.set()

    def shutdown(self) -> None:
        log.info("Shutting down node runner…")
        self._heartbeat.stop()
        self._heartbeat.join(timeout=3.0)
        self._publisher.stop()
        try:
            self._input.close()
        except Exception:
            pass
        self._cache.close()

    # ---- main loop ----------------------------------------------------

    def run_forever(self) -> None:
        s = self._settings
        log.info("Node %s entering main loop (sample_interval=%ss)", s.node_id, s.sample_interval_seconds)
        while not self._stop.is_set():
            try:
                snapshot = self._input.sample()
            except Exception as e:
                log.error("Input sample failed: %s", e)
                self._stop.wait(s.sample_interval_seconds)
                continue

            for code, channels in snapshot.items():
                sm = self._state_machines.get(code)
                if sm is None:
                    # Hot-added turnout (rare; e.g. operator updated the
                    # simulator config) — create a fresh machine.
                    sm = TurnoutStateMachine(
                        code=code,
                        failure_debounce_seconds   = s.failure_debounce_seconds,
                        keepalive_interval_seconds = s.state_publish_interval_seconds,
                    )
                    self._state_machines[code] = sm

                update = sm.feed(channels.channel_a, channels.channel_b)

                if not (update.is_transition or update.is_keepalive):
                    continue

                self._publish_state(update)

            self._stop.wait(s.sample_interval_seconds)

    # ---- helpers ------------------------------------------------------

    def _publish_state(self, update) -> None:
        s = self._settings
        topic = state_topic(s.mqtt_topic_prefix, s.node_location, update.code)
        payload = {
            "timestamp":    _now_iso(),
            "turnout_code": update.code,
            "state":        update.state.value if isinstance(update.state, TurnoutState) else str(update.state),
            "channel_a":    bool(update.channel_a),
            "channel_b":    bool(update.channel_b),
            "node_id":      s.node_id,
        }
        if self._publisher.publish_json(topic, payload):
            self._cache.record("state", topic, payload)
            if update.is_transition:
                log.info("→ %s %s (was %s)", update.code, update.state, update.previous_state)

    def _on_signal(self, signum, _frame) -> None:
        log.info("Signal %s received, stopping…", signum)
        self.stop()
