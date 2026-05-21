# MRT Turnout Monitoring System

Web-based realtime monitoring & historian platform for MRT railway
turnouts (wesel). Reads the existing signaling system's turnout
indication terminals **passively** (no command, no voltage injection),
classifies state, and surfaces it on a realtime dashboard with replay,
export, and alarm notifications.

The complete project specification lives in [BLUEPRINT.md](BLUEPRINT.md).
This README is the operational guide — installation, configuration,
deployment, usage.

---

## Table of Contents

- [1. System Overview](#1-system-overview)
- [2. System Topology](#2-system-topology)
- [3. Repository Structure](#3-repository-structure)
- [4. Tech Stack](#4-tech-stack)
- [5. Prerequisites](#5-prerequisites)
- [6. Quick Start](#6-quick-start)
- [7. Server Installation](#7-server-installation)
  - [7.1 Windows (Laragon) — Development](#71-windows-laragon--development)
  - [7.2 Ubuntu Server — Production](#72-ubuntu-server--production)
- [8. Node Installation](#8-node-installation)
  - [8.1 Local Python (Development)](#81-local-python-development)
  - [8.2 Docker per Station (Production)](#82-docker-per-station-production)
- [9. Configuration Reference](#9-configuration-reference)
  - [9.1 Server `.env`](#91-server-env)
  - [9.2 Node `.env`](#92-node-env)
  - [9.3 Mosquitto Broker](#93-mosquitto-broker)
  - [9.4 Laravel Reverb (WebSocket)](#94-laravel-reverb-websocket)
- [10. Running the System](#10-running-the-system)
  - [10.1 Development Workflow](#101-development-workflow)
  - [10.2 Production Operation](#102-production-operation)
- [11. Usage Guide](#11-usage-guide)
- [12. Deployment Walkthroughs](#12-deployment-walkthroughs)
  - [12.1 Windows + Laragon (Dev Workstation)](#121-windows--laragon-dev-workstation)
  - [12.2 Ubuntu Server 24.04 (Depot Production)](#122-ubuntu-server-2404-depot-production)
  - [12.3 Per-Station Node (Industrial Mini-PC)](#123-per-station-node-industrial-mini-pc)
- [13. Maintenance & Operations](#13-maintenance--operations)
- [14. Troubleshooting](#14-troubleshooting)
- [15. Safety & Security](#15-safety--security)

---

## 1. System Overview

The system has **two halves**:

| Half | Role | Where it runs |
| ---- | ---- | ------------- |
| **Central server** (`server/`) | Web dashboard, API, historian, MQTT subscriber, broadcaster | One depot box (Ubuntu) |
| **Station node** (`node/`) | Reads turnout indication, publishes telemetry to broker | One industrial mini-PC per station |

Three stations are currently provisioned:

| Station | Code | Turnouts |
| ------- | ---- | -------- |
| Lebak Bulus | `LBB` | 5 |
| Blok M     | `BLM` | 6 |
| Bundaran HI | `BHI` | 4 |

Data flow:

1. **Field**: existing turnout indication terminals (24–28 VDC, channels A / B).
2. **Isolated input module** (optocoupler / Modbus DI) at the station rack —
   passively senses A / B.
3. **Station node** (Python) classifies state → publishes MQTT.
4. **Depot broker** (Mosquitto) fans out to the **central server**.
5. **Server** stores in MySQL historian, then broadcasts via **Reverb (WebSocket)**.
6. **Operators** see live state in their browser.

What you get out of the box:

- Realtime per-station SVG dashboard with animated NORMAL / REVERSE / FAILURE
- Persistent fault popup + browser sound on FAILURE
- Historian (events, alarms, device health) with filtering & search
- Timeline replay engine (scrub historical state, play/pause/speed)
- Excel + PDF exports for every historian view
- Webhook / Email / WhatsApp alarm notifications (configurable in UI)
- User / role / permission management (Spatie permissions)
- Multi-station master data, audit logs, theme switching, dark mode

---

## 2. System Topology

### 2.1 Full Topology

```
                                                  +---------------------+
                                                  |  Operator Browser   |
                                                  |  (Vue 3 SPA)        |
                                                  +----------+----------+
                                                             | HTTPS (or HTTP dev)
                                                             | WebSocket (Reverb)
                                                  +----------v----------+
+-------------------------+                       |  Depot Server       |
|   Station Node (LBB)    |                       |  Ubuntu 24.04 LTS   |
|   Industrial Mini-PC    |  MQTT QoS 1           |                     |
|   + Isolated DI Module  +----------+            |  nginx              |
+-------------------------+          |            |  PHP-FPM 8.3        |
                                     |            |  Laravel 12         |
+-------------------------+          v            |  Reverb (WS:8080)   |
|   Station Node (BLM)    |   +-------------+     |  Queue worker       |
|   Industrial Mini-PC    +-->| Mosquitto   +<----+  Redis              |
|   + Isolated DI Module  |   |  :1883      |     |  MySQL 8            |
+-------------------------+   +------+------+     |  Mosquitto :1883    |
                                     ^            |  (or external)      |
+-------------------------+          |            |                     |
|   Station Node (BHI)    +----------+            |  artisan            |
|   Industrial Mini-PC    |                       |    mqtt:subscribe   |
|   + Isolated DI Module  |                       |    queue:work       |
+-------------------------+                       |    reverb:start     |
       ^                                          +---------+-----------+
       | passive read 24-28 VDC                             |
       | (optocoupler isolated)                             | LAN / FO
+------+------------------+                                 |
| EXISTING SIGNALING      |     ←  NEVER MODIFIED  →        |
| Turnout indication      |                                 |
| terminals (A + B)       |                                 v
+-------------------------+                       +---------------------+
                                                  |   Existing depot    |
                                                  |   LAN / fiber       |
                                                  +---------------------+
```

The signaling system is **never modified**. The monitoring system observes
only — see [Section 15](#15-safety--security).

### 2.2 Data Flow (Per State Change)

```
Field A/B    Optocoupler    Station Node                  Mosquitto    Laravel              Browser
  flip   →   bit flip    →  sample() every 500ms    →     QoS 1     →  MqttSubscribeCmd  →  Reverb WS
                            |                                            |                    |
                            v                                            v                    v
                       TurnoutStateMachine                          TelemetryIngest      realtime store
                       (debounce, classify)                         (DB upsert + event)  (Pinia)
                            |                                            |                    |
                            v                                            v                    v
                       Publish state topic                          Broadcast event       Animated SVG
                       Persist to SQLite cache                       (TurnoutStateUpdated) + alarm popup
                                                                          |
                                                                          v
                                                                    NotificationDispatcher
                                                                    (Webhook / Email / WA)
```

### 2.3 State Classification

Per [BLUEPRINT.md](BLUEPRINT.md) "EXISTING FIELD CONDITION":

| channel_a | channel_b | Classification                                            |
| --------- | --------- | --------------------------------------------------------- |
| `1`       | `0`       | **NORMAL** — Terminal A energised                         |
| `0`       | `1`       | **REVERSE** — Terminal B energised                        |
| `1`       | `1`       | **FAILURE** — both energised (short / fault)              |
| `0`       | `0`       | **FAILURE** — only after `FAILURE_DEBOUNCE_SECONDS`       |

The both-low debounce exists because turnouts transit through `(0, 0)`
briefly during a healthy NORMAL ↔ REVERSE flip — we don't want to false-fire.

### 2.4 MQTT Topic Tree

```
turnout/                                          ← MQTT_TOPIC_PREFIX (env)
└── station/
    ├── LBB/
    │   ├── turnout/
    │   │   ├── W1110/state           ← {timestamp, state, channel_a, channel_b, node_id}
    │   │   └── W1111/state
    │   └── node/
    │       └── LBB-NODE-01/
    │           ├── heartbeat         ← every HEARTBEAT_INTERVAL
    │           └── health            ← every HEALTH_INTERVAL (CPU/RAM/disk/uptime)
    ├── BLM/...
    └── BHI/...
```

The server's `php artisan mqtt:subscribe` daemon subscribes to the
equivalent `+` wildcards and forwards each message to `TelemetryIngestService`.

---

## 3. Repository Structure

```
monitoring_wesel/
├── BLUEPRINT.md                  Full project specification
├── README.md                     ← you are here
├── server/                       Laravel 12 + Vue 3 SPA (depot)
│   ├── app/
│   │   ├── Console/Commands/
│   │   │   └── MqttSubscribeCommand.php    MQTT subscriber daemon
│   │   ├── Events/                          Broadcast events (Reverb)
│   │   ├── Http/Controllers/Api/            REST controllers
│   │   ├── Listeners/                       Alarm → notification fan-out
│   │   ├── Models/                          Eloquent models
│   │   └── Services/
│   │       ├── TelemetryIngestService.php   Single ingest path
│   │       ├── ExportService.php            Excel + PDF
│   │       └── Notifications/               Webhook/Email/WhatsApp drivers
│   ├── config/
│   │   ├── broadcasting.php   reverb.php   mqtt.php   …
│   ├── database/
│   │   ├── migrations/                      Schema
│   │   └── seeders/                         RolePermission, Lines, RuntimeDemo
│   ├── docker/                              Mosquitto + nginx + php configs
│   ├── public/                              Web root (served by nginx / Laragon)
│   ├── resources/
│   │   ├── audio/    (alarm.mp3 mounted into public/audio)
│   │   ├── js/
│   │   │   ├── App.vue   app.js   router/
│   │   │   ├── components/   layouts/   pages/   services/   stores/   utils/
│   │   ├── scss/                            Bootstrap 5 + theme overrides
│   │   └── views/exports/                   dompdf templates
│   ├── routes/   api.php   web.php   channels.php   console.php
│   ├── .env                                 Environment (NOT committed)
│   ├── composer.json   package.json
│   └── vite.config.js
│
└── node/                         Python 3.12 (per station)
    ├── app/
    │   ├── main.py                          Entry point
    │   ├── config/settings.py               Env → dataclass
    │   ├── input/                           SimulatorInputReader, ModbusInputReader
    │   ├── state_machine/turnout_state.py   NORMAL/REVERSE/FAILURE classifier
    │   ├── mqtt/                            Topics + paho-mqtt publisher
    │   ├── historian/sqlite_cache.py        Local backup (rolling)
    │   ├── heartbeat/service.py             Heartbeat + psutil health
    │   └── services/runner.py               Orchestrator
    ├── tests/                               18 unittest (state + topics)
    ├── Dockerfile   docker-compose.yml      Per-station container
    ├── requirements.txt                     paho-mqtt + psutil
    ├── .env.example
    └── README.md                            Node-specific docs
```

---

## 4. Tech Stack

### 4.1 Server (`server/`)

| Layer | Tech | Notes |
| ----- | ---- | ----- |
| OS (prod) | Ubuntu Server 24.04 LTS | LTS-only |
| Language | PHP 8.3+ | 8.2 minimum per `composer.json` |
| Framework | Laravel 12 | API + SPA host |
| Auth | Laravel Sanctum (SPA cookie) | No tokens; CSRF-protected |
| Permissions | Spatie Laravel Permission 6 | Role-based |
| Database | MySQL 8 | UTF8MB4 |
| Cache / Queue | Redis 7 + Laravel queue (database driver default) | |
| Realtime | Laravel Reverb (WebSocket) + Echo + pusher-js | |
| MQTT | Eclipse Mosquitto 2.x + `php-mqtt/client` | Server runs the subscriber daemon |
| Frontend | Vue 3 + Vue Router + Pinia | SPA |
| Build | Vite 6 | |
| UI kit | Bootstrap 5.3 + Bootstrap Icons | **No external CDN** |
| Charts | Chart.js + vue-chartjs | |
| Export | PhpOffice/PhpSpreadsheet + barryvdh/laravel-dompdf | |

### 4.2 Node (`node/`)

| Layer | Tech | Notes |
| ----- | ---- | ----- |
| OS | Ubuntu Server (industrial mini-PC) | Intel N100/N95 recommended |
| Language | Python 3.12 | |
| MQTT | paho-mqtt 2.1 | |
| System metrics | psutil 6.1 | CPU / RAM / disk / uptime |
| Local cache | SQLite (stdlib `sqlite3`) | WAL mode |
| Container | Docker + Docker Compose | |
| Input drivers | Simulator (dev) / Modbus TCP stub (prod) | Replace stub per site |

---

## 5. Prerequisites

### 5.1 Development (Windows workstation)

- **Laragon Full** (PHP 8.2/8.3, MySQL 8, nginx, Composer bundled)
- **Node.js 20+** + npm (or use Laragon's bundled Node)
- **Python 3.12** (only if you want to run a node locally)
- **Git for Windows**
- **VS Code** (recommended)

### 5.2 Production (Depot — Ubuntu Server)

- Ubuntu Server 24.04 LTS (clean install)
- Static IP, reachable from station nodes over the depot LAN/fiber
- DNS or `/etc/hosts` entry for the dashboard hostname
- Outbound 587/465 SMTP (only if Email notifications enabled)

### 5.3 Production (Per Station — Industrial Mini-PC)

- Ubuntu Server 24.04 LTS or any Docker-capable distro
- Network reachability to the depot Mosquitto broker (port 1883)
- Isolated digital-input module wired to the existing turnout indication
  terminals (read-only, optocoupler-isolated — see [Section 15](#15-safety--security))

---

## 6. Quick Start

For impatient readers — full instructions in [Section 12](#12-deployment-walkthroughs).

### 6.1 Dev (Windows + Laragon, 5 minutes)

```powershell
# Inside Laragon's www folder
git clone <repo> monitoring_wesel
cd monitoring_wesel\server

composer install
npm install
copy .env.example .env
php artisan key:generate
# Edit .env: DB_DATABASE=turnout_monitoring, DB_USERNAME=root, DB_PASSWORD=
# Create the DB in Laragon's HeidiSQL: turnout_monitoring

php artisan migrate --seed
php artisan storage:link
npm run build

# Four terminals (or use composer dev):
php artisan serve            # API
npm run dev                  # Vite
php artisan reverb:start     # WebSocket
php artisan mqtt:subscribe   # MQTT (needs broker — see 9.3)
```

Visit http://localhost:8000 — log in with the seeded super-admin account
(printed by the seeder, or see `server/database/seeders/`).

### 6.2 Run a Simulator Node Locally

```powershell
cd monitoring_wesel\node
python -m venv .venv
.venv\Scripts\activate
pip install -r requirements.txt
copy .env.example .env
# Edit .env: MQTT_HOST=127.0.0.1 (or your dev broker host)
python -m app.main
```

The simulator drives realistic NORMAL ↔ REVERSE flips + occasional
FAILURE patterns. Dashboard's "Live Stations" cards animate in realtime.

---

## 7. Server Installation

### 7.1 Windows (Laragon) — Development

#### Step 1 — Install Laragon Full

Download from [laragon.org](https://laragon.org) and install with **Full**
edition so you get PHP 8.2/8.3, MySQL 8, nginx, Composer.

Confirm:

```powershell
php -v          # PHP 8.2+
composer -V
mysql --version # 8.x
node -v         # 20+
```

#### Step 2 — Clone & install

```powershell
cd C:\laragon\www
git clone <your-repo-url> monitoring_wesel
cd monitoring_wesel\server

composer install
npm install
```

#### Step 3 — Create database

In Laragon, open **HeidiSQL** → connect to MySQL → create a new database
`turnout_monitoring` (utf8mb4 / utf8mb4_unicode_ci). Default Laragon root
password is empty.

#### Step 4 — Environment

```powershell
copy .env.example .env
php artisan key:generate
```

Edit `.env` — see [Section 9.1](#91-server-env) for the full reference.
Minimum changes for dev:

```env
APP_URL=http://localhost:8000
DB_DATABASE=turnout_monitoring
DB_USERNAME=root
DB_PASSWORD=
SESSION_DOMAIN=null
SANCTUM_STATEFUL_DOMAINS=localhost,localhost:*,127.0.0.1,127.0.0.1:*
BROADCAST_CONNECTION=reverb
```

#### Step 5 — Database & seeders

```powershell
php artisan migrate --seed
php artisan storage:link
```

The seeders create:
- `RolePermissionSeeder` — `super-admin`, `admin`, `operator`, `viewer` roles
- `LineSeeder` — MRT line metadata
- `RuntimeDemoSeeder` — demo stations / nodes / turnouts so the dashboard
  has data even before a real node is publishing

Check the seeder output for the super-admin email/password.

#### Step 6 — Build assets

```powershell
npm run build     # one-shot production build
# OR
npm run dev       # Vite dev server with HMR
```

#### Step 7 — Run

Easiest — use the composer script that boots everything:

```powershell
composer dev
```

That starts `php artisan serve`, `queue:listen`, `pail` (log tail), and
`npm run dev` in parallel via npx concurrently. **Reverb** and
**mqtt:subscribe** are separate — open two more terminals:

```powershell
php artisan reverb:start
php artisan mqtt:subscribe
```

Open http://localhost:8000.

### 7.2 Ubuntu Server — Production

The blueprint targets Docker, but a native install is more pragmatic for
a single depot server. Both paths below work; pick one.

#### Path A — Native (recommended for single depot box)

##### A.1 OS prep

```bash
sudo apt update && sudo apt -y full-upgrade
sudo apt -y install software-properties-common curl git unzip nginx \
                    redis-server supervisor mosquitto mosquitto-clients
```

##### A.2 PHP 8.3

```bash
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update
sudo apt -y install php8.3-fpm php8.3-cli php8.3-mysql php8.3-redis \
                    php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip \
                    php8.3-bcmath php8.3-gd php8.3-intl php8.3-sqlite3
```

##### A.3 MySQL 8

```bash
sudo apt -y install mysql-server
sudo mysql_secure_installation
sudo mysql -e "CREATE DATABASE turnout_monitoring CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'turnout'@'localhost' IDENTIFIED BY 'CHANGE_ME';"
sudo mysql -e "GRANT ALL ON turnout_monitoring.* TO 'turnout'@'localhost'; FLUSH PRIVILEGES;"
```

##### A.4 Node.js 20 + Composer

```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt -y install nodejs
curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer
```

##### A.5 Clone & build

```bash
sudo mkdir -p /var/www
cd /var/www
sudo git clone <repo-url> monitoring_wesel
sudo chown -R www-data:www-data monitoring_wesel
sudo -u www-data bash -c '
  cd /var/www/monitoring_wesel/server
  composer install --no-dev --optimize-autoloader
  npm ci
  cp .env.example .env
  php artisan key:generate
'
```

Edit `/var/www/monitoring_wesel/server/.env`:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://turnout.depot.local        # your actual hostname
APP_TIMEZONE=Asia/Jakarta

DB_HOST=127.0.0.1
DB_DATABASE=turnout_monitoring
DB_USERNAME=turnout
DB_PASSWORD=CHANGE_ME

SESSION_DOMAIN=.depot.local                # match APP_URL domain
SANCTUM_STATEFUL_DOMAINS=turnout.depot.local

BROADCAST_CONNECTION=reverb
REVERB_HOST=turnout.depot.local            # public hostname browser connects to
REVERB_SERVER_HOST=0.0.0.0
REVERB_PORT=8080
REVERB_SCHEME=https                        # or http if not terminating TLS

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"

MQTT_HOST=127.0.0.1
MQTT_USERNAME=turnout
MQTT_PASSWORD=CHANGE_ME
```

##### A.6 Migrate, seed, build

```bash
cd /var/www/monitoring_wesel/server
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan db:seed --force
sudo -u www-data php artisan storage:link
sudo -u www-data npm run build
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
```

##### A.7 Mosquitto

```bash
sudo cp /var/www/monitoring_wesel/server/docker/mosquitto/mosquitto.conf /etc/mosquitto/conf.d/turnout.conf
sudo mosquitto_passwd -c /etc/mosquitto/passwd turnout    # enter MQTT_PASSWORD
sudo sed -i 's|password_file /mosquitto/config/passwd|password_file /etc/mosquitto/passwd|' \
        /etc/mosquitto/conf.d/turnout.conf
sudo systemctl restart mosquitto && sudo systemctl enable mosquitto
```

Confirm: `mosquitto_sub -h 127.0.0.1 -u turnout -P CHANGE_ME -t '#' -v`

##### A.8 nginx

Create `/etc/nginx/sites-available/turnout`:

```nginx
server {
    listen 80;
    server_name turnout.depot.local;
    root /var/www/monitoring_wesel/server/public;
    index index.php;

    client_max_body_size 20M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* { deny all; }
}

# WebSocket (Reverb)
server {
    listen 8080;
    server_name turnout.depot.local;
    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_read_timeout 60s;
    }
}
```

If you terminate TLS, put the certs in front and bump scheme to `https` /
`wss` in `.env`. Then:

```bash
sudo ln -s /etc/nginx/sites-available/turnout /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

##### A.9 Supervisor (queue, reverb, mqtt:subscribe)

Create `/etc/supervisor/conf.d/turnout.conf`:

```ini
[program:turnout-queue]
process_name=%(program_name)s
command=php /var/www/monitoring_wesel/server/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/turnout/queue.log

[program:turnout-reverb]
process_name=%(program_name)s
command=php /var/www/monitoring_wesel/server/artisan reverb:start
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/turnout/reverb.log

[program:turnout-mqtt]
process_name=%(program_name)s
command=php /var/www/monitoring_wesel/server/artisan mqtt:subscribe
autostart=true
autorestart=true
startsecs=10
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/turnout/mqtt.log
```

```bash
sudo mkdir -p /var/log/turnout && sudo chown www-data: /var/log/turnout
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl status
```

#### Path B — Docker Compose (server side)

The project does not ship a server-side `docker-compose.yml` yet (the
Mosquitto / nginx / mysql configs in `server/docker/` are the building
blocks). If you prefer Docker, write a compose stack that mounts those
configs and runs `php-fpm`, `nginx`, `mysql`, `redis`, `mosquitto`,
`reverb`, `queue-worker`, `mqtt-subscriber` as separate services. The
blueprint section "SERVER ARCHITECTURE → Containers" lists them all.
This is left as a future task; the native install is fully sufficient
for a single depot box.

---

## 8. Node Installation

### 8.1 Local Python (Development)

For developing or testing the pipeline without real hardware.

```bash
cd node
python3.12 -m venv .venv
source .venv/bin/activate     # Windows: .venv\Scripts\activate
pip install -r requirements.txt
cp .env.example .env
```

Edit `node/.env`:

```env
NODE_ID=DEV-NODE-01
NODE_LOCATION=LBB
MQTT_HOST=127.0.0.1           # or your dev server's IP
MQTT_USERNAME=turnout
MQTT_PASSWORD=password
INPUT_DRIVER=simulator
SIMULATOR_TURNOUTS=W1110,W1111,W1112,W1113,W1114
```

Run:

```bash
python -m app.main
```

You should see periodic `→ W1110 NORMAL (was REVERSE)` lines in stdout
and the dashboard's "Live Stations" should animate. Run the test suite
any time:

```bash
python -m unittest discover -s tests -v
```

### 8.2 Docker per Station (Production)

One container per physical station mini-PC.

```bash
sudo apt -y install docker.io docker-compose-plugin
sudo usermod -aG docker $USER && newgrp docker

cd /opt
sudo git clone <repo-url> monitoring_wesel
sudo chown -R $USER monitoring_wesel
cd monitoring_wesel/node

cp .env.example .env
# Edit: NODE_ID, NODE_LOCATION, MQTT_HOST (depot server IP), credentials,
#       INPUT_DRIVER=modbus (and implement ModbusInputReader.sample()
#       for the specific DI module on site), SIMULATOR_TURNOUTS if testing.

docker compose up -d --build
docker compose logs -f node
```

The compose file uses `restart: unless-stopped`, so a host reboot brings
the node back automatically. Data lives in the `node_storage` named
volume.

To upgrade after a `git pull`:

```bash
cd /opt/monitoring_wesel/node
git pull
docker compose up -d --build
```

---

## 9. Configuration Reference

### 9.1 Server `.env`

| Variable | Default | Purpose |
| -------- | ------- | ------- |
| `APP_NAME` | `"MRTJ Turnout Monitoring"` | Shown in UI / emails |
| `APP_ENV` | `local` / `production` | Laravel env |
| `APP_DEBUG` | `true` / `false` | **MUST be `false` in prod** |
| `APP_URL` | `http://localhost` | Base URL (no trailing slash) |
| `APP_TIMEZONE` | `Asia/Jakarta` | App-wide TZ |
| `DB_*` | `mysql://turnout:…@127.0.0.1/turnout_monitoring` | DB connection |
| `SESSION_DOMAIN` | `null` (dev) / `.domain` (prod) | Sanctum cookie scope |
| `SANCTUM_STATEFUL_DOMAINS` | `localhost,…` | Which hosts may use SPA cookie auth |
| `BROADCAST_CONNECTION` | `reverb` | WebSocket driver |
| `REVERB_APP_ID` / `_KEY` / `_SECRET` | `turnout` / `turnout` / `secret` | **Rotate in prod** |
| `REVERB_HOST` | `localhost` / public hostname | Browser connects here |
| `REVERB_SERVER_HOST` | `0.0.0.0` | Bind interface for the server |
| `REVERB_PORT` | `8080` | |
| `REVERB_SCHEME` | `http` / `https` | Match your TLS termination |
| `VITE_REVERB_*` | mirrors above | Build-time exposure to JS bundle |
| `MQTT_HOST` | `127.0.0.1` / broker IP | Where the subscriber connects |
| `MQTT_PORT` | `1883` | |
| `MQTT_USERNAME` / `_PASSWORD` | `turnout` / `password` | **Rotate in prod** |
| `MQTT_CLIENT_ID` | `server-core` | Prefix; subscriber appends random suffix |
| `MQTT_TOPIC_PREFIX` | `turnout` | Topic root |
| `TELEMETRY_INGEST_TOKEN` | `change-me-…` | Bearer for `/api/internal/telemetry/*` HTTP ingest path |
| `MAIL_*` | log driver | Configure SMTP for Email notifications |
| `ENABLE_EMAIL_NOTIFICATION` etc. | `false` | Feature flags |

### 9.2 Node `.env`

| Variable | Default | Purpose |
| -------- | ------- | ------- |
| `NODE_ID` | (required) | Unique per node, e.g. `LBB-NODE-01` — must exist in `nodes` table on the server |
| `NODE_NAME` | `"Unnamed Node"` | Human label |
| `NODE_LOCATION` | (required) | Station code (`LBB` / `BLM` / `BHI`) |
| `TZ` | `Asia/Jakarta` | Container TZ |
| `MQTT_HOST` / `PORT` / `USERNAME` / `PASSWORD` | (required) | Broker creds |
| `MQTT_TOPIC_PREFIX` | `turnout` | MUST match server |
| `MQTT_KEEPALIVE` | `30` | Seconds |
| `MQTT_QOS` | `1` | At-least-once delivery |
| `SQLITE_PATH` | `/app/storage/local.db` | Inside container |
| `SQLITE_RETAIN_EVENTS` | `50000` | Rolling cap |
| `HEARTBEAT_INTERVAL` | `5` | Seconds |
| `HEALTH_INTERVAL` | `30` | Seconds |
| `SAMPLE_INTERVAL` | `0.5` | Seconds between input reads |
| `FAILURE_DEBOUNCE_SECONDS` | `5` | Both-low timeout before FAILURE |
| `STATE_PUBLISH_INTERVAL` | `60` | Keepalive re-publish |
| `INPUT_DRIVER` | `simulator` | `simulator` / `modbus` |
| `SIMULATOR_TURNOUTS` | `W1110,W1111,W1112` | Comma-separated codes (dev only) |
| `LOG_LEVEL` | `INFO` | Python log level |

### 9.3 Mosquitto Broker

Production config lives in `server/docker/mosquitto/mosquitto.conf` (also
deployable to native install — see [§7.2 A.7](#a7-mosquitto)).

Key points:
- Listens on `:1883`
- Persistence enabled — retained messages survive restart
- `allow_anonymous false` + `password_file` — credentials required
- Create the password file once with `mosquitto_passwd -c ... turnout`,
  then secret-mount it

For an external managed broker (HiveMQ, EMQX cloud, etc.), just point
`MQTT_HOST` at it and skip the local Mosquitto install. Topic structure
stays identical.

### 9.4 Laravel Reverb (WebSocket)

Reverb runs as a long-lived PHP process via `php artisan reverb:start`.

- **Bind address**: `REVERB_SERVER_HOST=0.0.0.0` (server listens on all interfaces)
- **Public host**: `REVERB_HOST=turnout.depot.local` (what the browser
  connects to — mirrored via `VITE_REVERB_HOST` so Vite bakes it into the bundle)
- **Behind nginx**: proxy `/app/<key>` (or just `:8080`) with
  `Connection: upgrade` headers
- **TLS**: set `REVERB_SCHEME=https`, terminate at nginx with a wildcard
  or specific cert

Echo client (`server/resources/js/services/echo.js`) reads these from
`import.meta.env.VITE_REVERB_*`. Rebuild the SPA after changing them.

---

## 10. Running the System

### 10.1 Development Workflow

Per the auto-memory: **no Docker for dev**. Laragon handles MySQL +
PHP-FPM; Vite handles the SPA. Open 4–5 terminals (or use `composer dev`
to batch four of them):

| Terminal | Command | Purpose |
| -------- | ------- | ------- |
| 1 | `php artisan serve` | HTTP API (port 8000) |
| 2 | `npm run dev` | Vite (HMR on the SPA) |
| 3 | `php artisan queue:listen --tries=1` | Background jobs |
| 4 | `php artisan reverb:start` | WebSocket broker |
| 5 | `php artisan mqtt:subscribe` | MQTT ingest daemon |
| 6 (optional) | `python -m app.main` (in `node/`) | Simulator node |

Logs: `tail -f storage/logs/laravel.log` or `php artisan pail`.

### 10.2 Production Operation

All daemons run under **supervisor** (see [§7.2 A.9](#a9-supervisor-queue-reverb-mqttsubscribe)):

```bash
sudo supervisorctl status                       # check
sudo supervisorctl restart turnout-mqtt         # one daemon
sudo supervisorctl restart all                  # everything
```

Nginx + PHP-FPM + MySQL + Redis + Mosquitto are systemd units:

```bash
sudo systemctl status nginx php8.3-fpm mysql redis-server mosquitto
sudo systemctl restart php8.3-fpm
```

After deploy:

```bash
cd /var/www/monitoring_wesel/server
sudo -u www-data git pull
sudo -u www-data composer install --no-dev --optimize-autoloader
sudo -u www-data npm ci && sudo -u www-data npm run build
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
sudo supervisorctl restart all
```

---

## 11. Usage Guide

### 11.1 Login & Roles

Open `/login`. Seeded roles:

| Role | Can | Cannot |
| ---- | --- | ------ |
| `super-admin` | Everything (Gate::before bypass) | — |
| `admin` | Full CRUD on master data + settings | — |
| `operator` | View dashboards, historian, replay, exports, notifications | Edit master data / users |
| `viewer` | View dashboards, alarms, master data (read-only) | Anything write |

### 11.2 Dashboard (`/`)

- **Live Stations cards** — one per station. Click any card → modal with
  per-turnout SVG grid (animated). Card pulses red when any turnout in the
  station is FAILURE.
- **Realtime indicator** — green dot pulses when Reverb is connected.
- **KPI cards** — turnouts / nodes / active alarms / healthy nodes.
- **State distribution + Event trend charts** — Chart.js, theme-aware.
- **Recent events table** — last 10 turnout state changes.
- **Alarm popup stack** — floating top-right, persistent until dismissed.
  Plays `/audio/alarm.mp3` (drop your own file; falls back to Web Audio
  beep otherwise).

### 11.3 Historian

- **Turnout Events** (`/turnout-events`) — every state change. Filter by
  station, state, date range, code/name search, transitions-only.
- **Turnout Alarms** (`/turnout-alarms`) — active + resolved, with
  duration. Filter by station, status, type, date.
- **Device Health Logs** (`/device-health-logs`) — CPU/RAM/disk/uptime
  per node. Filter by station, MQTT status, date.

Each historian page has **Export Excel / Export PDF** buttons that
respect the current filters.

### 11.4 Replay (`/replay`)

1. Pick a station + time window (defaults: last 6h).
2. **Load Timeline** — fetches all events + seed state.
3. Use the transport: ▶ Play / ⏸ Pause / step ◀ ▶ / 🔄 Rewind.
4. **Speed**: 1× / 4× / 10× / 25× / 60× events per second.
5. **Scrubber** lets you jump anywhere in the window.
6. The SVG grid live-renders as the playhead moves; the recent-event
   ribbon underneath narrates the last 8 transitions.

### 11.5 Master Data (`/stations`, `/lines`, `/nodes`, `/turnouts`)

Full CRUD pages with validation + audit logging. Turnouts support photo
upload; node IPs / MQTT status are tracked.

### 11.6 Users & Roles (`/users`, `/roles`)

Admin-only. Assign roles + per-permission overrides via the Roles page.
Spatie permissions; super-admins bypass via `Gate::before`.

### 11.7 Settings → Notifications (`/settings`)

Operators can add, edit, enable/disable, and **test** notification
channels:

- **Webhook** — POST JSON `{event, payload, timestamp}` to any URL with
  configurable headers, method, timeout.
- **Email** — uses the configured `MAIL_*` driver. Recipients list +
  subject prefix.
- **WhatsApp** — provider-agnostic HTTP POST (defaults match WAHA's
  `/sendText` shape; customise field names for Twilio / Cloud API / etc.).

Each channel subscribes to one or more events: `alarm.raised`,
`alarm.cleared`, `test`, or empty = all.

The **Test** button dispatches a synthetic notification and shows the
delivery status inline. Logs are in `notification_logs` table for
post-mortem.

### 11.8 Audit Logs (`/audit-logs`)

Append-only record of every CRUD action on auditable models (Stations,
Lines, Nodes, Turnouts, Users) — actor, action, before/after, IP, UA.

---

## 12. Deployment Walkthroughs

### 12.1 Windows + Laragon (Dev Workstation)

See [§7.1](#71-windows-laragon--development). TL;DR:

1. Laragon Full → clone into `C:\laragon\www`.
2. `composer install && npm install && php artisan key:generate`.
3. Create `turnout_monitoring` DB in HeidiSQL.
4. `php artisan migrate --seed && php artisan storage:link`.
5. Run all daemons in separate terminals (or `composer dev` for four).
6. Need a local broker? Install Mosquitto for Windows from
   [mosquitto.org/download](https://mosquitto.org/download/), or skip
   it and use the HTTP ingest path `POST /api/internal/telemetry/state`
   (token-protected) to push telemetry from anywhere.

### 12.2 Ubuntu Server 24.04 (Depot Production)

See [§7.2 Path A](#path-a--native-recommended-for-single-depot-box). End
state:

```
nginx:80,8080  ──→  PHP-FPM 8.3  ──→  Laravel 12
                                       │
                                       ├──→ MySQL 8 (turnout_monitoring)
                                       └──→ Redis (cache + queue + Reverb scaling-ready)

systemd: nginx, php8.3-fpm, mysql, redis-server, mosquitto

supervisor:
  ├── turnout-queue    (php artisan queue:work)
  ├── turnout-reverb   (php artisan reverb:start)
  └── turnout-mqtt     (php artisan mqtt:subscribe)
```

**TLS** — put a reverse proxy (Caddy or nginx with certbot) in front and
flip `APP_URL`, `REVERB_SCHEME`, `VITE_REVERB_SCHEME` to `https`/`wss`.
Rebuild SPA after each `.env` change that mutates `VITE_*` values.

**Firewall** — open only what's needed:

```bash
sudo ufw allow 22                    # SSH
sudo ufw allow 80,443                # HTTP(S)
sudo ufw allow 8080                  # Reverb (or proxy through 443)
sudo ufw allow from <station-subnet> to any port 1883   # MQTT
sudo ufw enable
```

### 12.3 Per-Station Node (Industrial Mini-PC)

See [§8.2](#82-docker-per-station-production). One-time setup per station:

1. Flash Ubuntu Server, hostname e.g. `lbb-node-01`.
2. Install Docker + Compose plugin.
3. Clone the repo, `cd node/`, copy `.env.example` → `.env`, fill in
   `NODE_ID`, `NODE_LOCATION`, `MQTT_HOST` (depot IP), credentials.
4. For **simulator** mode (commissioning): set `INPUT_DRIVER=simulator`
   and a `SIMULATOR_TURNOUTS` list matching the master-data codes.
5. For **production** (Modbus DI module wired to the indication
   terminals): set `INPUT_DRIVER=modbus`, and implement
   `app/input/modbus.py`'s `sample()` for the specific DI hardware
   (Moxa/Wago/Advantech — each has its own register map).
6. `docker compose up -d --build`.

Verify on the depot dashboard: the station's live card flips green and
turnouts populate within `HEARTBEAT_INTERVAL` seconds.

---

## 13. Maintenance & Operations

### 13.1 Backups

- **MySQL** — daily `mysqldump turnout_monitoring | gzip > /backup/turnout_$(date +%F).sql.gz`,
  retain 30 days. Rotate with cron + `tmpwatch` / `find -mtime`.
- **Mosquitto** — config + password file: `/etc/mosquitto/` (rarely
  changes; commit a sanitised copy to git).
- **Node SQLite** — `node_storage` Docker volume. Optional — only useful
  for forensic recovery if the depot historian is wiped.

### 13.2 Log rotation

- **Server**: `storage/logs/laravel.log` — add a logrotate rule or let
  Laravel daily channel handle it (`LOG_CHANNEL=daily`).
- **Supervisor**: configure logrotate for `/var/log/turnout/*.log`.
- **Node**: container stdout — `docker compose logs --tail 1000`.
  Container logs rotate per Docker daemon config (default 10MB × 3).

### 13.3 Upgrades

```bash
# Server (Ubuntu prod)
cd /var/www/monitoring_wesel/server
sudo -u www-data git pull
sudo -u www-data composer install --no-dev --optimize-autoloader
sudo -u www-data npm ci && sudo -u www-data npm run build
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan config:cache route:cache view:cache
sudo supervisorctl restart all

# Node (each station)
cd /opt/monitoring_wesel/node
git pull
docker compose up -d --build
```

### 13.4 Health checks

- **Reverb up?** `curl http://localhost:8080/app/turnout/?protocol=7` (Pusher protocol probe)
- **MQTT subscriber up?** `sudo supervisorctl status turnout-mqtt`
- **Broker reachable?** `mosquitto_sub -h $MQTT_HOST -u turnout -P … -t '#' -v -C 3`
- **Node alive?** `docker compose ps && docker compose logs --tail 50 node`
- **DB connection?** `php artisan tinker --execute='echo DB::select("select 1")[0]->{"1"};'`

---

## 14. Troubleshooting

| Symptom | Likely cause | Fix |
| ------- | ------------ | --- |
| Login redirects loop, 419 errors | `SESSION_DOMAIN` doesn't match the host you're visiting | Set `SESSION_DOMAIN=null` (dev) or `.<base-domain>` (prod) |
| `Sanctum: stateful` request rejected | Host not in `SANCTUM_STATEFUL_DOMAINS` | Add it (supports wildcard `*` per entry) |
| Dashboard shows no live data | (a) Reverb not running (b) MQTT subscriber not running (c) Node not publishing | Check supervisor + `mosquitto_sub` |
| `MQTT connect failed: Automatic reconnects cannot be used together with the clean session flag` | Old version of `MqttSubscribeCommand.php` | Already fixed — `git pull` |
| `MQTT connect failed: maximum reconnect attempts cannot be fewer than 1` | Same | Already fixed |
| Alarm popup but no sound | Browser blocked autoplay | Click anywhere on the dashboard once — `primeAlarm()` unlocks audio |
| Excel export empty / 500 | `phpoffice/phpspreadsheet` not installed | `composer install` |
| `Class … DomPDF not found` | `barryvdh/laravel-dompdf` not installed | `composer install` |
| Node container restarts in a loop | `MQTT_HOST` unreachable from container | Check container can ping/route to broker; use depot IP, not `127.0.0.1` |
| Reverb connects then immediately drops | `wsHost` in browser doesn't match `REVERB_HOST` | Rebuild SPA after editing `VITE_REVERB_HOST` |
| `mqtt:subscribe` connects but no rows appear | Topic prefix mismatch between node and server | Both must use the same `MQTT_TOPIC_PREFIX` |
| Notification "Test" returns `skipped` | Channel `config` missing the required field | Open the channel, fill in required fields, save, test again |

---

## 15. Safety & Security

**This system is passive monitoring.** Per
[BLUEPRINT.md](BLUEPRINT.md) "SIGNALING SAFETY PRINCIPLES":

- MUST be passive read-only
- MUST NOT send command to turnout system
- MUST NOT inject voltage
- MUST use isolated sensing (optocoupler / Modbus DI)
- MUST use high impedance input
- MUST NOT affect existing signaling behavior

The Python node's `InputReader` abstraction enforces this — drivers only
read. There is **no code path** that writes back to field hardware.

Operational security:

- Rotate `REVERB_APP_SECRET`, `MQTT_PASSWORD`, `TELEMETRY_INGEST_TOKEN`,
  and DB passwords for production. Never commit `.env`.
- Run `php artisan key:generate` on each new install.
- `APP_DEBUG=false` in production.
- TLS-terminate the dashboard (depot LAN is not the public internet but
  still — defence in depth).
- Limit Mosquitto's bind interface (`bind_address 10.x.x.x`) so the
  broker doesn't accept connections from outside the depot LAN.
- Use a dedicated MySQL user with database-scoped privileges, not `root`.
- Audit log every master-data change (already wired via the `Auditable`
  trait).

---

## Appendix — Useful Commands

```bash
# --- Server (artisan) ---
php artisan migrate:fresh --seed          # Reset DB + reseed (DESTRUCTIVE — dev only)
php artisan tinker                        # REPL
php artisan route:list --path=api         # Inspect API routes
php artisan reverb:start --debug          # WebSocket with verbose logs
php artisan mqtt:subscribe                # Foreground MQTT ingester
php artisan db:seed --class=RolePermissionSeeder
php artisan permission:cache-reset

# --- Server (composer) ---
composer dev                              # Boot serve+queue+pail+vite together
composer install --no-dev -o              # Production install

# --- Server (npm) ---
npm run dev                               # Vite HMR
npm run build                             # Production bundle

# --- Mosquitto sanity ---
mosquitto_sub -h 127.0.0.1 -u turnout -P password -t 'turnout/#' -v
mosquitto_pub -h 127.0.0.1 -u turnout -P password \
              -t 'turnout/station/LBB/turnout/W1110/state' \
              -m '{"timestamp":"2026-05-21T10:00:00+07:00","turnout_code":"W1110","state":"NORMAL","channel_a":true,"channel_b":false,"node_id":"LBB-NODE-01"}'

# --- Node ---
python -m app.main                        # Run foreground (dev)
python -m unittest discover -s tests -v   # Tests
docker compose up -d --build              # Build + run (prod)
docker compose logs -f node               # Tail logs
docker compose exec node sqlite3 /app/storage/local.db 'select count(*) from local_events;'

# --- Supervisor (prod) ---
sudo supervisorctl status
sudo supervisorctl restart turnout-mqtt
sudo supervisorctl tail -f turnout-reverb stdout
```

---

For deeper architectural / design rationale, read
[BLUEPRINT.md](BLUEPRINT.md). For node-specific docs, see
[node/README.md](node/README.md).
