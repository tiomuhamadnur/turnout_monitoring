"""
Abstract input layer. Each driver reads the raw (channel_a, channel_b)
pair for every turnout the node is responsible for and hands them to the
state machine. The state machine — NOT the driver — decides NORMAL vs
REVERSE vs FAILURE.

Drivers MUST be passive (read-only). See BLUEPRINT.md "SIGNALING SAFETY
PRINCIPLES" — we never source voltage onto the indication terminals.
"""

from __future__ import annotations

from abc import ABC, abstractmethod
from dataclasses import dataclass
from typing import Dict


@dataclass(frozen=True)
class TurnoutChannels:
    """Raw electrical reading for one turnout at one sample tick."""
    code: str
    channel_a: bool   # Terminal A energised (24-28VDC)
    channel_b: bool   # Terminal B energised (24-28VDC)


class InputReader(ABC):
    """
    A driver returns a snapshot of every turnout it owns whenever
    `sample()` is called. The runner polls this at SAMPLE_INTERVAL.
    """

    @abstractmethod
    def turnout_codes(self) -> list[str]:
        """List of turnout codes this reader knows about. Stable for the
        process lifetime — used at startup to bootstrap state machines."""

    @abstractmethod
    def sample(self) -> Dict[str, TurnoutChannels]:
        """Return a {code: TurnoutChannels} map for the current instant."""

    def close(self) -> None:
        """Release any underlying resources (sockets, files, GPIO). Default no-op."""
        return None
