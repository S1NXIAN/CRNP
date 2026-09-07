#!/bin/sh
# keepalive.sh — hit the public URL so Render's router sees inbound HTTP
# and resets the 15-minute free-tier idle timer. Must use the public URL:
# localhost never leaves the container, so the router never counts it.
# RENDER_EXTERNAL_URL is injected by Render on every web service.
# Quiet on success; one line when skipped or failed (surfaces cron misconfig).
if [ -z "${RENDER_EXTERNAL_URL:-}" ]; then
    echo "[keepalive] RENDER_EXTERNAL_URL unset, skipping ping"
    exit 0
fi
curl -fsS -m 20 "${RENDER_EXTERNAL_URL}/health.php" >/dev/null \
    || echo "[keepalive] ping failed: ${RENDER_EXTERNAL_URL}/health.php"
