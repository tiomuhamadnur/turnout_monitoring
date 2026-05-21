"""
Topic builders. Must match BLUEPRINT.md "MQTT DESIGN" — server-side
MqttSubscribeCommand subscribes to the equivalent wildcards.

Mismatch here = silent drop; tests in tests/test_topics.py keep us honest.
"""

from __future__ import annotations


def state_topic(prefix: str, station: str, turnout_code: str) -> str:
    return f"{prefix.strip('/')}/station/{station}/turnout/{turnout_code}/state"


def alarm_topic(prefix: str, station: str, turnout_code: str) -> str:
    return f"{prefix.strip('/')}/station/{station}/turnout/{turnout_code}/alarm"


def heartbeat_topic(prefix: str, station: str, node_id: str) -> str:
    return f"{prefix.strip('/')}/station/{station}/node/{node_id}/heartbeat"


def health_topic(prefix: str, station: str, node_id: str) -> str:
    return f"{prefix.strip('/')}/station/{station}/node/{node_id}/health"
