#!/usr/bin/env bash
set -euo pipefail

# Mirrors phing-tasks/UpgradeDbTask.php's folder-scan logic (every database_schema/upgrades/<version>/
# folder, schema.sql then data.sql if present) as a plain bash script so the CI deploy job doesn't
# need the phing/phive toolchain installed on the Runner. Reads DB creds from the VM's own .env
# (the same file docker-compose.prod.yml already reads MYSQL_* from) -- the Runner runs on this
# same machine, so no GitLab CI/CD secret variables are needed for this.
#
# Every mysql invocation is pinned to --default-character-set=utf8mb4 -- a migration applied
# without this on 2026-08-10 corrupted the two resource_type_name values (see
# database_schema/upgrades/4.4/fix-encoding.sql and COASIA-CHANGES.md §66/§67). This script exists
# specifically so that mistake can't happen again.

cd "$(dirname "$0")/.."

if [ ! -f .env ]; then
    echo "apply-migrations.sh: .env not found in $(pwd) -- expected MYSQL_USER/MYSQL_PASSWORD/MYSQL_DATABASE here" >&2
    exit 1
fi

set -a
# shellcheck disable=SC1091
source .env
set +a

: "${MYSQL_USER:?MYSQL_USER not set in .env}"
: "${MYSQL_PASSWORD:?MYSQL_PASSWORD not set in .env}"
: "${MYSQL_DATABASE:?MYSQL_DATABASE not set in .env}"

UPGRADES_DIR="database_schema/upgrades"

for version_dir in $(find "$UPGRADES_DIR" -mindepth 1 -maxdepth 1 -type d -printf '%f\n' | sort -V); do
    full_dir="$UPGRADES_DIR/$version_dir"

    for file in schema.sql data.sql; do
        path="$full_dir/$file"
        if [ -f "$path" ]; then
            echo "apply-migrations.sh: applying $path"
            docker compose -f docker-compose.prod.yml exec -T db \
                mysql --default-character-set=utf8mb4 \
                -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" < "$path"
        fi
    done
done

echo "apply-migrations.sh: done"
