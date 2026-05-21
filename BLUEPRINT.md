# BLUEPRINT.md

# MRT TURNOUT MONITORING SYSTEM

## PROJECT OVERVIEW

This project is a web-based realtime monitoring and historian platform for MRT railway turnouts (wesel).

The system is designed for:

- Passive monitoring only
- Non-intrusive integration
- Industrial environment
- Rapid development iteration
- Long-term maintainability
- Dockerized deployment
- Realtime visualization
- Historian and replay analysis
- Future predictive maintenance

This project MUST NOT interfere with the existing signaling/interlocking system.

---

# VERY IMPORTANT PROJECT PRINCIPLES

## DO NOT HALLUCINATE

Any AI coding agent working on this project MUST follow these rules:

- NEVER assume electrical specifications without confirmation
- NEVER assume signaling behavior beyond what is documented
- NEVER invent protocol structures
- NEVER invent database fields without checking blueprint
- NEVER invent MQTT topic structures
- NEVER assume UI behavior without specification
- NEVER create hidden business logic
- NEVER hardcode credentials
- NEVER hardcode IP addresses
- NEVER hardcode environment-specific values
- NEVER use external CDN resources
- NEVER create direct electrical coupling to signaling system

If something is unclear:

STOP.
ASK FOR CONFIRMATION.
DO NOT GUESS.

---

# SIGNALING SAFETY PRINCIPLES

The monitoring system:

- MUST be passive read-only
- MUST NOT send command to turnout system
- MUST NOT inject voltage
- MUST use isolated sensing
- MUST use high impedance input
- MUST NOT affect existing signaling behavior
- MUST survive electrical noise and transient environment

---

# EXISTING FIELD CONDITION

## Existing Turnout Indication

Inside signaling/server rack, there are existing turnout indication terminals.

Terminal voltage range:

```text
24-28VDC
```

Turnout indication logic:

## NORMAL

```text
Terminal A = 24-28VDC
Terminal B = 0VDC
```

## REVERSE

```text
Terminal A = 0VDC
Terminal B = 24-28VDC
```

## FAILURE CONDITION

```text
A = 0VDC
B = 0VDC
for longer than configurable timeout
```

OR

```text
A = 24-28VDC
B = 24-28VDC
```

Monitoring system MUST only sense these conditions.

---

# DEPLOYMENT ARCHITECTURE

## Station Nodes

There are 3 monitoring nodes:

| Station | Turnout Count |
|---|---|
| LBB | 5 |
| BLM | 6 |
| BHI | 4 |

Each station has:

- Existing LAN switch
- Fiber optic connection to depot
- Existing UPS-backed rack

---

# HIGH LEVEL ARCHITECTURE

```text
+-----------------------------+
| Existing Signaling System   |
| Turnout Indication Terminal |
| 24-28VDC                    |
+--------------+--------------+
               |
               |
+--------------v--------------+
| Isolated Input Module       |
| Optocoupler Isolation       |
+--------------+--------------+
               |
               |
+--------------v--------------+
| Station Monitoring Node     |
| Ubuntu + Docker             |
| MQTT Publisher              |
| Local SQLite Historian      |
+--------------+--------------+
               |
               | LAN / FO
               |
+--------------v--------------+
| Central Server (Depot)      |
| Laravel + Vue + MQTT        |
| MySQL Historian             |
| Realtime Dashboard          |
+-----------------------------+
```

---

# CORE TECHNOLOGY STACK

## Operating System

- Ubuntu Server 24.04 LTS

---

## Backend

- Laravel 12
- PHP 8.3+

---

## Frontend

- Vue.js 3
- Bootstrap 5 latest stable version
- Vite
- SVG-based visualization

---

## Realtime Communication

- Laravel Reverb
- Redis
- MQTT
- Eclipse Mosquitto

---

## Database

- MySQL 8
- SQLite (local node cache)

---

## Deployment

- Docker
- Docker Compose

---

# UI/UX REQUIREMENTS

