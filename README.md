
# CoAsia Booking

[![PHP](https://img.shields.io/badge/PHP-8.2%2B-brightgreen.svg?logo=php)](https://www.php.net/)
[![Database](https://img.shields.io/badge/Database-MySQL%208.0-blue.svg?logo=mysql)](https://www.mysql.com/)
[![Docker](https://img.shields.io/badge/Docker-Supported-blue?logo=docker)](docker-compose.dev.yml)
[![License: GPL v3](https://img.shields.io/badge/license-GPLv3-blue.svg)](LICENSE.md)

CoAsia 內部的會議室／資源預約系統，服務全體員工。基於開源專案 [LibreBooking](https://github.com/LibreBooking/librebooking) 客製化而成，把它從一套通用的資源排程工具，改造成整合公司既有系統（AD 網域、LINE 官方帳號、內部 SMTP）的內部服務。

- 內部網址（規劃中）：`https://booking.coasia.com.tw`
- 程式碼保存於公司 GitLab（`coasiait/coasia-booking`）與個人 GitHub（[Shawn0604/LibreBooking-CoAsia](https://github.com/Shawn0604/LibreBooking-CoAsia)，備份用）
- 上游原始專案：[LibreBooking/librebooking](https://github.com/LibreBooking/librebooking)（追蹤 `develop` branch，未來可 `git fetch upstream` 合併官方更新）

---

## 這個 fork 做了什麼客製化

跟原版 LibreBooking 相比，主要差異：

- **Active Directory 網域登入**：員工用既有的 AD 帳密登入，首次登入自動建立帳號（預設一般員工權限），既有管理員身份完整保留
- **LINE 官方帳號通知**：預約建立/提醒等通知透過 LINE 推播，取代/補充 email
- **視訊會議資訊自動帶入**：預約 Zoom / Google Meet 等視訊資源時，確認頁、確認信、提醒信自動帶出對應的連結、會議 ID、密碼；實體會議室完全不受影響
- **重新設計的管理後台與預約介面**：Quick Create 快速建立、統一日期時間選擇器、參與者/外部訪客邀請（含 ICS 行事曆邀請）
- **員工帳號批次匯入**：串接 HR/AD 匯出的名單批次建立帳號，讓從未登入過的員工也能立即被搜尋、邀請
- **連接公司內部服務**：SMTP 改走內部 relay，不再掛在個人信箱底下

完整異動記錄與每項改動的原因，見 [COASIA-CHANGES.md](COASIA-CHANGES.md)。

## 技術棧

- PHP 8.2+ / Apache
- MySQL 8.0
- Smarty 樣板、Bootstrap 5 前端
- Docker Compose（開發與正式環境都用容器跑）

## 開發環境

```bash
docker compose -f docker-compose.dev.yml up -d
```

啟動後：App 在 `http://localhost:8080`，Mailpit（假 SMTP，攔截開發時寄出的信）在 `http://localhost:8025`，MySQL 對外開在 `3307`。詳細設定見 [Dockerfile.dev](Dockerfile.dev) / [docker-compose.dev.yml](docker-compose.dev.yml)。

常用指令：

```bash
composer install          # PHP 依賴
composer preflight         # 檢查環境（PHP 版本、擴充套件、config.php、資料庫連線）
composer phpunit           # 跑單元測試
composer phpstan           # 靜態分析
npm install && npm run lint:frontend   # 前端 lint（JS/CSS）
```

## 正式環境部署

正式環境跑在公司內網的 Linux VM 上，用 `Dockerfile.prod` / `docker-compose.prod.yml` / `Caddyfile` 這一組容器設定，跟開發環境的容器分開。完整的部署架構、操作步驟、以及部署過程中踩過的坑，見 **[COASIA-DEPLOYMENT.md](COASIA-DEPLOYMENT.md)**。

## 文件索引

| 文件 | 內容 |
| --- | --- |
| [COASIA-CHANGES.md](COASIA-CHANGES.md) | 客製化異動記錄：改了什麼、為什麼這樣改 |
| [COASIA-GOLIVE-CHECKLIST.md](COASIA-GOLIVE-CHECKLIST.md) | 正式對全體員工開放前，還缺什麼 |
| [COASIA-DEPLOYMENT.md](COASIA-DEPLOYMENT.md) | 正式環境部署架構與操作手冊 |

## 授權與致謝

本專案基於 [LibreBooking](https://github.com/LibreBooking/librebooking)（GPL-3.0）修改而成，LibreBooking 本身是 Booked Scheduler 的 fork。原始專案的說明文件、安裝指引、社群資源請參考 [LibreBooking 官方 repo](https://github.com/LibreBooking/librebooking) 與[官方文件](https://librebooking.readthedocs.io/en/latest/)。

License：[GPL-3.0](LICENSE.md)
