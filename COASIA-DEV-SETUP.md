# 本機開發環境：從 0 到 1 完整搭建指南

這份文件的目的：假設你現在是一台**全新的電腦**，什麼都沒裝，只想把這個專案完整跑起來做開發/測試。全程只用得到 repo 裡本來就有、進了版控的東西（`docker-compose.dev.yml`、`config/config.dist.php`、`database_schema/`），**不需要任何公司內部的真實密碼**（AD 帳密、LINE channel token、正式 SMTP、AD CS 憑證）——本機開發環境本來就是設計成完全獨立、可攜帶的。

跟 [COASIA-DEPLOYMENT.md](COASIA-DEPLOYMENT.md) 的差異：那份是「把這個專案部署到公司內網正式環境」，綁定公司 VM／AD／憑證，離開公司網路就用不了。這份是「在任何一台電腦上，從零建出一份完全獨立、只有假資料的本機開發環境」。

---

## 目錄

1. [前置需求](#一前置需求)
2. [取得程式碼](#二取得程式碼)
3. [啟動容器](#三啟動容器)
4. [安裝 PHP 依賴](#四安裝-php-依賴)
5. [設定 config.php](#五設定-configphp)
6. [建立資料庫結構](#六建立資料庫結構)
7. [建立第一個帳號並登入](#七建立第一個帳號並登入)
8. [驗證安裝是否成功](#八驗證安裝是否成功)
9. [常用開發指令](#九常用開發指令)
10. [這台機器上跑不動的功能](#十這台機器上跑不動的功能)
11. [清空重來](#十一清空重來)
12. [疑難排解](#十二疑難排解)

---

## 一、前置需求

| 項目 | 說明 |
| --- | --- |
| **Docker Desktop** | 內建 Docker Compose，Windows 上建議搭配 WSL2 backend（安裝時預設會問，選 WSL2）。[docker.com/products/docker-desktop](https://www.docker.com/products/docker-desktop/) |
| **Git** | 拉程式碼用。Windows 上建議裝 [Git for Windows](https://git-scm.com/download/win)，內附 Git Bash（本文件的指令都用 bash 語法，PowerShell 也可以跑，但語法要自己轉換） |
| **空的 Port** | `8080`（App）、`8025`（Mailpit 網頁）、`1025`（Mailpit SMTP）、`3307`（MySQL 對外）四個 port 不能被其他程式佔用 |
| **磁碟空間** | 至少留 5GB（Docker image、MySQL data volume、`vendor/` 依賴） |

不需要另外裝 PHP、Composer、MySQL——這些都在 Docker container 裡，`Dockerfile.dev` 已經包好了。

---

## 二、取得程式碼

```bash
git clone https://github.com/Shawn0604/LibreBooking-CoAsia.git
cd LibreBooking-CoAsia
```

（這是私人 repo，需要你自己的 GitHub 帳號有存取權限；HTTPS 會要求輸入帳密或 personal access token，也可以改用 SSH clone。）

---

## 三、啟動容器

```bash
docker compose -f docker-compose.dev.yml up -d
```

第一次執行會 build `app` image（依照 `Dockerfile.dev`），需要幾分鐘。完成後會啟動三個服務：

| 服務 | 用途 | 存取方式 |
| --- | --- | --- |
| `app` | PHP 8.2 + Apache，專案程式碼透過 bind mount 掛進容器（改本機檔案，容器內即時同步） | `http://localhost:8080` |
| `db` | MySQL 8.0，啟動時用 `docker-compose.dev.yml` 裡寫死的假帳密自動建立空的 `librebooking` 資料庫與 `lb_user` 使用者 | 容器內部用 `db` 這個 hostname；本機 GUI 工具（如 DBeaver、TablePlus）可用 `127.0.0.1:3307` 連線 |
| `mailpit` | 假 SMTP 伺服器，攔截應用程式寄出的所有信件，不會真的寄出去 | 網頁介面 `http://localhost:8025` |

確認三個容器都正常：

```bash
docker compose -f docker-compose.dev.yml ps
```

三個服務的 `STATUS` 都應該是 `Up`（`db` 需要等 healthcheck 通過，第一次啟動可能要等 10~30 秒）。

---

## 四、安裝 PHP 依賴

`vendor/` 目錄是獨立的 Docker volume（不是 bind mount），所以第一次啟動後需要手動跑一次：

```bash
docker compose -f docker-compose.dev.yml exec app composer install
```

這步會安裝 `composer.lock` 鎖定的所有 PHP 套件版本，跑完之後 `app` container 裡才有完整的 `vendor/`。

（前端資源如果要跑 lint，另外在**本機**裝 Node.js，執行 `npm install`——這步是選用的，跑不跑不影響網站能不能開起來。）

---

## 五、設定 config.php

`config/config.php` 不進版控（裡面之後會放本機的假密碼），從範本複製一份：

```bash
cp config/config.dist.php config/config.php
```

打開 `config/config.php`，確認／修改以下幾個欄位（其他欄位保持預設即可）：

```php
'database' => [
    'type' => 'mysql',
    'hostspec' => 'db',                          // docker-compose 服務名稱，不是 127.0.0.1
    'name' => 'librebooking',
    'user' => 'lb_user',
    'password' => 'librebooking_dev_password',    // 對應 docker-compose.dev.yml 裡的假密碼
],

'script.url' => 'http://localhost:8080/Web',

'install.password' => 'devinstall123',            // 暫時密碼，只是為了解鎖下一步的安裝精靈，裝完可以刪掉
```

> `hostspec` 填 `db` 是因為 `app` 跟 `db` 在同一個 Docker Compose network 裡，Docker 會自動把服務名稱解析成該容器的內部 IP。跟本機 GUI 工具連線用的 `127.0.0.1:3307` 是兩回事。

---

## 六、建立資料庫結構

這時候資料庫是空的（`db` 容器開機時只建了空白的 `librebooking` schema，沒有任何資料表）。用官方推薦的**自動安裝精靈**建表，它會照正確順序自動跑完 `create-schema.sql` → 所有 `database_schema/upgrades/*` → `create-data.sql`，不用自己手動一個個匯入 SQL 檔（目前 repo 裡有 15 個版本的 upgrade script，手動匯入很容易出錯或漏掉）。

1. 瀏覽器打開 `http://localhost:8080/Web/install/`
2. 輸入上一步設定的 `install.password`（`devinstall123`）
3. 照畫面指示一步步跑完（會顯示資料庫連線測試、建表進度）
4. 完成後，**把 `config/config.php` 裡的 `install.password` 改回空字串或刪掉這一行**——這是安全考量，避免安裝頁面一直開著讓人可以重跑

---

## 七、建立第一個帳號並登入

安裝精靈只建表結構，不會自動建帳號。有兩種方式拿到可以登入的帳號：

### 方式 A：自行註冊成為管理員（推薦，跟正式環境行為一致）

1. 打開 `http://localhost:8080/Web/registration/`
2. 用 `config.php` 裡 `admin.email` 設定的那個 email 註冊（預設是 `admin@example.com`，可以先照抄或改成你自己的）
3. 這個帳號會**自動拿到 Application Admin 權限**

### 方式 B：載入官方範例資料，直接拿到現成測試帳號

```bash
docker compose -f docker-compose.dev.yml exec -T db \
  mysql -uroot -plibrebooking_root_password librebooking \
  < database_schema/sample-data-utf8.sql
```

跑完會多兩個現成帳號：
- `admin` / `password`（管理員）
- `user` / `password`（一般使用者）

還會附帶 2 筆示範資源，方便直接測試預約流程。**這份範例資料跟公司正式環境的房間清單無關**，純粹是上游 LibreBooking 專案內建的展示資料。

---

## 八、驗證安裝是否成功

```bash
docker compose -f docker-compose.dev.yml exec app composer preflight
```

這個指令會檢查 PHP 版本、必要擴充套件、`config.php` 是否存在、資料庫連線是否正常，並印出目前的 `schema version`——只要有印出版本號（例如 `4.4`），就代表資料表都建好了。

接著瀏覽器打開 `http://localhost:8080/Web/`，應該能看到登入頁，用上一步建立的帳號登入，行事曆頁面能正常顯示。

寄信類功能（註冊驗證信、預約通知信）測試時，去 `http://localhost:8025` 看 Mailpit 有沒有收到信——本機環境不會真的寄出任何 email。

---

## 九、常用開發指令

```bash
# 改了 PHP 程式碼但網頁沒反應？dev 環境為了效能關掉了 opcache 的即時檔案檢查，改完要重啟：
docker compose -f docker-compose.dev.yml restart app

# 跑單元測試
docker compose -f docker-compose.dev.yml exec app composer phpunit

# 靜態分析
docker compose -f docker-compose.dev.yml exec app composer phpstan

# 前端 lint（需要本機先裝 Node.js + npm install）
npm run lint:frontend

# 看某個服務的 log
docker compose -f docker-compose.dev.yml logs -f app

# 進 app container 裡的 shell
docker compose -f docker-compose.dev.yml exec app bash
```

---

## 十、這台機器上跑不動的功能

以下這些客製化功能依賴公司內部系統，本機開發環境**沒有真實憑證，所以測試不到**（程式碼邏輯本身沒問題，純粹是缺後端系統可以連）：

| 功能 | 為什麼跑不動 | 本機替代方案 |
| --- | --- | --- |
| AD／LDAP 帳密登入 | 沒有真實的公司 AD 網域可以連 | 用內建的帳號密碼登入（方式 A／B）即可測試其他功能 |
| LINE 官方帳號推播 | 需要真實的 LINE channel secret／access token | 功能預設 `LB_LINE_ENABLED=false`，不用理它；如果真的要測，去申請一組自己的免費 LINE Developers 測試帳號 |
| 正式 SMTP 寄信 | 公司內部郵件伺服器，外部連不到 | Mailpit 已經完整取代，介面看得到信件內容、標題、收件人 |
| HTTPS／AD CS 憑證 | 那是公司內網才信任的憑證鏈 | 本機開發本來就用 `http://localhost:8080`，不需要 HTTPS |
| 視訊會議連結自動帶入 | 其實這個**跑得動**，不需要真實 Zoom/Google Meet 帳號——功能只是把資源的 `video_url` 自訂屬性顯示出來，去後台「自訂屬性」隨便填個假連結就能測試整套流程 |  |

---

## 十一、清空重來

想整個重來（例如資料庫壞了、想確認安裝流程有沒有寫對）：

```bash
docker compose -f docker-compose.dev.yml down -v
```

`-v` 會把 `lb_dev_db_data`（資料庫內容）跟 `lb_dev_vendor`（composer 依賴）這兩個 volume 一起刪掉，等於回到最原始狀態，重新從[第三步](#三啟動容器)開始即可。

只想清資料庫、留著 vendor（比較快）：

```bash
docker compose -f docker-compose.dev.yml down
docker volume ls | grep lb_dev_db_data   # 找出實際的 volume 名稱（前綴依資料夾名稱而定）
docker volume rm <上一步查到的名稱>
docker compose -f docker-compose.dev.yml up -d
```

---

## 十二、疑難排解

- **`docker compose exec app composer install` 出現權限錯誤**：Windows 上 bind mount 偶爾會有檔案權限問題，通常重開 Docker Desktop 或確認該資料夾有加進 Docker Desktop 的檔案共享設定（Settings → Resources → File sharing）就會好。
- **Port 已被佔用**（`8080`／`8025`／`3307`）：改 `docker-compose.dev.yml` 裡對應服務的 `ports` 左邊數字（本機端口），例如 `"8081:80"`。
- **改了 PHP 檔案但畫面沒變化**：忘記 `restart app`，見[第九節](#九常用開發指令)。
- **安裝精靈一直卡在資料庫連線失敗**：檢查 `config.php` 的 `hostspec` 是不是填成 `127.0.0.1`（container 內部連不到，要填 `db`），以及密碼是不是跟 `docker-compose.dev.yml` 裡的 `MYSQL_PASSWORD` 一致。
- **Composer install 很慢或失敗**：確認網路能連到 `packagist.org`，公司防火牆/VPN 環境下偶爾需要另外設定 proxy。
