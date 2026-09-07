#!/bin/sh
# docker-entry.sh — boot: hand runtime env to cron, then run cron + apache.
# Cron jobs don't inherit container env (Render injects RENDER_EXTERNAL_URL
# only into PID 1's environment), so export what cron needs to a file the
printenv | grep '^RENDER_EXTERNAL_URL=' | sed 's/^/export /' > /etc/cron.env
chmod 644 /etc/cron.env
cron
exec apache2-foreground
