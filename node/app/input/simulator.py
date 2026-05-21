"""
Simulator input driver — used for development and CI so the full
telemetry pipeline can run without real hardware.

Behaviour per turnout:
  - Sits in NORMAL or REVERSE for a randomised hold time (5–60s).
  - Occasionally enters a FAILURE pattern:
      * both-low (in transit / cable break)   — ~10% of failures
      * both-high (short)                     — ~10% of failures
      * straight flip with bounce             — rest are bounce, not failure
  - The state machine downstream debounces; we just produce raw bits.
"""

from __future__ import annotations

import random
import time
from typing import Dict

from .base import InputReader, TurnoutChannels


class _SimTurnout:
    __slots__ = ("code", "channel_a", "channel_b", "next_change_at")

    def __init__(self, code: str) -> None:
        self.code = code
        # Start in NORMAL.
        self.channel_a = True
        self.channel_b = False
        self.next_change_at = time.monotonic() + random.uniform(5, 60)

    def step(self, now: float) -> None:
        if now < self.next_change_at:
            return

        roll = random.random()
        if roll < 0.05:
            # Both-low (in transit / fault). State machine will count down
            # FAILURE_DEBOUNCE_SECONDS before classifying as FAILURE.
            self.channel_a = False
            self.channel_b = False
            self.next_change_at = now + random.uniform(6, 12)
        elif roll < 0.08:
            # Both-high (short). Immediate FAILURE classification.
            self.channel_a = True
            self.channel_b = True
            self.next_change_at = now + random.uniform(3, 8)
        else:
            # Healthy flip to the opposite leg.
            if self.channel_a and not self.channel_b:
                self.channel_a, self.channel_b = False, True
            else:
                self.channel_a, self.channel_b = True, False
            self.next_change_at = now + random.uniform(20, 90)


class SimulatorInputReader(InputReader):
    def __init__(self, turnout_codes: list[str]) -> None:
        if not turnout_codes:
            raise ValueError("Simulator needs at least one turnout code.")
        self._turnouts = {code: _SimTurnout(code) for code in turnout_codes}

    def turnout_codes(self) -> list[str]:
        return list(self._turnouts.keys())

    def sample(self) -> Dict[str, TurnoutChannels]:
        now = time.monotonic()
        for t in self._turnouts.values():
            t.step(now)
        return {
            code: TurnoutChannels(code=code, channel_a=t.channel_a, channel_b=t.channel_b)
            for code, t in self._turnouts.items()
        }
