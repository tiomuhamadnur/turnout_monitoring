"""
Env-driven configuration. We deliberately stay stdlib-only here — pydantic
adds 20 MB to the container for ~20 lines of work.

BLUEPRINT.md NODE_ENVIRONMENT_VARIABLES is the source of truth. Variable
names below MUST match that list; renaming requires a blueprint update.
"""

from __future__ import annotations

import os
from dataclasses import dataclass, field
from pathlib import Path
from typing import List


def _env(name: str, default: str | None = None, *, required: bool = False) -> str:
    val = os.environ.get(name, default)
    if required and not val:
        raise RuntimeError(f"Required environment variable not set: {name}")
    return val  # type: ignore[return-value]


def _env_int(name: str, default: int) -> int:
    try:
        return int(os.environ.get(name, str(default)))
    except ValueError:
        return default


def _env_list(name: str, default: List[str]) -> List[str]:
    raw = os.environ.get(name)
    if not raw:
        return list(default)
    return [p.strip() for p in raw.split(",") if p.strip()]


@dataclass(frozen=True)
class Settings:
    # Identity
    node_id: str
    node_name: str
    node_location: str

    # MQTT broker
    mqtt_host: str
    mqtt_port: int
    mqtt_username: str | None
    mqtt_password: str | None
    mqtt_topic_prefix: str
    mqtt_keepalive: int
    mqtt_qos: int

    # Local cache
    sqlite_path: Path
    sqlite_retain_events: int

    # Timings
    heartbeat_interval_seconds: int
    health_interval_seconds: int
    sample_interval_seconds: float
    failure_debounce_seconds: int      # both-low timeout → FAILURE
    state_publish_interval_seconds: int  # keepalive state re-publish

    # Input driver selection
    input_driver: str                  # 'simulator' | 'modbus'
    simulator_turnouts: List[str]      # list of turnout codes for the simulator

    # Logging
    log_level: str
    log_retention_days: int


def load_settings() -> Settings:
    """
    Hydrate Settings from the process environment. Called once at startup;
    the result is treated as immutable for the lifetime of the process.
    """
    sqlite_path = Path(_env("SQLITE_PATH", "/app/storage/local.db"))
    sqlite_path.parent.mkdir(parents=True, exist_ok=True)

    return Settings(
        node_id        = _env("NODE_ID", required=True),
        node_name      = _env("NODE_NAME", "Unnamed Node"),
        node_location  = _env("NODE_LOCATION", required=True),

        mqtt_host          = _env("MQTT_HOST", required=True),
        mqtt_port          = _env_int("MQTT_PORT", 1883),
        mqtt_username      = _env("MQTT_USERNAME"),
        mqtt_password      = _env("MQTT_PASSWORD"),
        mqtt_topic_prefix  = _env("MQTT_TOPIC_PREFIX", "turnout").strip("/"),
        mqtt_keepalive     = _env_int("MQTT_KEEPALIVE", 30),
        mqtt_qos           = _env_int("MQTT_QOS", 1),

        sqlite_path           = sqlite_path,
        sqlite_retain_events  = _env_int("SQLITE_RETAIN_EVENTS", 50_000),

        heartbeat_interval_seconds      = _env_int("HEARTBEAT_INTERVAL", 5),
        health_interval_seconds         = _env_int("HEALTH_INTERVAL", 30),
        sample_interval_seconds         = float(_env("SAMPLE_INTERVAL", "0.5")),
        failure_debounce_seconds        = _env_int("FAILURE_DEBOUNCE_SECONDS", 5),
        state_publish_interval_seconds  = _env_int("STATE_PUBLISH_INTERVAL", 60),

        input_driver        = _env("INPUT_DRIVER", "simulator").lower(),
        simulator_turnouts  = _env_list("SIMULATOR_TURNOUTS", ["W1110", "W1111", "W1112"]),

        log_level             = _env("LOG_LEVEL", "INFO").upper(),
        log_retention_days    = _env_int("LOG_RETENTION_DAYS", 30),
    )
