# CoAsia Booking 備份與還原

正式環境資料庫 / 上傳檔 / 機密設定的備份怎麼跑、壞掉了怎麼救。備份腳本本身在
[`scripts/db-backup.sh`](scripts/db-backup.sh)、[`scripts/db-restore-drill.sh`](scripts/db-restore-drill.sh)。
部署架構見 [`COASIA-DEPLOYMENT.md`](COASIA-DEPLOYMENT.md)。

最後更新：2026-08-28

---

## 一、備份了什麼、放哪、留多久

| 項目 | 內容 | 備份檔 |
| --- | --- | --- |
| 資料庫 | `mysqldump` 全庫（`--single-transaction`，含 routines/triggers/events） | `librebooking-<時間戳>.sql.gz` |
| 上傳檔 | `app` 容器的 3 個 uploads volume（`/var/www/html/uploads`、`Web/uploads/images`、`Web/uploads/tos`） | `uploads-<時間戳>.tgz` |
| 機密設定 | 不在版控的檔案：`config/config.php`、`.env`、`app.env`、`ActiveDirectory.config.php`、`certs/` | `secrets-<時間戳>.tgz`（`chmod 600`） |

- **位置**：Synology `CETP-SVR-NAS03`（`10.1.3.151`）的 `NetBackup` 共享資料夾底下
  `Booking_backup_from_10.1.8.32/<時間戳>/`（時間戳格式 `YYYYMMDD-HHMMSS`），
  透過 NFS 掛在 BookingServer 的 `/mnt/nas-booking`。
- **排程**：`shawn_admin` 的 crontab，每晚 **02:30**，log 寫到 `~/booking-backup.log`。
- **保留**：**30 天** rolling（腳本的 `KEEP_DAYS`）。每次成功執行時刪掉超過 30 天的資料夾；
  若某次因 NAS 沒掛好而提早失敗，那天不刪，下次成功再補。
- **改保留天數**：crontab 那行前面加 `KEEP_DAYS=60`，或改 `scripts/db-backup.sh` 的預設值。

### ⚠️ `secrets-*.tgz` 是明文機密

裡面有 DB 密碼、LINE token、AD 服務帳號密碼、影片主持人加密金鑰。腳本雖然 `chmod 600`，
但在 all-squash 的 NFS 上等於「NAS 管理員可讀」——跟「NAS 管理員能讀所有備份」是同一個信任
邊界，內部 NAS 可接受。要更嚴就把這包改成 `gpg` / `openssl enc` 加密、passphrase 另外保管。

---

## 二、怎麼確認備份正常

```bash
# 最近幾天的備份資料夾
ls -lt /mnt/nas-booking/Booking_backup_from_10.1.8.32/ | head

# 最新一份的三個檔都在、大小合理
ls -lh /mnt/nas-booking/Booking_backup_from_10.1.8.32/*/ | tail

# 排程有沒有正常跑（每天早上看一次就好）
tail -20 ~/booking-backup.log        # 要有 "DONE -> ..." 那行

# crontab 還在
crontab -l
```

備份檔完整性在 `db-backup.sh` 裡已經自動檢查（`gzip -t` + 結尾要有 `Dump completed`），
不通會 `exit 1` 並在 log 留 `FATAL`。

---

## 三、還原演練（非破壞性，上線前 + 之後定期做）

把備份灌進一個**臨時**資料庫 `librebooking_restore_test`，比對主要表筆數，
**完全不碰正式的 `librebooking`**。

```bash
cd ~/coasia-booking
scripts/db-restore-drill.sh                       # 用 NAS 上最新一份
scripts/db-restore-drill.sh /path/to/某份.sql.gz  # 指定某一份
DROP=1 scripts/db-restore-drill.sh                # 跑完自動清掉臨時 DB，不問
```

輸出會列 `users` / `reservation_series` / `reservation_instances` / `resources` /
`schedules` / `groups` 六張表的「正式 vs 還原」筆數，全 `OK` 代表備份救得回來。

建議頻率：上線前一次，之後每季一次，或每次改動備份流程後。

---

## 四、真的要還原：情境 A — 只還原資料庫

用在「資料被誤刪 / 誤改 / 損壞，要整個資料庫回到某個備份時間點」。
**這會 DROP 掉現在的 `librebooking` 資料庫**，操作前想清楚。

