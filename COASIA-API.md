# CoAsia Booking — REST API 對接說明

給要「不進網站、直接打 API」串接這套會議室 / 資源預約系統的人。

- 完整逐端點參考(欄位、範例):[docs/source/API.rst](docs/source/API.rst),或 API 啟用後開
  `https://booking.coasia.com.tw/Web/Services/index.php`(自動產生的線上文件頁)
- 這份文件只補上游文件沒有的東西:CoAsia 的網址、連線前提、啟用步驟、安全設定、實際範例

---

## 1. 這是什麼

這套系統(LibreBooking fork)**內建一組完整的 REST / JSON API**,程式碼在
[WebServices/](WebServices/),由跟網站同一個 `app` 容器提供服務。不需要另外架 API server。

| | 說明 |
| --- | --- |
| 協定 | HTTP + JSON |
| 認證 | 帳號密碼換 session token(不是固定 API key) |
| 可做的事 | 查 / 建 / 改 / 刪預約、查會議室與可用時段、查 schedule、查使用者清單等 |
| 權限模型 | 跟網站同一套 —— 透過 API 能做的事,不會比那個帳號在網頁上能做的更多 |

---

## 2. 連線前提(重要)

**正式站只在公司內網 / VPN 內開放**,沒有 NAT / port forward 到公網
(見 [Caddyfile](Caddyfile)、[docker-compose.prod.yml](docker-compose.prod.yml))。
唯一的對外通道是 LINE webhook 專用的 Cloudflare Tunnel,而且只放行
`/Web/line-webhook.php` 一條路徑,API 走不進來。

> 結論:呼叫端那台機器 **必須位於 CoAsia 內網或已連 VPN**,條件跟使用者要連 VPN
> 才能開網站一樣。若對接方在外部網際網路,現階段無解 —— 除非另外決定要不要幫
> API 開對外通道(不建議,會破壞「內網限定」的前提)。

---

## 3. 啟用 API(目前是關閉狀態)

`api.enabled` 是整個 API 的總開關,程式在每個請求分派前先檢查
([Web/Services/index.php:61](Web/Services/index.php:61)):

- `false`(現況)→ 任何請求直接回 **HTTP 503**「LibreBooking API is disabled」
- `true` → 請求放行,進入認證與端點邏輯

### 步驟

1. 在 VM `BookingServer`(`10.1.8.32`)的 `/opt/…/config/config.php` 找到 `'api'` 區塊,
   或改 `app.env`(任何非空的 `LB_*` 會覆蓋 `config.php`,見
   [COASIA-DEPLOYMENT.md](COASIA-DEPLOYMENT.md))

   ```php
   'api' => [
       'enabled' => true,

       // 只有這個 AD 群組的成員能透過 API 登入(admin 不受限)
       'authentication.group' => 'API Clients',

       // 自助註冊維持關閉
       'registration.allow.self' => false,

       // 視需求收斂各資源讀寫範圍(留空 = 已登入者皆可,見第 6 節)
       'reservations.rw.group' => 'API Clients',
   ],
   ```

2. 因為正式站是 bake-into-image,`git pull` + rebuild image + 重建容器才生效:

   ```bash
   cd /opt/coasia-booking   # 實際路徑見 COASIA-DEPLOYMENT.md
   git pull
   docker compose -f docker-compose.prod.yml build app
   docker compose -f docker-compose.prod.yml up -d --force-recreate app
   ```

3. 驗證:

   ```bash
   curl -s -X POST https://booking.coasia.com.tw/Web/Services/index.php/Authenticate/ \
     -H 'Content-Type: application/json' \
     -d '{"username":"<user>","password":"<pass>"}'
   ```

> ⚠️ `authentication.group` / `*.ro.group` / `*.rw.group` 填的是**群組「名稱」**,
> 程式啟動時用名稱去 DB 找 group id;**名稱打錯會讓整個 API 拋例外、完全無法使用**
> ([Web/Services/index.php](Web/Services/index.php) 的 `GetConfigGroup()`)。填之前先確認
> 「管理群組」頁面有這個名稱、且大小寫一致。

---

## 4. Base URL

```
https://booking.coasia.com.tw/Web/Services/index.php
```

後面接端點路徑,例如 `.../Web/Services/index.php/Resources/`。
**注意結尾斜線** —— 文件標示有 `/` 的就要帶,沒有的就別帶。

---

## 5. 認證流程

### 5.1 登入,換 token

`POST /Authenticate/` — POST body 必須是 JSON。

Request:

```json
{ "username": "your-username", "password": "your-password" }
```

Response(成功):

```json
{
  "sessionToken": "xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
  "sessionExpires": "2026-09-09T12:34:56+0800",
  "userId": 123,
  "isAuthenticated": true,
  "version": "..."
}
```

