# MRT Turnout Monitoring — Station Node
# Python 3.12 slim. No build toolchain needed (paho-mqtt + psutil ship wheels).
FROM python:3.12-slim AS runtime

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    PIP_NO_CACHE_DIR=1 \
    TZ=Asia/Jakarta

# tzdata so the node logs in the operator's local time.
RUN apt-get update \
 && apt-get install -y --no-install-recommends tzdata \
 && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY requirements.txt ./
RUN pip install --no-cache-dir -r requirements.txt

COPY app/ ./app/

# Storage dir is mounted as a named volume from docker-compose so the
# SQLite cache survives container restarts.
RUN mkdir -p /app/storage /app/logs

# Run as non-root for safety.
RUN useradd --create-home --shell /usr/sbin/nologin node \
 && chown -R node:node /app
USER node

# Lightweight health probe: process must keep responding to import.
# Single-line form so dockerfile linters don't mistake the embedded
# CMD subcommand for a top-level CMD instruction.
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 CMD ["python", "-c", "import app.main"]

CMD ["python", "-m", "app.main"]
