"""
Heartbeat + device-health publisher. Runs on its own daemon thread so
sample/publish latency on the runner never delays a heartbeat.

  - HEARTBEAT every HEARTBEAT_INTERVAL seconds  → {node_id, status, ip, mqtt_status}
  - HEALTH    every HEALTH_INTERVAL   seconds  → CPU/RAM/disk/uptime/MQTT/containers

The server's TelemetryIngestService accepts both shapes; container_health
is optional and only sent when DOCKER_CONTAINER_HEALTH_PATH is set.
"""

from __future__ import annotations

import json
import logging
import socket
import threading
import time
from datetime import datetime, timezone
from typing import Any, Dict, Optional

import psutil

from app.config import Settings
from app.mqtt import heartbeat_topic, health_topic
from app.mqtt.publisher import MqttPublisher


log = logging.getLogger(__name__)


def _now_iso() -> str:
    return datetime.now(timezone.utc).astimezone().isoformat()


def _primary_ip() -> str:
    """Best-effort local IP. Using a UDP "connect" trick so we don't need
    to enumerate interfaces — works on Linux/Windows alike."""
    s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    try:
        s.connect(("10.255.255.255", 1))
        return s.getsockname()[0]
    except OSError:
        return "127.0.0.1"
    finally:
        s.close()


class HeartbeatService(threading.Thread):
    def __init__(
        self,
        settings: Settings,
        publisher: MqttPublisher,
        cache_record: Optional[callable] = None,  # type: ignore[type-arg]
    ) -> None:
        super().__init__(name="heartbeat", daemon=True)
        self._settings = settings
        self._publisher = publisher
        self._cache_record = cache_record
        self._stop_event = threading.Event()
        self._last_health_at = 0.0
        self._boot_at = time.time()

    def stop(self) -> None:
        self._stop_event.set()

    def run(self) -> None:
        # Stagger the first heartbeat a touch so MQTT connect can settle.
        self._stop_event.wait(2.0)

        while not self._stop_event.is_set():
            self._tick_heartbeat()

            now = time.monotonic()
            if (now - self._last_health_at) >= self._settings.health_interval_seconds:
                self._tick_health()
                self._last_health_at = now

            self._stop_event.wait(self._settings.heartbeat_interval_seconds)

    # ---- heartbeat ----------------------------------------------------

    def _tick_heartbeat(self) -> None:
        s = self._settings
        topic = heartbeat_topic(s.mqtt_topic_prefix, s.node_location, s.node_id)
        payload: Dict[str, Any] = {
            "timestamp":   _now_iso(),
            "node_id":     s.node_id,
            "ip_address":  _primary_ip(),
            "status":      "online",
            "mqtt_status": self._publisher.status_label(),
        }
        self._publish("heartbeat", topic, payload)

    # ---- health -------------------------------------------------------

    def _tick_health(self) -> None:
        s = self._settings
        topic = health_topic(s.mqtt_topic_prefix, s.node_location, s.node_id)

        try:
            cpu = psutil.cpu_percent(interval=None)
            ram = psutil.virtual_memory().percent
            disk = psutil.disk_usage("/").percent if hasattr(psutil, "disk_usage") else None
            uptime_s = int(time.time() - psutil.boot_time())
        except Exception as e:
            log.warning("psutil read failed: %s", e)
            cpu, ram, disk, uptime_s = None, None, None, int(time.time() - self._boot_at)

        payload: Dict[str, Any] = {
            "timestamp":      _now_iso(),
            "node_id":        s.node_id,
            "cpu_usage":      cpu,
            "ram_usage":      ram,
            "disk_usage":     disk,
            "uptime_seconds": uptime_s,
            "mqtt_status":    self._publisher.status_label(),
        }
        self._publish("health", topic, payload)

    # ---- shared -------------------------------------------------------

    def _publish(self, kind: str, topic: str, payload: Dict[str, Any]) -> None:
        ok = self._publisher.publish_json(topic, payload)
        if ok and self._cache_record is not None:
            try:
                self._cache_record(kind, topic, payload)
            except Exception as e:
                log.warning("Cache record failed for %s: %s", kind, e)