失敗回 `isAuthenticated: false` 加 `message`(帳密錯 → "Login failed. Invalid username
or password.";不在 `authentication.group` → "Login failed. API access not
authorized.")。

### 5.2 後續呼叫帶 header

所有標示 *secure* 的端點都要帶這兩個 header:

```
X-Booked-SessionToken: <sessionToken>
X-Booked-UserId: <userId>
```

token 會過期(`sessionExpires`),過期後重新 `Authenticate` 即可。

### 5.3 登出(可選)

`POST /SignOut/`

```json
{ "userId": 123, "sessionToken": "<sessionToken>" }
```

---

## 6. 端點清單

存取層級:
**公開** = API 開啟後即可(仍在內網)/
**需登入** = 要帶 token /
**需群組** = 若對應 `*.rw.group` / `*.ro.group` 有設,要是成員 /
**僅 admin** = 只有應用程式管理員

### Authentication

| 方法 | 路徑 | 存取 | 說明 |
| --- | --- | --- | --- |
| POST | `/Authenticate/` | 公開 | 帳密換 token |
| POST | `/SignOut/` | 公開 | 註銷 token |

### Reservations(預約)

| 方法 | 路徑 | 存取 | 說明 |
| --- | --- | --- | --- |
| GET | `/Reservations/` | 需登入 | 預約清單。query:`userId` `resourceId` `scheduleId` `startDateTime` `endDateTime`;不給日期預設回未來兩週 |
| GET | `/Reservations/:referenceNumber` | 需登入 | 單筆預約明細(`referenceNumber` = 預約參考碼) |
| POST | `/Reservations/` | 需群組(rw) | 建立預約 |
| POST | `/Reservations/:referenceNumber` | 需群組(rw) | 更新預約 |
| POST | `/Reservations/:referenceNumber/Approval` | 需群組(rw) | 核准待審預約 |
| POST | `/Reservations/:referenceNumber/CheckIn` | 需群組(rw) | 報到 |
| POST | `/Reservations/:referenceNumber/CheckOut` | 需群組(rw) | 簽退 |
| DELETE | `/Reservations/:referenceNumber` | 需群組(rw) | 刪除預約 |

### Resources(會議室 / 資源)

| 方法 | 路徑 | 存取 | 說明 |
| --- | --- | --- | --- |
| GET | `/Resources/` | 需登入 | 所有資源 |
| GET | `/Resources/:resourceId` | 需登入 | 單一資源 |
| GET | `/Resources/Availability` | 需登入 | 全部資源未來 7 天可用性 |
| GET | `/Resources/:resourceId/Availability` | 需登入 | 指定資源可用性 |
| GET | `/Resources/Groups` | 需登入 | 資源群組樹 |
| GET | `/Resources/Types` | 需登入 | 資源類型 |
| GET | `/Resources/Status` | 公開 | 資源狀態列舉 |
| GET | `/Resources/Status/Reasons` | 需登入 | 狀態原因列舉 |
| POST | `/Resources/` | 僅 admin | 建立資源 |
| POST | `/Resources/:resourceId` | 僅 admin | 更新資源 |
| DELETE | `/Resources/:resourceId` | 僅 admin | 刪除資源 |

### Schedules(排程表)

| 方法 | 路徑 | 存取 | 說明 |
| --- | --- | --- | --- |
| GET | `/Schedules/` | 需登入 | 所有 schedule |
| GET | `/Schedules/:scheduleId` | 需登入 | 單一 schedule(含時段版面 layout) |
| GET | `/Schedules/:scheduleId/Slots` | 需登入 | 指定 schedule 的時段 + 已被預約狀態。query:`resourceId` `startDateTime` `endDateTime` |

### Users(使用者)

| 方法 | 路徑 | 存取 | 說明 |
| --- | --- | --- | --- |
| GET | `/Users/` | 需登入 | 目前使用者可見的使用者清單 |
| GET | `/Users/:userId` | 需登入 | 單一使用者 |
| POST | `/Users/` | 僅 admin | 建立使用者 |
| POST | `/Users/:userId` | 僅 admin | 更新使用者 |
| POST | `/Users/:userId/Password` | 僅 admin | 改密碼 |
| DELETE | `/Users/:userId` | 僅 admin | 刪除使用者 |

### 其他(較少用)

| 方法 | 路徑 | 存取 | 說明 |
| --- | --- | --- | --- |
| GET | `/Accessories/`、`/Accessories/:id` | 需登入 | 附屬設備 |
| GET | `/Groups/`、`/Groups/:id` | 需登入 | 群組(寫入僅 admin) |
| GET | `/Attributes/Category/:categoryId`、`/Attributes/:id` | 需登入 | 自訂欄位定義(寫入僅 admin) |
| GET | `/Accounts/:userId` | 需登入 | 目前帳號資訊;`POST /Accounts/` 可自助註冊(預設關閉) |

---

## 7. 常用範例(curl)

先設好變數:

```bash
BASE="https://booking.coasia.com.tw/Web/Services/index.php"

# 登入
RESP=$(curl -s -X POST "$BASE/Authenticate/" -H 'Content-Type: application/json' \
  -d '{"username":"svc-booking-api","password":"********"}')
TOKEN=$(echo "$RESP" | jq -r .sessionToken)
UID=$(echo "$RESP" | jq -r .userId)
AUTH=(-H "X-Booked-SessionToken: $TOKEN" -H "X-Booked-UserId: $UID")
```

### 列出所有會議室

```bash
curl -s "${AUTH[@]}" "$BASE/Resources/"
```

### 查某會議室指定期間的可用 / 已預約時段

```bash
curl -s "${AUTH[@]}" \
  "$BASE/Schedules/1/Slots?resourceId=5&startDateTime=2026-09-10T00:00:00+0800&endDateTime=2026-09-10T23:59:59+0800"
```

### 查某人的預約

```bash
curl -s "${AUTH[@]}" "$BASE/Reservations/?userId=123"
```

### 建立一筆預約

```bash
curl -s -X POST "${AUTH[@]}" -H 'Content-Type: application/json' \
  "$BASE/Reservations/" -d '{
    "resourceId": 5,
    "title": "專案週會",
    "description": "",
    "startDateTime": "2026-09-10T14:00:00+0800",
    "endDateTime":   "2026-09-10T15:00:00+0800",
    "userId": 123
  }'
```

回應含新預約的 `referenceNumber`,後續更新 / 取消都用它。

### 取消預約

```bash
curl -s -X DELETE "${AUTH[@]}" "$BASE/Reservations/<referenceNumber>"
```

> 日期時間建議一律帶時區偏移(`+0800`)。沒帶的話系統會用「已登入使用者的時區」推定。

---

## 8. 錯誤碼

| HTTP | 意思 |
| --- | --- |
| 200 | 成功 |
| 401 | 未帶 / token 失效 → 重新 `Authenticate` |
| 403 | 已登入,但該帳號不在這個端點要求的群組、或非 admin |
| 404 | 路徑或資源不存在(注意結尾斜線) |
| 503 | API 未啟用(`api.enabled = false`) |
| 500 | 伺服器例外,詳情看 VM 上 `lb_prod_logs` volume 的 `app.log` |

---

## 9. 安全建議

1. **開一個專用服務帳號 + 專用 AD 群組**,把 `api.authentication.group` 設成那個群組。
   如此一來能打 API 的就只有這個服務帳號,而不是全公司 AD 使用者;針對
   `/Authenticate/` 的密碼嘗試面也一起收掉。
2. **寫入端點收斂**:`reservations.rw.group` 設成同一個群組;不需要建 / 改資源、
   使用者的話,那些本來就已經是 *僅 admin*,服務帳號別給 admin。
3. **服務帳號密碼**當機密管理,不要進版控、不要貼在指令 / 截圖裡。
4. API 只在內網 —— 不要為了讓外部串接而放寬 Caddy / tunnel 設定。
5. 日期時間一律帶時區。

---

## 10. 疑難排解

| 症狀 | 原因 / 處理 |
| --- | --- |
| 全部請求回 503 | `api.enabled` 還是 `false`,或 `app.env` 的 `LB_API_ENABLED` 蓋掉了 config.php |
| 改了 config.php 沒反應 | 正式站是 image 內建,要 rebuild + 重建容器;且先檢查 `app.env` 有沒有同名 `LB_*` 覆蓋 |
| API 整個掛掉、每個請求都 500 | `authentication.group` / `*.group` 填的群組名稱在 DB 找不到 → `GetConfigGroup()` 拋例外。修正名稱 |
| `Authenticate` 回 "API access not authorized" | 該帳號不在 `authentication.group`。把服務帳號加進群組 |
| 連線逾時 / 連不到 | 呼叫端不在內網 / 沒連 VPN |
| GET 回 404 但路徑看起來對 | 結尾斜線 —— `/Reservations/` 要斜線,`/Reservations/<ref>` 不要 |

---

## 參考

- 上游完整 API 文件:[docs/source/API.rst](docs/source/API.rst)
- 線上自動文件(API 啟用後):`https://booking.coasia.com.tw/Web/Services/index.php`
- 路由註冊、存取層級定義:[Web/Services/index.php](Web/Services/index.php)
- API 設定鍵定義:[lib/Config/ConfigKeys.php](lib/Config/ConfigKeys.php)(搜尋 `API_`)
- 部署 / VM / 容器操作:[COASIA-DEPLOYMENT.md](COASIA-DEPLOYMENT.md)
