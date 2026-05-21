"""
Turnout state machine.

Mapping from BLUEPRINT.md "EXISTING FIELD CONDITION":

    NORMAL   : channel_a=True,  channel_b=False     (Terminal A 24-28VDC)
    REVERSE  : channel_a=False, channel_b=True      (Terminal B 24-28VDC)
    FAILURE  : channel_a=True,  channel_b=True      (both energised — instant)
    FAILURE  : channel_a=False, channel_b=False     (both 0V) — ONLY after
               FAILURE_DEBOUNCE_SECONDS, because turnouts pass through
               both-low briefly during a healthy transition.

The state machine emits a transition exactly once per real change, plus
periodic "keepalive" re-publishes so a freshly-started server can recover
without waiting for the next physical movement.
"""

from __future__ import annotations

import time
from dataclasses import dataclass
from enum import Enum
from typing import Optional


class TurnoutState(str, Enum):
    NORMAL  = "NORMAL"
    REVERSE = "REVERSE"
    FAILURE = "FAILURE"
    UNKNOWN = "UNKNOWN"


def classify_raw(channel_a: bool, channel_b: bool, *, both_low_is_failure: bool) -> TurnoutState:
    """
    Pure mapping (channel_a, channel_b) -> state. The both-low case is
    only FAILURE once the caller has waited out the debounce window —
    we receive `both_low_is_failure` as an explicit boolean so this
    function stays free of timing logic / monotonic clocks.
    """
    if channel_a and not channel_b:
        return TurnoutState.NORMAL
    if channel_b and not channel_a:
        return TurnoutState.REVERSE
    if channel_a and channel_b:
        return TurnoutState.FAILURE
    # both False
    return TurnoutState.FAILURE if both_low_is_failure else TurnoutState.UNKNOWN


@dataclass
class StateUpdate:
    """Result of feeding one sample into the machine."""
    code: str
    state: TurnoutState
    previous_state: Optional[TurnoutState]
    channel_a: bool
    channel_b: bool
    is_transition: bool
    is_keepalive: bool          # True when re-publish triggered by interval


class TurnoutStateMachine:
    """
    One instance per turnout. Stateful across samples — tracks the
    both-low debounce window and the last published state so the runner
    can decide whether to emit a transition.
    """

    def __init__(
        self,
        code: str,
        failure_debounce_seconds: int,
        keepalive_interval_seconds: int,
        clock: callable = time.monotonic,  # type: ignore[type-arg]
    ) -> None:
        self.code = code
        self._failure_debounce_seconds = failure_debounce_seconds
        self._keepalive_interval_seconds = keepalive_interval_seconds
        self._clock = clock

        self._current_state: TurnoutState = TurnoutState.UNKNOWN
        self._both_low_since: Optional[float] = None
        self._last_published_at: float = 0.0

    @property
    def state(self) -> TurnoutState:
        return self._current_state

    def feed(self, channel_a: bool, channel_b: bool) -> StateUpdate:
        now = self._clock()
        both_low = (not channel_a) and (not channel_b)

        # Track / reset the both-low debounce timer.
        if both_low:
            if self._both_low_since is None:
                self._both_low_since = now
            both_low_is_failure = (now - self._both_low_since) >= self._failure_debounce_seconds
        else:
            self._both_low_since = None
            both_low_is_failure = False

        new_state = classify_raw(channel_a, channel_b, both_low_is_failure=both_low_is_failure)

        previous = self._current_state
        is_transition = (new_state != previous and new_state != TurnoutState.UNKNOWN)

        # Keepalive re-publish: even if the state hasn't changed, send a
        # fresh copy every N seconds so a restarted server resyncs quickly.
        is_keepalive = (
            not is_transition
            and new_state != TurnoutState.UNKNOWN
            and (now - self._last_published_at) >= self._keepalive_interval_seconds
        )

        if is_transition:
            self._current_state = new_state
            self._last_published_at = now
        elif is_keepalive:
            self._last_published_at = now

        return StateUpdate(
            code=self.code,
            state=new_state,
            previous_state=previous if previous != TurnoutState.UNKNOWN else None,
            channel_a=channel_a,
            channel_b=channel_b,
            is_transition=is_transition,
            is_keepalive=is_keepalive,
        )
