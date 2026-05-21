"""
MRT Turnout Monitoring — Station Node entrypoint.

Boots config → wires Runner → blocks until SIGINT/SIGTERM.

Run locally for development:
    python -m app.main

Run inside the container (see Dockerfile):
    CMD ["python", "-m", "app.main"]
"""

from __future__ import annotations

import logging
import sys

from app.config import load_settings
from app.services import Runner


def _configure_logging(level: str) -> None:
    logging.basicConfig(
        level=getattr(logging, level, logging.INFO),
        format="%(asctime)s %(levelname)s [%(name)s] %(message)s",
        datefmt="%Y-%m-%d %H:%M:%S",
        stream=sys.stdout,
    )


def main() -> int:
    settings = load_settings()
    _configure_logging(settings.log_level)

    log = logging.getLogger("node.main")
    log.info("Booting MRT Turnout Node: %s (%s) @ %s", settings.node_id, settings.node_name, settings.node_location)
    log.info("Broker: %s:%s  prefix=%s", settings.mqtt_host, settings.mqtt_port, settings.mqtt_topic_prefix)
    log.info("Input driver: %s", settings.input_driver)

    runner = Runner(settings)
    runner.start()
    try:
        runner.run_forever()
    except KeyboardInterrupt:
        pass
    finally:
        runner.shutdown()

    log.info("Bye.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
