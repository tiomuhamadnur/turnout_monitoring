# MRT Turnout Monitoring — Station Node

Passive per-station monitoring node. Reads the existing turnout
indication terminals (24–28 VDC, channel A/B) through an isolated
input module, classifies state, and publishes telemetry to the depot
Mosquitto broker over MQTT.

Stack: Python 3.12 + paho-mqtt + psutil, deployed via Docker on each
station's industrial mini-PC. See `../BLUEPRINT.md` for the full system
specification — this README only covers the node software.

---

## Architecture

```
+--------------------------+
| Existing turnout         |
| indication terminal      |   24-28 VDC A/B
+------------+-------------+
             |
+------------v-------------+
| Isolated input module    |   optocoupler / Modbus DI
+------------+-------------+
             |
+------------v-------------+
| InputReader (this app)   |   simulator | modbus
+------------+-------------+
             |
+------------v-------------+
| TurnoutStateMachine      |   NORMAL/REVERSE/FAILURE + debounce
+------------+-------------+
             |
+------------v-------------+
| MqttPublisher  ──────────┼──> depot Mosquitto broker
| SqliteCache              |   (local backup, rolling)
| HeartbeatService         |   heartbeat + CPU/RAM/disk/uptime
+--------------------------+
```

State mapping (per `BLUEPRINT.md` "EXISTING FIELD CONDITION"):

| channel_a | channel_b | classification                                  |
| --------- | --------- | ----------------------------------------------- |
| `1`       | `0`       | `NORMAL`                                        |
| `0`       | `1`       | `REVERSE`                                       |
| `1`       | `1`       | `FAILURE` (both energised, immediate)           |
| `0`       | `0`       | `FAILURE` after `FAILURE_DEBOUNCE_SECONDS`      |

---

## Layout

```
node/
├── app/
│   ├── main.py                  Entry point
│   ├── config/settings.py       Env-driven Settings dataclass
│   ├── input/                   base / simulator / modbus stub
│   ├── state_machine/           NORMAL/REVERSE/FAILURE classifier + debounce
│   ├── mqtt/                    Topic builders + paho publisher
│   ├── historian/               SqliteCache (rolling local log)
│   ├── heartbeat/               Heartbeat + device-health thread
│   └── services/runner.py       Orchestrator
├── tests/                       unittest suite (no broker / hardware needed)
├── Dockerfile
├── docker-compose.yml
├── requirements.txt
└── .env.example
```

---

## Quick start — Docker (recommended)

```bash
cp .env.example .env
# edit .env: at minimum set NODE_ID, NODE_LOCATION, MQTT_HOST
docker compose up -d --build
docker compose logs -f node
```

The container `restart: unless-stopped` survives host reboots. The
SQLite cache lives in the `node_storage` named volume.

## Quick start — Local Python (dev)

```bash
python3.12 -m venv .venv
source .venv/bin/activate   # or .venv\Scripts\activate on Windows
pip install -r requirements.txt
cp .env.example .env
# point MQTT_HOST at a reachable broker (or run server/docker/mosquitto)
INPUT_DRIVER=simulator python -m app.main
```

The simulator generates realistic NORMAL ↔ REVERSE flips plus the
occasional FAILURE pattern, so you can verify the full pipeline end-to-end
against the dashboard without touching real hardware.

---

## Tests

```bash
python -m unittest discover -s tests -v
```

The included suite covers the state machine (debounce edge cases,
keepalive interval, transition emission) and the MQTT topic builders
(must match the server-side `MqttSubscribeCommand` wildcards). It does
**not** require paho-mqtt or a broker — the topic test imports the
submodule directly so the suite is hermetic.

---

## Production deployment

1. Install the isolated input module (optocoupler + Modbus DI) per the
   site electrical plan. NEVER source voltage onto the existing
   turnout indication terminals — see `BLUEPRINT.md` "SIGNALING SAFETY
   PRINCIPLES".
2. Set `INPUT_DRIVER=modbus` and provide your Modbus host + register
   mapping (currently a stub in `app/input/modbus.py` — implement
   `sample()` for your specific DI module).
3. `docker compose up -d --build` on each station's mini-PC.
4. Verify on the depot dashboard: the station's live card should turn
   green and show the per-turnout state within `HEARTBEAT_INTERVAL`
   seconds.

## Environment variables

See `.env.example` — full list lives in `BLUEPRINT.md` "NODE ENVIRONMENT
VARIABLES" and is the source of truth.
