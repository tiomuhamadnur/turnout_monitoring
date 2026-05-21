"""
Modbus TCP input driver (STUB).

Industrial Digital-Input modules (Moxa, Advantech, Wago, etc.) all expose
their isolated inputs over Modbus TCP. The actual register layout differs
per device, so we don't ship a baked-in mapping — the operator wires it
up at deploy time via a JSON file path in MODBUS_MAPPING_PATH.

This file intentionally raises NotImplementedError on sample() until the
real deployment hardware is known, per BLUEPRINT.md's "NEVER assume
electrical specifications without confirmation" rule.
"""

from __future__ import annotations

from typing import Dict

from .base import InputReader, TurnoutChannels


class ModbusInputReader(InputReader):
    def __init__(self, host: str, port: int = 502, mapping_path: str | None = None) -> None:
        self._host = host
        self._port = port
        self._mapping_path = mapping_path

    def turnout_codes(self) -> list[str]:
        raise NotImplementedError(
            "Modbus driver not configured. Provide MODBUS_HOST + a "
            "JSON mapping of turnout codes to (channel_a_register, channel_b_register) "
            "via MODBUS_MAPPING_PATH, then implement sample()."
        )

    def sample(self) -> Dict[str, TurnoutChannels]:
        raise NotImplementedError(
            "Configure the deployment-specific Modbus DI module here. "
            "Use pymodbus.client.ModbusTcpClient.read_discrete_inputs() "
            "and map register bits to TurnoutChannels per the operator's mapping file."
        )
