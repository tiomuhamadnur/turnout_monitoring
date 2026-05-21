"""
Local SQLite "historian" — a rolling on-disk log of every payload this
node has emitted, regardless of whether the broker acked it.

Why store it locally even though MQTT QoS 1 already retries?
  - If the broker is wiped or the central historian goes down, the node
    still has its own copy for ad-hoc forensics.
  - Operators on-site can pull the DB to a USB key without needing
    network access to the depot server.

The table is intentionally simple: one row per published payload, plus
a trim to SQLITE_RETAIN_EVENTS rows so it can't grow unbounded.
"""

from __future__ import annotations

import json
import logging
import sqlite3
import threading
from pathlib import Path
from typing import Any, Dict


log = logging.getLogger(__name__)


_SCHEMA = """
CREATE TABLE IF NOT EXISTS local_events (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    kind         TEXT NOT NULL,          -- 'state' | 'heartbeat' | 'health'
    topic        TEXT NOT NULL,
    payload_json TEXT NOT NULL,
    created_at   TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ', 'now'))
);
CREATE INDEX IF NOT EXISTS ix_local_events_kind     ON local_events (kind, id);
CREATE INDEX IF NOT EXISTS ix_local_events_created  ON local_events (created_at);
"""


class SqliteCache:
    def __init__(self, db_path: Path, retain_events: int) -> None:
        self._path = db_path
        self._retain = max(1_000, int(retain_events))
        # sqlite3 connections are thread-affine — protect with a lock so the
        # heartbeat/runner threads can share one connection safely.
        self._lock = threading.Lock()
        self._db = sqlite3.connect(str(db_path), check_same_thread=False, isolation_level=None)
        self._db.executescript(_SCHEMA)
        self._db.execute("PRAGMA journal_mode=WAL")
        self._db.execute("PRAGMA synchronous=NORMAL")

    def record(self, kind: str, topic: str, payload: Dict[str, Any]) -> None:
        body = json.dumps(payload, separators=(",", ":"), default=str)
        with self._lock:
            try:
                self._db.execute(
                    "INSERT INTO local_events (kind, topic, payload_json) VALUES (?, ?, ?)",
                    (kind, topic, body),
                )
            except sqlite3.Error as e:
                log.warning("SQLite insert failed: %s", e)
                return

            # Lazy trim — every 256 inserts, drop anything beyond retain count.
            row = self._db.execute("SELECT last_insert_rowid()").fetchone()
            if row and row[0] % 256 == 0:
                self._trim()

    def _trim(self) -> None:
        try:
            self._db.execute(
                """
                DELETE FROM local_events
                WHERE id IN (
                    SELECT id FROM local_events
                    ORDER BY id DESC
                    LIMIT -1 OFFSET ?
                )
                """,
                (self._retain,),
            )
        except sqlite3.Error as e:
            log.warning("SQLite trim failed: %s", e)

    def close(self) -> None:
        with self._lock:
            try:
                self._db.close()
            except Exception:
                pass