## Design Philosophy

UI MUST be:

- Modern
- Minimalist
- Industrial style
- Responsive
- Mobile friendly
- Tablet friendly
- Desktop optimized

---

## Theme System

User MUST be able to:

- Change color theme
- Change accent color
- Toggle dark/light mode

Theme MUST persist per user.

---

## Layout Structure

UI components MUST be separated into reusable components:

```text
Header
Sidebar
Main Content
Footer
Modal
Alarm Popup
Notification Area
Replay Timeline
Dashboard Widget
```

---

## Sidebar Requirements

Sidebar MUST:

- Use relevant icons
- Support collapse mode
- Be responsive
- Support mobile layout

---

## Realtime Visualization

Turnout visualization MUST:

- Animate realtime state changes
- Use SVG graphics
- Green = NORMAL
- Red = REVERSE
- Flashing = FAILURE
- Show persistent fault indicator

---

## Alarm UI

Failure event MUST:

- Show popup notification
- Play browser sound
- Persist turnout fault state visually

Acknowledgement feature is NOT required.

---

## No External CDN

STRICT RULE:

NO INTERNET CDN.

Everything MUST be served locally:

- Bootstrap
- Icons
- Fonts
- JavaScript libraries
- CSS libraries

This system must work completely inside isolated internal MRT network.

---

# MASTER DATA REQUIREMENTS

Every master data entity MUST have full CRUD.

---

## Required CRUD Modules

### User Management

- Create user
- Edit user
- Disable user
- Role assignment
- Password reset
- User status

---

### Role Management

- Create role
- Permission assignment
- Edit permission
- Delete role

---

### Turnout Management

Fields:

- UUID
- Code
- Name
- Description
- Type
- Line
- Station
- Chainage
- Latitude
- Longitude
- Photo
- Manufacturer

---

### Station Management

- CRUD station
- CRUD node assignment

---

### Node Management

- Node status
- Node health
- Node IP
- Heartbeat
- MQTT status

---

### Notification Management

- Email webhook
- WhatsApp webhook
- Generic webhook

---

### Theme Management

- Theme selection
- Accent color
- Dark mode

---

# SERVER ARCHITECTURE

## Containers

```text
nginx
laravel-app
queue-worker
reverb
mysql
redis
mosquitto
```

---

# SERVER DIRECTORY STRUCTURE

```text
server/
├── app/
├── bootstrap/
├── config/
├── database/
├── docker/
│   ├── nginx/
│   ├── php/
│   ├── mysql/
│   ├── redis/
│   └── mosquitto/
├── public/
├── resources/
│   ├── js/
│   │   ├── components/
│   │   ├── layouts/
│   │   ├── pages/
│   │   ├── stores/
│   │   ├── services/
│   │   └── router/
│   ├── css/
│   ├── svg/
│   └── audio/
├── routes/
├── storage/
├── tests/
├── docker-compose.yml
├── Dockerfile
├── .env
└── README.md
```

---

# NODE ARCHITECTURE

## Hardware Recommendation

Recommended hardware:

- Industrial Fanless Mini PC
- Intel N100/N95
- 8GB RAM
- 128GB SSD

Reason:

- Easy maintenance
- Docker support
- Future scalability
- Local historian support
- Current monitoring expansion

---

## Node Runtime

- Ubuntu Server
- Python 3.12
- Docker Compose

---

# NODE DIRECTORY STRUCTURE

```text
node/
├── app/
│   ├── main.py
│   ├── mqtt/
│   ├── input/
│   ├── historian/
│   ├── heartbeat/
│   ├── config/
│   ├── state_machine/
│   └── services/
├── storage/
├── logs/
├── docker/
├── tests/
├── Dockerfile
├── docker-compose.yml
├── requirements.txt
├── .env
└── README.md
```

---

# ENVIRONMENT CONFIGURATION POLICY

STRICT RULE:

Everything configurable MUST use `.env`.

NEVER hardcode:

