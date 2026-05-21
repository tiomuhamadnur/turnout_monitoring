"""
Tests for the turnout state machine. These pin down the exact behaviour
described in BLUEPRINT.md "EXISTING FIELD CONDITION" + "FAILURE CONDITION".

Run from node/:
    python -m unittest discover -s tests
"""

from __future__ import annotations

import unittest

from app.state_machine import TurnoutState, TurnoutStateMachine, classify_raw


class _FakeClock:
    """Deterministic monotonic clock for time-sensitive tests."""

    def __init__(self) -> None:
        self.t = 1_000.0

    def __call__(self) -> float:
        return self.t

    def advance(self, seconds: float) -> None:
        self.t += seconds


class ClassifyRawTests(unittest.TestCase):
    def test_normal(self):
        self.assertEqual(classify_raw(True, False, both_low_is_failure=False), TurnoutState.NORMAL)

    def test_reverse(self):
        self.assertEqual(classify_raw(False, True, both_low_is_failure=False), TurnoutState.REVERSE)

    def test_both_high_is_failure(self):
        self.assertEqual(classify_raw(True, True, both_low_is_failure=False), TurnoutState.FAILURE)

    def test_both_low_unknown_before_debounce(self):
        self.assertEqual(classify_raw(False, False, both_low_is_failure=False), TurnoutState.UNKNOWN)

    def test_both_low_failure_after_debounce(self):
        self.assertEqual(classify_raw(False, False, both_low_is_failure=True), TurnoutState.FAILURE)


class StateMachineTests(unittest.TestCase):
    def setUp(self) -> None:
        self.clock = _FakeClock()
        self.sm = TurnoutStateMachine(
            code="W1110",
            failure_debounce_seconds=5,
            keepalive_interval_seconds=60,
            clock=self.clock,
        )

    def test_initial_transition_to_normal(self):
        u = self.sm.feed(True, False)
        self.assertEqual(u.state, TurnoutState.NORMAL)
        self.assertTrue(u.is_transition)
        self.assertFalse(u.is_keepalive)
        self.assertIsNone(u.previous_state)

    def test_same_state_no_transition(self):
        self.sm.feed(True, False)
        u = self.sm.feed(True, False)
        self.assertFalse(u.is_transition)
        self.assertFalse(u.is_keepalive)

    def test_normal_to_reverse_emits_transition(self):
        self.sm.feed(True, False)
        u = self.sm.feed(False, True)
        self.assertTrue(u.is_transition)
        self.assertEqual(u.state, TurnoutState.REVERSE)
        self.assertEqual(u.previous_state, TurnoutState.NORMAL)

    def test_both_high_is_immediate_failure(self):
        self.sm.feed(True, False)
        u = self.sm.feed(True, True)
        self.assertTrue(u.is_transition)
        self.assertEqual(u.state, TurnoutState.FAILURE)

    def test_both_low_under_debounce_does_not_emit_failure(self):
        # Healthy NORMAL → brief both-low (transit) → REVERSE.
        self.sm.feed(True, False)

        self.clock.advance(1)
        u = self.sm.feed(False, False)
        self.assertEqual(u.state, TurnoutState.UNKNOWN)
        self.assertFalse(u.is_transition)

        self.clock.advance(1)  # still under 5s threshold
        u = self.sm.feed(False, False)
        self.assertFalse(u.is_transition)

        self.clock.advance(1)
        u = self.sm.feed(False, True)
        self.assertTrue(u.is_transition)
        self.assertEqual(u.state, TurnoutState.REVERSE)

    def test_both_low_over_debounce_becomes_failure(self):
        self.sm.feed(True, False)        # establish NORMAL
        self.sm.feed(False, False)       # first both-low: debounce timer starts (UNKNOWN)
        self.clock.advance(6)            # 6s > 5s debounce
        u = self.sm.feed(False, False)
        self.assertTrue(u.is_transition)
        self.assertEqual(u.state, TurnoutState.FAILURE)

    def test_both_low_resets_when_signal_recovers(self):
        """A blip back to a valid level mid-debounce restarts the timer."""
        self.sm.feed(True, False)

        self.clock.advance(3)
        self.sm.feed(False, False)       # debounce armed at t+3

        self.clock.advance(1)
        self.sm.feed(True, False)        # recovered (NORMAL), debounce cancelled

        # Now spend 4s more low — should NOT classify as FAILURE yet (timer reset).
        self.clock.advance(1)
        self.sm.feed(False, False)
        self.clock.advance(3)
        u = self.sm.feed(False, False)   # 4s after the second both-low started
        self.assertFalse(u.is_transition)
        self.assertEqual(u.state, TurnoutState.UNKNOWN)

    def test_keepalive_after_interval(self):
        self.sm.feed(True, False)
        self.clock.advance(61)           # > keepalive 60s
        u = self.sm.feed(True, False)
        self.assertFalse(u.is_transition)
        self.assertTrue(u.is_keepalive)
        self.assertEqual(u.state, TurnoutState.NORMAL)


if __name__ == "__main__":
    unittest.main()
