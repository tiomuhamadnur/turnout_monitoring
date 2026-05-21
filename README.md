# MRT Turnout Monitoring System

Web-based realtime monitoring and historian platform for MRT railway
turnouts (wesel). The system reads existing turnout indication terminals
passively, classifies state, stores history, and exposes live status on
the dashboard.

The authoritative specification remains [BLUEPRINT.md](BLUEPRINT.md).
This README is the operational guide, with production deployment now
standardized on Docker Compose for both `server/` and `node/`.

---

## Table of Contents

- [1. System Overview](#1-system-overview)
- [2. Repository Structure](#2-repository-structure)
- [3. Runtime Topology](#3-runtime-topology)
- [4. Development Workflow](#4-development-workflow)
- [5. Production Deployment](#5-production-deployment)
  - [5.1 Central Server (`server/`)](#51-central-server-server)
  - [5.2 Station Node (`node/`)](#52-station-node-node)
- [6. Configuration Reference](#6-configuration-reference)
- [7. Operations](#7-operations)
- [8. Troubleshooting](#8-troubleshooting)
- [9. Safety & Security](#9-safety--security)

---

## 1. System Overview

The system has two deployable parts:

| Part | Folder | Role |
| ---- | ------ | ---- |
| Central server | `server/` | Laravel + Vue dashboard, historian, MQTT subscriber, Reverb, MySQL, Redis, Mosquitto |
| Station node | `node/` | Python service per station node that reads inputs and publishes MQTT |

Current stations from the blueprint:

| Station | Code | Turnouts |
| ------- | ---- | -------- |
| Lebak Bulus | `LBB` | 5 |
| Blok M | `BLM` | 6 |
| Bundaran HI | `BHI` | 4 |

State mapping:

| channel_a | channel_b | state |
| --------- | --------- | ----- |
| `1` | `0` | `NORMAL` |
| `0` | `1` | `REVERSE` |
| `1` | `1` | `FAILURE` |
| `0` | `0` | `FAILURE` after debounce |

---

## 2. Repository Structure

```text
monitoring_wesel/
|-- BLUEPRINT.md
|-- README.md
|-- server/
|   |-- app/
|   |-- bootstrap/
|   |-- config/
|   |-- database/
|   |-- docker/
|   |-- public/
|   |-- resources/
|   |-- routes/
|   |-- Dockerfile
|   |-- docker-compose.yml
|   `-- .env.docker.example
`-- node/
    |-- app/
    |-- tests/
    |-- Dockerfile
    |-- docker-compose.yml
    `-- .env.example
```

---

## 3. Runtime Topology

### 3.1 Server stack

Production `server/` runs these containers:

- `nginx`
- `app`
- `queue-worker`
- `reverb`
- `mqtt-subscriber`
- `mysql`
- `redis`
- `mosquitto`
- `init` (one-shot bootstrap)

Persistent named volumes:

- `mrt_turnout_app_storage`
- `mrt_turnout_app_bootstrap_cache`
- `mrt_turnout_mysql_data`
- `mrt_turnout_redis_data`
- `mrt_turnout_mosquitto_data`
- `mrt_turnout_mosquitto_log`

### 3.2 Node stack

Each station mini-PC runs one `node` container with persistent volumes:

- `mrt_turnout_node_storage`
- `mrt_turnout_node_logs`

---

## 4. Development Workflow

Development remains non-Docker for the Laravel app, following the
existing Laragon / local workflow. Docker is the production standard.

### 4.1 Server dev

```powershell
cd server
composer install
npm install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
```

Run the dev services:

```powershell
php artisan serve
npm run dev
php artisan reverb:start
php artisan mqtt:subscribe
```

### 4.2 Node dev

```powershell
cd node
python -m venv .venv
.venv\Scripts\activate
pip install -r requirements.txt
copy .env.example .env
python -m app.main
```

---

## 5. Production Deployment

### 5.1 Central Server (`server/`)

### Prerequisites

- Ubuntu Server 24.04 LTS
- Docker Engine + Docker Compose plugin
- reachable hostname or static IP
- network path from station nodes to MQTT port `1883`

### 1. Install Docker

```bash
sudo apt update && sudo apt -y full-upgrade
sudo apt -y install ca-certificates curl git

curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker $USER
newgrp docker
docker compose version
```

Optional firewall baseline:

```bash
sudo ufw allow 22
sudo ufw allow 80
sudo ufw allow from <station-subnet> to any port 1883
sudo ufw enable
```

### 2. Clone repository

```bash
sudo mkdir -p /opt
cd /opt
sudo git clone <repo-url> monitoring_wesel
sudo chown -R $USER:$USER monitoring_wesel
cd /opt/monitoring_wesel/server
```

### 3. Prepare `.env`

Use the Docker-specific template:

```bash
cp .env.docker.example .env
```

Generate one `APP_KEY`:

```bash
docker run --rm php:8.3-cli php -r 'echo "base64:".base64_encode(random_bytes(32)).PHP_EOL;'
```

Minimum values to review before first boot:

```env
APP_KEY=base64:...
APP_ENV=production
APP_DEBUG=false
APP_URL=http://turnout.depot.local
APP_HTTP_PORT=80

DB_DATABASE=turnout_monitoring
DB_USERNAME=turnout
DB_PASSWORD=change-me-db-password
DB_ROOT_PASSWORD=change-me-root-password

REDIS_PASSWORD=

MQTT_HOST=mosquitto
MQTT_PORT=1883
MQTT_USERNAME=turnout
MQTT_PASSWORD=change-me-mqtt-password

REVERB_APP_ID=turnout
REVERB_APP_KEY=change-me-reverb-key
REVERB_APP_SECRET=change-me-reverb-secret
REVERB_HOST=turnout.depot.local
REVERB_PORT=80
REVERB_SCHEME=http
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080

VITE_REVERB_APP_KEY=change-me-reverb-key
VITE_REVERB_HOST=turnout.depot.local
VITE_REVERB_PORT=80
VITE_REVERB_SCHEME=http

SESSION_DOMAIN=turnout.depot.local
SANCTUM_STATEFUL_DOMAINS=turnout.depot.local

APP_RUN_SEEDERS=true
APP_SEED_CLASS=DatabaseSeeder
```

Important notes:

- `DB_HOST`, `REDIS_HOST`, and `MQTT_HOST` should stay as Docker service names unless you intentionally externalize those dependencies.
- `VITE_REVERB_*` is compile-time. Any change to host, port, or scheme requires `docker compose up -d --build`.
- `REVERB_SERVER_PORT=8080` is internal only. Browser clients should use `REVERB_PORT` through nginx.
- `APP_RUN_SEEDERS=true` is intended for first boot only. Set it back to `false` after initial deployment.

### 4. Build and start

```bash
docker compose build
docker compose up -d
docker compose ps
```

The one-shot `init` container will:

1. wait for MySQL, Redis, and Mosquitto;
2. run `php artisan migrate --force`;
3. create `public/storage`;
4. optionally run `db:seed`;
5. cache config, routes, and views.

Check bootstrap output:

```bash
docker compose logs init
docker compose logs -f app queue-worker reverb mqtt-subscriber nginx
```

### 5. First-boot cleanup

After initial deployment succeeds:

1. verify login and seeded data;
2. set `APP_RUN_SEEDERS=false` in `.env`;
3. rebuild and restart:

```bash
docker compose up -d --build
```

### 6. TLS / reverse proxy

The shipped stack exposes plain HTTP on `APP_HTTP_PORT`. For production,
terminate TLS in front of the stack, then update:

```env
APP_URL=https://turnout.depot.local
REVERB_PORT=443
REVERB_SCHEME=https
VITE_REVERB_HOST=turnout.depot.local
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
```

Apply changes with:

```bash
docker compose up -d --build
```

### 7. What persists

Server data that survives container recreation:

- MySQL database
- Redis append-only data
- Mosquitto retained/persistent broker data
- Laravel `storage/`
- Laravel `bootstrap/cache`

### 5.2 Station Node (`node/`)

Each physical station mini-PC runs one compose project from `node/`.

### 1. Install Docker

```bash
sudo apt update
sudo apt -y install docker.io docker-compose-plugin git
sudo usermod -aG docker $USER
newgrp docker
```

### 2. Clone repository

```bash
cd /opt
sudo git clone <repo-url> monitoring_wesel
sudo chown -R $USER:$USER monitoring_wesel
cd /opt/monitoring_wesel/node
```

### 3. Prepare `.env`

```bash
cp .env.example .env
```

Typical production node values:

```env
NODE_ID=LBB-NODE-01
NODE_NAME=LBB Monitoring Node
NODE_LOCATION=LBB
TZ=Asia/Jakarta

MQTT_HOST=10.10.10.10
MQTT_PORT=1883
MQTT_USERNAME=turnout
MQTT_PASSWORD=change-me-mqtt-password
MQTT_TOPIC_PREFIX=turnout

SQLITE_PATH=/app/storage/local.db

INPUT_DRIVER=modbus
LOG_LEVEL=INFO
```

Important notes:

- use a unique `NODE_ID` per physical node;
- keep one `.env` per station device;
- use `INPUT_DRIVER=simulator` only for commissioning and lab testing;
- implement `app/input/modbus.py` for the actual DI module before field deployment.

### 4. Start the node

```bash
docker compose up -d --build
docker compose ps
docker compose logs -f node
```

The node stack is intentionally simple:

- one service only;
- `restart: unless-stopped`;
- persistent SQLite cache volume;
- persistent log volume;
- `no-new-privileges` and `tmpfs /tmp`.

---

## 6. Configuration Reference

### 6.1 Server environment

Key server variables:

| Variable | Purpose |
| -------- | ------- |
| `APP_URL` | public dashboard URL |
| `APP_HTTP_PORT` | host port mapped to nginx `:80` |
| `DB_*` | MySQL credentials and database |
| `REDIS_*` | Redis connection |
| `MQTT_*` | broker connection for Laravel subscriber |
| `REVERB_*` | public and internal websocket settings |
| `VITE_REVERB_*` | compile-time frontend websocket settings |
| `SESSION_DOMAIN` | Sanctum/Laravel session cookie domain |
| `SANCTUM_STATEFUL_DOMAINS` | allowed stateful SPA origins |
| `APP_RUN_SEEDERS` | first-boot seeding toggle |

### 6.2 Node environment

Key node variables:

| Variable | Purpose |
| -------- | ------- |
| `NODE_ID` | unique node identifier |
| `NODE_LOCATION` | station code |
| `MQTT_HOST` / `MQTT_PORT` | depot broker |
| `MQTT_USERNAME` / `MQTT_PASSWORD` | broker credentials |
| `SQLITE_PATH` | local cache path in container |
| `INPUT_DRIVER` | `simulator` or `modbus` |
| `FAILURE_DEBOUNCE_SECONDS` | both-low failure debounce |
| `STATE_PUBLISH_INTERVAL` | keepalive republish |

### 6.3 Mosquitto in production

The broker uses [`server/docker/mosquitto/mosquitto.conf`](server/docker/mosquitto/mosquitto.conf).

Behavior:

- listens on `1883`;
- persistence enabled in a named volume;
- anonymous access disabled;
- password file is generated from `MQTT_USERNAME` and `MQTT_PASSWORD`
  on every container start.

### 6.4 Reverb in production

Reverb runs as a dedicated container and is proxied by nginx.

- internal listen: `reverb:8080`
- browser endpoint: `http(s)://<REVERB_HOST>:<REVERB_PORT>/app/...`
- if `VITE_REVERB_*` changes, rebuild the images

---

## 7. Operations

### 7.1 Common server commands

```bash
cd /opt/monitoring_wesel/server

docker compose ps
docker compose logs -f app queue-worker reverb mqtt-subscriber nginx
docker compose restart app
docker compose restart queue-worker
docker compose restart reverb
docker compose restart mqtt-subscriber
docker compose up -d
docker compose down
```

### 7.2 Upgrade server

```bash
cd /opt/monitoring_wesel/server
git pull
docker compose up -d --build
```

### 7.3 Upgrade node

```bash
cd /opt/monitoring_wesel/node
git pull
docker compose up -d --build
```

### 7.4 Backups

Logical MySQL backup:

```bash
cd /opt/monitoring_wesel/server
mkdir -p ./backups
docker compose exec -T mysql mysqldump -uroot -p"$DB_ROOT_PASSWORD" "$DB_DATABASE" \
  | gzip > "./backups/turnout_$(date +%F_%H%M%S).sql.gz"
```

Recommended backup targets:

- `mrt_turnout_mysql_data`
- `mrt_turnout_redis_data`
- `mrt_turnout_mosquitto_data`
- `mrt_turnout_app_storage`
- `mrt_turnout_node_storage` on each station node

### 7.5 Health checks

Server:

```bash
cd /opt/monitoring_wesel/server
docker compose ps
docker compose logs init
docker compose logs --tail 50 reverb
docker compose logs --tail 50 mqtt-subscriber
```

Node:

```bash
cd /opt/monitoring_wesel/node
docker compose ps
docker compose logs --tail 50 node
```

---

## 8. Troubleshooting

| Symptom | Likely cause | Fix |
| ------- | ------------ | --- |
| Dashboard has no live updates | `reverb`, `mqtt-subscriber`, or node container is down | check `docker compose ps` and logs on server and node |
| Reverb connects then drops | `VITE_REVERB_*` does not match public host/port/scheme | fix `.env`, then `docker compose up -d --build` in `server/` |
| Login loop / 419 | bad `SESSION_DOMAIN` or `SANCTUM_STATEFUL_DOMAINS` | align both with the actual hostname |
| Node restarts repeatedly | broker unreachable or bad credentials | verify `MQTT_HOST`, network route, username/password |
| `mqtt-subscriber` starts but no rows appear | topic prefix mismatch | keep the same `MQTT_TOPIC_PREFIX` on node and server |
| Export endpoints fail after deploy | stale app image | rebuild with `docker compose up -d --build` |
| First deployment boots but users/roles are missing | seeder was disabled | set `APP_RUN_SEEDERS=true`, run `docker compose up -d --build`, then turn it off again |

---

## 9. Safety & Security

This system is passive monitoring only. Per the blueprint:

- MUST be read-only toward field signaling;
- MUST NOT send commands to turnout equipment;
- MUST NOT inject voltage;
- MUST use isolated sensing;
- MUST NOT alter signaling behavior.

Operational guidance:

- never commit `.env`;
- rotate `APP_KEY`, DB passwords, `MQTT_PASSWORD`, `REVERB_APP_SECRET`,
  and `TELEMETRY_INGEST_TOKEN` per environment;
- keep `APP_DEBUG=false` in production;
- terminate TLS in front of the stack;
- expose MySQL and Redis externally only when explicitly required;
- keep each station node on a stable identity and controlled network path.

---

For deeper functional and architectural detail, read
[BLUEPRINT.md](BLUEPRINT.md). For node-focused notes, see
[node/README.md](node/README.md).