- Credentials
- Passwords
- API keys
- IP addresses
- MQTT topics
- Database names
- Ports
- Timeout values
- Theme defaults
- Notification endpoint
- Storage paths
- SSL settings
- Debug mode

---

# SERVER ENVIRONMENT VARIABLES

```env
APP_NAME="MRT Turnout Monitoring"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost
APP_TIMEZONE=Asia/Jakarta

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=turnout_monitoring
DB_USERNAME=turnout
DB_PASSWORD=password

REDIS_HOST=redis
REDIS_PORT=6379

MQTT_HOST=mosquitto
MQTT_PORT=1883
MQTT_USERNAME=turnout
MQTT_PASSWORD=password
MQTT_CLIENT_ID=server-core
MQTT_TOPIC_PREFIX=turnout

REVERB_APP_ID=turnout
REVERB_APP_KEY=turnout
REVERB_APP_SECRET=secret
REVERB_HOST=0.0.0.0
REVERB_PORT=8080

ENABLE_EMAIL_NOTIFICATION=false
ENABLE_WHATSAPP_NOTIFICATION=false
ENABLE_WEBHOOK_NOTIFICATION=false
```

---

# NODE ENVIRONMENT VARIABLES

```env
NODE_ID=LBB-NODE-01
NODE_NAME=LBB Monitoring Node
NODE_LOCATION=LBB
TZ=Asia/Jakarta

MQTT_HOST=10.10.10.10
MQTT_PORT=1883
MQTT_USERNAME=turnout
MQTT_PASSWORD=password
MQTT_TOPIC_PREFIX=turnout

SQLITE_PATH=/app/storage/local.db

HEARTBEAT_INTERVAL=5
COMMUNICATION_TIMEOUT=30

LOG_LEVEL=INFO
LOG_RETENTION_DAYS=30
```

---

# MQTT DESIGN

## Topic Structure

### State Update

```text
turnout/station/LBB/turnout/W1110/state
```

---

### Alarm

```text
turnout/station/LBB/turnout/W1110/alarm
```

---

### Heartbeat

```text
turnout/station/LBB/node/LBB-NODE-01/heartbeat
```

---

# MQTT PAYLOAD FORMAT

```json
{
  "timestamp": "2026-05-20T10:00:00+07:00",
  "turnout_uuid": "uuid",
  "turnout_code": "W1110",
  "state": "NORMAL",
  "channel_a": true,
  "channel_b": false,
  "node_id": "LBB-NODE-01"
}
```

- `channel_a = true` indicates Normal position (N)
- `channel_b = true` indicates Reverse position (R)
- if both are false the turnout is not in a valid Normal/Reverse indication

---

# DATABASE TABLES

```text
users
roles
permissions
stations
nodes
turnouts
turnout_states
turnout_events
turnout_alarms
device_health_logs
audit_logs
notification_logs
settings
user_preferences
```

---

# REPLAY ENGINE REQUIREMENTS

Replay engine MUST:

- Replay historical turnout movement
- Reconstruct state from historical events
- Support timeline navigation
- Support date filtering
- Support speed control

---

# EXPORT REQUIREMENTS

Supported export:

- Excel
- PDF
- REST API

Supported filtering:

- By turnout
- By station
- By state
- By alarm
- By date range

---

# DEVICE HEALTH REQUIREMENTS

Node MUST periodically report:

- CPU usage
- RAM usage
- Disk usage
- Uptime
- Container health
- MQTT connection status
- Last heartbeat

---

# FUTURE EXPANSION

The architecture MUST be future-ready for:

- Motor current monitoring
- Predictive maintenance
- GIS visualization
- Cloud synchronization
- Database replication
- High availability
- AI anomaly detection
- Mobile application
- Grafana integration

---

# DEVELOPMENT PHASES

IMPORTANT:

Each phase MUST be completed sequentially.

AI agents MUST update status after completion.

---

# PHASE 1 — FOUNDATION

## Status

```text
[x] COMPLETED
```

## Scope

