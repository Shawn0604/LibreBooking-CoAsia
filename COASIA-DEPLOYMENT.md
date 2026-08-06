# CoAsia Booking 正式環境部署指南

這份文件記錄「把 CoAsia Booking 從 Windows 開發環境，部署到公司內網正式環境」的架構與流程。跟 `COASIA-GOLIVE-CHECKLIST.md`（上線前還缺什麼）不同，這份是**部署架構本身怎麼設計、怎麼操作**的參考文件；跟 `COASIA-CHANGES.md`（記錄已經做了什麼）也不同，這份不隨每次程式碼變更更新，只在部署架構本身改變時更新。

最後更新：2026-08-06

---

## 目前進度

- ✅ 程式碼已推上公司 GitLab（`coasiait/coasia-booking`），SSH 連線方式
- ✅ Linux VM 已建立：`BookingServer`（Ubuntu 26.04 LTS），固定 IP `10.1.8.32`（DHCP 保留，MAC 綁在 `dc7.coasia.com.tw` 的 `user_dhcp` scope）
- ✅ VM 到 GitLab（22/443）、SMTP relay（10.1.3.140:25）、AD LDAP（10.1.3.125:389）連線都測試成功
- ✅ Docker 29.7.1 + Docker Compose v5.4.0 已安裝
- ✅ `Dockerfile.prod`、`docker-compose.prod.yml`、`Caddyfile`、`.env.prod.example`、`.dockerignore` 已寫好（見下方元件設計）
- ⬜ 尚未在 VM 上實際跑過第一次 build／up
- ⬜ TLS 憑證策略、NAS 備份協定、GitLab Runner 部署方式仍待決定（見下方「尚待確認的決策」）

## 目標架構

```
Windows（開發、測試、git commit）
        │  git push
        ▼
公司 GitLab（10.1.3.148，coasiait/coasia-booking）── 保存程式碼與版本、CI/CD、Container Registry
        │  建立 Tag 觸發 pipeline
        ▼
Linux VM：BookingServer（10.1.8.32，Ubuntu 26.04 LTS）
        │
        ▼
Docker Compose（docker-compose.prod.yml）
  ├─ app：Apache + PHP 8.2 + CoAsia Booking（Dockerfile.prod）
  ├─ db：MySQL 8.0
  └─ caddy：反向代理，處理 HTTPS（Caddyfile）
        │
        ▼
Docker Volume（永久保存 MySQL 資料與上傳檔案，不受 app 容器重建影響）
        │
        ▼
每日備份 → NAS／其他 Server
```

正式網址：`https://booking.coasia.com.tw`

---

## 元件設計

### Docker Compose 服務

| 服務 | 說明 |
| --- | --- |
| `app` | [Dockerfile.prod](Dockerfile.prod)，不像 `Dockerfile.dev` 用 bind mount，程式碼直接 COPY 進 image；`composer install --no-dev --optimize-autoloader` |
| `db` | `mysql:8.0`，不對外開 port（現行 `docker-compose.dev.yml` 開了 3307 純粹方便本機除錯，正式環境不需要） |
| `caddy` | [Caddyfile](Caddyfile)，終止 HTTPS，往 `app` 轉發；憑證來源見下方「尚待確認的決策」，Caddyfile 裡已經寫好三種模式（Let's Encrypt／內部 CA／Caddy 內建 CA）可以直接切換 |
| `backup` | 排程任務（VM cron 或獨立 sidecar），不是長駐服務，尚未寫成腳本 |

不在正式環境放 phpMyAdmin 等管理工具對外開放；要用的話走 SSH tunnel。

### 跟 `docker-compose.dev.yml` 的差異

現行的 [Dockerfile.dev](Dockerfile.dev) 與 [docker-compose.dev.yml](docker-compose.dev.yml) 是開發專用設定，不能直接沿用到正式環境：

- opcache 關掉 `validate_timestamps`（見 Dockerfile.dev 註解），正式環境保留同樣設定換效能，但部署腳本要在重建容器時清快取
- 原始碼是 bind mount 進容器，正式環境要直接 bake 進 image
- `mailpit` 是開發用的假 SMTP 服務，正式環境改連公司內部 SMTP relay（`10.1.3.140:25`，見 `COASIA-GOLIVE-CHECKLIST.md`）
- db 的 3307 對外 port、開發用密碼，正式環境都不能沿用

---

## 設定與機密管理

`config/config.php`、`.env`、`uploads/`、`var/`、`tpl_c/` 本來就在 `.gitignore` 裡，不會進 GitLab——這代表 Linux VM 上要維護幾份**不經過版控**的實體檔案，部署時用 bind mount 掛進 `app` 容器，不是靠 git pull 帶過去。`docker-compose.prod.yml` 需要兩個不同的環境檔案，注意不要搞混：