```bash
cd ~/coasia-booking

# 1. 停掉 app，避免還原過程中有寫入
docker compose -f docker-compose.prod.yml stop app

# 2. 選要還原的那一份（預設抓最新；要更早的自己改路徑）
DUMP=$(ls -t /mnt/nas-booking/Booking_backup_from_10.1.8.32/*/librebooking-*.sql.gz | head -1)
echo "will restore from: $DUMP"

# 3. 重建空資料庫，灌回備份（密碼在 db 容器內，不出現在指令）
docker compose -f docker-compose.prod.yml exec -T db sh -c \
  'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --user=root -e "DROP DATABASE librebooking; CREATE DATABASE librebooking CHARACTER SET utf8mb4;"'
gunzip -c "$DUMP" | docker compose -f docker-compose.prod.yml exec -T db sh -c \
  'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --user=root librebooking'

# 4. 起 app，驗證
docker compose -f docker-compose.prod.yml start app
docker compose -f docker-compose.prod.yml exec -T app composer preflight -- --no-color
```

- `preflight` 要顯示 `Database connection: connected` 跟 `schema version`。
- **schema 版本注意**：還原回來的 schema 版本 = 那份備份當下的版本。如果是拿舊備份灌到
  新程式碼上，可能對不上，需要之後補跑 `database_schema/upgrades/`。盡量「程式碼跟資料庫
  還原到同一個時期」。
- uploads / 機密設定在情境 A 不需要動（只有 DB 壞的話）。

---

## 五、真的要還原：情境 B — 整台 VM 沒了，從零重建

```bash
# 1. 新 VM：裝 Docker（見 COASIA-DEPLOYMENT.md 四），然後
git clone git@10.1.3.148:coasiait/coasia-booking.git ~/coasia-booking
cd ~/coasia-booking && git checkout develop

# 2. 掛回 NAS（見 COASIA-DEPLOYMENT.md 十二），確認看得到備份
ls /mnt/nas-booking/Booking_backup_from_10.1.8.32/

# 3. 還原機密設定（archive 內是相對路徑，直接解到專案根目錄）
TS=<挑一份時間戳>
tar xzf /mnt/nas-booking/Booking_backup_from_10.1.8.32/$TS/secrets-$TS.tgz -C ~/coasia-booking/
#   -> 復原 config/config.php、.env、app.env、ActiveDirectory.config.php、certs/

# 4. 先只起 db，灌回資料庫
docker compose -f docker-compose.prod.yml up -d db
sleep 10
docker compose -f docker-compose.prod.yml exec -T db sh -c \
  'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --user=root -e "CREATE DATABASE IF NOT EXISTS librebooking CHARACTER SET utf8mb4;"'
gunzip -c /mnt/nas-booking/Booking_backup_from_10.1.8.32/$TS/librebooking-$TS.sql.gz | \
  docker compose -f docker-compose.prod.yml exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --user=root librebooking'

# 5. build + 起全部（會建立 uploads 等 volume）
docker compose -f docker-compose.prod.yml build
docker compose -f docker-compose.prod.yml up -d

# 6. 還原上傳檔（archive 內是 var/www/html/... 的路徑，從 / 解回容器 volume）
APP=$(docker compose -f docker-compose.prod.yml ps -q app)
docker run --rm --volumes-from "$APP" \
  -v /mnt/nas-booking/Booking_backup_from_10.1.8.32/$TS:/backup alpine \
  sh -c 'cd / && tar xzf /backup/uploads-'"$TS"'.tgz'

# 7. 驗證
docker compose -f docker-compose.prod.yml exec -T app composer preflight -- --no-color
docker compose -f docker-compose.prod.yml exec -T app curl -sI http://localhost/Web/
```

然後：`config/config.php` 的 `database.hostspec` 要是 `db`（不是 IP）、內部 DNS
`booking.coasia.com.tw` 指到新 VM 的 IP、Caddy 憑證（`certs/`）已由步驟 3 還原。

---

## 六、常見問題

| 症狀 | 原因 / 處理 |
| --- | --- |
| `booking-backup.log` 出現 `FATAL: /mnt/nas-booking 未掛載` | NFS 掉了。`sudo mount /mnt/nas-booking`；查 NAS 開機沒、NFS 服務有沒有關 |
| `db-restore-drill.sh` 某表 `ERR` | 那份備份可能不完整，或表名在該版本不存在（六張表清單是寫死的健檢，個別 `ERR` 不一定代表備份壞） |
| 還原後 `preflight` schema version 比程式碼舊 | 拿舊備份灌新程式碼，補跑 `database_schema/upgrades/` |
| 想改備份時間 / 保留天數 | `crontab -e` 改那行；保留天數在行首加 `KEEP_DAYS=N` |

---

## 相關文件

- [`scripts/db-backup.sh`](scripts/db-backup.sh) / [`scripts/db-restore-drill.sh`](scripts/db-restore-drill.sh)
- [`COASIA-DEPLOYMENT.md`](COASIA-DEPLOYMENT.md) 十二 — NAS/NFS 掛載、cron 設定
- [`COASIA-GOLIVE-CHECKLIST.md`](COASIA-GOLIVE-CHECKLIST.md) — 上線前待辦