- Docker setup
- Laravel installation
- Vue installation
- Bootstrap integration
- Authentication
- Role system
- Base layout
- Sidebar
- Header
- Footer
- Theme engine
- Dark mode

## Deliverables

- Login page
- Responsive dashboard layout
- User CRUD
- Role CRUD
- Theme switching

---

# PHASE 2 — MASTER DATA

## Status

```text
[x] COMPLETED
```

## Scope

- Station CRUD
- Node CRUD
- Turnout CRUD
- Photo upload
- Validation
- Audit logging

---

# PHASE 3 — MQTT INTEGRATION

## Status

```text
[x] COMPLETED
```

## Scope

- Mosquitto container
- MQTT subscriber
- Topic parser
- Event storage
- Node heartbeat
- Device health logging

---

# PHASE 4 — REALTIME DASHBOARD

## Status

```text
[x] COMPLETED
```

## Scope

- Realtime SVG turnout visualization
- WebSocket integration
- Animated turnout state
- Alarm popup
- Browser sound
- Responsive dashboard

## Deliverables

- Laravel Reverb installed + broadcasting configured (`BROADCAST_CONNECTION=reverb`)
- Private channels `turnouts.global` and `turnouts.station.{code}` (auth in `routes/channels.php`)
- Broadcast events: `TurnoutStateUpdated`, `TurnoutAlarmRaised`, `TurnoutAlarmCleared` (ShouldBroadcastNow)
- `TelemetryIngestService` fans out broadcasts after each state commit
- `GET /api/dashboard/live` snapshot for initial hydration
- Pinia `useRealtimeStore` keeps state fresh via Echo + Reverb
- `TurnoutSvg.vue` animated component (green/amber/flashing red per blueprint)
- Compact `StationLiveCard.vue` (one card per station, opens modal on click)
- `StationLiveModal.vue` (per-station SVG grid, live-updating)
- `AlarmToastStack.vue` floating popup with persistent fault indicator
- `alarmSound.js` — `/audio/alarm.mp3` with Web Audio API beep fallback (no external CDN)

## Run-time notes

- Start the broker: `php artisan reverb:start`
- Echo client config is via `VITE_REVERB_*` envs (mirror of `REVERB_*`)
- Drop a custom `alarm.mp3` into `public/audio/` to override the beep

---

# PHASE 5 — HISTORIAN

## Status

```text
[x] COMPLETED
```

## Scope

- Event historian
- State duration calculation
- Alarm historian
- Communication historian
- Filtering
- Search

## Deliverables

- TurnoutEvent / TurnoutAlarm / DeviceHealthLog controllers now support
  `station_id`, `from`/`to`, `search`, and per-entity scoping filters
- `HistorianController` with `/state-duration`, `/communication`, `/alarm-summary`
  endpoints (folds raw events into duration/uptime/count aggregates)
- Shared `HistorianFilters.vue` strip with station / state / date / search
- Resource payloads carry nested `station` info for cross-entity UI joins

---

# PHASE 6 — REPLAY ENGINE

## Status

```text
[x] COMPLETED
```

## Scope

- Timeline playback
- Historical reconstruction
- Replay UI
- Speed control

## Deliverables

- `ReplayController` exposes `/api/replay/stations` and `/api/replay/timeline`
  (with pre-window seed state, so playback starts from a known position)
- `pages/Replay.vue` with scrubber, play/pause, step, speed select (1×…60×),
  recent-event ribbon
- Re-uses `TurnoutSvg.vue` for the grid — same component drives live & replay

---

# PHASE 7 — EXPORT ENGINE

## Status

```text
[x] COMPLETED
```

## Scope

- Excel export
- PDF export
- API endpoint
- Flexible filtering

## Deliverables

