# CoAsia Booking — 給 AI 助理的專案指南

這是 **CoAsia 內部的會議室／資源預約系統**，基於開源專案 [LibreBooking](https://github.com/LibreBooking/librebooking) 客製化而成——**不是原版上游專案**。下方 `@AGENTS.md` 匯入的是上游 LibreBooking 專案的通用開發規範（程式碼風格、commit 訊息格式、測試指令），對這個 fork 仍然適用，但完全沒提到 CoAsia 這邊的客製化背景。以下是每次對話一開始就該知道的事：

## 這是什麼系統

- 服務對象：CoAsia 全體員工，訂公司內部會議室／資源
- 正式環境：`https://booking.coasia.com.tw`（**僅限公司內網／VPN**，不對外公開），架在 Linux VM `BookingServer`（`10.1.8.32`），Docker Compose 跑 `app`／`db`／`caddy`（＋範圍限定的 `cloudflared` 只給 LINE webhook 用）
- 正式環境是 **bake-into-image 部署，不是 bind mount**——改完程式碼要 `git pull` + rebuild image + 重建容器才會生效，光 `git pull` 不會影響正在跑的服務
- 程式碼**兩邊都要同步**：公司 GitLab（`coasiait/coasia-booking`，`git@10.1.3.148`）跟 GitHub（`Shawn0604/LibreBooking-CoAsia`）。`git push` 完要記得 `git push origin develop` **和** `git push gitlab develop`
- 上游原始專案是 `upstream` remote（`LibreBooking/app`），追蹤 `develop` branch

## 先讀這些文件（依需求挑著看，不用每次全讀）

| 文件 | 什麼時候看 |
| --- | --- |
| [README.md](README.md) | 想快速了解這個 fork 做了哪些客製化 |
| [COASIA-ARCHITECTURE.md](COASIA-ARCHITECTURE.md) | 要動程式碼前——前端／後端／資料庫怎麼組織、request 怎麼流過系統、完整資料庫 schema（**`create-schema.sql` 不是完整 schema，要疊上 `database_schema/upgrades/` 才是**，這份文件有完整表清單） |
| [COASIA-CHANGES.md](COASIA-CHANGES.md) | 想知道某個功能為什麼長這樣、之前改過什麼——逐項記錄需求→根因→修法→驗證，持續累加章節號 |
| [COASIA-DEPLOYMENT.md](COASIA-DEPLOYMENT.md) | 要處理部署／正式環境問題——VM、Docker、憑證、資料庫遷移、踩過的坑 |
| [COASIA-BACKUP-RESTORE.md](COASIA-BACKUP-RESTORE.md) | 要備份／還原正式環境資料庫、上傳檔、機密設定——保留策略、還原演練、破窗還原步驟 |
| [COASIA-DEV-SETUP.md](COASIA-DEV-SETUP.md) | 要在全新電腦上搭本機開發環境 |
| [COASIA-GOLIVE-CHECKLIST.md](COASIA-GOLIVE-CHECKLIST.md) | 想知道正式上線前還缺什麼 |

## 重要慣例

- **改動語言檔（`lang/zh_tw.php`／`lang/en_us.php`）要一起更新**，不要只改一邊
- **commit 完要推到兩個 remote**（見上方）
- 做完一輪有意義的改動，習慣性在 `COASIA-CHANGES.md` 新增一節記錄，格式比照既有章節（需求 → 根因分析 → 修法 → 驗證）——重點是記「為什麼這樣改」，不是只記「改了什麼」
- 本機開發容器的 PHP opcache 設定 `validate_timestamps=0`——改完 PHP 程式碼、要在**瀏覽器**看到效果，得 `docker compose -f docker-compose.dev.yml restart app`；CLI 測試腳本是獨立行程、不受影響，容易誤判「CLI 測過了應該沒問題」
- 正式環境改了 `.tpl` 樣板，如果 `cache.templates=true`，Smarty 不會自動偵測改動，要手動清 `tpl_c/` 才會生效
- 正式環境的機密設定（`config/config.php`、`.env`、`app.env`、`ActiveDirectory.config.php`）是 bind mount 從 VM 實體檔案掛進容器，不在版控裡
- ⚠️ **`app.env` 會蓋掉 `config.php`——不是只有 DB／mailer 那幾個 key，是「任何」非空的 `LB_*` 都會蓋**：`Configuration::GetKey()` 先查 `LB_<KEY>` 環境變數（`ConfigKeysMeta::envKeyForConfig()` 把 `reservation.reminders.enabled` 轉成 `LB_RESERVATION_REMINDERS_ENABLED`），只要有非空字串就用它、**完全忽略 `config.php`**。正式機的 `app.env` 是整份從 `.env.example` 複製來的，等於把幾乎每個設定都釘死成 `.env.example` 的預設值。**所以「改了 `config.php` 卻沒反應」時，第一個要查的就是 `app.env` 有沒有對應的 `LB_*` 行**（`grep` 那個 key，或直接跑 `scripts/audit-app-env.sh <key>`，它會列出 config.php 值 / app.env 有沒有蓋 / `GetKey()` 實際值）。修法是把 `app.env` 裡不是真的要覆寫的那行清成空值或刪掉，再 `restart app`。用 `php -r` 直接 `include config.php` 讀原始陣列手動測會繞過這層覆蓋、怎麼測都對，特別容易被誤導——這個坑 DB 連線踩過一次、提醒開關又踩一次（見 `COASIA-DEPLOYMENT.md` 的「app.env 蓋掉 config.php」章節）
- **絕對不要把密碼／密鑰貼在指令或截圖裡**——資料庫密碼等機密要透過容器自己已有的環境變數（例如 `db` 容器的 `$MYSQL_ROOT_PASSWORD`）帶入指令，不要手動輸入或印出明文

## 開發規範（來自上游 LibreBooking 專案，程式碼風格／測試／commit 慣例仍然適用）

@AGENTS.md