#!/usr/bin/env bash
#
# CoAsia Booking — 每日備份:資料庫 + uploads + 不在版控的機密設定 → NAS
#
# 目的地:Synology CETP-SVR-NAS03 (10.1.3.151) 的 NetBackup 共享資料夾,
#   透過 NFS 掛在 BookingServer 的 /mnt/nas-booking,備份寫進 booking-backup/ 子目錄。
#
# 排程(shawn_admin 的 crontab,該帳號已在 docker 群組):
#   30 2 * * * /home/shawn_admin/coasia-booking/scripts/db-backup.sh >> /home/shawn_admin/booking-backup.log 2>&1
#
# DB 密碼不落地:mysqldump 在 db 容器內執行,用容器自己的 $MYSQL_ROOT_PASSWORD。
# 覆寫預設值用環境變數:PROJECT_DIR / DEST_ROOT / DB_NAME / KEEP_DAYS。

set -euo pipefail

PROJECT_DIR="${PROJECT_DIR:-/home/shawn_admin/coasia-booking}"
COMPOSE="$PROJECT_DIR/docker-compose.prod.yml"
NAS_MOUNT="${NAS_MOUNT:-/mnt/nas-booking}"
DEST_ROOT="${DEST_ROOT:-$NAS_MOUNT/booking-backup}"
DB_NAME="${DB_NAME:-librebooking}"
KEEP_DAYS="${KEEP_DAYS:-30}"

log() { echo "[$(date -Is)] $*"; }
dc() { docker compose -f "$COMPOSE" "$@"; }

# NAS 沒掛好就直接失敗 —— 不要把備份默默寫進本機根目錄硬碟
mountpoint -q "$NAS_MOUNT" || { log "FATAL: $NAS_MOUNT 未掛載,備份中止"; exit 1; }

APP_CID="$(dc ps -q app || true)"
[ -n "$APP_CID" ] || { log "FATAL: app 容器沒在跑"; exit 1; }

TS="$(date +%Y%m%d-%H%M%S)"
DEST="$DEST_ROOT/$TS"
mkdir -p "$DEST"

# --- 1) 資料庫 ---------------------------------------------------------------
log "dump DB $DB_NAME ..."
dc exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysqldump --user=root \
      --single-transaction --quick --routines --triggers --events \
      --set-gtid-purged=OFF --no-tablespaces '"$DB_NAME" \
  | gzip -c > "$DEST/${DB_NAME}-${TS}.sql.gz"

# 完整性檢查:gzip 沒壞 + mysqldump 有寫到結尾的完成標記(被截斷就會少這行)
gzip -t "$DEST/${DB_NAME}-${TS}.sql.gz"
gunzip -c "$DEST/${DB_NAME}-${TS}.sql.gz" | tail -5 | grep -q 'Dump completed' \
  || { log "FATAL: dump 結尾沒有 'Dump completed',可能被截斷"; exit 1; }
log "DB OK ($(du -h "$DEST/${DB_NAME}-${TS}.sql.gz" | cut -f1))"

# --- 2) uploads -----------------------------------------------------------
# 直接借用 app 容器掛著的 volume,不用去查 volume 名稱
log "tar uploads ..."
docker run --rm --volumes-from "$APP_CID" -v "$DEST:/backup" alpine \
  tar czf "/backup/uploads-${TS}.tgz" \
    /var/www/html/uploads \
    /var/www/html/Web/uploads/images \
    /var/www/html/Web/uploads/tos 2>/dev/null || log "WARN: uploads tar 有非致命錯誤(某個目錄可能是空的)"
log "uploads OK ($(du -h "$DEST/uploads-${TS}.tgz" 2>/dev/null | cut -f1 || echo '-'))"

# --- 3) 不在版控的機密設定 / 憑證 ----------------------------------------
# config/config.php、.env、app.env、ActiveDirectory.config.php、certs/ 都在 .gitignore
# 裡,只存在這台 VM。VM 掛掉就再也拿不回來,一起備。
log "tar secrets ..."
tar czf "$DEST/secrets-${TS}.tgz" -C "$PROJECT_DIR" \
  config/config.php .env app.env \
  plugins/Authentication/ActiveDirectory/ActiveDirectory.config.php \
  certs 2>/dev/null || log "WARN: secrets tar 有非致命錯誤(某個檔可能不存在)"
chmod 600 "$DEST/secrets-${TS}.tgz"

# --- 4) 輪替 -------------------------------------------------------------
find "$DEST_ROOT" -mindepth 1 -maxdepth 1 -type d -name '20*' -mtime "+$KEEP_DAYS" -exec rm -rf {} +

log "DONE -> $DEST"