- `.env`（跟 `docker-compose.prod.yml`同一層）：docker compose 自己讀的，只放 `MYSQL_*` 這幾個 compose 層級要用的變數，範本在 [.env.prod.example](.env.prod.example)
- `app.env`：app 自己的 `LB_` 前綴設定（timeout、預設語言等，範本在既有的 [.env.example](.env.example)），會被掛進容器變成 `/var/www/html/.env`，給 PHP 的 Dotenv 讀
- `config/config.php`：資料庫、LDAP、SMTP 等機密設定，直接放在 VM 上這個路徑，用 bind mount（`:ro`）掛進容器
- `plugins/Authentication/ActiveDirectory/ActiveDirectory.config.php`：AD 服務帳號設定，同樣被 `.gitignore` 排除（`**/*.config.php`），同樣用 bind mount 掛進容器，不會被 bake 進 image
- 這四個檔案（`.env`、`app.env`、`config/config.php`、`ActiveDirectory.config.php`）都已經在 `.gitignore` 裡（剛補上 `app.env`），檔案權限建議設 600。VM 上沒有的話用 `scp` 從 Windows 開發機傳過去

---

## Volumes 規劃

| Volume | 掛載到 | 需要持久化？ |
| --- | --- | --- |
| `lb_prod_db_data` | `/var/lib/mysql` | 需要，備份重點 |
| `lb_prod_uploads` | `uploads/` | 需要 |
| `lb_prod_uploads_images` | `Web/uploads/images` | 需要 |
| `lb_prod_uploads_tos` | `Web/uploads/tos` | 需要 |
| `caddy_data` / `caddy_config` | Caddy 內部狀態（憑證等） | 需要，換 image 時不想重新申請憑證 |
| `config/config.php`、`.env`、`app.env` | 三個機密檔案 | 用 bind mount，不是 named volume，見上 |
| `tpl_c/` | Smarty 編譯快取 | **不需要**，每次部署重建即可，持久化反而可能讓部署後還吃到舊快取 |

---

## 部署流程

對應「本機測試 → push GitLab → 建立 Tag → Linux 取得版本 → 備份資料庫 → 重建 App → 健康檢查」：

1. **本機測試**：現行 `docker-compose.dev.yml` + `composer phpunit`／`phpstan`，不變
2. **push GitLab**：觸發 CI（lint、phpunit，可沿用現有 `.github/workflows` 的邏輯搬到 `.gitlab-ci.yml`）
3. **建立 Tag**：觸發 build job，`docker build -f Dockerfile.prod` → push 到 GitLab Container Registry（版本號 tag + `stable` 別名）
4. **Linux 取得版本**：建議把 GitLab Runner 直接註冊在這台 Linux VM 上（shell executor），deploy stage 就在 VM 本機執行，不用額外管理 SSH deploy key
5. **備份資料庫**：deploy 腳本第一步先 `docker exec db mysqldump`，存到本機（檔名帶時間戳＋即將部署的 tag 版本號），成功才繼續
6. **重建 App**：`docker compose pull/build` 新 image → `up -d app`；`db` 服務不動，volume 不受影響
7. **健康檢查**：`docker exec app composer preflight -- --no-color --skip-db`（見 `lib/preflight.php`）+ `docker compose ps` 確認 healthy
8. **失敗可回上一個 Tag**：VM 上保留最近幾個 image tag，失敗時用上一個 tag 重啟 `app` 容器即可回退——但**這只回退程式碼，不回退資料庫結構**。若該次 tag 含 schema 變更（目前透過 `Web/install/` 或 `database_schema/` 手動執行，沒有自動 migration 工具），回退程式碼後可能跟資料庫結構對不上，需要靠步驟 5 的備份還原，而不是單純切 tag。

---

## 每日備份

`mysqldump` 全庫 + `uploads/` 打包，排程跑在 VM（cron 或獨立小 container），推到 NAS（協定待確認，見下）。建議保留至少 14–30 天 rolling，上線前務必實際跑一次還原演練。

---

## 尚待確認的決策

- **HTTPS 憑證策略**：`booking.coasia.com.tw` 的 DNS 是否對外可解析？可解析才能用 Let's Encrypt（`Caddyfile` 預設值）；純內網的話要改用 `Caddyfile` 裡已經寫好的內部 CA 或 Caddy 內建 CA 選項
- **NAS 備份協定**：SMB／NFS／rsync，保留天數政策；備份腳本還沒寫
- **GitLab Runner 部署方式**：建議直接裝在 `BookingServer` 這台 VM 上（最簡單），或必須透過既有共用 Runner + SSH

已解決：Linux distro 用了 Ubuntu 26.04 LTS；VM（`10.1.8.32`）到 GitLab、SMTP relay、AD LDAP（389）的連線都已測試成功，網路路徑沒問題。

---

## 相關文件

- [COASIA-GOLIVE-CHECKLIST.md](COASIA-GOLIVE-CHECKLIST.md) — 上線前還缺什麼
- [COASIA-CHANGES.md](COASIA-CHANGES.md) — 已經做了什麼、為什麼
- [Dockerfile.dev](Dockerfile.dev) / [docker-compose.dev.yml](docker-compose.dev.yml) — 現行開發環境設定
- [Dockerfile.prod](Dockerfile.prod) / [docker-compose.prod.yml](docker-compose.prod.yml) / [Caddyfile](Caddyfile) / [.env.prod.example](.env.prod.example) — 正式環境設定
