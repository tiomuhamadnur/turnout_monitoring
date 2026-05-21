"""
Pin the topic shape exactly to BLUEPRINT.md MQTT DESIGN. If this test
breaks, the server's MqttSubscribeCommand subscription will silently
miss messages.
"""

from __future__ import annotations

import unittest

# Import from the submodule directly so the test doesn't pull in paho-mqtt
# via the package __init__. Lets `python -m unittest discover` work without
# the full runtime dependency tree.
from app.mqtt.topics import (
    alarm_topic,
    health_topic,
    heartbeat_topic,
    state_topic,
)


class TopicShapeTests(unittest.TestCase):
    def test_state_topic(self):
        self.assertEqual(
            state_topic("turnout", "LBB", "W1110"),
            "turnout/station/LBB/turnout/W1110/state",
        )

    def test_alarm_topic(self):
        self.assertEqual(
            alarm_topic("turnout", "LBB", "W1110"),
            "turnout/station/LBB/turnout/W1110/alarm",
        )

    def test_heartbeat_topic(self):
        self.assertEqual(
            heartbeat_topic("turnout", "LBB", "LBB-NODE-01"),
            "turnout/station/LBB/node/LBB-NODE-01/heartbeat",
        )

    def test_health_topic(self):
        self.assertEqual(
            health_topic("turnout", "LBB", "LBB-NODE-01"),
            "turnout/station/LBB/node/LBB-NODE-01/health",
        )

    def test_trailing_slash_in_prefix_is_stripped(self):
        self.assertEqual(
            state_topic("turnout/", "LBB", "W1110"),
            "turnout/station/LBB/turnout/W1110/state",
        )


if __name__ == "__main__":
    unittest.main()
