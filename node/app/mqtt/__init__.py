# Topic builders are pure-stdlib and safe to load eagerly.
# `MqttPublisher` lives in publisher.py and is imported directly by the
# runner/heartbeat to keep this package importable in test environments
# that don't have paho-mqtt installed (e.g. running just tests/test_topics.py).
from .topics import (
    state_topic,
    alarm_topic,
    heartbeat_topic,
    health_topic,
)

__all__ = [
    "state_topic",
    "alarm_topic",
    "heartbeat_topic",
    "health_topic",
]
