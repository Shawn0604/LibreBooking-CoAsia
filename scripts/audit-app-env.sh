#!/usr/bin/env bash
#
# CoAsia Booking — audit which config.php settings are being shadowed by app.env.
#
# `Configuration::GetKey()` consults the LB_<KEY> env var FIRST and, if it holds any
# non-empty string, ignores config.php. A prod `app.env` copied from `.env.example`
# therefore silently overrides almost every setting — every "I changed config.php but
# nothing happened" bug traces back to this. Run this after any deploy or config edit.
#
#   ./scripts/audit-app-env.sh                 # every non-empty LB_* override in app.env
#   ./scripts/audit-app-env.sh reservation     # filter by key/section substring
#   ./scripts/audit-app-env.sh --shadowing     # only rows where app.env != config.php
#
# Exit code is non-zero when at least one line in app.env overrides a different value in
# config.php, so it can gate a deploy.
#
# Override PROJECT_DIR / COMPOSE_FILE via env if the layout differs.

set -euo pipefail

PROJECT_DIR="${PROJECT_DIR:-/home/shawn_admin/coasia-booking}"
COMPOSE_FILE="${COMPOSE_FILE:-$PROJECT_DIR/docker-compose.prod.yml}"

cd "$PROJECT_DIR"

# Copy the checker in rather than assuming the running image already has it, so this works
# straight after a `git pull` without a rebuild.
docker compose -f "$COMPOSE_FILE" cp scripts/audit-app-env.php app:/tmp/audit-app-env.php
status=0
docker compose -f "$COMPOSE_FILE" exec -T app php /tmp/audit-app-env.php "$@" || status=$?
docker compose -f "$COMPOSE_FILE" exec -T app rm -f /tmp/audit-app-env.php
exit "$status"
