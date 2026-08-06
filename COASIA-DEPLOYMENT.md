# CoAsia Booking 正式環境部署指南

這份文件記錄「把 CoAsia Booking 從 Windows 開發環境，部署到公司內網正式環境」的架構與流程。跟 `COASIA-GOLIVE-CHECKLIST.md`（上線前還缺什麼）不同，這份是**部署架構本身怎麼設計、怎麼操作**的參考文件；跟 `COASIA-CHANGES.md`（記錄已經做了什麼）也不同，這份不隨每次程式碼變更更新，只在部署架構本身改變時更新。

最後更新：2026-08-06

---

## 目標架構

```
Windows（開發、測試、git commit）
        │  git push
        ▼
公司 GitLab（10.1.3.148，CoasiaIT 群組）── 保存程式碼與版本、CI/CD、Container Registry
        │  建立 Tag 觸發 pipeline
        ▼
Linux VM（正式執行環境，ESXi 上的 VM）
        │
        ▼
Docker Compose
  ├─ app：Apache + PHP 8.2 + CoAsia Booking（Dockerfile.prod）
  ├─ db：MySQL 8.0
  └─ reverse-proxy：Nginx／Caddy，處理 HTTPS
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
| `app` | 用 `Dockerfile.prod`（尚待建立）build，不像 `Dockerfile.dev` 用 bind mount，程式碼直接 COPY 進 image；`composer install --no-dev --optimize-autoloader` |
| `db` | `mysql:8.0`，不對外開 port（現行 `docker-compose.dev.yml` 開了 3307 純粹方便本機除錯，正式環境不需要） |
| `reverse-proxy` | Nginx 或 Caddy，終止 HTTPS，往 `app` 轉發；憑證來源見下方「尚待確認的決策」 |
| `backup` | 排程任務（VM cron 或獨立 sidecar），不是長駐服務 |

不在正式環境放 phpMyAdmin 等管理工具對外開放；要用的話走 SSH tunnel。

### 跟 `docker-compose.dev.yml` 的差異

現行的 [Dockerfile.dev](Dockerfile.dev) 與 [docker-compose.dev.yml](docker-compose.dev.yml) 是開發專用設定，不能直接沿用到正式環境：

- opcache 關掉 `validate_timestamps`（見 Dockerfile.dev 註解），正式環境保留同樣設定換效能，但部署腳本要在重建容器時清快取
- 原始碼是 bind mount 進容器，正式環境要直接 bake 進 image
- `mailpit` 是開發用的假 SMTP 服務，正式環境改連公司內部 SMTP relay（`10.1.3.140:25`，見 `COASIA-GOLIVE-CHECKLIST.md`）
- db 的 3307 對外 port、開發用密碼，正式環境都不能沿用

---

## 設定與機密管理

`config/config.php`、`.env`、`uploads/`、`var/`、`tpl_c/` 本來就在 `.gitignore` 裡，不會進 GitLab——這代表 Linux VM 上要維護一份**不經過版控**的實體檔案，部署時用 volume 掛進 `app` 容器，不是靠 git pull 帶過去：

- `config/config.php`：資料庫、LDAP、SMTP 等機密設定，直接放在 VM 上，掛進容器
- `.env`（`LB_` 前綴的應用層設定，如 timeout、預設語言）：同樣放 VM 本機，不進 GitLab
- MySQL root／app 使用者密碼：透過 `.env` 或 docker secrets 注入，不寫死在 `docker-compose.prod.yml` 明碼裡；`.env` 檔案權限設 600

---

## Volumes 規劃

| Volume | 掛載到 | 需要持久化？ |
| --- | --- | --- |
| `lb_prod_db_data` | `/var/lib/mysql` | 需要，備份重點 |
| `lb_prod_uploads` | `uploads/`、`Web/uploads/images`、`Web/uploads/tos` | 需要 |
| `lb_prod_config` | `config/config.php` | 需要（但內容不進版控，見上） |
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

以下幾點會直接影響 `Dockerfile.prod`、`docker-compose.prod.yml`、CI pipeline 怎麼寫，確認後才能動手：

- **HTTPS 憑證策略**：`booking.coasia.com.tw` 的 DNS 是否對外可解析？可解析才能用 Let's Encrypt；純內網的話要走公司內部 CA 或自簽
- **NAS 備份協定**：SMB／NFS／rsync，保留天數政策
- **GitLab Runner 部署方式**：建議直接裝在 Linux VM 上（最簡單），或必須透過既有共用 Runner + SSH
- **Linux distro**：有無 IT 既定標準（現有清單多是 CentOS 7／RHEL 9，CentOS 7 已 EOL，建議 Rocky Linux 9 或 Ubuntu 22.04/24.04 LTS）
- **AD/LDAP 連線**：`COASIA-GOLIVE-CHECKLIST.md` 提到的 TLS 1.0／過期憑證問題，Linux VM 到三台 DC 的網路路徑要重新跟 IT 確認（389 目前用的暫時明文方案，636 修好後要再測一次）

---

## 相關文件

- [COASIA-GOLIVE-CHECKLIST.md](COASIA-GOLIVE-CHECKLIST.md) — 上線前還缺什麼
- [COASIA-CHANGES.md](COASIA-CHANGES.md) — 已經做了什麼、為什麼
- [Dockerfile.dev](Dockerfile.dev) / [docker-compose.dev.yml](docker-compose.dev.yml) — 現行開發環境設定