- `composer require phpoffice/phpspreadsheet barryvdh/laravel-dompdf`
- `ExportService` centralises filter-parity row queries + XLSX / PDF writers
- 6 endpoints under `/api/exports/{turnout-events|turnout-alarms|device-health}.{xlsx|pdf}`
- Blade templates in `resources/views/exports/` (dompdf-safe, inline CSS)
- Shared `ExportButtons.vue` + `utils/download.js` (binary blob via axios)
- Wired into TurnoutEvents / TurnoutAlarms / DeviceHealthLogs pages —
  current filters apply to the export

---

# PHASE 8 — NOTIFICATION ENGINE

## Status

```text
[x] COMPLETED
```

## Scope

- Webhook notification
- Email notification
- WhatsApp integration
- Notification testing

## Deliverables

- `notification_channels` + `notification_logs` tables
- `NotificationDispatcher` fan-out with per-channel drivers:
  - `WebhookDriver` (generic, configurable URL / headers / method)
  - `EmailDriver` (uses Laravel Mail; works with any MAIL_MAILER)
  - `WhatsappDriver` (provider-agnostic; defaults match WAHA / Cloud-API shapes)
- `SendAlarmNotifications` listener subscribed via `AppServiceProvider`
  (synchronous; drivers own their own timeouts)
- `NotificationChannelController` — CRUD + `POST /test` per channel
- `pages/Settings.vue` with full channel manager + per-type form + Test button
- `notifications.view` / `notifications.manage` permissions seeded

---

# PHASE 9 — NODE SOFTWARE

## Status

```text
[x] COMPLETED
```

## Scope

- Python monitoring service
- MQTT publisher
- Local SQLite cache
- Heartbeat service
- State machine
- Failure detection

## Deliverables

- `node/` package (Python 3.12, paho-mqtt + psutil only — stdlib otherwise)
- `app/config/settings.py` — env-driven dataclass, matches BLUEPRINT envs
- `app/input/` — `InputReader` interface, `SimulatorInputReader` (dev),
  `ModbusInputReader` stub (raises until operator wires their DI module)
- `app/state_machine/turnout_state.py` — NORMAL/REVERSE/FAILURE classifier
  with `FAILURE_DEBOUNCE_SECONDS` both-low timeout + `STATE_PUBLISH_INTERVAL`
  keepalive re-publish
- `app/mqtt/` — topic builders matching server `MqttSubscribeCommand` +
  `MqttPublisher` with paho 2.x async loop & auto-reconnect
- `app/historian/sqlite_cache.py` — rolling on-disk backup
  (`SQLITE_RETAIN_EVENTS` cap, WAL mode, thread-safe)
- `app/heartbeat/service.py` — daemon thread emitting heartbeat
  (`HEARTBEAT_INTERVAL`) + device-health (`HEALTH_INTERVAL`, psutil-sourced)
- `app/services/runner.py` + `app/main.py` — orchestrator with SIGINT/SIGTERM
  handlers
- `Dockerfile` (python:3.12-slim, non-root user, healthcheck) +
  `docker-compose.yml` (named volumes, `restart: unless-stopped`) +
  `.env.example`
- `tests/` — 18 unittest cases (state machine debounce edges + topic shape)
  with no broker / hardware dependency

## Run-time notes

- Dev: `INPUT_DRIVER=simulator python -m app.main` (no hardware needed)
- Prod: `INPUT_DRIVER=modbus`, implement `ModbusInputReader.sample()` for
  the specific DI module on site
- Tests: `python -m unittest discover -s tests` (hermetic — no paho needed)

---

# PHASE 10 — FUTURE ANALYTICS

## Status

```text
[ ] NOT STARTED
```

## Scope

- Motor current monitoring
- Predictive maintenance
- AI anomaly detection
- GIS integration

---

# AI AGENT OPERATION RULES

Every AI agent MUST:

- Read this blueprint completely before coding
- Follow the defined architecture
- Use reusable components
- Separate frontend components properly
- Use responsive design
- Keep code modular
- Keep code maintainable
- Keep code dockerized
- Use environment variables everywhere
- Ask for clarification if requirements are unclear

If uncertainty exists:

DO NOT ASSUME.
ASK USER.

---

# END
