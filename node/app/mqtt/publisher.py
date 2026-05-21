"""
Thin wrapper around paho-mqtt that knows how to:
  - connect with auto-reconnect (loop_start runs in a background thread)
  - publish JSON payloads with QoS configurable per env
  - report connection state to the caller (for the heartbeat 'mqtt_status' field)

Per BLUEPRINT.md the payload format is fixed; we do NOT add fields the
server doesn't recognise.
"""

from __future__ import annotations

import json
import logging
import threading
from typing import Any, Dict, Optional

import paho.mqtt.client as mqtt


log = logging.getLogger(__name__)


class MqttPublisher:
    def __init__(
        self,
        client_id: str,
        host: str,
        port: int,
        username: Optional[str],
        password: Optional[str],
        keepalive: int,
        qos: int,
    ) -> None:
        self._host = host
        self._port = port
        self._keepalive = keepalive
        self._qos = qos
        self._connected = threading.Event()

        # paho-mqtt 2.x: use CallbackAPIVersion explicitly.
        self._client = mqtt.Client(
            client_id=client_id,
            callback_api_version=mqtt.CallbackAPIVersion.VERSION2,
            clean_session=True,
        )
        if username:
            self._client.username_pw_set(username, password)

        self._client.on_connect = self._on_connect
        self._client.on_disconnect = self._on_disconnect

        # paho's built-in reconnect — backs off up to ~2 min between attempts.
        self._client.reconnect_delay_set(min_delay=1, max_delay=120)

    # ---- lifecycle ----------------------------------------------------

    def start(self) -> None:
        """Non-blocking connect. The internal background thread handles
        reconnects, so the rest of the app can publish synchronously."""
        try:
            self._client.connect_async(self._host, self._port, keepalive=self._keepalive)
        except Exception as e:
            log.warning("MQTT initial connect failed (%s); will retry in background", e)
        self._client.loop_start()

    def stop(self) -> None:
        try:
            self._client.disconnect()
        except Exception:
            pass
        self._client.loop_stop()

    # ---- introspection ------------------------------------------------

    @property
    def is_connected(self) -> bool:
        return self._connected.is_set()

    def status_label(self) -> str:
        return "connected" if self.is_connected else "disconnected"

    # ---- publish ------------------------------------------------------

    def publish_json(self, topic: str, payload: Dict[str, Any], *, retain: bool = False) -> bool:
        """Returns True if paho accepted the message for delivery (it may
        still be queued for a reconnect — paho holds it in its outbound
        queue). Returns False on serialization / queue errors."""
        try:
            body = json.dumps(payload, separators=(",", ":"), default=str)
        except (TypeError, ValueError) as e:
            log.error("Failed to serialise payload for %s: %s", topic, e)
            return False

        info = self._client.publish(topic, body, qos=self._qos, retain=retain)
        if info.rc != mqtt.MQTT_ERR_SUCCESS:
            log.warning("MQTT publish to %s returned rc=%s", topic, info.rc)
            return False
        return True

    # ---- callbacks ----------------------------------------------------

    def _on_connect(self, client, userdata, flags, reason_code, properties=None):
        if reason_code == 0:
            self._connected.set()
            log.info("MQTT connected to %s:%s", self._host, self._port)
        else:
            self._connected.clear()
            log.warning("MQTT connect refused: %s", reason_code)

    def _on_disconnect(self, client, userdata, *args, **kwargs):
        self._connected.clear()
        log.warning("MQTT disconnected (paho will auto-reconnect)")
