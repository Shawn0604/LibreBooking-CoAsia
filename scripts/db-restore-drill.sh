#!/usr/bin/env bash
#
# CoAsia Booking — 還原演練
#
# 把一份 mysqldump 備份灌進一個「臨時」資料庫,比對主要資料表的筆數,確認備份
# 真的救得回來。不會動到正式的 librebooking 資料庫。
#
# 用法:
#   scripts/db-restore-drill.sh                  # 用 NAS 上最新的一份
#   scripts/db-restore-drill.sh /path/to/dump.sql.gz
#   DROP=1 scripts/db-restore-drill.sh           # 跑完自動 DROP 臨時 DB,不問

set -euo pipefail

PROJECT_DIR="${PROJECT_DIR:-/home/shawn_admin/coasia-booking}"
COMPOSE="$PROJECT_DIR/docker-compose.prod.yml"
SRC_DB="${SRC_DB:-librebooking}"
TEST_DB="${TEST_DB:-librebooking_restore_test}"
DEST_ROOT="${DEST_ROOT:-/mnt/nas-booking/booking-backup}"

DUMP="${1:-$(ls -t "$DEST_ROOT"/*/"${SRC_DB}"-*.sql.gz 2>/dev/null | head -1 || true)}"
[ -n "$DUMP" ] && [ -f "$DUMP" ] || { echo "找不到備份檔:$DUMP"; exit 1; }
echo "還原來源:$DUMP"

dc() { docker compose -f "$COMPOSE" "$@"; }
myq() { dc exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --user=root -N -e "'"$1"'"' | tr -d '\r'; }

echo "建立臨時 DB $TEST_DB ..."
myq "DROP DATABASE IF EXISTS \`$TEST_DB\`; CREATE DATABASE \`$TEST_DB\` CHARACTER SET utf8mb4;" >/dev/null

echo "灌入 ..."
gunzip -c "$DUMP" | dc exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --user=root '"$TEST_DB"

echo
echo "== 筆數比對(正式 vs 還原)=="
ok=1
for t in users reservation_series reservation_instances resources schedules groups; do
  a="$(myq "SELECT COUNT(*) FROM \`$SRC_DB\`.\`$t\`" 2>/dev/null || echo ERR)"
  b="$(myq "SELECT COUNT(*) FROM \`$TEST_DB\`.\`$t\`" 2>/dev/null || echo ERR)"
  mark=OK; [ "$a" = "$b" ] || { mark=DIFF; ok=0; }
  printf "  %-22s %8s vs %-8s %s\n" "$t" "$a" "$b" "$mark"
done
echo
[ "$ok" = 1 ] && echo "結果:一致 ✓" || echo "結果:有差異 ✗ —— 檢查備份是否完整"

if [ "${DROP:-}" = 1 ]; then
  myq "DROP DATABASE \`$TEST_DB\`;" >/dev/null && echo "已 DROP $TEST_DB"
else
  read -r -p "DROP $TEST_DB? [y/N] " yn
  [ "${yn:-N}" = y ] && myq "DROP DATABASE \`$TEST_DB\`;" >/dev/null && echo "已 DROP $TEST_DB"
fi
