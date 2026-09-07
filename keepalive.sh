#!/bin/sh
# keepalive.sh — hit the public URL so Render's router sees inbound HTTP
# and resets the 15-minute free-tier idle timer. Must use the public URL:
# localhost never leaves the container, so the router never counts it.
# RENDER_EXTERNAL_URL is injected by Render on every web service.
# No output on success (keeps cron mail/log quiet); one line on failure.
set -u
[ -z "${RENDER_EXTERNAL_URL:-}" ] && exit 0
if ! curl -fsS -m 20 "${RENDER_EXTERNAL_URL}/health.php" >/dev/null; then
    echo "[keepalive] ping failed: ${RENDER_EXTERNAL_URL}/health.php"
fi
