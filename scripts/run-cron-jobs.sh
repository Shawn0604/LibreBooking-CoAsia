#!/usr/bin/env bash
#
# CoAsia Booking — 週期性排程工作(在 app 容器內跑 LibreBooking 的 Jobs/*.php）。
#
# 目前只跑「預約提醒」(開始前 / 結束前),這是唯一需要每分鐘檢查的工作。
# 之後若要接其他 Jobs(autorelease / sendmissedcheckin / sessioncleanup …)
# 在下方 JOBS 陣列加檔名即可,再依需要調整 crontab 頻率。
#
# 排程(shawn_admin 的 crontab,該帳號已在 docker 群組):
#   * * * * * /home/shawn_admin/coasia-booking/scripts/run-cron-jobs.sh >> /home/shawn_admin/booking-cron.log 2>&1
#
# 需要 reservation.reminders.enabled = true(prod 的 config/config.php,不在版控)。
# 覆寫預設值:PROJECT_DIR。

set -euo pipefail

PROJECT_DIR="${PROJECT_DIR:-/home/shawn_admin/coasia-booking}"
COMPOSE="$PROJECT_DIR/docker-compose.prod.yml"

JOBS=(
    "Jobs/sendreminders.php"
)

cd "$PROJECT_DIR"

for job in "${JOBS[@]}"; do
    docker compose -f "$COMPOSE" exec -T app php "/var/www/html/$job" || \
        echo "$(date '+%F %T') WARN: $job exited non-zero"
done
