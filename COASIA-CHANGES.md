# CoAsia 客製化說明文件

本文件記錄對 LibreBooking（`develop` branch fork）所做的修改，起點是讓使用者在預約「Video Conference 資源」（Zoom TW / Zoom CN / Zoom Oversea / Google Meet1 / Google Meet2）時，確認頁、確認信、提醒信都能自動帶出該資源的視訊連結，實體會議室預約則完全不受影響、不顯示任何視訊區塊；後續逐步擴展到本文件涵蓋的所有客製化項目（§1–§76）。

Repo：公司 GitLab（`coasiait/coasia-booking`）
上游：https://github.com/LibreBooking/app（`upstream`，未來要同步官方更新可 `git fetch upstream` 再 merge）

---

## 0. 前置需求：Custom Attributes 設定

所有功能都依賴管理員在後台「自訂屬性」（Custom Attributes，scope = Resource）建立以下三個屬性，並在 5 個 Video Conference 資源上填值：

| Attribute 名稱（`display_label`）| 說明 |
|---|---|
| `video_url` | 視訊連結（必填，判斷「這是不是視訊資源」的依據） |
| `video_meeting_id` | 會議 ID（Zoom 系列填，Google Meet 留空） |
| `video_passcode` | 密碼（Zoom 系列填，Google Meet 留空） |

**判斷邏輯**：程式碼不是用「資源分類」判斷是不是視訊資源，而是檢查該資源的 `video_url` 屬性值是否為空。空 → 當作實體會議室處理（不顯示視訊區塊、不報錯）；非空 → 顯示視訊區塊。這三個 attribute 的 label 名稱必須完全是 `video_url` / `video_meeting_id` / `video_passcode`（英文小寫、底線），程式是用字串比對這幾個 label 名稱去找屬性 ID，因為 admin 建立屬性時實際的屬性 ID 是動態產生的。

---

## 1. Task 1 — 預約確認頁

**對應 spec**：完成預約後，confirmation popup 要顯示視訊連結。

LibreBooking 沒有獨立的「確認頁」，而是預約送出後用 AJAX 回傳一段 HTML 片段（popup），顯示在畫面上。

### 修改的檔案

| 檔案 | 做了什麼 |
|---|---|
| `Domain/Values/VideoConferenceInfo.php` **(新增)** | 簡單的值物件：`ResourceName` / `Url` / `MeetingId` / `Passcode` |
| `Domain/namespace.php` | 註冊上面這個新檔案的 `require_once`（本專案沒有 PSR-4 autoload，靠這種手動註冊） |
| `Presenters/Reservation/ReservationSavePresenter.php` | 存檔成功後，遍歷 `AllResources()`，用 `IAttributeRepository::GetByCategory(RESOURCE)` 找出 `video_url`/`video_meeting_id`/`video_passcode` 三個屬性的 ID（依 label 比對），再逐一呼叫 `resource->GetAttributeValue($id)` 取值。`video_url` 為空的資源直接跳過 |
| `Presenters/Reservation/ReservationPresenterFactory.php` | 把 `AttributeRepository` 注入 `ReservationSavePresenter` 的建構子 |
| `Pages/Ajax/ReservationSavePage.php` | `IReservationSavePage` 介面新增 `SetVideoConferenceResources()`，把結果塞進 Smarty 樣板變數 `VideoConferenceResources` |
| `tpl/Ajax/reservation/save_successful.tpl` | 在既有的 Resources 區塊後面加 conditional 視訊區塊：`{if $VideoConferenceResources...}` |
| `lang/en_us.php` / `lang/zh_tw.php` | 新增翻譯字串 `VideoConferenceInfo`（視訊連結）、`VideoLink`（連結）、`MeetingId`（會議 ID）、`Passcode`（密碼） |
| `WebServices/Controllers/ReservationSaveController.php` | REST API 也實作了 `IReservationSavePage`，補上 `SetVideoConferenceResources()` 的 no-op 實作，否則會因為介面沒實作完整而直接 fatal error |
| `tests/fakes/FakeReservationSavePage.php`、`tests/Presenters/Reservation/ReservationSavePresenterTest.php` | 因為改了介面/建構子而需要同步更新的測試替身 |

### 範圍說明

只改了「新建預約」這條路徑（`ReservationSavePresenter`）。「編輯預約」（`ReservationUpdatePresenter`）共用同一份 `save_successful.tpl`，但沒有特別去 set `VideoConferenceResources` 變數，Smarty 會直接跳過該區塊，不會報錯，只是編輯後的確認框不會顯示視訊資訊（spec 也只要求新建流程）。

### 驗證方式

用 Docker 起本機環境，實際登入 admin、預約一個綁定 `video_url` 的資源（Zoom TW），確認畫面顯示：
```
視訊連結
連結：https://zoom.us/j/94512345678
會議 ID：945 1234 5678
密碼：coasia2026
```
再預約一個沒有 `video_url` 的資源（實體會議室），確認完全沒有視訊區塊、無錯誤。

---

## 2. Task 2 — 確認信（Email）+ ICS 附件

**對應 spec**：預約人 + 所有參與者收到的確認信要有視訊連結；ICS 行事曆附件的 LOCATION/DESCRIPTION 也要帶連結。

LibreBooking 所有跟「預約」相關的信件類別（`ReservationCreatedEmail`、`ReservationUpdatedEmail`、`ParticipantAddedEmail`、`InviteeAddedEmail`、`GuestAddedEmail`⋯）都繼承同一個共用基底類別 `ReservationEmailMessage`，所以改一次基底就能讓 owner + participants + invitees + guests 全部收到。

### 修改的檔案

| 檔案 | 做了什麼 |
|---|---|
| `lib/Email/Messages/ReservationEmailTemplateContext.php` | 新增 `VideoConferenceResources()` 方法。重用既有的 `Resources()`（原本就會把每個資源的自訂屬性組好，含 admin-only 過濾），從裡面找 `video_url`/`video_meeting_id`/`video_passcode` 三個 label，組成 `VideoConferenceInfo[]`，避免重複查一次 DB |
| `lib/Email/Messages/ReservationEmailMessage.php` | `PopulateTemplate()` 把上面的結果 set 進 `VideoConferenceResources` 樣板變數；`PopulateIcsAttachment()` 改成把主要資源的 video_url 附加到 ICS 的 `LOCATION` 欄位（格式：`"會議室名稱 - https://zoom.us/j/..."`，這是 Zoom 官方日曆整合常見的做法，Outlook/Google Calendar 手機版可以直接點擊加入會議） |
| `lang/en_us/ReservationCreated.tpl`（owner 收到的信） | 大改版，見下方「信件版面重新設計」 |
| `lang/en_us/ReservationInvitation.tpl`（participants/invitees/guests 收到的信） | 同上 |
| `tests/Application/Reservation/ReservationEmailTemplateContextTest.php` | 新增 `testVideoConferenceResourcesOnlyIncludesResourcesWithVideoUrl` |

### 信件版面重新設計（依你的回饋追加）

原始版本是純英文、而且會把「Resource Details」這種通用自訂屬性 dump 成一個表格（把 `video_meeting_id`、`video_passcode`、`video_url` 又用原始 label 顯示一次，跟下面精美的視訊區塊重複、雜亂）。依你的回饋做了兩輪調整：

1. **拿掉重複的通用屬性表格**，改成手寫、乾淨的中文版面（`Reservation Details:`→`您的預約已建立`、`Start/End`→`時間`⋯）
2. **加上品牌色橫幅、卡片式版面、按鈕樣式的連結**（查看預約／加入行事曆／加入 Google 日曆），視訊區塊用淺藍底 + 左側色條標示

這兩個 email 樣板檔案沒有走翻譯系統（`{translate key=...}`），是**直接寫死繁體中文**——因為 LibreBooking 的 email 樣板是「每個語言一份獨立檔案」（`lang/{語言}/*.tpl`），這個 fork 只服務 CoAsia 一家公司，沒有多語系需求，所以比起另外生一份 `lang/zh_tw/*.tpl` 再確保每個使用者語言設定正確，直接改寫英文版檔案內容更簡單可靠（不管使用者帳號語言設定是什麼，都會看到中文）。

### 已知小狀況

- **Yahoo Mail 網頁版**顯示附件檔名為 `reservation.ics.ics`（重複副檔名）。經確認是 Yahoo Mail 自己顯示時的行為，不是程式問題——用 Mailpit 檢查過信件原始內容，附件檔名其實就是 `reservation.ics`，沒有重複；不影響下載開啟。

### 驗證方式

- PHPUnit：`ReservationEmailTemplateContextTest` 全數通過
- 端對端：透過真實 Gmail SMTP relay（`smtp.gmail.com`）寄到 `shawn_tsai@coasia.com`，實際收信確認內容、排版、ICS 附件的 LOCATION 欄位都正確

---

## 3. Task 3 — 提醒信（Reminder Email）

**對應 spec**：預約開始前 60 分鐘的提醒信要帶視訊連結；SQL 要用 LEFT JOIN 避免實體會議室的提醒信被漏寄。

提醒信是一支獨立的 cron script（`Jobs/sendreminders.php`），**設計上要每分鐘執行一次**，每次執行去比對「現在時間（精確到分鐘）」是否等於「某筆預約開始時間 − 使用者設定的提前分鐘數」。這條 SQL（`GET_REMINDER_NOTICES`）原本完全沒有查詢自訂屬性。

### 修改的檔案

| 檔案 | 做了什麼 |
|---|---|
| `lib/Database/Commands/ColumnNames.php` | 新增 `VIDEO_URL`、`VIDEO_MEETING_ID`、`VIDEO_PASSCODE` 三個欄位名稱常數 |
| `lib/Database/Commands/Queries.php`（`GET_REMINDER_NOTICES`） | 在 SELECT 清單加入三個 **correlated subquery**（每個都是 `SELECT ... WHERE rrv.series_id = rs.series_id LIMIT 1`），分別撈 `video_url`/`video_meeting_id`/`video_passcode`。這種寫法效果等同 LEFT JOIN：找不到值就回傳 `NULL`，**不會把該筆預約從結果中排除**，符合 spec「實體會議室的提醒信不能被影響」的要求。同時**修掉一個既有 bug**：原本 SQL 的 WHERE 子句用到 `rr.minutes_prior`，但 SELECT 清單裡從來沒有選這個欄位，導致 `ReminderNotice::ReminderMinutes()` 永遠是 `null`（這個 bug 跟這次客製化無關，是上游程式碼本來就有的問題，只是剛好在改這條 SQL 時發現，順手修掉） |
| `Domain/ReminderNotice.php` | 建構子新增三個可選參數 `videoUrl`/`videoMeetingId`/`videoPasscode`（都有預設值 `null`，向下相容），`FromRow()` 從新欄位解析 |
| `lib/Email/Messages/ReminderEmail.php` | `Body()` 組出 `VideoConferenceResources` 陣列（比照 Task 1/2 的資料結構），只在 `ResourceNames()` 對應資源有 `video_url` 時才有內容 |
| `lang/en_us/StartReminderEmail.tpl` | 重新設計（同 Task 2 的中文卡片式風格）+ 視訊區塊 |
| `lang/en_us/EndReminderEmail.tpl` | 同樣重新設計，但**不含視訊區塊**（spec 只要求開始前提醒要有視訊資訊） |
| `tests/fakes/DBRows.php` | `ReminderNoticeRow` 測試替身新增可選的 video 欄位參數 |
| `tests/Application/Reservation/ReminderEmailTest.php` **(新增)** | 3 個測試：有 video_url 時信件內容含視訊資訊、沒有 video_url 時（實體會議室）不含視訊區塊、End 提醒信一律不含視訊區塊 |

### 驗證方式

實際跑過 `Jobs/sendreminders.php`（手動塞測試資料到 `reservation_reminders` 表，時間點精確對到「現在這一分鐘」），確認：
- SQL 正確找到通知、`video_url` 正確帶出
- 實體會議室的預約在同一條 SQL 下確認拿到 `video_url = NULL` 但**沒有被排除**在結果之外
- 真實透過 Gmail SMTP 寄出，過程無錯誤

### 正式上線注意事項（尚未處理，見下方 Section 5）

Task 3 的程式邏輯已完成，但「每分鐘執行一次」這件事**依賴正式主機上的真實 crontab**，目前的 Docker 開發環境沒有設定。上線時要在正式主機加入：
```
* * * * * php /var/www/html/Jobs/sendreminders.php >> /var/log/librebooking-reminders.log 2>&1
```

---

## 4. 本機開發／測試環境

為了能實際跑起來驗證（而不是憑空看程式碼），額外建立了一套**本機專用**的 Docker 環境：

| 檔案 | 用途 |
|---|---|
| `Dockerfile.dev` | PHP 8.2 + Apache + 必要擴充套件（intl, ldap, mysqli, pdo_mysql, zip, gd） |
| `docker-compose.dev.yml` | 三個服務：`app`（PHP/Apache）、`db`（MySQL 8.0）、`mailpit`（假 SMTP 伺服器，攔截測試信，UI 在 `localhost:8025`） |
| `config/config.php` | **沒有進 git**（`.gitignore` 排除），本機測試用，含資料庫密碼、SMTP 設定 |

啟動方式：
```bash
docker compose -f docker-compose.dev.yml up -d --build
docker compose -f docker-compose.dev.yml exec app composer install
# 匯入 database_schema/ 下的 schema + data
```

**安全性補充**：LibreBooking 後台「系統設定」頁面存檔時，會自動產生 `config/config_{時間戳記}.bck.php` 備份檔，這些備份檔會包含當時設定的 SMTP 密碼明文。原本這個檔名規則沒有被 `.gitignore` 排除，已經補上 `/config/config_*.bck.php` 規則（見 commit `chore(dev)`）。

**這次驗證使用的正式 SMTP**：使用者自己的公司網域信箱 `shawn_tsai@gm.coasia.com`（Google Workspace 帳號應用程式密碼），只設定在本機 `config/config.php`，沒有寫入任何會被 commit 的檔案。

---

## 5. 測試涵蓋

- 完整 PHPUnit 測試套件：**1651 個測試、0 failure**（含這次新增的測試）
- 三個 Task 都做過「真實寄信」端對端驗證，不只是單元測試

---

## 6. Task 4 — UI 美化（Phase 1：既有頁面重新配色）

Task 4 依 spec 拆成兩階段做。**Phase 1（已完成）**：不動任何模板/後端邏輯，純粹套用 CoAsia 品牌視覺到「現有」頁面（登入頁、dashboard、預約表單、schedule.php 行事曆）。**Phase 2**（週檢視多資源分色行事曆，只存在於 mockup 階段）：屬於新功能而非重新設計，尚未評估/動工。

LibreBooking 本身已經有現成的擴充機制，剛好就是 spec 講的「額外 CSS 檔案覆蓋，不動核心模板」：

| 機制 | 用法 |
|---|---|
| `css.extension.file`（config key）| 指到 `Web/css/` 底下一個檔名，該檔案會在所有核心 CSS **之後**載入（`tpl/globalheader.tpl` 裡的載入順序最後一個），源碼順序 + 相同選擇器就能直接覆蓋核心變數 |
| `Web/img/custom-logo.png` | 存在的話會自動被抓去取代預設 logo（`Pages/Page.php::findCustomFile`），不用改任何模板 |
| `--primary` / `--reserved` / `--reservedMine` 等 CSS 變數 | 核心 CSS（`librebooking.css`、`schedule.css`）本來就用這些變數驅動按鈕、連結、行事曆色塊，改變數本身就能廣泛套用顏色，不用一個個選擇器改 |

### 修改的檔案

| 檔案 | 做了什麼 |
|---|---|
| `Web/css/coasia-theme.css` **(新增)** | 覆蓋 `[data-bs-theme='default']` 的 `--primary`（深藍 `#1a3f73`），`:root` 的 `--reserved`/`--reservedMine`/`--reservedParticipating`/`--reservedPending`（行事曆色塊顏色，「我的預約」用品牌紅），Bootstrap 5 的 `--bs-border-radius`/`--bs-box-shadow`（圓角、柔和陰影廣泛套用到 `.card`/`.btn`/`.form-control` 等），`--bs-body-font-family`（中文內容改用新細明體，英文/數字走系統字體） |
| `Web/img/custom-logo.png` **(新增)** | 真實 CoAsia logo（PNG），會被自動偵測取代預設 logo |
| `.gitignore` | 上游專案原本會排除 `custom-logo.png`（避免 fork 誤傳個人 logo），但這是 CoAsia 專屬 fork，logo 本來就該隨部署一起進版控，所以加了一條例外規則 |

**部署注意**：`config/config.php` 本身沒有進 git（含密碼等機密），所以 `css.theme` 保持 `default`、`css.extension.file` 要設成 `'coasia-theme.css'` 這兩行，正式環境設定時要記得加上，不然視覺主題不會生效（`Web/img/custom-logo.png` 因為是靜態檔案，只要檔案存在就會自動生效，不需要額外設定）。

`css.theme` 沒有直接改成自訂名稱（例如 `'coasia'`），是因為這個 key 有寫死的白名單驗證（`ConfigKeys::CSS_THEME` 的 `choices`），改成 `default` 以外的值會被直接擋掉、靜默重置——所以做法是覆蓋 `default` 主題本身的顏色，而不是新增一個主題。

### 驗證方式

實際在 Docker 環境跑起來，用瀏覽器 JS 檢查 computed style 確認：登入頁按鈕、dashboard 的「Reserve」按鈕、`schedule.php` 的預約色塊，都正確吃到新的深藍/紅配色與圓角；主控台沒有任何錯誤。這階段完全沒有改動任何 PHP 程式碼，所以沒有跑 PHPUnit 的必要（純 CSS + 靜態檔案 + 本機設定變更）。

### 還沒做的（Phase 1 範圍內，之後可以再做）

- 預約表單的「更多選項收合」——把重複規則/設備/點數等欄位包進可摺疊區塊，這個需要動 Smarty 模板（不是純 CSS），還沒做
- 頂部導覽列的毛玻璃效果、mockup 裡的圓角卡片深度視覺——目前只做了顏色/圓角/陰影這種變數層級的廣泛套用，還沒有針對個別頁面做精修

---

## 7. Task 4 — UI 美化（Phase 2：跨資源週檢視總覽頁）

新增一個獨立頁面 `Web/overview.php`（選單「時間表 → 總覽」），跟現有 `schedule.php` 完全分開、互不影響。畫面是「天為欄、時為列」的週檢視，一次顯示所有資源的預約，依資源分色，可用上方標籤篩選只看某個資源。

### 為什麼是新頁面，不是改 schedule.php

`schedule.php` 整條路徑（`SchedulePresenter`、`ReservationService`、`ScheduleService::GetLayout` 等）都寫死只吃「一個 schedule ID」，而 CoAsia 的 spec 是每個資源各自一個獨立 Schedule（12 個）。要在 `schedule.php` 原本的框架裡做到「一次看所有資源」，等於要重構好幾層現有程式碼，風險高。改成獨立新頁面之後：

- 完全不用管「不同 schedule 的時段設定可能不一樣」這個複雜度（新頁面不使用 `IScheduleLayout` 那套時段版型系統，直接用固定的 09:00–18:00 業務時間畫格子）
- 不會動到 `schedule.php` 既有的任何邏輯，原本的日/週/月檢視繼續給需要精細操作單一資源排程的情境使用（例如管理員調整某間會議室）
- 大部分底層查詢/著色管線其實已經現成：`ReservationItemView::GetColor()`（`Domain/ReservationItemView.php`）本來就會吃資源設定的顏色，`Domain/Access/ReservationViewRepository::GetReservations()` 本來就支援不限定 schedule/resource 查詢——這兩塊直接重用，沒有另外寫

### 新增的檔案

| 檔案 | 做了什麼 |
|---|---|
| `Presenters/OverviewPresenter.php` **(新增)** | 查一週範圍內、**所有資源**的預約（`ReservationViewRepository::GetReservations()`，不限定 schedule/resource），依「星期幾＋00:00–24:00 全天」換算每筆預約色塊的位置百分比；資源顏色優先用後台設定的 `BookableResource::GetColor()`，沒設定的話用一組固定的備用色盤（跟 Task 4 mockup 的分類色一致） |
| `Pages/OverviewPage.php` **(新增)** | 標準 `SecurePage` 頁面，處理 `?sd=` 週起始日參數、把 Presenter 算好的資料丟給樣板 |
| `Web/overview.php` **(新增)** | 入口檔案 |
| `tpl/Overview/overview.tpl` **(新增)** | 週檢視樣板：週導航（上一週/下一週/今天）、資源篩選標籤、7 天 × 24 小時（00:00-24:00）格子、色塊 |
| `Web/css/overview.css` **(新增)** | 這個頁面專屬的樣式（不影響其他頁面），沿用 Task 4 Phase 1 已經建立的 `--primary`/`--reservedMine`/`--bs-border-radius` 等變數 |
| `Web/scripts/overview.js` **(新增)** | 資源標籤篩選（純前端顯示/隱藏，不用重新打 API）；點空白格子直接依點擊位置換算日期/時間，導去 `reservation.php?rid=...&sd=...&ed=...` 新增預約（見下方「點格子新增預約」） |
| `Pages/Pages.php` | 新增 `OVERVIEW` 路由常數 |
| `tpl/globalheader.tpl` | 選單「時間表」底下加一個「總覽」連結 |
| `lang/en_us.php` / `lang/zh_tw.php` | 新增 `Overview`/`Weekend`/`Untitled`/`YourReservations` 翻譯字串 |

### 點擊行為——重用既有流程，沒有另外寫訂位邏輯

- 點色塊（既有預約）→ 導去現有的 `reservation.php?rn=...`（跟一直以來測試用的查看/編輯頁完全同一支）
- 沒有另外做「快速新增」表單去呼叫 `ajax/reservation_save.php`——這樣要重新實作 `IReservationSavePage` 介面（將近 30 個方法），風險/工作量都不划算。改成點空白格子時導去既有的 `reservation.php`（見下一節）

### 點格子新增預約

點週檢視裡任一天欄位的空白處，會依滑鼠點擊的 Y 座標換算出時間（抓到最近的 30 分鐘），連同該欄位的日期、目前篩選中的資源（沒篩選就用第一個資源），組成 `reservation.php?rid={資源ID}&sd={日期}+{開始時間}&ed={日期}+{結束時間}` 導過去，直接落在新增預約表單、日期/時段/資源三者都已預先帶好。

- `Web/scripts/overview.js` 負責座標換算跟導頁；`tpl/Overview/overview.tpl` 的 `.day-col` 上帶了 `data-date`/`data-day-start`/`data-day-end`
- 全程沒有另外碰 `ReservationInitializerBase`／`NewReservationInitializer` 等既有訂位表單初始化邏輯，`?rid=`/`?sd=`/`?ed=` 都是這幾支檔案本來就支援的既有查詢字串（`QueryStringKeys::RESOURCE_ID` / `START_DATE` / `END_DATE`）

**除錯過程中發現的環境問題（非程式碼 bug）**：一開始測試時，不管點幾點，表單的「開始時段」永遠跳到 20:00。追下去發現是本機開發資料庫裡，LibreBooking demo 內建的 schedule（`layout_id=1`，被 `Conference Room 1/2` 兩個 demo 資源共用）的可預約時段（08:00–18:00）是綁在 demo 預設的 **`America/New_York`** 時區，不是公司實際使用的 `Asia/Taipei`。換算下來，08:00 美東時間 = 20:00 台北時間，跟症狀完全對上——這是 `GetStartSlotClosestTo()`（`lib/Application/Reservation/ReservationInitializerBase.php`）為了「找最接近且可預約的時段」正確運作的結果，不是程式邏輯錯誤。隨著下面「換成真實資源」的資料庫調整，已經把 schedule 時區改成 `Asia/Taipei`，問題就消失了。

### 驗證方式

實際在瀏覽器測試：週範圍計算正確（含跨月份邊界）、所有既有測試資料的預約都正確依資源分色顯示、資源篩選標籤能正確篩選、點色塊連結正確導到 `reservation.php?rn=...`、點空白格子能正確導到 `reservation.php?rid=...&sd=...&ed=...` 並且表單的日期/時段/資源都精準對上點擊位置、上一週/下一週導航網址正確、瀏覽器 console 跟 PHP log 都沒有錯誤。

### 還沒做的（可以再加強）

- 週檢視以外的「日檢視」「清單檢視」（目前只有週檢視這一種）
- 後台目前沒有幫任何資源設定顏色（`BookableResource::SetColor()`），所以現在看到的顏色都是備用色盤，不是刻意指定的——需要的話可以去後台「管理資源」個別設定

### 本機開發資料庫：換成真實的 CoAsia 資源清單

一開始（Task 1-3 測試期間）本機開發資料庫用的是 LibreBooking 內建的 demo 資料（`Conference Room 1`／`Conference Room 2`，共用同一個 `America/New_York` 時區的 schedule）。為了讓總覽頁跟預約流程能反映 spec 實際規劃的資源結構，新增了 `database_schema/coasia-resources.sql`，把本機資料庫改成：

- **7 個實體會議室**（各自獨立 Schedule，全部 `Asia/Taipei` 時區，08:00–18:00 可預約，跟 demo 原本的時段規則一樣、只是時區改對）：台北 Taipei、上海 Shanghai、新加坡 Singapore、新德里 New Delhi、深圳 Shenzhen、首爾 Seoul、高爾夫球室 Golf Room
- **5 個視訊會議資源**（同樣各自獨立 Schedule）：Zoom TW、Zoom CN、Zoom Oversea、Google Meet1、Google Meet2，並各自填好 `video_url`／`video_meeting_id`／`video_passcode`（Google Meet 只填 `video_url`，符合 spec 3. 的規則）
- 對應 spec section 2「12 個獨立 Schedule」的要求（跟 section 6 checklist 寫的「建 1 個 Schedule」不一致，以 section 2 較詳細的規格為準）
- 兩個 Resource Group（台北會議室／Video Conference），對應 spec section 6 的 admin 設定清單
- 原本 `resource_id=1`（demo 的 Conference Room 1）身上測試用的 video 屬性值被清掉，因為它現在是實體房間「台北 Taipei」

這只是**本機開發/展示用的資料庫種子腳本**，正式環境還是要照 spec section 6 的 checklist 在 admin 後台手動建立（欄位值不會寫死在程式碼或腳本裡）。腳本本身可重跑於全新資料庫，用來重建本機測試環境；如果要在既有本機庫上重跑會因為 ID 衝突而失敗（設計上只跑一次）。

---

## 9. 本機開發環境效能問題：每個頁面都要跑一下

使用者反映本機測試時每個畫面都要等一下，體感很卡。實測（用瀏覽器已登入的 session 直接 `fetch` 計時，排除掉沒帶 cookie 誤測到登入頁的干擾）發現同一頁多次載入的時間落差很大（幾十毫秒到 2-3 秒不等），且不是單純「第一次慢、之後就快」的暖機模式，找到兩個疊加的根因：

1. **`config/config.php` 的 `cache.templates` 被設成 `false`**：這會讓 Smarty 樣板引擎（`lib/Common/SmartyPage.php` 的 `force_compile = !$cacheTemplates`）**每一次請求都重新編譯全部用到的 .tpl 樣板**，而不是用編譯好的快取。這應該是先前做 Task 4 UI 大量調整版面時，為了讓改完 .tpl 能立即看到效果而關掉的，後來忘記改回來。現在 UI 已經穩定，改回 `true`（Smarty 預設值也是 true）。
2. **PHP opcache 的 `validate_timestamps` 在 Windows bind mount 下的 stat() 成本**：專案資料夾是用 Windows bind mount（`docker-compose.dev.yml` 的 `.:/var/www/html`）掛進容器的，這種掛法下每一個檔案的 `stat()`/`open()` 系統呼叫都比原生檔案系統慢很多。用一個暫時性的診斷腳本量測發現，光是首頁 dashboard 一次請求就 `require_once` 了 **375 個 PHP 檔案**（這個專案大量用手動 `require_once`／`namespace.php` 串接，不是單純靠 Composer 自動載入）。opcache 預設每次請求都會對每個已快取的檔案做一次 `stat()` 確認有沒有改過（`validate_timestamps=On`），375 次 stat() 疊加 Windows bind mount 的延遲，就是感受到的卡頓——關掉 DB 查詢紀錄比對後，同一次頁面請求的 DB 查詢時間只佔了 ~130ms，其餘 1-3 秒都花在這類檔案系統呼叫上，證實資料庫不是瓶頸。

**修法**：在 `Dockerfile.dev` 新增 `/usr/local/etc/php/conf.d/zz-opcache-dev.ini`，設定 `opcache.validate_timestamps=0`，讓 PHP 完全信任已編譯進 opcache 的內容，不再每次請求都重新檢查檔案有沒有變動。

**代價**：改完 PHP 程式碼後，opcache 不會自動偵測到變動，需要手動讓它失效才看得到新版本，例如 `docker compose -f docker-compose.dev.yml restart app`（重開容器最乾淨）。這是用「開發時偶爾要手動重啟一下」換「日常瀏覽不卡頓」，正式環境（非本機開發用的 `docker-compose.yml`）本來就應該用類似設定（`validate_timestamps=0` + 部署時清一次 opcache），這不是本機限定的權宜之計。

**效果**：同樣的 4 個頁面（首頁、預約表單、schedule.php、總覽頁），修正前後各測 3 次（已登入 session 內用瀏覽器 `fetch` 計時）：

| 頁面 | 修正前 | 修正後 |
|---|---|---|
| 首頁 dashboard | 2.5–3.8 秒 | 0.32–0.36 秒 |
| 預約表單 `reservation.php` | 0.7–1.2 秒 | 0.14–0.32 秒 |
| `schedule.php` | 0.15–0.5 秒（原本就還好） | 0.15–0.34 秒 |
| 總覽頁 `overview.php` | 0.1–1.2 秒（忽快忽慢） | 0.13 秒（穩定） |

首頁 dashboard 目前是最重的一頁（~330ms），主要是它一次載入 6-7 個 dashboard 小工具（公告、即將到來的預約、資源可用狀態……），如果之後還是覺得慢，可以考慮的下一步是把這些小工具的查詢結果做短時間快取，但目前這個量級對本機開發已經足夠流暢，先不處理。

> **踩坑記錄**：在 Windows Git Bash 用 `mysql ... < coasia-resources.sql` 第一次匯入時，中文名稱全部變成亂碼（UTF-8 bytes 被 mysql client 用非 utf8mb4 的預設字元集讀進去，導致雙重編碼）。修正方式是匯入指令要帶 `--default-character-set=utf8mb4`，腳本開頭也補了 `SET NAMES utf8mb4;` 避免下次再犯。

---

## 10. Task 4 — UI 全面重建為 Google Calendar（Material 3）風格

在 Phase 1/2 之後，依需求把整個介面（不只總覽頁）以 Google Calendar Web 為唯一設計基準重新建立，而不是逐項微調顏色。

### 左側導覽（`tpl/globalheader.tpl`）

- 原本的頂部導覽列改成常駐左側 sidebar（240px，白底），品牌區、使用者頭像圓圈、`+新增預約` 按鈕都在最上面
- 只保留日常真的會用到的連結：`我的會議`、`通知偏好設定`；`總覽` 拿掉（logo/mini 月曆本身就能點回總覽頁，不需要重複連結）
- 一個月曆迷你元件（`#miniCal`），可以左右切月、點某天直接跳去那一週的總覽
- 資源篩選清單（實體會議室／視訊會議室兩個可摺疊群組，各自帶顏色勾選框），跟總覽頁的「實體／視訊」分頁標籤連動——分頁標籤決定目前顯示哪個類別，篩選清單只顯示對應類別的資源
- Admin 專用的「程式管理」群組：先前檢查過資料庫實際使用狀況，只保留真的有資料在用的 `ManageReservations`／`ManageSchedules`／`ManageResources`／`ManageUsers`／`CustomAttributes`；`ManageBlackouts`／`ManageQuotas`（0 筆資料）、`ManageAccessories`（LibreBooking 內建的示範資料，從未被實際使用）、`ManageAnnouncements`（0 筆資料，Dashboard 又已經不是首頁）都改成 `{if false}` 隱藏但保留程式碼；`ManageGroups` 原本也在隱藏名單裡，後來因為「全體員工」權限群組機制（見下方 Section 12）變成真的會用到，移出來恢復顯示
- 拿掉頁尾的 LibreBooking 版本／GPLv3 字樣

### 總覽頁行事曆（`tpl/Overview/overview.tpl`、`Web/css/overview.css`、`Web/scripts/overview.js`）

- 工具列 + 日期列改成 `position: sticky`，往下捲動時維持在畫面頂端不消失（拿掉行事曆卡片原本的圓角/陰影/`overflow:hidden`——這幾個屬性會讓 sticky 定位失效，改成跟 Google 一樣的無邊框平面網格）
- 工具列右側加了「實體會議室／視訊會議室」分頁標籤，切換會同時篩選行事曆格子跟左側資源清單
- 拖曳格子可以直接選取時間範圍建立預約（原本只能點一下產生固定 30 分鐘），拖曳時會顯示範圍預覽；滑鼠移到空白格子上不會再跳出常駐的預覽框（Google 本身也只在真的拖曳時才顯示範圍，不是整個格子都在跟著游標反白）
- 點既有預約色塊會彈出一個小卡片（標題／時間／資源 + 查看詳情連結），不會像以前一樣直接整頁跳走
- 頁面載入自動捲到約早上 7 點（業務時段開始前），不會停在半夜 00:00
- 已經結束的預約會變半透明；已經是本週時「今天」按鈕會停用
- 新增/建立預約的彈窗（quick-create modal）比照 Google 的同畫面彈窗：標題用底線輸入框、時間可以直接改（半小時為單位的下拉選單）、可以切換實體／視訊資源分類

---

## 11. 修復預約權限缺口 + 登入後預設頁面

- `Pages::DEFAULT_LOGIN`／homepage id 1 原本指向 `dashboard.php`，但這支頁面已經不是主要入口，改成 `overview.php`——目前所有帳號的 homepage id 都是預設值 1，所以這個改動讓每個人登入後都直接進總覽頁
- 發現一個資料缺口：12 個資源裡，`user_resource_permissions` 這張表原本只有前兩個（台北、上海）有授權紀錄，其餘 10 個從未授權給任何一般使用者，導致非 admin 帳號完全訂不了那 10 個資源（`PermissionService::CanBookResource()` 對 admin 直接放行，所以一直沒被發現）。已修正本機資料庫，並在 `database_schema/coasia-resources.sql` 補上對應的授權 INSERT，避免重建資料庫時再次出現同樣缺口

---

## 12. 預約權限模型：全體員工預設開放 + 部門可個別限制

原本上一節是用「每個使用者對每個資源各自授權一筆」的方式修補，考量到之後要接 AD 登入（AD 使用者第一次登入會自動在本機建帳號），改成更長久的做法：

- 建立一個「全體員工」權限群組，標記 `isdefault=1`，並授予全部 12 個資源 Full 權限
- LibreBooking 本身既有的機制：`UserRepository::Add()`（不管是自助註冊還是 AD 登入觸發的自動建帳號，最終都會呼叫到這裡）本來就會自動把新建立的使用者加進所有 `isdefault=1` 的群組——所以這個群組建好之後，**之後任何新帳號（含 AD 使用者）都會自動繼承全部資源的預約權限，不需要額外寫程式或外掛**
- 之後如果要限制某個資源只給特定部門：到後台「程式管理 → ManageGroups」把該資源在「全體員工」群組的權限改回 None、另外建一個部門群組對該資源設 Full 並加入該部門成員即可，全部透過既有後台操作，不需要改程式碼
- 原本應急用的個人授權（Section 11）已經撤掉，改成完全依賴這個群組機制，避免系統裡同時存在兩套授權路徑

### 順手修掉的既有 bug

在加 `UserRepository::LoadByPreference()`（見下方 Section 13）時，發現 `UserRepository::Load()` 的「找不到資料」分支呼叫了一個不存在的 `User::Null()` 方法（`Domain/User.php` 只有 `NullUser` 這個類別，沒有 `User::Null()` 這個靜態工廠方法），只要真的觸發到這個分支就會直接 fatal error——目前應該從未被觸發過，屬於潛在地雷，已經改成 `new NullUser()`。

---

## 13. LINE 推播通知（新功能，spec 之外）

CoAsia 提出的新需求：除了 email，也想用 LINE 官方帳號推播「新預約確認」跟「提醒」訊息。這不在原始 spec 範圍內，是這次額外加的功能。

### 架構

- 新增 `lib/Line/`：`LineService`（用既有的 `mashape/unirest-php` 呼叫 LINE Messaging API 的 push/reply 端點）、`NullLineService`（`line.enabled=false` 時的空實作，比照既有 `NullEmailService` 的模式）、`LineSignatureVerifier`（驗證 webhook 的 `X-Line-Signature` HMAC-SHA256）
- 帳號綁定：重用既有的 `user_preferences` key-value 表（`Domain/Values/UserPreferences.php` 新增 `LINE_USER_ID`／`LINE_LINK_CODE` 兩個常數），不需要新增資料表。使用者在「通知偏好設定」頁面看到系統產生的 6 位數代碼，加 LINE 官方帳號好友後把代碼傳過去，`Web/line-webhook.php` 收到訊息後完成綁定
- 通知：新增 `OwnerLineCreatedNotification`（掛進既有的 `AddReservationNotificationService` 通知組合清單，跟 email 通知並存、互不影響），`Jobs/sendreminders.php` 的提醒迴圈也各自加了一段：已綁定 LINE 的使用者額外收到一則 LINE 推播
- 設定：`config/config.php`（沒進 git）新增 `line` 區塊（`enabled`/`channel.secret`/`channel.access.token`/`official.account.id`），預設 `enabled=false`

### 測試

- 新增單元測試：`tests/lib/Line/LineSignatureVerifierTest.php`（簽章驗證邏輯）、`tests/Presenters/Line/LineWebhookPresenterTest.php`（綁定成功/代碼找不到/忽略非文字訊息等情境）
- 端對端：用 Cloudflare quick tunnel（`cloudflared tunnel --url`）打通本機到 LINE 平台的 webhook 驗證，LINE 官方的 webhook 驗證請求（`LineBotWebhook/2.0`）確認能正確打到、簽章驗證通過、回應 200
- 實際「加好友→傳代碼→收到綁定成功回覆」這個完整迴圈，在對話當下卡在 LINE 官方帳號後台的應答設定（可能是聊天模式攔截了訊息，沒轉發到 webhook），尚未最終確認成功——這部分需要使用者自行檢查 LINE Official Account Manager 的回應設定

---

## 14. Active Directory 登入串接（進行中，尚未完成）

嘗試串接公司 AD 登入，過程：

- 已建立 `plugins/Authentication/ActiveDirectory/ActiveDirectory.config.php`（沒進 git，`**/*.config.php` 已在 `.gitignore` 規則內），填入網管提供的域控伺服器（`dc6`/`dc7`/`DC3.coasia.com.tw`）、base DN、帳號後綴等非機密欄位；帳號密碼由使用者自行填入，過程中沒有經手實際密碼
- 為了避免萬一 AD 設定有誤就整個鎖死既有登入，把 `database.auth.when.ldap.user.not.found` 設為 `true`（LDAP 驗證失敗時，回退用本機資料庫密碼登入）
- **卡住的地方**：網路連線本身沒問題（DNS 解析、port 389/636 都連得到），但用一般 LDAP simple bind（PHP `ldap_bind()`，也是 LibreBooking 這個外掛實際使用的方式）驗證帳密一直是 `Invalid credentials`（LDAP code 49），即使帳密格式已確認正確（用 PowerShell 的 `System.DirectoryServices.DirectoryEntry` 驗證同一組帳密可以成功）。推測是網域的「LDAP 伺服器簽章要求」政策擋掉了未加密的 simple bind，改測 LDAPS（port 636）發現 TLS 交握直接被中斷（伺服器收到 ClientHello 後無回應就斷線），研判 636 這個 port 雖然開著防火牆通，但網域控制站上可能從未實際綁定/設定 LDAPS 憑證。這部分需要網管進一步確認 DC 上的 LDAPS 憑證設定與 LDAP 簽章原則，暫停在這裡，尚未接通
- 在此之前系統一律使用本機帳號密碼登入，不受影響；`plugins.authentication` 目前仍是空字串（未啟用）

---

## 15. 總覽頁精修：格線/表頭對齊、事件排版、Google Calendar 風格重疊事件並排

在 Task 4 把總覽頁重建成 Google Calendar 風格之後，使用者逐項回饋了 7 個具體的視覺瑕疵，全部只改 `Presenters/OverviewPresenter.php`、`tpl/Overview/overview.tpl`、`Web/css/overview.css`、`Web/css/coasia-theme.css`、`lang/zh_tw.php` 這幾支既有檔案，沒有新增檔案。

| # | 問題 | 修法 |
|---|---|---|
| 1 | 側邊欄跟日曆格線之間的間距不對，且不能縮窄側邊欄本身 | `Web/css/overview.css` 的 `#page-overview` padding 由 `0 24px` 改成 `0 20px 60px 16px`（左側留少一點，因為側邊欄自己已經有邊框，滿 24px 會多一截灰底）；`.week-grid` 第一欄（時間軸）由 `54px` 收窄到 `48px` |
| 2 | 跨月/跨年的週標題只會顯示單一月份，看不出這週橫跨兩個月 | `tpl/Overview/overview.tpl` 的 `.month-label` 改成三段判斷：`$WeekStart` 跟 `$WeekEnd` 同月份 → `Y年n月`；同年不同月 → `Y年n月－n月`；跨年 → `Y年n月－Y年n月` |
| 3 | 「今天」那一欄的日期用圓形徽章、其他欄是純文字，兩種高度不一樣，導致星期文字（一二三四五六日）沒有對齊 | `.day-head .num` 改成固定 `height:40px` 的 flex 置中容器（不管是不是今天都佔一樣高度），`.day-head` 整體高度維持原本的 56px 不變 |
| 4 | 每個事件不管長度都硬塞「標題＋時間＋資源名稱」三行字，30 分鐘的短會議會被文字撐爆 | 用 CSS Container Query（`container-type:size` 掛在 `.event` 本身，讓 `.rr`／`.tt` 這些子元素可以查詢**自己所在事件卡片的實際高度**）：`@container eventCard (min-height: 32px)` 以上才顯示時間副標，資源名稱移到 hover 才出現的 popover，不再固定佔一行 |
| 5 | 沒標題的預約顯示「(沒有標題)」，用語生硬 | `lang/zh_tw.php` 的 `Untitled` 字串改成「未命名預約」 |
| 6 | 事件卡片視覺太陽春 | `.event` 的 padding 由 `2px 6px` 調整為 `3px 8px`、圓角 4px、加上 `:hover`（brightness 0.96）跟 `:focus-visible`（outline）狀態；`OverviewPresenter::PastelColor()` 的 `whiteWeight` 由 `0.82` 降到 `0.70`，讓底色跟側邊欄勾選框的顏色飽和度更接近 |
| 7 | 格線缺乏層次（垂直/水平粗細一樣）、現在時間線的圓點太大 | `coasia-theme.css` 新增 `--gcal-border-strong` token，套用在 `.day-head`／`.day-col` 的 `border-left`（垂直分隔線），水平的小時格線維持原本較淺的 `--gcal-border`；`.now-line::before` 的圓點直徑由 8px 縮到 6px |

### Google Calendar 風格重疊事件並排

修完上面 7 項後，使用者發現「同一時段有兩筆以上預約時會直接互相覆蓋」（因為當時每個 `.event` 都用同一組寫死的 `left:3px; right:3px`），要求做成跟 Google Calendar 一樣的並排顯示。

- **演算法**（`Presenters/OverviewPresenter.php` 新增 `AssignEventColumns()`／`FinalizeEventCluster()`／`EventColumnStyle()`）：對每天的事件先依 `(開始時間, 結束時間)` 排序（**故意不依賴資料庫回傳順序**），用貪婪演算法幫每筆事件找「最早騰出來的欄位」（`columnEndTimes[i] <= 事件開始時間` 才算空出來）；同時用一個 `clusterMaxEnd` 追蹤「目前這串連續重疊的事件最晚到幾點」，一旦下一筆事件的開始時間 `>=` 這個值，就代表前面那串重疊已經結束（形成一個 cluster），把這個 cluster 裡所有事件一起結算成最終的欄數
- **邊界規則**：11:00 結束跟 11:00 開始算「相鄰但不重疊」，不會被硬拆欄——用 `>=` 而不是 `>` 比較開始/結束時間來判斷
- **輸出**：每筆事件算出 `left`／`width` 兩個 CSS 字串（巢狀 `calc()`，例如 `calc(3px + 1 * (((100% - 8px) / 2) + 2px))`），完全在 PHP 端算好，`tpl/Overview/overview.tpl` 只是把這兩個值原樣輸出到 `style` 屬性，前端 JS 不用做任何版面運算；`.event` 的 CSS `left:3px;right:3px` 只留著當作萬一 inline style 漏掉時的保底，正常情況下一定被 inline style 蓋過去
- **測試**：新增 `tests/Presenters/OverviewPresenterTest.php`，用 Reflection 直接呼叫 private 的 `AssignEventColumns()`，涵蓋「單一事件佔滿整欄」「相鄰但不重疊」「完全重疊拆兩欄」「部分重疊拆兩欄」「三個事件串成一條鏈共用兩欄」「輸入順序打亂結果不變（用 `"開始-結束"` 當事件 id，而不是陣列 index，才能正確驗證這件事）」「不同的重疊群組互不影響」共 7 個案例

---

## 16. FullCalendar 週視圖：從唯讀評估原型，到升級為主要行事曆

### 16.1 起因與評估範圍

使用者想知道：剛做完的「重疊事件並排」這類本來就是行事曆函式庫該做的事，如果換成免費、MIT 授權的 [FullCalendar](https://fullcalendar.io) Standard 版本，能不能省掉這些手刻邏輯。要求先做一個**完全獨立、唯讀**的原型頁評估，明確不能動到 `overview.php` 那條路徑的任何檔案，也先不 commit。

**關鍵發現**：`Web/assets/vendor/fullcalendar/6.1.20/` 這個路徑在專案裡**早就存在**（`index.global.min.js` 284KB + `locales-all.global.min.js` 25KB + `metadata.json` 標明 `"license":"MIT"`）。掃過 bundle 內容確認有 `timeGridWeek`／`dayGridMonth`，**沒有** `schedulerLicenseKey`／`resourceTimeGrid`／`resourceTimeline` 這些 Premium 專屬字串——即免費 Standard 版，不需要另外安裝任何套件、不用 CDN，直接照專案既有的 `{vendor_js src="..."}` 慣例引用。

原型建置的新增檔案（全部獨立、完全不動 `overview.php` 相關的任何一支）：

| 檔案 | 用途 |
|---|---|
| `Web/overview_fullcalendar.php`（158 bytes） | 入口，比照 `Web/overview.php` 的極簡 bootstrap 寫法 |
| `Pages/OverviewFullCalendarPage.php`（1.4KB） | `SecurePage`，負責側欄用的資源清單/顏色 |
| `Presenters/OverviewFullCalendarPresenter.php`（6.6KB） | 側欄資料 Presenter（複製一小段 `OverviewPresenter::SplitByType()`／`AssignColors()`，因為原本是 private method 沒辦法直接重用又不能改動那支檔案） |
| `tpl/Overview/overview_fullcalendar.tpl`（8.2KB） | 樣板，`{include file='globalheader.tpl'}` 沿用共用側邊欄 |
| `Web/css/overview_fullcalendar.css`（25KB） | 本頁專用樣式 |
| `Web/scripts/overview_fullcalendar.js`（33KB） | FullCalendar 初始化與所有互動邏輯 |
| `Web/ajax/overview_fullcalendar_events.php`（177 bytes） | JSON 事件來源入口 |
| `Pages/Ajax/OverviewFullCalendarEventsPage.php`（1.5KB） | 解析 FullCalendar 帶的 `start`/`end` 參數 |
| `Presenters/OverviewFullCalendarEventsPresenter.php`（7.6KB） | 組出 FullCalendar Event Object，含權限過濾／隱私過濾 |
| `tests/Presenters/OverviewFullCalendarEventsPresenterTest.php`（7.3KB，6 案例） | 權限過濾、admin 略過檢查、隱私標題替換、空標題預設值、事件欄位完整性（確認不含 `top`/`height`/`left`/`width`，證明版面完全交給 FullCalendar 自己算） |

原型第一版驗收：PHPUnit 全數通過、未登入請求正確 302 導回登入頁（跟 `overview.php` 行為一致）、`git status` 確認除了上面這批全新檔案外，既有的 5 個修改檔案（Section 15 那批）內容完全沒被動到。

順手做對一件現有系統沒做的事：**`OverviewFullCalendarEventsPresenter` 會呼叫 `lib/Application/Reservation/PrivacyFilter.php` 的 `CanViewDetails()`，尊重 `privacy.hide.reservation.details` 設定**（沒權限看細節的人，標題會換成「忙碌」）。現有的 `overview.php`／`OverviewPresenter::BuildDays()` 從來沒做這個檢查，是既有系統的一個隱私缺口——這次刻意沒去動 `overview.php` 修它（不在這次授權範圍內），但新原型有正確做對。

### 16.2 從「評估原型」升級為「主要行事曆」

使用者實際比較過後決定：**以後都以 `overview_fullcalendar.php` 為主**。改動 `Pages/Pages.php` 這張「頁面 ID 對應表」：

```php
public const OVERVIEW_FULLCALENDAR = 'overview_fullcalendar.php';  // 新增常數
public const DEFAULT_LOGIN = 'overview_fullcalendar.php';           // 原本是 overview.php
private static $_pages = [
    1 => ['url' => Pages::OVERVIEW_FULLCALENDAR, 'name' => 'Overview'],  // 原本是 Pages::OVERVIEW
    ...
];
```

因為 `Pages::UrlFromId(1)` 這個查表函式同時被三個地方呼叫（`Pages/Page.php` 的 `$HomeUrl`／側邊欄 Logo 連結、`Presenters/Authentication/LoginRedirector.php` 的登入後導向、`Pages/Page.php::SanitizeRedirectUrl()` 的導向失敗回退頁），只改這一張表就讓「點 Logo」「登入後」「導向驗證失敗回退」三個入口同時生效，不用逐一去改各自的呼叫點。

`Pages::OVERVIEW`（純字串常數 `'overview.php'`）本身沒有改，`overview.tpl` 內部自己的上一週/下一週連結都是直接引用這個常數，維持指向自己，所以 `overview.php` 這條路徑依然完整可以獨立運作，只是不再是預設入口。

### 16.3 真正的功能性 bug（不是快取，是程式邏輯錯誤）

這次過程中兩度出現「會議資訊完全消失」的回報，兩次的根因都不是快取，是真的程式碼寫錯：

**Bug A：`require_once` 少帶一個依賴，每次請求都 fatal error**

`Presenters/OverviewFullCalendarEventsPresenter.php` 一開始只 `require_once('lib/Application/Reservation/PrivacyFilter.php')`，但 `PrivacyFilter::CanViewDetails()` 內部依賴同目錄下的 `ReservationDetailsFilter` 類別，而這支類別沒有被單獨載入——正確作法是 `require_once` 整個 `lib/Application/Reservation/namespace.php`（這個 namespace 檔案會把 `PrivacyFilter.php` 跟 `ReservationDetailsFilter.php` 一起載入）。缺這一行的後果是**每一次真實 HTTP 請求都會 fatal error**（`Class "ReservationDetailsFilter" not found`），被框架的例外處理機制吃掉、前端只看到空白的行事曆。用單元測試完全測不出來，因為 `tests/fakes/FakePrivacyFilter.php` 是假物件，不會真的去呼叫這個依賴鏈。

診斷方式：寫一支唯讀診斷腳本，透過 `docker compose exec app php -r "..."` 直接呼叫 `OverviewFullCalendarEventsPresenter::GetEvents()`（繞過 HTTP／session，直接對真實資料庫跑），才看到完整的例外堆疊。

**Bug B：呼叫了語意完全不對的權限檢查方法**

側欄資源清單跟事件過濾都呼叫了 `PermissionService::CanViewResource()`，方法名稱聽起來像是「這個使用者能不能看這個資源」，但實際實作是 `$this->_store->GetViewOnlyResources($user->UserId)`——只檢查一組叫「view-only」的**小眾**權限清單，跟一般使用者透過群組拿到的「完整/可預約」權限（`GetAllResources()`）是完全不同的兩組資料。結果是：admin 帳號因為有 `IsAdmin` 短路直接跳過檢查，完全沒事；但一個透過「全體員工」群組（見 Section 12）拿到全部 12 個資源完整權限的一般帳號（測試帳號 `shawn`，`user_id=3`），呼叫 `CanViewResource()` 對這 12 個資源全部回傳 `false`——側邊欄資源清單、行事曆事件全部因此被濾成空的。

用真實帳號直接驗證：

```
CanViewResource(shawn, 每個資源)   → 12 個全部 false
CanAccessResource(shawn, 每個資源) → 12 個全部 true   ← 應該用這個
```

修法：`OverviewFullCalendarPresenter.php`、`OverviewFullCalendarEventsPresenter.php` 兩處都改成呼叫 `CanAccessResource()`。順手把 `tests/fakes/FakePermissionService.php` 也修正——原本的單元測試設定的是 `_CanViewResource`（一個假物件屬性），但因為生產程式碼已經改成呼叫 `CanAccessResource()`，舊測試其實是在驗證一個不會被呼叫到的方法，等於沒在測任何東西。新增 `_CanAccessResource` 屬性（沿用跟 `_CanViewResource` 一樣「可傳陣列依 resource id 對應、也可傳單一布林值」的方便寫法，`null` 時退回原本既有測試依賴的循序 `ReturnValues` 行為，不影響其他測試檔案），把 `tests/Presenters/OverviewFullCalendarEventsPresenterTest.php` 的 6 個案例全部改用新屬性，重新驗證仍然全過。

### 16.4 快取地雷：兩層快取都要清，只重啟容器不夠

這個專案疊了兩層快取，只清一層不會生效：

1. **PHP opcache**（`validate_timestamps=0`，Section 9 為了效能關掉的）：改完 PHP 檔案後，網頁請求（Apache/php-fpm）不會自動偵測到變動，但**CLI 執行不受影響**（`docker compose exec app php -r "..."` 每次都是全新行程，不共用 opcache）——這造成過程中好幾次「CLI 診斷腳本測試通過，但使用者在瀏覽器上還是看到舊行為」的誤判，因為忘記在確認 CLI 測試通過後，再對著**網頁请求**重啟一次容器（`docker compose restart app`）才會生效。
2. **Smarty 樣板編譯快取**（`tpl_c/` 目錄）：改完 `.tpl` 檔案後，如果 Smarty 的 `compile_check` 沒開（跟 `cache.templates` 設定連動），會繼續用舊版編譯結果，不會自動比對原始檔案的修改時間重新編譯。有一次新增的工具列元素（今天按鈕、分類切換 tab）完全沒有出現在畫面上，肉眼看起來像「改動沒生效」，實際上是 `tpl_c/` 底下留著一份修改前的編譯檔案（`ls -la` 確認時間戳記比最新一次編輯還早）。修法：`rm -f tpl_c/*overview_fullcalendar*` 清掉對應的編譯快取。

之後每一輪改完 PHP／`.tpl` 相關檔案，固定流程是：清 `tpl_c/` 對應快取 → `docker compose restart app` → 重新用診斷腳本 / PHPUnit 驗證，這個順序缺一步都可能讓下一次回報看起來像是「修了但沒生效」。

### 16.5 視覺精修過程中的具體 bug 與修法

| 問題 | 根因 | 修法 |
|---|---|---|
| 主日曆圓角外框看起來不連續，好像疊了兩層邊框 | FullCalendar 自己的樣式表在最外層的 `.fc-scrollgrid` 上又畫了一層方角的 `border:1px solid var(--fc-border-color)`，跟外層 `.fc-frame` 自己的 16px 圓角框重疊，方角在圓角轉角處露出來 | `#fullcalendarRoot .fc-scrollgrid { border:0; }`，讓 `.fc-frame` 的外框是唯一一層邊框 |
| 表頭跟下方日期欄的分隔線沒有對齊 | 同一個 `.fc-scrollgrid` 雙層邊框問題連帶造成的視覺錯位 | 同上 |
| 工具列文字/按鈕/圖示沒有垂直對齊 | Bootstrap Icons 字型本身的內部度量（ascent/descent）不一定精準置中在文字行高上；`<h1>` 裡的徽章 `<span>` 用 inline 排版，行高會被撐大 | 幫工具列所有可互動元素統一 `line-height:1`；`<h1>` 本身改成 `display:flex; align-items:center`，讓徽章跟文字用 flex 對齊而不是依賴字型行高 |
| Logo 跟「總覽」標題文字沒有對齊 | `#main` 有 Bootstrap 的 `my-3`（16px 上邊距），但共用側邊欄的品牌列是貼齊視窗最上緣、沒有這個邊距，兩排的起始高度差了 16px | `#page-overview-fc` 加 `margin-top:-16px` 把這 16px 抵消掉，讓工具列（64px）也貼齊 y=0，兩排都用同一個 `--gcal-control-height` 高度 flex 置中，內容自然對齊 |
| 月視圖裡，今天以前的每一個日期整格被塗成很重的灰色，過去的日期也是 | 開了 `selectable:true` 之後，FullCalendar 會把過去日期標記為不可拖曳選取，這個狀態預設有一層灰底樣式。**中間繞了一大圈**：一開始以為是 `.fc-day-disabled` 這個 class（FullCalendar 文件／原始碼字面上看起來像是這個），改了三輪都沒用；最後直接把頁面用真實資料在瀏覽器裡跑起來，用 `getComputedStyle()` 對著真正變灰的元素查，才發現正確的 class 其實是 **`.fc-day-past`**，`.fc-day-disabled` 在這個情境下根本從未被套用過 | `#fullcalendarRoot .fc-day-past { background:transparent !important; }`；週末的淺灰底色規則（`.fc-day-sat`/`.fc-day-sun`）維持在後面、也加 `!important`，讓「過去的週末」還是顯示週末底色而不是被這條規則蓋成全透明 |
| 月視圖事件跟週/日視圖的顏色風格差很多（月視圖是純文字加一個小圓點，週/日視圖是有底色的色塊） | FullCalendar 月視圖對「有時間」的事件預設用 `eventDisplay:'auto'`，只顯示一個小圓點+文字，不會把 `backgroundColor`/`borderColor` 畫成色塊 | 加 `eventDisplay:'block'` 選項，強制三個視圖都用同一套色塊渲染；`eventContent` 統一輸出同一組 `.evc`/`.tt`/`.rr` 結構（月視圖省略時間那一行，因為格子太矮放不下），確保顏色跟 CSS class 完全共用同一套規則 |
| 月視圖日期數字顯示「27日」而不是單純的「27」，且沒有置中，字體大小也跟週視圖不一致 | FullCalendar 用 zh-tw 語系內建的日期格式化，會自動加「日」字尾；預設排版靠右對齊 | 新增 `dayCellContent` callback，自己回傳 `arg.date.getUTCDate()` 純數字（不透過語系格式化）；`.fc-daygrid-day-top` 改成 `justify-content:center`，字體大小/字重/tabular-nums 比照週視圖表頭的 `.num` 規則 |
| 月視圖標題顯示「2026年6月－8月」這種跨三個月的怪異範圍 | `datesSet` callback 原本用 `info.start`/`info.end`（月視圖為了湊滿 6×7 的方格，畫面上實際渲染的日期範圍會往前後月份延伸），拿這個範圍去算跨月標題，被延伸出去的天數誤判成「這個月橫跨好幾個月」 | 月視圖改用 `info.view.currentStart`/`info.view.currentEnd`（FullCalendar 提供的「邏輯月份範圍」，不含補滿格線用的延伸天數）；週/日視圖維持用實際渲染範圍不變（一週橫跨兩個月時，本來就應該顯示兩個月） |
| 事件很多的日期，月視圖那一列會被撐得很高，跟其他列高度不一致；且捲軸一直顯示著 | 一開始用 `overflow-y:auto` 讓超出的事件在格子內捲動，但捲軸持續可見 | `.fc-daygrid-day-events` 設固定 `max-height:132px`；預設 `overflow-y:hidden`（超出的先收起來，不能捲），`:hover` 時才切成 `overflow-y:auto`，滑鼠移過去才會出現可捲動的捲軸 |
| 日/週視圖右上角有一組奇怪的、疊在一起的小小上下箭頭 | FullCalendar 為了讓表頭那排（不需要捲動）跟下方時間格（真的需要捲動）維持同樣寬度，故意把表頭的捲動容器也設成 `overflow-y:scroll`（即使沒有任何內容要捲）——這種「有捲軸軌道但沒有內容可捲」的情況，Windows 上的 Chrome/Edge 會把捲軸畫成只剩頭尾兩顆箭頭按鈕疊在一起，中間沒有滑塊 | `#fullcalendarRoot .fc-scroller::-webkit-scrollbar-button { display:none; }`，只隱藏箭頭按鈕本體，捲軸滑塊跟滑鼠滾輪/觸控板捲動都不受影響 |

### 16.6 新增預約功能搬遷（比照 `overview.php` 的互動水準）

原型第一版是唯讀的（Phase 1 明確排除拖曳建立、彈窗等互動），使用者決定升級為主要行事曆後，要求把 `overview.php` 那一整套「同畫面建立預約」體驗完整搬過來：

- **拖曳選取時段**：改用 FullCalendar 原生 `selectable:true` + `select` callback，不是重新刻一套滑鼠座標運算——FullCalendar 自己的 `.fc-highlight` 提供拖曳視覺回饋，`slotDuration`（30 分鐘）本身就會自動吸附刻度，不需要另外算。月視圖的 `select` 選到的是整天而非時段，預設用 09:00–09:30 開表單，時間可以在表單裡再調整
- **同畫面小表單**：`#quickCreateModal` 整個 HTML／CSS 從 `overview.tpl`／`overview.css` 複製過來（因為那兩支檔案不能動，只能複製而不是共用），JS 邏輯（`TimeDropdown` 下拉元件、`openQuickCreate()`、`refreshTimeUI()`、`refreshResourceUI()`）幾乎逐行搬過來，只把 `window.overviewOpts` 換成這支檔案自己的 `window.overviewFullCalendarOpts`。存檔一樣打 `ajax/reservation_save.php`，欄位名稱／CSRF token 完全比照原本的做法
- **跟 `overview.php` 不同的地方**：存檔成功後不整頁重新整理（`overview.php` 原本用 `window.location.reload()`），改叫 `calendar.refetchEvents()` 局部刷新——因為這個頁面本來就有一支獨立的 JSON 事件來源可以重打，沒必要做全頁重整這種比較重的操作
- **點擊既有預約**：原本 `eventClick` 是直接整頁跳轉去 `reservation.php`，改成跳出 `#eventPopover` 小卡片（標題／時間／資源 + 查看詳情連結），比照 `overview.php` 的既有互動，一樣只攔截單純的左鍵點擊（放過 Ctrl/Cmd/Shift 等修飾鍵點擊，讓瀏覽器原生的「開新分頁」等行為不受影響）
- **側邊欄「+新增預約」按鈕**：點了會用目前分類（實體/視訊）下第一個勾選的資源，開啟同一個小表單，預設時段是下一個整點/半點

### 16.7 分類切換 + 視圖切換

- **實體會議室／視訊會議室分類 tab**：比照 `overview.php` 的 `.cal-category-tabs` 行為——切換分類同時決定側邊欄顯示哪一組資源清單、以及行事曆只顯示該分類資源的事件。資料來源是 `window.overviewFullCalendarOpts.resourceGroups`，樣板端輸出的 JSON 結構跟 `overview.tpl` 的 `window.overviewOpts.resourceGroups` 完全一致
- **日／週／月視圖切換**：`overview.php` 完全沒有這個功能，這次是新增的。工具列加了三個切換鈕，呼叫 FullCalendar 原生的 `calendar.changeView('timeGridDay' / 'timeGridWeek' / 'dayGridMonth')`

### 16.8 尚未完成／後續建議

- 月視圖目前不支援拖曳跨天新增預約（月視圖選到的是整天，套用時段表單語意上怪怪的，先留給之後有需要再做）
- 沒有 hover 預覽格子（`overview.php` 有一個「滑鼠移到空白格子上顯示範圍預覽」的效果，這次沒有搬）
- 沒有做同畫面即時預覽下一步的日/週視圖切換動畫或載入態
- `overview.php` 本身仍然完整保留、可獨立訪問，尚未決定是否/何時要整支移除
- 產出這份文件當下，`Web/img/logo.png`（2020 年上游遺留、從未被 git 追蹤的舊 placeholder 圖檔）仍原封不動留在檔案系統上，跟這次改動無關，沒有一併處理

---

## 17. 側邊欄品牌列（Logo）在資源清單較長時會被壓扁

上線後回報：側邊欄 Logo 有時候會變小，且看起來每個頁面不太一致。追查過程繞了幾輪彎路（一度以為是圖片本身的 `max-height` 設定問題、一度以為是瀏覽器快取、一度以為是縮放比例），最後由使用者自己用 DevTools 量出關鍵數字才定位到真正原因：`.sidebar-brand`（Logo 所在的品牌列）在正常頁面高度是 `64px`，但在側邊欄資源清單較長的頁面（例如 FullCalendar 總覽頁，清單有 12 個資源 + 迷你月曆）會被壓縮到只剩 `28.73px`。

**根因**：`.app-sidebar` 是一個縱向（`flex-direction:column`）、固定 `height:100vh` 的 flex 容器，原本設定 `overflow-y:auto` 讓自己整個捲動；`.sidebar-brand` 沒有明確設定 `flex-shrink:0`，預設值是 `1`（允許縮小）。當側邊欄裡的導覽/資源清單內容總高度超過 `100vh` 時，瀏覽器的 flex 排版演算法會先嘗試「縮小」有 `flex-shrink` 的項目來塞進固定高度，而不是直接讓內容溢出捲動——結果就是品牌列被當成犧牲品縮小，資源越多的頁面縮得越嚴重。

**修法**（`Web/css/coasia-theme.css`）：
- `.sidebar-brand` 改成 `flex: 0 0 64px; min-height: 64px;`，明確禁止縮小
- 導覽區域 `#librebooking-navigation.sidebar-nav-collapse` 改成 `flex: 1 1 auto; min-height: 0; overflow-y: auto;`，讓「需要捲動」這件事改由這個區塊自己承擔，而不是拖累整個側邊欄
- `.app-sidebar` 本身的 `overflow-y:auto` 改成 `overflow:hidden`（它自己不再需要捲動），原本掛在它身上的自訂捲軸樣式（隱藏軌道、hover 才出現滑塊）比照搬到 `#librebooking-navigation.sidebar-nav-collapse` 上，捲動責任轉移到哪裡，捲軸的視覺效果就要跟到哪裡
- 手機版斷點（`max-width:991.98px`，側邊欄改 `height:auto` 整頁捲動）不受影響，因為那個情境下容器沒有固定高度，不會觸發這個壓縮行為

**驗證**：刻意把瀏覽器視窗壓到 `1920×700`、側邊欄塞滿全部 12 個資源重現問題（修正前 `.sidebar-brand` 量到 28.73px），修正後同樣條件下量測：導覽區 `scrollHeight`（982px）大於 `clientHeight`（636px），證實內容確實超出、正確觸發內部捲動；`.sidebar-brand` 全程維持 `64px`、Logo 維持 `28px`，不再受影響。

**順手一併修掉的相關小問題**：Logo 圖片本身（`<img class="logo">`）先前只設定 `max-height:28px`，沒有固定 `width`／`flex-shrink:0`，在同一個排版壓力下也可能被連帶壓扁；改成明確的 `height:28px; width:auto; flex-shrink:0;`，跟外層連結 `<a>` 一起排除在縮放範圍外。

---

## 18. FullCalendar 原型三項細節：重複捲軸、迷你月曆選取週樣式、現在時間線圓點位置

- **表頭多一條沒用的捲軸**：FullCalendar 為了讓不會捲動的日期表頭跟真正會捲動的時間格欄寬保持一致，刻意在表頭也放了一個 `overflow-y:scroll` 的捲動容器，Windows 上會多畫出一條視覺上多餘的捲軸。只隱藏這個容器的捲軸視覺（`scrollbar-width:none` + `::-webkit-scrollbar{display:none}`），保留它原本的 `overflow-y:scroll` 行為不動，避免破壞 FullCalendar 自己用來對齊欄寬的機制——實測表頭跟下方日期欄的左右邊界座標完全一致，沒有跑版。
- **迷你月曆「本週」標示改成連續色塊**：原本每天都是各自獨立的圓形，改成整週中間幾天用小圓角矩形相連（`border-radius:4px`），只有週一（`:nth-child(7n+1)`）、週日（`:nth-child(7n)`）保留圓角收尾，視覺上讀起來像一條連續的淡色長條而不是 7 個分開的點。若週一剛好是今天，會維持今天原本的實心圓形徽章（渲染邏輯裡 `today` 跟 `in-week` 是互斥的 class，不會衝突）。
- **現在時間線的圓點位置**：這項來回修了三次才對：第一次只加 `margin-left:3px`，太小，量出來圓點其實整個落在時間欄範圍裡面（偏差 33px，不是切到邊緣的誤差）；第二次改成量出來的 `margin-left:39px`，結果圓點的直接父層容器（`.fc-timegrid-now-indicator-container`，寬度精準對齊時間欄、且 `overflow:hidden`）把移出範圍的圓點直接裁掉，整個消失不見；第三次改用容器右邊界定位（`right:-3px; left:auto;`，圓點半徑 3px），因為那個容器的右邊界本來就精準對齊時間欄／日期欄的分界線（紅線的起點也是同一條界線），不管時間欄實際寬度是多少都會自動對齊，不再是猜出來的固定像素值。連帶把容器的 `overflow:hidden` 改成 `visible`，避免圓點的視覺半徑超出容器邊界被裁切。

---

## 19. LINE 綁定：修正真正卡住的原因 + 一支手機測多帳號會產生殭屍綁定的 bug

### 之前一直測不成功的真正原因

Section 13 當時懷疑是 LINE 官方帳號後台的「聊天模式」設定攔截了訊息、沒有轉發到 webhook，一直沒有解開。這次回頭排查，發現**根本不是那個設定的問題**：本機測試用的 Cloudflare Quick Tunnel（`cloudflared tunnel --url http://localhost:8080`）網址早就失效了——這種免費、匿名的通道每次重新啟動都會換一個全新的隨機網址，只要開著的終端機被關掉或電腦重開機/睡眠過，舊網址就會直接 DNS 解析失敗。用 `curl` 直接測就能確認（回應是「無法解析主機」，不是逾時或拒絕連線）。

換一個新的 tunnel 網址、更新回 LINE 官方帳號後台的 Webhook 設定並重新驗證後：
- Docker 的存取紀錄直接看到 `POST /Web/line-webhook.php` 從 `LineBotWebhook/2.0` 這個 user agent 進來，回應 200
- 資料庫確認 `LineUserId` 欄位真的被寫入了 LINE 使用者 ID、`LineLinkCode` 也正確被清空
- 實際建一筆預約，也確認收到 LINE 推播通知

這是這個功能第一次真正端對端驗證成功。

**给未來的提醒**：Cloudflare Quick Tunnel 只適合臨時測試，正式環境需要一個固定、不會斷線的網址（見 `COASIA-GOLIVE-CHECKLIST.md`），不能繼續依賴這種免帳號的臨時通道。

### 順手修掉的 bug：一支手機測多個帳號會產生殭屍綁定

在實際測試「換帳號重新綁定」的過程中發現：`Presenters/Line/LineWebhookPresenter.php` 原本只會把收到的綁定碼對應到「哪個帳號」，然後把那個帳號的 `LineUserId` 設成傳訊息的人——但完全沒檢查「這個 LINE 身份是不是已經綁在別的系統帳號上」。如果同一支手機（同一個 LINE 身份）先綁了帳號 A、後來又綁了帳號 B，帳號 A 會留下一個看起來還綁著、但那個 LINE 身份早就跑去帳號 B 了的殭屍狀態——帳號 A 的畫面上完全看不出來，通知只會靜靜地收不到。

**修法**：綁定新帳號之前，先用 `LoadByPreference(LINE_USER_ID, $lineUserId)` 查一次這個 LINE 身份目前是不是已經綁在別的帳號上，如果是，就先把舊帳號的 `LineUserId` 清空，再綁到新帳號——後綁的算數，不會再留下殭屍綁定。

新增了 2 個測試案例（`tests/Presenters/Line/LineWebhookPresenterTest.php`）：換帳號綁定會正確解除舊的、同一帳號重複綁定不會誤觸發解綁。同時把 `tests/fakes/FakeUserRepository.php` 的 `LoadByPreference()` 加強成可以依 `(name, value)` 精確配對回傳不同的假使用者（原本只能回傳同一個，測不出「兩次查詢要回傳不同結果」這種情境），並把 `Update()` 改成同時記錄所有被更新過的使用者（`_UpdatedUsers[]`），不再只留最後一次。全部測試（1677，含新增的）全過。

---

## 20. 側邊欄單選會議室、「只看我的」預約範圍、事件卡片開始支援編輯／刪除

這次是使用者實際以「一般員工」角度操作系統後，一連串回饋累積出來的一批修改，最後收斂成一次比較大的介面升級。

### 20.1 側邊欄會議室清單從多選改成單選

使用者開會後回饋：畫面目前是「全部會議室打勾、疊在同一週檢視上、用顏色區分」的 Google Calendar 風格，但實際使用情境是「點哪一間會議室，就只顯示那一間」。改法：

- `Web/scripts/overview_fullcalendar.js` 新增 `initSingleSelectResources()`／`selectSingleResource()`，讓側邊欄 checkbox 在**同一個資源分類（實體／視訊）內**維持「永遠剛好一個被勾選」——勾其中一個會自動取消其他個，想取消唯一勾選的那個會被擋下來（永遠留一個）。底層 `<input type="checkbox">` 完全沒動，因為這個標記／樣式跟 `overview.php` 共用（那邊仍然要維持真正的多選疊圖），所以排他邏輯只在這個頁面自己的 JS 裡處理，沒有動共用樣板。
- 視覺上原本是「方塊 checkbox + 打勾符號」，改成「固定顯示的圓形色點（純粹當顏色圖例）+ 選中列反白、文字變粗體主色」，跟側邊欄其他導覽項目「目前選中」的視覺語言（`.sidebar-sublink.active`）一致，不再是另外發明一套勾選符號。

### 20.2 兩個真實回歸 bug（都是使用者自己抓出根因的）

- **點擊靠下方的會議室（例如高爾夫球室），側邊欄整個跑版、行事曆時間軸重設到 00:00**：真正根因是 `.res-check input` 用 `position:absolute` 但 `width:0; height:0`，導致這個可以取得焦點的隱藏 checkbox 縮成一個 0 大小的點；瀏覽器為了讓取得焦點的元素進入可視範圍，會捲動 `.app-sidebar`，看起來就像側欄上方的 Logo/Admin/新增預約整組消失。修法：`.res-check` 補上 `position:relative`，`.res-check input` 改成 `inset:0; width:100%; height:100%`，讓隱藏的 input 覆蓋整列而不是縮成一個點。同時 FullCalendar 選項補上 `scrollTimeReset:false`（原本 `refetchEvents()` 會把時間軸捲動位置重設回預設的 08:00 附近，`scrollTimeReset:false` 讓它保留使用者原本捲到的位置）。
- **選中會議室後名字變粗體，長名字的會議室（「高爾夫球室 Golf Room」）會把側欄整個撐寬**：`.res-check-name` 有 `overflow:hidden` + `text-overflow:ellipsis`，但沒有 `min-width:0`——flex 子元素預設的 `min-width:auto` 會用文字完整展開的寬度當底線，導致 ellipsis 從來沒真的生效過；平常字重 400 時剛好卡在邊界勉強擠得下，選中後變粗體字（500）才真的撐爆。補上 `min-width:0` 後恢復正常截斷。

### 20.3 新增「我的會議」→ 後來拆成兩個獨立維度：預約範圍 × 資源類型

一開始的做法是把「我的會議」當成第三個分類分頁，跟「實體會議室／視訊會議」放在同一組互斥按鈕裡；使用者馬上指出這是錯的：**資源類型跟預約範圍是兩個不同維度，不該擠在同一組分頁**。改成兩組完全獨立的控制（外加原本就有的檢視方式 日／週／月），三組在工具列上用比較大的間距（8px→20px）隔開，讓使用者一眼看出是三個維度：

```
預約範圍：[全體｜只看我的]
資源類型：[實體會議室｜視訊會議]
檢視方式：[日｜週｜月]
```

JS 狀態對應拆成 `activeCalCategory`（只會是 `'physical'`/`'video'`）跟獨立的 `var mineOnly = false;`，兩者互不影響——切換「只看我的」不會動到目前選的資源分類、勾選的會議室、日期、檢視方式或捲動位置。抓事件時 `mine=1` 只決定要不要加這個查詢參數，資源分類＋勾選會議室的過濾**永遠都套用**，不會因為切到「只看我的」就整個繞過。

後端：`OverviewFullCalendarEventsPresenter::GetEvents()` 新增 `$mineOnly` 參數，開啟時改用跟既有「我的會議」獨立頁面（`MyMeetingsPresenter`）完全相同的查詢方式——`GetReservations(..., $userSession->UserId, ReservationUserLevel::ALL, ...)`。追進 SQL 層（`lib/Database/Commands/Queries.php` 的 `GET_RESERVATION_LIST`）確認 `ReservationUserLevel::ALL`（值 0）真的會讓「我建立的＋我是participant＋我是invitee」都撈到，不是只有建立人——「只看我的」這個標籤名稱是準確的。同時 `$mineOnly` 模式刻意跳過資源可視權限過濾，因為是自己的預約，就算之後對該資源的權限被收回也該看得到。

### 20.4 事件卡片點不開的 bug

使用者發現：`overview_fullcalendar.php` 頁面完全無法點開已存在的預約。透過 Console 逐步排查（`document.getElementById('eventPopover').outerHTML` 前後對照）確認 `eventClick` 有正常執行、資料也完全正確、`show` class 也確實被加上去了——問題出在 `tpl/Overview/overview_fullcalendar.tpl` 裡 `#eventPopover` 一開始就帶有 Bootstrap 的 `d-none`（`display:none !important`），JS 只負責加 `show`，從未移除 `d-none`，`.event-popover.show` 的 `opacity/visibility` 規則完全無法覆蓋過 `!important` 的 `display:none`。修法：直接移除模板上多餘的 `d-none`，讓 `.event-popover` 自己的 `opacity:0; visibility:hidden` 初始狀態接手。

### 20.5 事件卡片支援編輯／刪除（Stage 1）

使用者指出：新版行事曆完全沒有修改／刪除既有預約的路徑，只有新增能用。查證後確認：底層能力（`ReservationAuthorization::CanEdit()`、`Web/ajax/reservation_delete.php` 等）都還在、也還能正常運作，缺的是新版 UI 完全沒接上去。

- **後端**：`OverviewFullCalendarEventsPresenter::GetEvents()` 新增 `IReservationAuthorization` 依賴，透過既有 `ReservationAuthorization::CanEdit()`（不是猜 `ownerId === currentUserId`）計算每筆事件的 `canEdit`/`canDelete`；每個 reference number 記憶化呼叫一次 `GetReservationForEditing()`，避免同一預約橫跨多資源時重複查詢。關鍵防護：`canEdit = canViewDetails && CanEdit(...)`——就算授權服務判定可編輯，只要隱私規則不讓你看到真實標題（例如只看得到「忙碌」），編輯／刪除一律不給；同一邏輯也套用在新增的 `ownerName` 欄位上（看得到「是誰訂的」但看不到訂了什麼，一樣算洩漏）。
- **前端**：事件卡片 popover 依 `canEdit`/`canDelete` 顯示或隱藏編輯／刪除；刪除走新增的確認 modal，單次預約簡單確認、重複預約要選範圍（僅這一次／這一次及之後／整個系列，對應既有 `SeriesUpdateScope`），送出打既有 `ajax/reservation_delete.php`。
- **踩到的坑**：一開始刪除送到 `?response_type=json`，實際上這個系統判斷要不要回傳乾淨 JSON 靠的常數是 `QueryStringKeys::RESPONSE_TYPE`，真實值是 `'rs'`——參數名稱猜錯，導致後端悄悄 fallback 成 HTML 片段回應，刪除本身在資料庫層是成功的，但前端解析失敗顯示假的「Delete failed.」，使用者因此重試好幾次、對已刪除的預約再次刪除還觸發了一個 500 錯誤（`ApplyChangesTo() on null`）。改成 `?{QueryStringKeys::RESPONSE_TYPE}=json` 後正常。同時補上刪除送出中防止重複點擊。
- 新增 6 個 PHPUnit 測試（`canEdit` 反映真實授權而非 owner 判斷、privacy 優先於授權、`isRecurring`／`ownerName` 正確性、「只看我的」不會讓 participant/invitee 拿到編輯權），另外補上一個被遺漏在 `tests/fakes/namespace.php` 裡從未真正接上的 `FakeReservationAuthorization`。

### 20.6 事件卡片與刪除對話框視覺改版（Google Calendar / Material 3 風格）

Stage 1 做完後使用者指出：編輯/刪除的視覺跟主行事曆的 Google Calendar 風格不一致——popover 底部塞了三個文字按鈕看起來像傳統後台，刪除對話框是標準 Bootstrap header/footer + 三顆紅色按鈕。改版：

- **事件卡片**：右上角改成三個 36×36px 圓形 icon button（編輯／刪除／關閉），預設透明背景、hover 淡灰圓底，刪除只有 hover 才轉紅（平時中性灰色）；「查看詳情」改成卡片底部的純文字連結（帶箭頭圖示），不再是主要按鈕；新增「預約人」列。
- **刪除對話框**：拿掉 Bootstrap 標準 `.modal-header`/`.modal-footer`/`.btn-outline-danger`，改成置中垃圾桶圖示＋標題＋「刪除後參與者會收到取消通知」提示文字；重複預約的範圍選擇從三顆並排紅色按鈕改成 radio list，整個對話框只留一顆實心紅色按鈕（確認鍵）；送出中顯示 spinner 並停用按鈕。
- 在 `#page-overview-fc` 新增一組共用 design token（`--surface`/`--surface-muted`/`--border`/`--text-primary`/`--text-secondary`/`--danger`/`--dialog-radius`/`--control-radius`/`--icon-button-size`），`--primary` 等既有全站變數則直接沿用 `coasia-theme.css` 的定義，沒有另外定義一份數值不同、會造成分裂的顏色。

### 尚未完成：同頁快速編輯（Stage 2）

使用者要求編輯也要能不離開行事曆頁面、用跟快速新增同一顆 modal（`mode=create`/`mode=edit`）完成基本欄位修改，進階內容（重複規則、參與者、提醒等）交給「更多選項」連去舊表單。這部分還沒做——先查證了資料庫，確認目前真的有預約在用參與者（2 筆）、提醒（4 筆）、自訂欄位（`Test Number`/`Test String`，各 21 筆）這幾個「快速編輯 UI 不會露出、但絕對不能被清空」的欄位，附件/訪客邀請/配件則整個系統停用或完全沒有資源掛勾，可以放心跳過。下一步要先做一個「讀取完整預約資料」的後端端點，把這些沒露出的欄位原封不動地帶回去，再接上共用 modal，避免快速編輯把使用者既有的參與者/提醒/自訂欄位資料洗掉。

## 21. 預約 Drawer（取代「更多選項」跳轉舊表單）+ 資料安全稽核

使用者明確表示不接受「更多選項」跳到舊版 `reservation.php` 作為新增/編輯的正式流程，要求做一個完整的 Drawer（側邊面板），涵蓋新增、編輯、刪除。這一輪只做 Phase 1-3：Drawer 外殼與開關、基本欄位新增/編輯、刪除與重複預約範圍整合。

- **新端點（只處理「基本欄位」，刻意不是整個表單的 JSON 版本）**：`Presenters/OverviewFullCalendarReservationPresenter.php`（讀取，`Web/ajax/overview_fullcalendar_reservation.php`）與 `Presenters/OverviewFullCalendarReservationUpdatePresenter.php`（更新基本欄位，`Web/ajax/overview_fullcalendar_reservation_update.php`）。更新端點刻意不是 `Pages/Ajax/ReservationUpdatePage.php` 的簡化版——舊表單邏輯是「整份表單重新提交，沒帶到的欄位視同清空」，Drawer 目前只露出標題/說明/時間/資源，若走舊表單邏輯會把參與者、提醒、自訂欄位等使用者看不到也沒填的欄位直接洗掉。改法：後端先讀出完整既有預約（`IUpdateReservationPersistenceService::LoadByReferenceNumber()`），只呼叫 `Update()`（標題/說明/資源，owner 保持不變）與 `UpdateDuration()`，其餘欄位的 mutator 完全不呼叫，資料原封不動留著。
- 新增走既有 `ajax/reservation_save.php`（新預約沒有「既有資料會被洗掉」的風險），刪除走既有 `ajax/reservation_delete.php`，都重用同一套後端驗證/通知/授權，沒有另外做一套。

### 21.1 使用者主動抓到的 P0 資料安全漏洞：編輯會悄悄把 owner 換成操作者

使用者以審查者角度重新檢視 Drawer，直接讀 `Domain/ExistingReservationSeries.php::Update()` 原始碼後指出：`Update()` 的第一個參數會變成序列的新 `UserId()`，若跟目前不同就觸發 `OwnerChangedEvent`。原本的實作把「目前登入使用者」傳進這個參數——代表任何有編輯權限的人（例如管理員）修改別人的預約，都會把該預約的擁有者悄悄換成自己。

修法：新增的更新端點一律傳 `$existingSeries->UserId()`（讀出來的、真正的原 owner），不是操作者的 session id；伺服器端獨立重新檢查 `IReservationAuthorization::CanEdit()`，不信任前端算好的 `canEdit` 旗標。此修法連同一輪的完整 round-trip 測試（含真實 DB 建立→編輯→讀回→刪除→確認真的消失）已完成並驗證。

## 22. P0：預約資料庫併發安全（resource lock + optimistic version locking + idempotency）

使用者指定範圍：這一輪只做「多人同時操作同一筆/同一資源預約時的資料庫併發安全」，不做其他 UI 或進階功能，並且明確要求——**在真實雙 connection 測試、version locking、idempotency 全部完成以前，不能宣稱系統已經避免重複預約與互相覆蓋**。以下是全部完成後的驗收報告，格式依使用者指定的 8 項整理。

### 22.0 起點稽核（實作前）

直接讀 `Domain/Access/ReservationRepository.php`、`lib/Database/Database.php`、`lib/Database/MySQL/MySqlConnection.php` 確認：這個專案原本**完全没有**任何 transaction／row lock 機制——`Database::Query()`/`Execute()`/`ExecuteInsert()` 每一次呼叫都各自獨立 `Connect()`→執行→`Disconnect()`，代表就算硬寫兩個 SQL 呼叫也不可能落在同一個 DB session/transaction 裡。MySQL isolation level 是預設的 REPEATABLE-READ，資料庫層也沒有能防止重複預約的 unique constraint。CSV 匯入路徑（`ReservationImportCsv.php` → `UnsafeAdd()`）完全繞過驗證，不只是併發不安全，是連單一請求的衝突檢查都沒有。

### 22.1 交易（transaction）起訖點

`ResourceLockService::LockResources()`（`lib/Application/Reservation/Concurrency/ResourceLockService.php`）呼叫 `Database::BeginTransaction()` 開始交易，接著在同一個交易內對要用到的 `resources` 資料列下 `SELECT ... FOR UPDATE` 鎖；交易在 `ReservationHandler::Handle()`（`lib/Application/Reservation/ReservationHandler.php`）裡結束——驗證通過且成功寫入後呼叫 `Commit()`，驗證沒過或發生例外一律 `Rollback()`。中間的「重新驗證＋寫入」（`IReservationValidationService::Validate()` 與 `IReservationPersistenceService::Persist()`）都在鎖與交易還開著的時候執行，不是鎖完就放掉。

底層關鍵修法：`MySqlConnection::Disconnect()` 原本每次都會真的 `mysqli_close()`，在交易期間會讓鎖跟交易一起消失。改成：交易期間（`_inTransaction=true`）`Disconnect()` 直接 no-op，讓連線活到 `Commit()`/`Rollback()` 主動呼叫真正的中斷連線為止——這樣 `Database::Query()`/`Execute()` 原本「每次呼叫都各自連線/斷線」的既有寫法完全不用改，交易期間會自動變成同一個 session。

### 22.2 Resource lock SQL 與涉及檔案

- SQL：`SELECT resource_id FROM resources WHERE resource_id IN (@resourceid) ORDER BY resource_id FOR UPDATE`（`LockResourcesCommand`）。資源 id 一律先 int 轉型、去重、**由小到大排序**再鎖，讓任兩個請求不管前端送來的資源順序為何，都用同一個順序取鎖，避免鎖順序不同造成的死鎖。
- 檔案：`lib/Application/Reservation/Concurrency/ResourceLockService.php`（`IResourceLockService`/`ResourceLockService`）、`lib/Application/Reservation/ReservationHandler.php`（唯一呼叫點，`GetResourceIdsToLock()` 會把「更新時被移除的資源」也一併納入鎖定範圍——釋出資源跟佔用資源一樣可能讓第三方的衝突請求被放行或擋下）。

### 22.3 已受保護的寫入入口

全部經過同一個 `ReservationHandler::Handle()`（唯一寫入通道，沒有另外開後門）：

- 一般新增／編輯／刪除（`Pages/Ajax/ReservationSavePage.php`／`ReservationUpdatePage.php`／`ReservationDeletePage.php`）
- Drawer 新增／編輯／刪除（新增與刪除重用上面同一批端點；編輯走 `OverviewFullCalendarReservationUpdatePresenter`）
- 後台管理（`Pages/Admin/ManageReservationsPage.php` 走同一批 Presenter）
- Web Service API（`WebServices/Controllers/ReservationSaveController.php` 一樣透過 `ReservationHandler::Create()`）
- Check-in／核准（`ReservationCheckinPresenter`／`ReservationApprovalPresenter`，都經過同一個 handler）
- 一般批量／重複預約（`RepeatOptions` 產生多筆 instance，仍是同一個 `ReservationSeries`/`Handle()`，沒有另一條路徑）

### 22.4 尚未受保護（或保護程度較低）的入口，及原因

- **CSV 匯入（`lib/Application/Admin/ReservationImportCsv.php` → `UnsafeAdd()`）**：這次**沒有**改動。現況仍是完全繞過驗證與鎖定，是既有行為，不是這輪引入的退化，但也還沒有照使用者的要求把它改成「預設關閉、需要明確 opt-in ＋ UI 警示 ＋ 稽核紀錄」的例外路徑。這是本輪最大的已知缺口，建議下一輪優先處理。
- **拖曳改期（drag-to-reschedule）**：目前 FullCalendar 檢視是唯讀的（沒有拖曳寫入 UI），這次沒有新增這個功能，自然也没有對應的寫入端點需要保護。
- **黑名單時段（blackout）建立/編輯**：這次沒有把 `ManageBlackoutsService`／`BlackoutRepository` 接上 `ResourceLockService`，還是走原本路徑，沒有 resource lock 保護。
- **Version optimistic locking 的覆蓋範圍**：`reservation_series.version` 的檢查已經是共用層（`ReservationRepository::Update()`/`DeleteSeriesEventCommand::Execute()`），**任何**呼叫這兩個方法的路徑都會拿到「至少同一個 request 內」的版本檢查（防止同一次寫入之間被別人插隊）；但**跨 session 的「使用者編輯前是否已經過期」偵測**（載入表單後過一段時間才送出，這段時間被別人改過）只有 Drawer 的更新/刪除端點有把客戶端送回來的 `expectedVersion` 接上去（見 22.5），舊版 `reservation.php` 表單／後台管理／Web Service 目前都沒有在畫面上帶著 version 走一整個編輯流程，所以這幾條路徑仍然只有「同一次請求內」等級的保護，不是完整的跨 session 保護。
- **Idempotency（P0-C）目前只接在「新增」路徑**：完全比照使用者需求範圍（「使用者雙擊或網路重送時只能建立一筆預約」），更新／刪除沒有走 idempotency key（用 optimistic version locking 處理，性質不同）。Web Service API 的新增請求 schema 目前沒有 `requestId` 欄位，所以 API 呼叫的新增也還沒接上 idempotency 保護（`ReservationRequestResponseFacade::GetRequestId()` 目前固定回傳 `null`）。

### 22.5 Optimistic version locking：SQL 與流程

- Schema：`reservation_series` 新增 `version INT UNSIGNED NOT NULL DEFAULT 0`（`database_schema/upgrades/4.1/schema.sql`，已手動套用到 dev DB 並以 `DESCRIBE` 驗證）。
- 更新：`UPDATE reservation_series SET ..., version = version + 1 WHERE series_id = ? AND version = @expectedVersion`。刪除（軟刪除）：`UPDATE reservation_series SET status_id = @statusid, ..., version = version + 1 WHERE series_id = ? AND version = @expectedVersion`。
- `Database::GetAffectedRows()` 在 `UpdateReservationSeriesCommand`/`DeleteSeriesCommand` 執行後檢查，0 筆代表版本不符，拋出新增的 `ReservationVersionConflictException`（`Domain/Access/ReservationRepository.php`），順著 `ReservationHandler::Handle()` 既有的 `catch (Throwable) { Rollback(); throw; }` 一路往上丟。
- **修過一個真的會炸的 bug**：`MySqlConnection::GetAffectedRows()` 原本是「即時查 `mysqli_affected_rows()`」，但 idempotency 的 claim/reclaim（22.6）刻意不在 resource lock 的交易內執行，`Database::Execute()` 執行完會真的斷線，斷線後才呼叫 `GetAffectedRows()` 等於在已關閉的連線上呼叫 mysqli 函式，會是未捕捉的 `TypeError`。改成：`MySqlConnection::Execute()` 執行完當下就把 `mysqli_affected_rows()` 的結果存起來，`GetAffectedRows()` 改成回傳快取值——不管呼叫方有沒有在交易內都正確。這個修法連同既有 P0-B 版本檢查一起用真實 DB 重新驗證過，行為不變。
- 讀取端點：`OverviewFullCalendarReservationPresenter::GetReservationData()` 新增 `version` 欄位（來源：`ReservationView::Version`，由 `ReservationViewRepository::GetReservationForEditing()` 填入）。Drawer 更新／刪除都會把載入時拿到的 `version` 原封不動送回（`overview_fullcalendar.js` 的 `buildEditBody()`／`openDrawerDeleteConfirm()`），伺服器用 `ExistingReservationSeries::SetExpectedVersion()` 蓋掉「這次請求剛好重新載入到的版本」，確保比對的是「使用者上次看到的版本」而不是「這次請求自己剛讀到的版本」（後者永遠會通過檢查，等於沒檢查）。
- 錯誤形狀：HTTP 409 + `{"success":false,"code":"reservation_changed","message":"這筆預約已被其他人修改，請重新載入後再試。"}`（更新走 `Pages/Ajax/OverviewFullCalendarReservationUpdatePage.php`，刪除走 `Pages/Ajax/ReservationDeletePage.php`／`ReservationDeleteJsonPage`）。Drawer 前端（`overview_fullcalendar.js` 的 `showDrawerVersionConflict()`）遇到這個 code 會顯示訊息＋一顆「重新載入最新內容」按鈕，不會自動覆蓋、也不會自動丟棄使用者輸入。
- `resource_conflict`（一般資源時段衝突，非版本衝突）目前只在 Drawer 的更新端點接上（`SetCanBeRetried(true)` → `code: 'resource_conflict'` + HTTP 409），前端收到會 `calendar.refetchEvents()` 但保留使用者輸入、不關閉 Drawer；新增流程（`ajax/reservation_save.php`）回傳的是 HTML 片段而非 JSON，這次沒有改動它的回應格式，所以新增路徑還沒有這個明確的 code，僅有既有的通用錯誤訊息。

### 22.6 Idempotency：schema 與流程

- Schema（同一個 4.1 migration）：`reservation_requests(request_id VARCHAR(36), user_id MEDIUMINT UNSIGNED, status VARCHAR(16), reference_number VARCHAR(50) NULL, date_created DATETIME, last_modified DATETIME, PRIMARY KEY(request_id, user_id))`。
- 服務：`lib/Application/Reservation/Concurrency/IdempotencyService.php`。`TryClaim($requestId, $userId)` 三種結果：`Claimed()`（可以繼續建立）、`AlreadySucceeded($referenceNumber)`（直接回放原本的 reference number，不再建立）、`InProgress()`（另一個相同 id 的請求正在處理中）。沒有搶到的一方靠 `(request_id, user_id)` 的 PRIMARY KEY 唯一性擋下，不是用應用層的鎖。停在 `processing` 超過 60 秒視為請求端已經死掉，允許被重新認領（避免永久卡死）；`failed` 狀態允許立即重新認領（允許重試）。
- 刻意**不**跟 resource lock 共用同一個交易——idempotency 是以 `(request_id, user_id)` 為鍵，不是以資源為鍵，claim/finalize 各自是獨立、會立即提交的短交易，這樣「已經 claim 到」這件事會馬上對其他請求可見，不用等整個預約交易 commit 才看得到。
- 前端：`overview_fullcalendar.js` 在 Drawer 開成 `mode=create` 時產生一個 UUID（`generateRequestId()`，`crypto.randomUUID()` 有支援就用，沒有就用內建 fallback），存在 `drawerState.requestId`，同一次新增嘗試（含重送）都帶著同一個值，直到成功或使用者重新開一次 Drawer 才會換新的。
- 錯誤代碼：`request_in_progress`（`ReservationRequestInProgressException`，`ReservationSavePage::PageLoad()` 內轉成 HTTP 409 + 訊息「另一個相同的預約請求正在處理中，請稍後再試。」）。`duplicate_request` 這個代碼目前沒有實際用到——因為設計上「重複請求」不會回錯誤，而是直接回放原本成功的結果（`success:true` + 原本的 reference number），這比回一個「重複」錯誤更貼近使用者實際想要的行為（雙擊應該表現得像只點了一次，而不是跳出一個「你重複了」的錯誤要使用者自己判斷該怎麼辦）。

### 22.7 真實雙（含 5 號情境三個）MySQL connection 測試結果

PHPUnit 全程都是用 `FakeDatabase`（單一 PHP process），結構上就不可能驗證真正的兩條連線／真正的 row lock／真正的 transaction isolation。因此另外寫了一組**不算在 PHPUnit 裡**的測試工具：`tests/concurrency/`（`worker.php` 是可重複使用的 CLI worker，每次執行都是一個全新的 PHP process、各自獨立連線資料庫；`run_concurrency_tests.php` 是協調端，用 `proc_open()` 同時起兩個以上的 worker，用「都回報 READY 才一起發 go」的 barrier 確保真的重疊，其中一邊會在拿到鎖之後刻意 `usleep()` 撐開重疊時間，逼真實的 InnoDB row lock 真的被兩條連線同時碰到，而不是靠作業系統排程運氣）。

實際執行結果（對真實 dev DB，事後已清除所有測試資料）：

```
Using resources: Google Meet1 (id 11), Google Meet2 (id 12)

PASS - Scenario 1: same-slot concurrent create, only one succeeds
PASS - Scenario 2: partial overlap concurrent create, only one succeeds
PASS - Scenario 3: adjacent non-overlapping slots, both succeed
PASS - Scenario 4: same slot, different resources, both succeed
PASS - Scenario 5: reverse-order multi-resource lock, no deadlock, only one succeeds (elapsed=3.49s)
PASS - Scenario 6: create-vs-update race for same slot, only one succeeds
PASS - Scenario 7: concurrent updates with same loaded version, second gets version conflict
PASS - Scenario 8: double-submit same idempotency key never creates two reservations
PASS - Scenario 9: exception mid-transaction leaves no partial data and releases the lock

==== SUMMARY: 9 passed, 0 failed ====
```

九項情境（含使用者指定的全部 9 種）全數通過，包含：同時段同資源只有一邊成功、部分重疊只有一邊成功、相鄰不重疊兩邊都成功、不同資源同時段兩邊都成功、反向資源鎖定順序不死鎖且只有一邊成功、新增與更新搶同一個時段只有一邊成功、兩個更新載入同一個版本第二個拿到 `ReservationVersionConflictException`、同一個 idempotency key 雙重送出資料庫最終只有一筆、交易中途拋例外不留半套資料且鎖會釋放（緊接著的下一次請求正常成功）。重跑指令：`docker compose -f docker-compose.dev.yml exec app php tests/concurrency/run_concurrency_tests.php`。

### 22.8 已知限制

- CSV 匯入（`UnsafeAdd()`）仍未改成 opt-in／稽核／警示，是目前最大的缺口（見 22.4）。
- Version optimistic locking 的「跨 session 過期偵測」只接在 Drawer；舊版表單／後台管理／Web Service 的更新只有「同一次請求」等級的版本保護。
- Idempotency 只接在新增路徑，且只接在 Drawer／`ajax/reservation_save.php`，Web Service API 新增尚未串上（schema 沒有 `requestId` 欄位）。
- `resource_conflict` 明確錯誤代碼只接在 Drawer 更新端點；Drawer 新增與其他既有端點仍是舊有的通用錯誤訊息（沒有 code 區分）。
- 拖曳改期尚未存在（UI 唯讀），黑名單建立/編輯尚未接上 resource lock。
- `tests/concurrency/` 的 9 項情境測試不是自動化 CI 的一部分（需要真實 DB、且會建立/刪除真實資料列），是手動驗證工具，之後若要放進 CI 需要額外評估（獨立測試資料庫、避免污染 dev 資料）。
- 60 秒的「processing 視為卡死」門檻與 idempotency 的 3 次重試上限都是經驗值，沒有經過正式的壓力測試調校。

以上 8 項完成後，現在可以確認：在本輪已列出的保護範圍內（22.3），系統不會因為併發請求而重複預約或互相悄悄覆蓋；22.4／22.8 列出的範圍則仍是已知、明確標示的缺口，尚未受到本輪保護。

### 22.9 修正：4.1 升級腳本沒有寫入 dbversion，且 ALTER TABLE 不是冪等的

上一版只手動套用了 `schema.sql` 到 dev DB，沒有補 `data.sql`，導致 `dbversion` 表仍停在 4.0。實際追查兩套會重跑升級腳本的機制後發現，這會造成兩個不同的問題：

- **`Presenters/Install/Installer.php`（網頁安裝／升級精靈用的路徑）**：`Upgrade()` 會拿 `dbversion` 目前最大版本號跟每個 upgrade 資料夾名稱比大小，只有資料夾版本號**大於**目前紀錄的版本才會執行；`dbversion` 沒有 4.1 就代表**每次重新整理安裝頁面都會再跑一次 4.1 的 schema.sql／data.sql**。
- **`phing-tasks/UpgradeDbTask.php`（CI／`build.xml` 的 `upgrade.db` 用的路徑）**：這支完全不看 `dbversion`，每次呼叫都無條件重跑 `upgrades/` 底下**全部**資料夾——CI 的用法是先用 `create-schema.sql` 建一個全新資料庫再跑這支，所以在 CI 情境下不是問題，但只要拿去對一個已經升級過的資料庫重放，就會立刻在更早期、本來就不是冪等寫法的舊版 schema.sql（例如 2.1）上炸掉，這是既有限制、不是本輪新增的問題。

修法：

1. 新增 `database_schema/upgrades/4.1/data.sql`，寫法與 4.0 完全一致的防重複 pattern（`INSERT ... SELECT ... WHERE NOT EXISTS (SELECT 1 FROM dbversion WHERE version_number = 4.1)`）。
2. `schema.sql` 原本的 `ALTER TABLE reservation_series ADD COLUMN version ...` 改寫成 `database_schema/upgrades/3.0/schema.sql` 已經在用的同一套寫法——先用 `information_schema.COLUMNS` 查欄位存不存在，用動態 SQL（`PREPARE`/`EXECUTE`/`DEALLOCATE`）決定要不要真的執行 `ALTER TABLE`。原因：實際對 MySQL 8.0.46 測試過，`ALTER TABLE ... ADD COLUMN IF NOT EXISTS` 是語法錯誤（MariaDB 支援、MySQL 不支援），這點 3.0 的程式碼註解已經寫得很清楚，這次只是沒有照著用。

驗證（對真實 dev DB 操作，非模擬）：

- 先手動把 dev DB 復原到「乾淨的 4.0」（`ALTER TABLE reservation_series DROP COLUMN version; DROP TABLE reservation_requests;`，`dbversion` 本來就還停在 4.0）。
- 直接套用新的 `schema.sql` + `data.sql`：`version` 欄位出現、`reservation_requests` 表出現、`dbversion` 出現一筆 4.1。
- **重新套用同一組 `schema.sql` + `data.sql`**：兩次都沒有任何錯誤；`dbversion` 裡 `version_number = 4.1` 剛好 1 筆（沒有重複插入）；`version` 欄位在 `information_schema.COLUMNS` 裡剛好 1 筆（沒有重複／衝突）。
- 另外直接呼叫真正的 `Presenters/Install/Installer.php`：`GetVersion()` 正確回傳 `'4.1'`；`Upgrade()` 執行 0 支腳本（4.1 資料夾被正確跳過，不會重跑）。
- PHPUnit 全數 1718 項測試在這個真實 DB 狀態下重新跑過，全過。

現在對 `database_schema/upgrades/4.1/` 的升級腳本可以確認：安裝精靈重新整理不會再重複執行 4.1 的 schema／data；兩個檔案本身也都對「被重新套用一次」是安全的（不會噴錯、不會重複寫入）。

## 23. P2：整體 UI 與操作邏輯一致（側邊欄收合、通知設定改版、登入頁去導覽、視覺規範複查）

使用者指定範圍：這一輪只做「整體 UI 與操作邏輯一致」，不需要跑全專案大量測試，只做與修改相關的基本檢查。五項工作都在 `tpl/globalheader.tpl`（全站共用側邊欄）與周邊頁面完成。

### 23.1 側邊欄收合／展開，行事曆自動重新計算寬度

- 新增 `#sidebarCollapseToggle`（`tpl/globalheader.tpl` 的 `.sidebar-brand` 內，絕對定位在該列右側，不佔用 flex 版位，也不會被 `.app-sidebar` 的 `overflow:hidden` 裁切）。只在桌面寬度（`min-width:992px`）顯示——手機寬度已經有既有的 `.sidebar-toggler`（漢堡按鈕）負責同一件事，兩者的顯示區間互斥，不會同時出現。
- 收合狀態存在 `localStorage`（`sidebar-collapsed`），並在 `<aside id="appSidebar">` 開始標籤後立刻插入一段小 script 讀取套用，避免整頁刷新時先看到展開再收合的閃爍。
- 收合時的文字隱藏用既有的 CSS cascade 技巧：文字是圖示旁邊的純文字節點、沒有自己的包裹元素可以選取，於是靠 `.sidebar-link i:first-child`（已存在、選擇器更精確）蓋掉新加的 `.app-sidebar.collapsed .sidebar-link{font-size:0}`——圖示保留原本大小，文字因為沒有自己的覆寫值而跟著父層變成 0。同一招也用在 `.create-btn`。迷你月曆、資源勾選清單、分隔線、子選單、版本徽章在收合時整個隱藏（螢幕太窄放不下，且和其他收合式側邊欄慣例一致）。
- 點擊已收合狀態下的群組展開鈕（例如「應用程式管理」）會先展開整條側邊欄，再用 Bootstrap 的 `Collapse.getOrCreateInstance(...).show()` 補開該子選單，而不是在 72px 寬的窄軌裡悄悄展開一個看不到的子選單。
- 行事曆重新計算寬度：沒有直接持有 FullCalendar 實例（`overview_fullcalendar.js` 是另一支獨立載入的頁面腳本），改成在切換收合狀態、以及側邊欄 `width` transition 結束時，`window.dispatchEvent(new Event('resize'))`。FullCalendar 6 預設 `handleWindowResize:true`，本來就監聽 window 的 `resize` 事件並據此呼叫內部的 `updateSize()`；派送一個合成事件足以觸發同一條路徑，其他沒有行事曆的頁面上這個 dispatch 也只是無害的 no-op。

### 23.2 移除側邊欄重複的「我的會議」入口

- Overview 頁工具列已有「全體／只看我的」（`ReservationScopeAll`/`ReservationScopeMine`，`tpl/Overview/overview_fullcalendar.tpl`）可以直接在主行事曆篩選，側邊欄原本另外有一個連到 `my-meetings.php`（議程列表頁）的「我的會議」連結，是同一個需求的第二個入口。
- 依既有慣例用 `{if false}` 包起來並附註說明（不是刪除整段程式），`my-meetings.php` 頁面本身沒有動，仍可透過網址直接開啟；之後如果要恢復，把 `{if false}` 拿掉即可。

### 23.3 通知設定頁改版

- `tpl/MyAccount/notification-preferences.tpl` 全面重寫，改用 `my-meetings.tpl`/`overview_fullcalendar.tpl` 已經在用的「`.shell` + `.page-head` + 白底圓角卡片＋陰影」版型，新增 `Web/css/notification-preferences.css`（比照 `Web/css/my-meetings.css` 的檔頭寫法：只認 `--primary`/`--bs-border-radius-lg`/`--bs-box-shadow`/`--gcal-border` 這些站台共用 token，不依賴只有 Overview 頁才有載入的 `overview_fullcalendar.css` 私有變數）。
- 表單欄位名稱／id（`{ReservationEvent::Created}` 等）完全沒變，`Pages/NotificationPreferencesPage.php`／`Presenters/NotificationPreferencesPresenter.php` 不需要也沒有修改。
- 六個通知項目原本各自搭配一個重複的「電郵給我」小標籤；改版拿掉那個逐項重複的標籤（保留 `visually-hidden` 版本給螢幕報讀器），改成一個圖示＋一句話取代，靠section 標題「電子郵件通知」統一交代這些都是 email 開關。
- 把難懂的中文說法換成看得懂的敘述（`lang/zh_tw.php`）：
  - `ReservationCreatedPreference`：「當我建立一個預約 或 一個預約已經為我建立」→「有新的預約建立（包含由他人代我建立）」
  - `ReservationUpdatedPreference`：原文其實複製貼上打錯字，寫的是「建立」不是「更新」（「當我建立一個預約 或 一個預約已經為我更新」）→「我的預約內容有異動，例如時間或地點被更改」
  - `ReservationDeletedPreference`：「當我刪除一個預約 或 一個預約已經為我刪除」→「我的預約被取消或刪除」
  - `ReservationApprovalPreference`：「當我的待審批預約獲得批准」→「我送出的預約申請通過審核」
  - 順手發現 `ReservationParticipationActivityPreference`／`ReservationSeriesEndingPreference` 這兩個既有的通知項目**在 `zh_tw.php` 裡從來沒有翻譯過**（只有 `en_us.php` 有），中文介面上這兩列原本會是空字串或英文 fallback。這次一併補上：「有人加入或退出我的預約」／「我的定期預約系列即將到期結束」。
  - 新增 `EmailNotifications`（區塊標題）、`NotificationPreferencesIntro`（副標）兩個新翻譯鍵，中英文都有。

### 23.4 登入頁（含註冊、忘記密碼）不再顯示主系統側邊欄

- `tpl/login.tpl`／`tpl/register.tpl`／`tpl/forgot_pwd.tpl` 原本 `{include file='globalheader.tpl'}` 沒有帶 `HideNavBar=true`，等於這三頁在沒有登入的狀態下仍然會渲染完整的 `.app-sidebar`（logo、"登入" 連結、若剛好有殘留的 session 資源清單等）。這幾頁只有在**未登入**時才會被渲染到——`LoginPresenter::PageLoad()` 一開始就檢查 `IsLoggedIn()`，是的話直接 `_Redirect()` 離開，不會走到畫面渲染——所以這三頁一律加上 `HideNavBar=true` 是安全的，不會有「已登入卻看不到側邊欄」的情形。
- 沿用專案裡既有、已經在用的同一個模式（`tpl/ResourceDisplay/resource-display-login.tpl` 等頁面本來就是這樣做），`globalheader.tpl` 在 `HideNavBar=true` 時渲染 `.app-content.app-content-bare`，不含 `.app-shell`／`.app-sidebar`，沒有另外寫新的 CSS。

### 23.5 複查「查看詳情／更多選項／新增·編輯預約」視覺規範

- 逐一讀了 `Web/css/overview_fullcalendar.css` 裡事件卡片（`.ep-*`）、刪除確認對話框（`.del-*`）、Drawer（`.reservation-*`/`.rd-*`）的按鈕、表單、間距、圓角、顏色定義：全部走同一組 `#page-overview-fc` 作用域下的共用 token（`--primary`/`--danger`/`--text-primary`/`--text-secondary`/`--control-radius`/`--dialog-radius`/`--icon-button-size`），按鈕一律是 100px 全圓角的實心（`--primary`/`--danger`）或文字按鈕，表單欄位一律 `--control-radius`（10px）＋ `--border`，是先前那一輪（COASIA-CHANGES.md §20–21）就刻意建立好的共用系統，這次沒有發現落漆的地方，不需要改動。
- 額外確認了「更多選項」（`#qcMoreOptions`，Bootstrap `.link-primary`）沒有脫離這套色彩系統：`Web/css/librebooking.css` 已經把 `.link-primary` 覆寫成 `color: var(--primary) !important`，而不是 Bootstrap 預設的藍色，所以這顆連結顏色跟「查看詳情」（`.ep-link-btn`，直接用 `var(--primary)`）是同一個顏色，沒有分岔。

### 23.6 基本檢查方式

沒有跑全專案 PHPUnit（使用者明確指定這輪不需要）。改成寫一支一次性的 smoke-test 腳本，直接用專案自己的 `SmartyPage`／`translate` 外掛把這次改動或新增的 5 支 `.tpl`（`login.tpl`／`register.tpl`／`forgot_pwd.tpl`／`MyAccount/notification-preferences.tpl`／`globalheader.tpl`，含 `LoggedIn=true`＋admin 權限的情境）各自 `fetch()` 編譯＋渲染一次，全部成功且沒有例外；另外把渲染結果存檔比對，確認「我的會議」連結（`navMyMeetings`）確實從輸出中消失、收合按鈕（`sidebarCollapseToggle`）確實出現在輸出裡。`lang/en_us.php`／`lang/zh_tw.php` 另外過了 `php -l`。跑完後清掉暫存腳本與 `tpl_c/` 裡這幾支模板的舊編譯快取（本機開發環境的既有陷阱：不清 `tpl_c/` 舊編譯結果會繼續生效）。

### 23.7 已知限制／建議下一輪處理

- 這次的「基本檢查」主要停在 Smarty 編譯＋渲染層級（沒有現成的開發帳密可以在自動化腳本裡登入）；實際的滑鼠互動、收合動畫、行事曆寬度是否真的補滿，是使用者事後在自己瀏覽器裡實測才發現／確認的（見 23.8）。
- 舊版 `reservation.php`（classic 表單，目前只作為 Drawer「更多選項」裡進階欄位——參與者、提醒、自訂欄位——的落地頁）沒有被納入這輪視覺規範複查範圍；`ep-`/`rd-`/`del-` 這套新 token 系統目前只覆蓋 Drawer／事件卡片／刪除對話框，舊表單本身仍是 Bootstrap 預設樣式。

### 23.8 使用者實測回饋：收合狀態的小 logo、以及一個新發現的快取陷阱

使用者在瀏覽器裡實際登入操作後回報兩件事：

1. **收合後的側邊欄小 logo**：72px 窄軌裡原本繼續放完整的「CoAsia」文字版 logo（`$LogoUrl`），太寬、觀感不佳。使用者指定改用既有的三角形品牌標記（就是 `Web/custom-favicon.png`，也就是瀏覽器分頁圖示那個，見 §17/§18 一帶的 favicon 修改記錄）。修法：`tpl/globalheader.tpl` 的 `.sidebar-brand` 內同時放兩個 `<a>`（`.sidebar-brand-logo-full` 用 `$LogoUrl`、`.sidebar-brand-logo-mark` 用既有的 `$FaviconUrl` 變數，不是另外複製一份圖檔或寫死路徑），`Web/css/coasia-theme.css` 用 `@media (min-width:992px) { .app-sidebar.collapsed .sidebar-brand-logo-full{display:none} .app-sidebar.collapsed .sidebar-brand-logo-mark{display:inline-flex} }` 切換顯示，展開時维持原本的完整 wordmark。
2. **通知設定頁翻譯顯示異常**：畫面上卡片標題／副標題顯示成「?」，`ReservationParticipationActivityPreference`／`ReservationSeriesEndingPreference` 兩列還是英文，即使 `lang/zh_tw.php`／`lang/en_us.php` 檔案內容本身是對的。追查後發現是本機環境的**第三個快取陷阱**（見 [[project-coasia-dev-gotchas]] 已經補上）：容器的 PHP `opcache.validate_timestamps=Off`，`.php` 檔案存檔後不會自動生效，要 `docker compose -f docker-compose.dev.yml restart app` 才會真正載入新內容。「?」是 `Resources::GetString()` 對「完全找不到這個 key」的既定回傳值（全新加的 `EmailNotifications`／`NotificationPreferencesIntro` 在 opcache 還沒重載時，新舊兩個語言檔都沒有這個 key）；英文則是因為 `zh_tw` 這個類別本身 `extends en_us`（`lang/zh_tw.php` 開頭 `require_once('en_us.php'); class zh_tw extends en_us`），舊版 zh_tw 缺這兩個 key、但舊版 en_us 已經有，自然照英文顯示。重啟容器後，直接用一支獨立腳本讀取 `zh_tw` 類別的 `Strings` 屬性重新確認過，六個新／改的翻譯鍵全部正確顯示繁體中文。

## 24. SMTP 改接公司內部郵件伺服器（取代 Gmail 個人帳號）

使用者提供公司內部 SMTP Relay（已用 PowerShell 驗證過可以正常寄信）：`10.1.3.140:25`，內網 open relay，不需要帳密／TLS，寄件人固定 `coasiaerp@coasia.com`（顯示名稱「擎亞會議室預約系統」）。取代先前 §14 一帶提到、記在 `COASIA-GOLIVE-CHECKLIST.md`「應該處理」清單裡的 Gmail 個人帳號（`smtp.gmail.com:587`＋`shawn_tsai@gm.coasia.com` App 密碼）寄信方式。

### 24.1 改的地方

只改了本機 `config/config.php`（`.gitignore` 排除，不會進版控，跟先前 Gmail 帳密的處理方式一致）：
- `phpmailer` 區塊：`smtp.host`→`10.1.3.140`、`smtp.port`→`25`、`smtp.secure`→空字串（不是 `'tls'`／`'ssl'`）、`smtp.autotls`→`false`、`smtp.auth`→`false`、`smtp.username`/`smtp.password`→空字串。
- `email` 區塊：`default.from.address`→`coasiaerp@coasia.com`、`default.from.name`→「擎亞會議室預約系統」。
- 這兩個區塊分別對應 `lib/Config/ConfigKeys.php` 的 `phpmailer.*`／`email.default.from.*`，實際組裝 `PHPMailer` 物件的地方是 `lib/Email/EmailService.php`，兩者是唯一的寄信路徑，本次沒有另外新增或繞過的路徑。

### 24.2 驗證

`config.php` 也是 `.php` 檔，同樣會撞到上面 23.8 提到的 opcache 快取陷阱——改完先 `docker compose -f docker-compose.dev.yml restart app`，再用 `Configuration::Instance()->GetKey(...)` 讀出全部七個相關設定值，確認跟改的內容完全一致（沒有殘留舊的 Gmail 設定），才繼續往下驗證：

- 直接用 `PHPMailer` 手動組一封信寄到 `10.1.3.140:25`（同 host/port/no-auth/no-TLS），`send()` 回傳成功。
- 另外寫一個實作 `IEmailMessage` 的最小類別，直接呼叫專案自己的 `EmailService`（`Presenters`/`lib/Application/Reservation/Notification/*` 實際寄確認信/提醒信時用的同一支類別，不是另外繞過），讓它自己從 `Configuration::Instance()` 讀設定、組 `PHPMailer`、寄出——同樣沒有拋出例外；應用程式的 error log（`config.php` 設定的 log level 是 `error`，`EmailService` 寄信失敗會呼叫 `Log::Error`）在寄信前後沒有出現任何新的錯誤紀錄，確認不是「呼叫成功但其實內部吞掉例外」的假陽性。
- 沒有真的透過網頁畫面跑一次完整的「建立預約 → 收到確認信」流程（那需要登入操作，這次沒有可用的開發帳密）——`Task 2`（確認信）／`Task 3`（提醒信）本身的寄信邏輯這次沒有改動，只換了底層 SMTP 設定，兩支測試已經確認同一組設定＋同一支 `EmailService` 可以正常把信送出去，可信度足夠，但如果要 100% 閉環建議之後找機會實際跑一次真的預約流程再收一次信確認。

### 24.3 順手更新的文件

`COASIA-GOLIVE-CHECKLIST.md`「應該處理」清單裡原本標記「SMTP 綁在個人 Gmail 帳號」的項目，已經改標成已解決並記錄新設定，不用等下一輪整理才更新。

## 25. 重新設計 CoAsia 預約通知信 + 修正 ICS 附件的一批真實 RFC 5545 錯誤

使用者要求重新設計五種預約狀態通知信（預約成功／已更新／已取消／等待審核／審核結果），統一寄件身分為 `CoAsia Booking <booking@coasia.com>`，並修正 ICS 附件的檔名、UID、SEQUENCE、METHOD、時區問題。這輪只動了 email 相關的 PHP／模板／少量 domain 欄位，沒有動預約寫入本身的邏輯。

### 25.1 起點稽核：這些「已知問題」哪些是真的

動手改之前先逐一確認使用者列的每個 ICS 問題是否真的存在於目前程式碼裡，而不是照單全收：

- **檔名 `reservation.ics.ics`**：程式碼裡沒找到會產生雙副檔名的路徑（`ReservationEmailMessage::PopulateIcsAttachment()` 原本固定用字串 `'reservation.ics'`，`PHPMailer::addStringAttachment()` 也不會自己加副檔名）。判斷這可能是某些郵件閘道／防毒掃描會對「看起來像泛用附件」的檔名自行加上偵測到的副檔名，不確定確切成因，但無論原因為何，防禦性修法都一樣：改成「先剝掉任何既有的 `.ics` 結尾、再固定加一次」的建構方式（`ReservationEmailMessage::BuildIcsFileName()`），而且順便把每封信的附件檔名從固定的 `reservation.ics` 改成帶參考編號的 `reservation-{referenceNumber}.ics`，不同預約不會撞名。
- **UID 是否每次都變**：追查 `UID:{$reservation->ReferenceNumber}&{$UID}`（`tpl/Export/ical.tpl`）用的參考編號，`Domain/Reservation.php` 只在**第一次建立**時呼叫 `ReferenceNumberGenerator::Generate()`，之後載入既有預約（`ReservationRepository::LoadByReferenceNumber()`）用的是資料庫裡已經存好的同一個參考編號，不會重新產生——這部分**其實已經是對的**，這次沒有改 UID 的產生邏輯本身，只是把它從模板裡的一次性拼接，順手做成可以在下面兩點驗證裡重複測試的東西。
- **SEQUENCE 固定是 0**：**確認是真的**——`tpl/Export/ical.tpl` 原本寫死 `SEQUENCE:0`，不管建立幾次、更新幾次都一樣，這正是 Outlook／Google Calendar 有時候不會把「更新信」當成同一顆事件的更新、而是當成另一封不明訊息或乾脆忽略的典型成因之一。
- **METHOD 永遠是 REQUEST，取消也一樣**：**確認是真的**——`tpl/Export/ical.tpl` 原本整份都是 `METHOD:REQUEST`，`ReservationDeletedEmail` 寄出的取消信附件跟建立信附件用的是同一個 `PopulateIcsAttachment()`，沒有分支。
- **時區不正確**：**確認是真的，而且是這批問題裡最根本的一個**——見 25.2。

### 25.2 真正的根因：DTSTAMP／CREATED／LAST-MODIFIED 被貼上錯誤的時區標籤

`lib/Common/Date.php` 的 `Format($format)` 只是把物件目前帶的時區原樣印出來，**不會自己做任何轉換**；`Resources::$systemDateKeys['ical'] = 'Ymd\THis\Z'` 這個格式字串裡的 `\Z` 只是一個**字面上**要印出來的字元 `Z`，不是「請印出真正的 UTC」的指令。也就是說，呼叫端如果沒有先呼叫 `->ToUtc()`，印出來的時間會是物件當下的時區（不管是什麼），但結尾照樣硬貼一個代表 UTC 的 `Z`。

`DTSTART`/`DTEND` 本來就有在呼叫端（`ReservationEmailMessage::PopulateIcsAttachment()`）先 `->ToUTC()` 過，所以一直是對的。但 `CREATED`／`DTSTAMP`／`LAST-MODIFIED` 這三個全部源自同一個 `iCalendarReservationView` 建構子裡的 `DateCreated` 欄位——而 email 這個路徑呼叫 `ReservationItemView` 建構子時，對應 `DateCreated` 的那個位置傳的是 `null`（這個 20 個參數的建構子本來就沒有 `DateCreated` 這個參數，是後續才靠公開屬性直接指定的慣例，這條路徑上沒有人這樣做），於是 `iCalendarReservationView` 的建構子退回 `else` 分支用 `Date::Now()`——**伺服器本機時區**（本專案設定是 `Asia/Taipei`，UTC+8），沒有轉 UTC，卻被印成帶 `Z` 的格式，等於每一封信附件裡的 `DTSTAMP`／`CREATED`／`LAST-MODIFIED` 都比真正的 UTC 時間**多了 8 小時**還宣稱自己是 UTC。

這正是使用者說「時區不對，Outlook／Google Calendar 會產生重複會議」最直接的成因——`DTSTAMP` 是行事曆軟體判斷「這是不是同一封邀請的較新版本」的關鍵欄位之一，一個離譜偏移、而且在同一個 UID 的多封信之間看起來幾乎沒有實質推進的 `DTSTAMP`，很容易讓客戶端判斷失準（誤判成同一份訊息的重送、或乾脆各自獨立處理）。

**修法**（`lib/Application/Schedule/iCalendarReservationView.php`）：
- `DateCreated`／`DateEnd`／`DateStart`／`LastModified` 統一在建構子裡補上 `->ToUtc()`（對於原本就已經是 UTC 的來源——凡是經 `Date::FromDatabase()` 讀出來的都已經是 UTC，見該方法自己 `Date::Parse($databaseValue, 'UTC')` 的寫法——這是無副作用的 no-op，`Date::ToTimezone()` 目標時區跟目前相同時直接回傳 `Copy()`；只有這次 `Date::Now()` 的分支是真正需要修正的情形）。
- 新增 `DtStamp` 屬性，**永遠**是 `Date::Now()->ToUtc()`，語意上跟 `CREATED`（預約真正的建立時間）分開——RFC 5545 對 `DTSTAMP` 的定義是「這份 iCalendar 訊息本身是什麼時候產生的」，不是「這個事件本身是什麼時候建立的」；每一封新寄出的通知信（不管是建立、更新、取消）都應該有自己當下的 `DTSTAMP`，而不是重複使用預約最初建立時的舊時間戳記。

### 25.3 SEQUENCE：接上既有的 `reservation_series.version`（P0 樂觀鎖欄位）

沒有另外設計一套計數機制，直接重用 P0 併發安全那輪（COASIA-CHANGES.md §22.5）已經建好的 `reservation_series.version`——語意上完全對得上：新建立是 0，每次成功更新／刪除都遞增，正好符合 iCalendar `SEQUENCE` 「同一個 UID 底下，每一版遞增」的定義。

- `Domain/ReservationSeries.php`：`Version()`／`WithVersion()` 從 `ExistingReservationSeries` 移到共用的基底類別（`ExistingReservationSeries` 原本各自宣告一份，`ReservationSeries`（全新、尚未寫入的預約）原本完全沒有）——這樣「建立信」跟「更新信」的 ICS 附件可以用同一套機制取得版本號，不用分別處理兩種情境。
- `Domain/Access/ReservationRepository.php`：`Update()`（一般更新）跟 `DeleteSeriesEventCommand::Execute()`（刪除／取消）在樂觀鎖檢查通過、確認真的寫進資料庫後，補上 `$reservationSeries->WithVersion($reservationSeries->ExpectedVersion() + 1)`，讓記憶體裡的物件立刻反映剛寫入的新版本號——這是必要的一步：通知信是在 `ReservationHandler::Handle()` 裡緊接著同一個物件、在 `Persist()` 成功之後才組出來的，如果不補這一行，讀到的會是「這次寫入之前」的舊版本號，等於 `SEQUENCE` 完全沒有真的推進。
- `Domain/ReservationItemView.php`：新增 `$Version` 公開屬性（同一套「呼叫端事後指定」慣例，比照既有的 `$Attributes`/`$UserPreferences`），`ReservationEmailMessage::PopulateIcsAttachment()` 寄信前指定 `$rv->Version = $this->reservationSeries->Version();`。

### 25.4 METHOD／STATUS：取消信改用 `METHOD:CANCEL`

- `iCalendarReservationView` 建構子新增 `$method` 參數（預設 `'REQUEST'`），並依此計算 `$Status`（`METHOD:CANCEL` 時強制 `STATUS:CANCELLED`，不再看 `IsPending`）。
- `ReservationEmailMessage` 新增可覆寫的 `GetIcsMethod()`（預設回傳 `'REQUEST'`），`PopulateIcsAttachment()` 呼叫這個方法決定要不要用 `CANCEL`。`ReservationDeletedEmail`／`ReservationDeletedEmailAdmin`（後者是走另一條 `ReservationCreatedEmailAdmin` 血緣、本來就沒有 ICS 附件，只是也用同一份共用模板顯示取消狀態）覆寫回傳 `'CANCEL'`。
- `tpl/Export/ical.tpl`：`SEQUENCE:0` 改成 `SEQUENCE:{$reservation->Sequence}`；`STATUS:` 改成直接印 `{$reservation->Status}`（原本是 `{if $reservation->IsPending}TENTATIVE{else}CONFIRMED{/if}` 這種模板端判斷，現在邏輯統一搬到 PHP 端算好）；`METHOD:` 從整份寫死的 `REQUEST` 改成先用 `{assign}` 從 `$Reservations[0]->Method` 算出 `$CalendarMethod`，再輸出 `METHOD:{$CalendarMethod}`。
    - **這裡踩到一個真正會炸的模板 bug，是寫測試才抓到的**：一開始直接寫 `METHOD:{if ...}{$Reservations[0]->Method}{else}REQUEST{/if}`（跟 `PRODID:...` 各自一行），跑起來後 `METHOD:` 那行會跟下一行的 `PRODID:` 黏在同一行輸出成 `METHOD:REQUESTPRODID:-//LibreBooking...`——一份少了換行的 ICS，會被行事曆軟體整份判讀失敗，比原本「永遠是 REQUEST」的舊 bug 還嚴重。改成先用一整行只有 `{assign}` 的敘述把方法算進一個變數，`METHOD:{$CalendarMethod}` 這行本身只剩單純的「字面文字＋一個變數」，問題就消失了。25.6 的測試腳本後來一直留著、每次改完都重跑，就是為了不要讓這類東西又悄悄回歸。

### 25.5 五種狀態信的內容與寄件身分

- `lib/Email/Messages/ReservationCreatedEmail.php` / `ReservationUpdatedEmail.php` / `ReservationDeletedEmail.php` / `ReservationApprovedEmail.php`：各自的 `PopulateTemplate()` 設定一個 `$Status`（`created`／`pending`／`updated`／`cancelled`／`approved`），`created` 跟 `pending` 由同一支 `ReservationCreatedEmail` 依 `RequiresApproval()` 動態決定，不是分成兩支類別。
- **更新信的差異列表**：`Domain/ExistingReservationSeries.php` 新增 `SnapshotForChangeDetection()`／`GetChangeSummary()`——`ReservationRepository::Load()`（`LoadByReferenceNumber()`／`LoadById()`／Drawer 走的 `UpdateReservationPersistenceService::LoadByReferenceNumber()` 全部經過這一個方法）在物件完整讀出、**還沒被任何呼叫端的 `Update()`/`UpdateDuration()` 動過**的當下，立刻拍一張標題／說明／資源／時間的快照；之後不管是哪個呼叫端改了什麼欄位，`GetChangeSummary()` 都能在通知信組信的當下比對「快照 vs 目前值」算出差異，不需要每個更新入口自己各自記錄前後值。`ReservationUpdatedEmail::PopulateTemplate()` 讀出這份差異、把時間範圍格式化成跟信件其他地方一致的日期格式，`Set('Changes', ...)`。
- **寄件身分**：`config/config.php` 的 `email.default.from.address`／`default.from.name` 改成 `booking@coasia.com`／`CoAsia Booking`（取代 §24 才剛設的 `coasiaerp@coasia.com`）。`ReservationEmailMessage` 沒有覆寫 `ReplyTo()`，繼承 `EmailMessage::ReplyTo() => From()`，而 `ReservationEmailMessage::From()` 回傳的是實際預約人的信箱——所以收件人看到的寄件人是統一品牌帳號，但按「回覆」還是會回到真正經手這筆預約的人，不是黑洞信箱。
- **視覺風格**：新增共用模板 `tpl/Email/reservation-notification-body.tpl`，`lang/en_us/ReservationCreated.tpl`（Created／Updated／Approved 共用）與 `lang/en_us/ReservationDeleted.tpl`（Cancelled）都只是 `{include}` 這一份共用檔案，靠 `$Status` 切換徽章文字／顏色與該狀態專屬的區塊（差異列表、取消原因），不是四份互相複製、容易日後各自長歪的檔案。配色直接沿用這次工程已經在其他地方用過的 token（不是另外發明新色票）：主色 `#1a3f73`（`coasia-theme.css` 的 `--primary`）、待審核徽章 `#a5562f`/`#fbeee0`（跟 `Web/css/my-meetings.css` 的 `.meeting-pending` 完全一樣的顏色）、取消徽章 `#c5221f`/`#fbe9e7`（跟 `overview_fullcalendar.css` 的 `--danger`／delete 按鈕 hover 底色一致）。按鈕統一只保留「查看預約」＋「加入視訊會議」（取消狀態底下強制不顯示加入會議按鈕，即使該預約原本有視訊資源），拿掉舊版模板裡的「加入行事曆」／「加入 Google 日曆」兩顆按鈕——這兩顆本來就是 ICS 附件已經內建、大多數行事曆 App 收到附件會直接跳出「加入行事曆」提示的重複功能，這次 ICS 本身的正確性已經修好了，不需要再靠額外按鈕頂替。
- **中文字串**：延續 §2 已經定調的作法（email 內文不走 `{translate}`，直接寫死繁體中文——因為 guest／未登入邀請對象沒有帳號語言設定可以依賴，一定會 fallback 到系統預設語言，寫死才能保證所有收件人都看到中文），連帶把四個 Subject 行翻譯鍵也從英文改成中文（`lang/en_us.php`）：原本這四行完全沒被前一輪的「email 內文中文化」處理到，之前寄出的信標題其實是英文、內文才是中文，這次一併修掉，另外也修掉一個原本埋在裡面的錯字——`ReservationUpdatedEmail::Subject()` 原本誤用 `ReservationUpdatedAdminSubjectWithResource`（給管理員版本用的鍵），不是給一般使用者的 `ReservationUpdatedSubjectWithResource`。

### 25.6 「只能在成功寫入後寄出」——稽核既有架構，沒有新增機制

檢查後確認這件事**已經是既有架構的一部分**，不需要新增程式碼：`ReservationHandler::Handle()`（`lib/Application/Reservation/ReservationHandler.php`）在 `$this->notificationService->Notify($reservationSeries)` 前面明確用註解寫著「Only after the transaction has committed and the resource lock released」，`Notify()` 只在 `$result`（驗證通過**且** `Persist()`／`Commit()` 都成功）為真時才會呼叫；驗證失敗或寫入拋例外都會 `Rollback()` 並在 `Notify()` 之前 return／rethrow。重送保護則是 P0-C 的 idempotency（§22.6）：`ReservationSavePresenter::HandleReservation()` 對於 `AlreadySucceededOutcome()`（同一個 `requestId` 先前已經成功過）直接回放舊的參考編號並 `return`，**完全不會**呼叫 `$this->handler->Handle()`，自然也不會再呼叫一次 `Notify()`——雙擊或網路重送不會造成重複通知信。

### 25.7 基本檢查方式

同樣沒有跑全專案 PHPUnit。寫了三支一次性腳本（用完即刪、`tpl_c/` 也清過）：

1. **`tpl/Export/ical.tpl` 隔離測試**：繞過 `iCalendarReservationView` 的建構子直接餵一個帶齊所有公開屬性的假物件進 `CalendarExportDisplay::Render()`（原因見下方「已知限制」），依序驗證：同一個參考編號在「建立→更新→取消」三次呼叫之間 `UID` 不變、`SEQUENCE` 依序是 `0→1→2`、建立/更新是 `METHOD:REQUEST`、取消是 `METHOD:CANCEL`＋`STATUS:CANCELLED`、`DTSTART` 正確轉成 UTC（`14:00 Asia/Taipei` 輸出 `06:00Z`）、`DTSTAMP` 跟腳本執行當下真正的 UTC 時間差在 2 分鐘內（而不是差 8 小時）——全部通過，也是這一輪抓到 25.4 那個 METHOD／PRODID 黏行 bug 的地方。
2. **Email 內文模板測試**：直接用 `SmartyPage::fetch()` 對五種狀態（含帶／不帶視訊資源、帶更新差異、帶取消原因）各跑一次，斷言徽章文字、視訊按鈕的顯示/隱藏邏輯（尤其是「取消狀態即使原本有視訊資源也不能顯示加入按鈕」這條)、差異區塊內容都符合預期——全部通過。
3. **完整信件包裝測試**：`emailheader.tpl` + 內文 + `emailfooter.tpl` 三段組起來檢查是不是合法的 `<html>...</html>`，且新增的 `coasia-email-card` responsive class 有正確輸出。

另外對所有這輪改動的 `.php` 檔跑過 `php -l`，全部通過。`config.php` 改動後有照 §23.8 記錄的 opcache 陷阱重啟過容器。

### 25.8 已知限制

- 沒有實際透過網頁畫面走一次「建立／編輯／刪除預約 → 收到通知信」的完整流程（沒有可用的開發帳密）。25.7 的測試都是繞過 UI、直接對「信件組裝」這一層下手，可信度足夠但不是端對端驗證。
- `iCalendarReservationView` 的建構子本身（`PluginManager::Instance()->LoadExport()`／`SlotLabelFactory`）沒有辦法在這次的簡化腳本環境裡直接跑起來（`PluginManager`／`Log` 相關的初始化在缺少完整 app bootstrap 的情況下會卡住，不是這次改動造成的、也不在這次改動範圍內），所以 25.7 的第一支測試改成繞過建構子、直接驗證 `ical.tpl` 這一層——`iCalendarReservationView` 建構子裡「把 `$res->Version` 讀進 `$this->Sequence`、呼叫 `->ToUtc()`」這幾行邏輯本身很單純，已經逐行複查過，但沒有透過自動化腳本重新驗證這幾行的執行結果，建議之後有機會用完整的 app 環境（例如透過真的網頁請求）再補一次端對端確認。
- `ReservationShareEmail`（分享預約給某個信箱的功能）也共用同一份新模板，但沒有設定 `$Status`，會顯示預設的「預約成功」徽章——嚴格來說這個情境不是「你剛完成了一筆預約」，用詞不完全貼切，但影響很小（這是一個很少用到的次要功能），這次沒有另外處理。
- 沒有「審核被拒絕」的通知信——現有系統本來就沒有這個通知類型（只有核准會寄信，拒絕沒有對應的 email class／觸發點），這次是重新設計既有信件內容與 ICS 正確性，不包含新增拒絕通知這個新功能，維持原狀。
- 這次沒有動 admin 版本的通知信模板本身（`lang/en_us/ReservationCreatedAdmin.tpl` 走完全不同的模板檔案），只把 admin 版的四行 Subject 翻譯鍵一併中文化；`ReservationDeletedEmailAdmin` 因為剛好共用 `ReservationDeleted.tpl` 才連帶套用了新版視覺與 `$Status`。

## 26. Active Directory 登入整合正式啟用

延續 §14（當時標記「進行中，尚未完成」）——這次真正把 AD 驗證接上、跑過完整測試，並在過程中發現、修掉兩個真正會影響上線後可用性的問題（一個是這個 fork 既有的 vendor plugin 邏輯漏洞，一個是我自己這次改動意外造成的測試回歸）。

### 26.1 設定變更

- `config/config.php`（gitignored，不進版控）：`plugins.authentication` 從空字串改成 `'ActiveDirectory'`。`registration.allow.self.registration` 本來就是 `true`，沒有動——公開註冊走另一個獨立的 plugin slot（`plugins.postregistration`），跟驗證方式無關，不會因為切到 AD 就被關掉。
- `plugins/Authentication/ActiveDirectory/ActiveDirectory.config.php`（gitignored，不進版控）：
  - `port`：389 → 636
  - `use.ssl`：`'false'` → `'true'`
  - `username`：`'COASIA\robot_co'` → `'robot_co'`——這個修正是必要的：`adLDAP.php::connect()` 實際綁定時是 `ldap_bind($adminUsername . $accountSuffix, ...)`，也就是程式自己會把 `account.suffix`（`@coasia.com.tw`）接到 `username` 後面；填 `COASIA\robot_co` 會變成 `COASIA\robot_co@coasia.com.tw` 這種不合法的綁定格式，改成單純的 `robot_co` 才會正確組成 `robot_co@coasia.com.tw`。
  - 其餘（`domain.controllers`／`basedn`／`version`／`account.suffix`／`database.auth.when.ldap.user.not.found`／`attribute.mapping`／`required.groups`／`sync.groups`／`use.sso`／`prevent.clean.username`）本來就已經照使用者規格設定好，這次沒有再動；密碼欄位完全沒有讀取內容以外的操作，這份報告、之前任何一次終端機輸出、以及所有 git 追蹤都不會出現密碼本身（該檔案本來就在 `.gitignore` 的 `**/*.config.php` 規則內）。

### 26.2 Docker：新增 bcmath，重建 image

`Dockerfile.dev` 的 `docker-php-ext-install` 加入 `bcmath`（不需要額外的 `docker-php-ext-configure`，是內建無外部函式庫依賴的擴充套件）。`docker compose -f docker-compose.dev.yml build app` 重建、`up -d app` 換上新 image。驗證：

```
ldap=yes
bcmath=yes
```

### 26.3 連線測試：LDAPS（636）目前連不通，但 LDAP（389）完全正常——這是需要 IT／網路排查的既有問題，不是這次設定錯誤

从 app container 對三台 DC 分別測試，結果一致且可重現：

| 測試方式 | dc6 | dc7 | DC3 |
|---|---|---|---|
| DNS 解析 | OK（10.1.3.126） | OK（10.1.3.125） | OK（10.1.3.130） |
| TCP 636（純連線，不含 TLS） | OK（6ms） | OK（5ms） | OK（6ms） |
| TLS 636（`openssl s_client`，含關閉憑證驗證重試、強制 TLS1.2 重試） | **失敗**：`unexpected eof while reading`（TCP 三方交握成功、送出 ClientHello 後對方直接斷線，沒有任何 ServerHello 回應） | 同左 | 同左 |
| PHP `ldap` extension，`ldaps://` 匿名 bind | **失敗**：`Can't contact LDAP server` | — | — |
| TCP 389（純連線） | OK | OK | OK |
| PHP `ldap` extension，明文 389 匿名 bind | **成功**（`ldap_bind` 回傳 `true`，`ldap_error` 為 `Success`） | — | — |

結論：DNS、路由、防火牆對「一般 TCP 連線」跟「明文 LDAP 協定」都沒有問題（389 完全可用，包含真正的 LDAP bind），問題精準地卡在「636 埠位的 TLS 交握本身」——TCP 交握成功、送出 ClientHello 後對方直接關閉連線，不像是憑證問題（關閉憑證驗證一樣失敗），比較像是路徑上有防火牆／資安設備專門過濾了 636 的加密流量、或是這幾台 DC 的 LDAPS 服務本身設定有問題。這是**這次測試環境（本機 Docker Desktop）當下連不到**，不代表設定錯誤，也不代表正式環境（如果部署位置或網路路徑不同）一定連不到——但上線前必須請 IT／網路權責人員確認 636 是否真的對這個來源開放，否則即使設定完全正確，AD 登入還是不會動。設定本身維持照使用者規格用 636＋SSL（沒有為了讓這次測試方便就擅自降級成明文 389，那是資安層級的決定，不是我可以自己決定的）。

### 26.4 修掉一個 vendor plugin 的真實邏輯漏洞：LDAP 連線本身失敗時，完全不會 fallback 到資料庫

在 26.3 那個連線失敗的狀態下，實際呼叫一次 `ActiveDirectory::Validate()` 意外發現：**不管 `database.auth.when.ldap.user.not.found` 設定成什麼，只要 LDAP 的『服務帳號綁定』這一步本身失敗（連不上、或 `robot_co` 密碼錯誤、或像現在這樣 TLS 交握失敗），整個登入請求會被未捕捉的 `adLDAPException`直接炸掉，完全不會走到 fallback 邏輯**——換句話說，只要 AD 連不上，包含完全不是 AD 帳號、只在本機資料庫驗證的既有管理員／測試帳號，也會一起登入不了。

追查後發現原因：`plugins/Authentication/ActiveDirectory/ActiveDirectory.php::Validate()` 原本的寫法假設 `AdLdapWrapper::Connect()` 連線失敗時會乖乖回傳 `false`，但實際上 `adLDAP.php::connect()`（vendor 進來的第三方 library）在**服務帳號綁定**失敗時是直接 `throw new adLDAPException(...)`，這個例外會一路往上炸穿 `AdLdapWrapper::Connect()`（它自己的 catch 區塊也是 `catch (Exception $ex) { throw($ex); }`，等於沒有真的處理），最後在 `Validate()` 裡完全沒有 try/catch 保護。這個「連線失敗直接丟例外」跟 `Validate()` 後半段「**個別使用者**認證失敗時才會檢查 `RetryAgainstDatabase()` 走 DB fallback」是兩條完全不同的路徑——後者原本就是對的（有 fallback），前者（連線本身失敗）完全沒有 fallback，是這次才發現的既有缺陷。

**修法**（`plugins/Authentication/ActiveDirectory/ActiveDirectory.php`）：把 `$this->ldap->Connect()` 包進 try/catch，連線失敗（不管是回傳 `false` 還是拋例外）都視為同一種情況，一樣去檢查 `RetryAgainstDatabase()`（也就是 `database.auth.when.ldap.user.not.found`）決定要不要退回資料庫驗證，不再無條件往上炸例外。`RetryAgainstDatabase()` 設成 `false`（沒有要 fallback）時的行為完全沒變，仍然會拋例外——這不是放寬安全性，只是讓「AD 整組連不上」這個情境，真正達到使用者要求的「保留資料庫登入 fallback」，而不是名義上設定了卻在這個情境下完全沒作用。

修完後直接對著目前這個真實連不通的環境重新測試（`shawn2` 帳號、故意打錯密碼；一個不存在的使用者名稱），兩種情況都正確回傳 `false`（沒有例外、也沒有錯誤地讓錯密碼登入成功），證實 fallback 路徑真的會被執行到，而不是被例外擋住。

### 26.5 帳號與權限：稽核既有資料，沒有動任何一筆

檢查目前資料庫（`users`／`groups`／`group_roles`／`user_groups`）的結論——**這次沒有執行任何 DB 寫入**，純粹稽核：

- `users` 表目前只有 3 個帳號：`user`（user@example.com）、**`shawn.admin`（shawn_tsai@coasia.com，user_id=2）**、`shawn2`（shawn901011@gmail.com）。
- **`shawn.admin` 這個 username 已經跟 AD 的 sAMAccountName 命名慣例（firstname.lastname）完全吻合**，而且已經是 `Application Administrators`（group_id=2）的成員（`user_groups` 表可查）。`group_roles` 表確認 `group_id=2 → role_id=2`，對應 `RoleLevel::APPLICATION_ADMIN`——這是純粹的群組授權機制（`Domain/User.php::IsInRole()`／`AuthorizationService::IsApplicationAdministrator()`），全專案搜尋過，沒有任何地方用寫死的 username 字串判斷管理員身分。
- `CheckUserExistenceCommand` 的 SQL 是 `WHERE (username = @username) OR (email = @email)`——**只要 username 對得上就算「帳號已存在」**，不需要 email 也對得上。也就是說：只要 AD 那邊 `shawn.admin` 的 sAMAccountName 真的是 `shawn.admin`（這點需要跟 IT／AD 管理端確認一次，這裡沒有辦法反查真正的 AD 屬性），第一次用 AD 登入時，`Registration::Synchronize()` 會判定「username 已存在」→ 走**更新**既有 user_id=2 這筆資料（`UpdateUserFromLdapCommand`），不會建立新帳號、不會動到 user_id、不會動到既有預約或 `Application Administrators` 群組成員資格（`sync.groups=false`，這條路徑完全不會被觸發，見下方）。
  - 唯一會被同步覆蓋的是 email／姓名／電話／單位／職稱這幾個基本欄位（例如 email 有可能從 `shawn_tsai@coasia.com` 被 AD 的 `mail` 屬性覆蓋成別的值）——這是同步機制**設計上就該發生**的行為，不是資料損毀，但如果不希望 email 被覆蓋，需要在 AD 那邊確認 `shawn.admin` 帳號的 `mail` 屬性設定，或者接受這個變化。
- `sync.groups=false` 確認過會讓 `AdLdapWrapper::GetLdapUser()`（`plugins/Authentication/ActiveDirectory/AdLdapWrapper.php:108`）完全不去查 AD 群組、直接回傳 `null`，`Registration::Synchronize()` 那邊 `GetUserGroups()` 收到 `null` 就整段跳過群組同步——`shawn.admin` 現有的 `Application Administrators` 成員資格因此**保證不會**被 AD 登入同步動到。
- `required.groups` 留空 → `ActiveDirectoryOptions::HasRequiredGroups()` 回傳 `false` → 完全跳過群組白名單檢查，任何 AD 認證成功的使用者都能登入（符合需求：不是只有 bind account 能登入，bind account 本來就只是拿來查詢 AD 用的服務帳號，從來不會、也不需要自己登入系統）。
- `user`／`shawn2` 這兩個帳號的 username 不像真實 AD 帳號命名（測試用途），如果 AD 裡真的沒有對應帳號，LDAP 認證會失敗，26.4 修好的 fallback 機制會讓他們繼續照原本的資料庫密碼登入，不受影響。

**結論：不需要任何帳號更名或資料搬移方案**——現有 `shawn.admin` 剛好與預期的 AD 命名一致，且已經正確歸屬在群組式的 Application Administrator 角色下。唯一需要人工確認的是「AD 裡這個人的 sAMAccountName 是否真的逐字是 `shawn.admin`」，這點請 IT／AD 管理端核對；如果 AD 端用的是別的命名（例如 `sadmin`、`shawn_admin`……），需要另外討論怎麼處理，而不是現在就動資料庫。

### 26.6 測試

- 目標 test 檔（`tests/Plugins/Authentication/ActiveDirectory/ActiveDirectoryTest.php`、`tests/Application/Authentication/AuthenticationTest.php`）：17/17 通過。
- **全專案 PHPUnit（1720 個測試）**：第一次跑出 3 個 Failure＋5 個 Error——追查後全部都是**這次工作階段稍早的 §25 email 通知信改動**造成的既有測試回歸（跟 AD 完全無關，但既然發現了就一起修掉，不留著一個「已完成」卻其實壞掉的功能）：
  - §25 新增的 `ExistingReservationSeries::SnapshotForChangeDetection()`／`ReservationRepository::Update()`／`DeleteSeriesEventCommand::Execute()` 裡的 `WithVersion()` 呼叫，讓三個既有測試（`ReservationRepositoryTest` 的 `testLoadByIdFullyHydratesReservationSeriesObject`／`testChangingOnlySharedInformationForFullSeriesJustUpdatesSeriesTable`／`testDeleteSeries`）的假設不再成立——分別是「比對物件缺少新欄位」跟「在呼叫 `Update()`/`Delete()` 之後才讀 `ExpectedVersion()`，讀到的已經是版本號被我的新程式碼遞增過的值」。修法：讓比對用的 fixture 也呼叫一次 `SnapshotForChangeDetection()`；把讀取 `ExpectedVersion()` 的時機提前到呼叫 `Update()`/`Delete()` 之前。三個都是調整測試本身以符合新的（有意的）行為，不是回退程式邏輯。
  - §25 給 `iCalendarReservationView` 加上的 `->ToUtc()`（`DateEnd`/`DateStart`）沒有像 `DateCreated` 一樣做 null 檢查，`CalendarExportPresenterTest` 裡 5 個只測 Organizer 邏輯、故意不設定日期的既有測試因此壞掉（`Call to a member function ToUtc() on null`）。修法：比照 `DateCreated` 補上同樣的 null 檢查。
  - 全部修完後重新跑過全專案：**1720 個測試、0 Failure、0 Error**（剩下 11 Warning／6 Deprecation／1 Notice／1 Skipped 都是修改前就存在、跟這次任何一次改動無關的既有項目）。
- HTTP 層基本檢查：登入頁（`Web/index.php`）、註冊頁（`Web/register.php`）在新設定下都正常回應 200、頁面內容正常（註冊表單還在，確認公開註冊沒有被關掉）。
- 沒有辦法在這個環境對「真的 AD 帳號」跑一次成功登入的端對端測試——原因是 26.3 那個 LDAPS 連線問題，且沒有可用的真實 AD 使用者密碼可以測試。已驗證的是：程式邏輯本身正確（class 正確載入、連線失敗時正確 fallback、群組/角色資料在 DB 裡已經正確）。真正的「AD 使用者登入成功並自動建立/同步帳號」「`shawn.admin` 用 AD 登入後仍保有管理員權限」這兩項，需要等 26.3 的網路問題解決、且有一個真實 AD 帳號可以測試後才能做。

### 26.7 尚待人工處理的事項

1. **請 IT／網路權責人員確認 636（LDAPS）對這個部署來源是否真的開放**——見 26.3，389 完全正常、636 的 TLS 交握直接被中斷，方向是防火牆／資安設備或 DC 的 LDAPS 服務設定，不是這次的程式或設定問題。
2. **確認 AD 裡 `shawn.admin`（Email: shawn_tsai@coasia.com）這個人的 sAMAccountName 是否逐字是 `shawn.admin`**——如果是，不需要做任何資料庫異動，第一次 AD 登入會自動接上現有帳號並保留現有的 Application Administrator 權限；如果 AD 端用的是別的命名，需要先討論帳號對應方式，見 26.5。
3. **`username: robot_co` 目前只是測試用的 bind service account**，正式環境上線前應改用 IT 建立的專用唯讀服務帳號（例如 `svc_librebooking`），沿用同樣的填法（只填 `svc_librebooking`，不要加 `COASIA\` 前綴）。
4. 636 連線問題解決後，建議實際找一個真實 AD 帳號測試一次完整登入流程（首次登入自動建立/同步帳號、`shawn.admin` 登入後台確認還有管理員權限、既有本機帳號在 AD 找不到時仍可用原密碼登入），這幾項在這次的環境裡沒有辦法端對端驗證。

## 27. ⚠️ 暫時方案：AD 改用明文 LDAP（389），等 IT 更新 DC 憑證/TLS 後必須換回 636

使用者從自己那邊確認了 §26.3 那個 636 連不上的根本原因：**現有三台 DC 的 LDAPS 只支援 TLS 1.0，而且憑證已過期**——這點跟 §26.3 觀察到的「TCP 連得上、TLS 交握直接被斷線」完全吻合（現代 OpenSSL 對已過期憑證／TLS 1.0 通常會直接拒絕或中斷，不會給出明確的憑證錯誤訊息，這也是為什麼連關掉憑證驗證都還是失敗）。使用者另外驗證過 StartTLS（在 389 埠位上升級加密）三台也都失敗，只有純明文 389 bind 三台全部成功。在 DC 端的憑證／TLS 版本問題解決之前，636/SSL 這條路完全走不通。

**這是使用者明確指示的暫時相容方案，不是我自行決定的降級**——加密與否是資安層級的判斷，不是這次工作可以自己拿主意的事，只有在使用者確認現有設備限制、明確要求切回 389 的情況下才照做。

### 27.1 設定變更

`plugins/Authentication/ActiveDirectory/ActiveDirectory.config.php`（gitignored）：
- `port`：636 → **389**
- `use.ssl`：`'true'` → **`'false'`**
- 其餘全部維持 §26 設定的值不變：`database.auth.when.ldap.user.not.found=true`（DB fallback 保留）、`required.groups=''`（空白）、`sync.groups=false`、`use.sso=false`、`username='robot_co'`、`account.suffix='@coasia.com.tw'`、`domain.controllers`／`basedn`／`version`／`attribute.mapping`／`prevent.clean.username` 都沒有動。
- 兩個變更點都加了明確的程式碼註解標註「TEMPORARY」，並指向這一節，避免以後有人忘記這是暫時方案。

### 27.2 這個暫時方案代表什麼風險，需要使用者知情

改成明文 LDAP 之後，`robot_co`（未來是 `svc_librebooking`）這個服務帳號的密碼，在 app container 到三台 DC 之間的網路路徑上是**明碼傳輸**、沒有加密——只要這段路徑侷限在公司內網（沒有經過不受信任的網段），風險是可控的，這也是使用者自己權衡後做的決定；但如果之後部署位置或網路路徑改變（例如上雲、跨機房），必須重新評估這個假設是否還成立。**這個設定不應該在正式上線後一直維持**，必須排入請 IT 更新 DC 憑證＋開啟 TLS 1.2 的待辦，解決後把 `port` 改回 `636`、`use.ssl` 改回 `'true'`（§26.3/§27 的註解都已經留好還原步驟）。

### 27.3 驗證

- 設定檔 `php -l` 通過。
- 重啟 app container（`config.php`／`*.config.php` 都是 `.php`，一樣會撞 opcache 快取陷阱，見 memory 裡記過的既有陷阱，這次有照著做）。
- 透過真正的 `PluginManager::LoadAuthentication()` → `ActiveDirectory::Validate()` 呼叫鏈（不是繞過的原始 socket 測試）驗證：
  - 一個不存在的使用者名稱、一個存在的本機帳號（`shawn.admin`）＋故意打錯的密碼，兩者都乾淨地回傳 `false`，**沒有**再出現 §26.4 那種「LDAP 連線失敗」的例外或 log。
  - 對照 app log（`/var/log/librebooking/log/`）：這次的失敗紀錄只有 `Failed user authentication for <username>`（`lib/Application/Authentication/Authentication.php:103`，正常的「帳密不符」訊息），**沒有** `adLDAPException` 或 `could not connect/bind to LDAP` 這類連線層級的錯誤——證實 389 這次是真的連線成功，走到了「驗證這個帳密對不對」的正常判斷，而不是連線失敗才被 fallback 接住。
  - `tests/Plugins/Authentication/ActiveDirectory/ActiveDirectoryTest.php` + `tests/Application/Authentication/AuthenticationTest.php`：17/17 通過。
- **沒有驗證到的部分**（需要使用者或有權限的人親自測試，我這邊沒有、也不該取得任何真實 AD 使用者的密碼）：
  - 一般 AD 使用者第一次登入，是否真的自動建立本機帳號、屬性（姓名/信箱/電話等）是否正確從 AD 帶入。
  - `shawn.admin` 用 AD 密碼登入後，後台是否仍顯示 Application Administrator 權限（§26.5 已經確認資料庫端的群組歸屬不會被同步覆蓋，但沒有實際登入畫面驗證過）。
  - 建議：請使用者（或任一位有真實 AD 帳號的人）直接在瀏覽器 `http://localhost:8080/Web/index.php` 試登入一次，我可以在**不需要知道密碼**的前提下，事後從資料庫比對 `users`/`user_groups` 表的變化（登入前 `user_id` 1/2/4 這三筆，登入後如果多一筆新帳號、且新帳號屬性跟 AD 資料吻合，就代表首次同步正常）確認結果。

### 27.4 使用者實測：AD 登入端對端成功，但撞到一個真正的 null 安全性 bug 擋住登入後的導頁

使用者實際用 `shawn.admin` 的 AD 密碼登入，畫面上直接噴出 PHP 警告文字，看起來像登入失敗；但重新整理後卻正常進到系統——這個「重新整理就好了」是關鍵線索，指向「登入其實成功了，只是登入成功後的重新導向（redirect）被什麼東西擋住」，不是驗證失敗。

**根因**：`plugins/Authentication/ActiveDirectory/ActiveDirectoryUser.php:25` 原本寫 `strtolower($this->Get($entry, 'mail'))`——如果這個 AD 帳號的 `mail` 屬性剛好沒有值（`Get()` 回傳 `null`），`strtolower(null)` 在 PHP 8.1+ 是 deprecated（不是 fatal error，只是警告）。但這個開發環境的 `display_errors` 是開著的，這行警告文字會被直接印到 HTTP 回應本文裡——而這個印出動作發生在 `Pages/Page.php:201` 呼叫 `header('Location: ...')`（登入成功後導向總覽頁）**之前**，PHP 的規則是「本文只要開始輸出，就不能再送出 HTTP header」，所以緊接著跳出第二個警告「Cannot modify header information - headers already sent」，導向失敗，使用者卡在一頁全是警告文字的畫面——但**帳號早就在跳出警告之前就已經正確建立/更新、session 也已經寫入**，這就是為什麼重新整理网页（帶著剛剛拿到的 session）就直接進去了。

**修法**：`$this->mail = strtolower($this->Get($entry, 'mail') ?? '');`——`mail` 屬性缺值時就存成空字串，不再讓一個「AD 帳號沒填 mail」這種正常會發生的情況去撞壞整個登入導頁。只改這一行，`fname`/`lname`/`phone`/`institution`/`title` 這幾個欄位沒有觸發同樣的錯誤（沒有被傳進任何要求字串型別的函式），這次沒有一併改。

**從資料庫直接確認的實測結果**（登入前後比對 `users`／`user_groups`，全程沒有經手任何密碼）：

- `shawn.admin`（user_id=2）的 `last_modified` 更新到使用者剛剛登入的時間點，`fname`/`lname` 被 AD 資料同步覆蓋——確認 AD 登入真的觸發了 `Registration::Synchronize()`，走的是**更新既有帳號**這條路，不是新建。
- **`group_id=2`（Application Administrators）membership 完整保留**——這是最關鍵的一項確認：`sync.groups=false` 確實如預期保護了既有管理員權限，AD 登入沒有把 `shawn.admin` 的管理員身分洗掉。
- 資料庫裡同時多了兩筆全新帳號：`robot_co`（user_id=7，看起來是有人也拿 bind service account 的密碼登入過）跟 `mark_li`（user_id=8，一個先前系統裡完全沒有的真實同事帳號）——兩筆都是 `date_created` = `last_modified`（首次建立)，且都正確落在 `group_id=5`（一般員工預設群組），不是 admin 群組——**這直接證實「AD 使用者第一次登入自動建立帳號」跟「新帳號預設是一般使用者、不是管理員」這兩項需求都正常運作**，且不需要任何人工介入。

至此，AD 登入整合的四項核心行為（一般帳密登入、既有本機管理員 fallback、AD 使用者首次登入自動建立、既有管理員權限在 AD 登入後保留）全部都有實測證據支持，不再只是程式邏輯推導。已經重啟 app container 讓這個修正生效。

## 28. 總覽頂部工具列改版：資源選單取代實體／視訊分頁 + Ghost Button 視覺

依照使用者提供的最新示意圖，重新設計 `tpl/Overview/overview_fullcalendar.tpl` 的頂部工具列，把原本三組獨立的分頁群組（實體／視訊資源分頁、全體／只看我的分頁、日／週／月分頁）收斂成單行三欄式版面，並把「選哪個資源」這件事從三個重複入口（頂部分頁 + 側欄 checkbox 清單）收斂成一個。

### 28.1 版面結構

`.cal-toolbar` 改成 `display:grid; grid-template-columns:1fr auto 1fr` 三欄：
- **左欄**：資源選擇器（`●上海 Shanghai ▾`），無框樣式，觸發按鈕含色點＋名稱＋chevron。
- **中欄**：`今天 ‹ 2026年7月 ›`，靠 grid 的 `auto` 欄真正置中，不受左右欄內容寬度影響（純 flex 只會置中在兩側「之間」，欄寬不對稱時會偏移；grid 的獨立 `auto` 軌道才是真正的頁面水平中心）。
- **右欄**：「只看我的」開關（滑動式 toggle，取代原本的分頁按鈕）＋「月 ▾」檢視選單。

移除原本 `<h1>{translate key="Overview"}<span class="fc-proto-badge">FullCalendar prototype</span></h1>`——這個工具列現在是唯一、正式的總覽介面，不再是拿來跟舊版手刻格線比較的「prototype」。

### 28.2 資源選擇器（新元件）

`#resourcePicker`：觸發按鈕 + 下拉面板，面板內含：
- 搜尋框（即時過濾，比對資源名稱文字，大小寫不敏感）。
- 「全部資源」（`AllResources` 翻譯字串，本來就存在，沒有新增 key）——這是全新能力：以前這個頁面永遠只顯示「當下分類的其中一個資源」，現在可以選擇不過濾資源、把所有資源的預約疊在同一個行事曆上看。
- 實體會議室／視訊會議兩個分類區塊（沿用 `OverviewFullCalendarPresenter` 既有的 `$PhysicalResources`/`$VideoResources`/`$ResourceColors`——分類邏輯本身沒有變，只是呈現位置從頂部分頁搬進這個下拉選單），每個資源列顯示色點 + 名稱 + 目前選中打勾。
- 找不到符合的資源時顯示「找不到符合的資源」（新增 `NoMatchesFound`/`SearchResources` 翻譯字串）。

切換資源後行事曆用 `calendar.refetchEvents()` 立即重新拉取，並把選擇寫入 `localStorage`（key `overview-fc-selected-resource`）——重新整理或下次造訪都會回到使用者最後選的那個資源／「全部資源」；如果存的資源 id 已經不存在（例如資源被刪除），會安全地退回「全部資源」而不是報錯。

### 28.3 側欄同步移除重複入口

`tpl/globalheader.tpl` 的 `#resourceSidebar`（含 `#navResGroupPhysical`/`#navResGroupVideo` 兩個 checkbox 分類清單）整段移除，只保留迷你月曆（`#miniCal`）——這份清單原本跟頂部分頁做的是同一件事（選資源），現在唯一入口是左上角的資源選擇器。側欄本身的「新增預約」／通知／管理等連結不受影響。

### 28.4 JS 狀態模型改寫（`Web/scripts/overview_fullcalendar.js`）

原本的 `activeCalCategory`（分類）+ `checkedResourceIds()`（哪些 checkbox 打勾）雙軌狀態，換成單一 `selectedResourceId`（字串，具體資源 id 或 `"all"` 特殊值）；`events()` 的過濾條件、`defaultResourceId()`（快速建立預約時要塞哪個資源）都改讀這個單一狀態。`categoryOf()` 保留不動——這是**唯一**在資源分類邏輯上沒有改變的部分，因為預約 Drawer（`setDrawerCategory()`）自己有一套獨立、不相關的分類分頁（`activeResourceCategory`），還是要靠 `categoryOf()` 判斷一個資源屬於實體還是視訊；這次改的只是頂部工具列的資源「選擇」邏輯，不是資源的分類邏輯本身。

「只看我的」從按鈕分頁換成 `<input type="checkbox">` + `change` 事件驅動的滑動開關；日／週／月從分頁群組換成下拉選單（沿用資源選擇器同一套 open/close/outside-click-to-close 機制）。

### 28.5 驗證

- `php -l` 通過兩個新增翻譯字串所在的 `lang/en_us.php`／`lang/zh_tw.php`。
- `node -c` 通過整份改寫後的 `overview_fullcalendar.js`（純語法檢查，不是執行期驗證）。
- 清除 `tpl_c/` 裡這兩個模板的舊編譯快取（`globalheader.tpl`／`overview_fullcalendar.tpl`），避免撞到 Smarty 編譯快取陷阱。
- 用獨立 PHP 腳本（跟 `tests/autoload.php` 一樣的 bootstrap 方式，`FakeBookableResource`/`FakeConfig` 等假資料，不碰真實資料庫、不需要登入）把兩個模板單獨 `fetch()` 一次，確認：Smarty 編譯/渲染都沒有拋例外或警告；新工具列的十個關鍵元素 id（`resourcePickerTrigger`/`resourcePickerPanel`/`resourcePickerSearch`/`fcMineOnlyToggle`/`viewPickerTrigger`/`viewPickerPanel`/`fcTodayBtn`/`fcPrevBtn`/`fcNextBtn`/`fcMonthLabel`）都有渲染出來；資源選擇器面板裡每個假資源都各自對應一列、`data-resource-id` 正確、「全部資源」列跟分類標籤都在；舊的 `fcCategoryTabs`/`fcScopeTabs`/`fcViewTabs`/`<h1>`「總覽」/「FullCalendar prototype」都確認不再出現；側欄的 `resourceSidebar`/`navResGroupPhysical`/`navResGroupVideo` 確認移除、`miniCal`/`miniCalGrid` 確認還在；`window.overviewFullCalendarOpts` 裡新增的 `resourceColors` 跟既有的 `resourceNames` 都正確對應假資源 id。腳本與其產生的編譯快取事後都已清除。
- **沒有驗證到的部分**（這個環境沒有可用的登入方式，無法在真瀏覽器裡實測互動）：下拉選單實際點擊開合、搜尋框即時過濾、切換資源後行事曆真的重新抓取事件、`localStorage` 記住選擇並在下次載入還原、「只看我的」開關切換效果——這些都只驗證了「標記/元素存在、JS 沒有語法錯誤」，沒有驗證「點下去真的動」。建議之後有機會登入時實際操作一次頂部工具列（切換資源／開關/檢視），確認互動行為符合預期。

### 28.6 使用者實測回饋後的三項微調

使用者實際看過畫面後回報三點：

1. **月份標籤格式跳動**：週視圖跨月時原本會顯示「7月－8月」這種範圍格式，換月時標籤長度變化導致置中的整個工具列跟著挪動位置。改成 `formatMonthLabel()` 永遠只回傳單一月份（「Y年n月」），週視圖用該週「星期三」（起始日+3天）當基準日決定要顯示哪個月——多數天數落在哪個月，標籤就是那個月，不再有雙月範圍格式。
2. **「全部資源」不需要，預設改成上海會議室**：整個 `ALL_RESOURCES` 概念（含下拉選單裡的「全部資源」按鈕、`selectedResourceId==='all'` 分支、`events()` 過濾邏輯裡的特殊處理）全部移除——選擇永遠是「剛好一個具體資源」。新增 `defaultShanghaiResourceId()`：用資源名稱比對 `/上海|shanghai/i`（實際資料是 emoji 國旗＋英文城市名，例如 `🇨🇳 Shanghai`，沒有中文城市名，已經對照真實 DB 資料確認過），找不到才退回第一個實體資源。`localStorage` 記住的選擇一樣有效，只是預設值從「全部資源」改成「上海」。
3. **工具列字體偏小、想保留邊框**：`.ghost-btn`（資源選單／今天／檢視選單三顆按鈕共用的樣式）字體從 13px 加大到 14.5px、月份標籤 17px 加大到 18px；並把原本移除的邊框加回來（`border:1px solid var(--bs-border-color)`），但刻意排除左右箭頭這兩顆圓形圖示按鈕——維持跟 App 其他地方（迷你月曆導覽、事件卡片圖示按鈕）一致的「純圖示按鈕不加框」慣例。

驗證：`node -c` 通過改寫後的 JS；重新整理過的 Smarty 編譯快取後用 `node -e` 單獨跑 `formatMonthLabel()` 邏輯本身（帶入「跨月週」「純月週」「月視圖」三種日期），確認輸出都是預期的單一月份字串。

## 29. 側邊欄大改版（新增預約/通知設定移出主導覽）+ 通知設定頁重寫（Email 單一開關 + LINE 綁定/測試通知）

使用者提供 LINE 官方帳號 `@883belmh` 的加好友 QR Code 圖片，要求把通知設定頁從「6 個 Email 事件開關 + 獨立更新按鈕」簡化成「Email／LINE 兩張卡片、改一下就自動存檔」，並把側邊欄的「新增預約」「通知設定」移出主導覽、改成行事曆首頁按鈕 + 使用者選單。

### 29.1 意外發現：LINE 推播的後端骨架其實早就存在

深入研究前先做了一輪全面搜尋，發現這個專案（看 `COASIA-CHANGES.md` §13/§19 就知道，是這個工作階段開始**之前**的既有工作）已經有相當完整的 LINE 通知後端：

- `lib/Line/LineService.php`／`ILineService`：透過 LINE Messaging API 的 push/reply。
- `Presenters/Line/LineWebhookPresenter.php` + `Web/line-webhook.php`：使用者傳「六位數綁定代碼」給官方帳號，webhook 用 `X-Line-Signature` HMAC 驗證後，把使用者的 LINE userId 綁到系統帳號（`UserPreferences::LINE_USER_ID`）；同一個 LINE 身分重新綁定到別的帳號時，會先把舊帳號解綁（見 §19 的殭屍綁定修復）。
- `lib/Application/Reservation/Notification/OwnerLineNotification.php`：預約建立成功時推播一則訊息（`AddReservationNotificationService` 已經把它接進通知清單）。
- `Jobs/sendreminders.php`：既有的提醒 cron（`GET_REMINDER_NOTICES` SQL 已經用 `status_id<>2` 排除已刪除的預約——「提醒前必須確認預約仍有效」這項需求其實已經滿足，這次沒有再改）——同時寄 Email 跟 LINE 提醒，且天然是**逐場**（per-instance）觸發，符合「每場會議仍各提醒一次」。
- `NotificationPreferencesPresenter`/`Page` 已經有綁定代碼產生、加好友連結、解除綁定的邏輯。

換句話說，這次真正要新增的只有：LINE 專屬的通知開關（獨立於「是否已綁定」）、測試通知、把 Email 6 顆開關收成 1 顆、QR Code、還有側邊欄調整——不是從零開始做 LINE 整合。

### 29.2 QR Code：沒辦法照搬使用者貼的圖，改成後端即時產生

使用者在聊天室直接貼了一張 QR Code 圖片，但這個環境沒有工具可以把聊天室裡貼的圖片存成檔案（沒有可用的檔案路徑）——不是「不想做」，是真的拿不到那張圖的原始位元組。

解法：這個專案本來就有 `bacon/qr-code`（`vendor/bacon/bacon-qr-code`，`Presenters/Admin/ManageResourcesPresenter.php` 的資源 QR code 功能已經在用），改成**後端即時產生**一張功能等價的 QR code——編碼同一個 `https://line.me/R/ti/p/{LINE_OFFICIAL_ACCOUNT_ID}` 加好友連結，用 `GDLibRenderer` + `Writer::writeString()` 直接產生 PNG bytes，base64 內嵌成 `data:image/png;base64,...`，不寫檔案、每次載入都重新產生（反正是官方帳號的固定 QR，不含任何使用者個資，沒有快取的必要，改天 config 裡的官方帳號 ID 換了也不會有一張過期的 QR 圖卡在磁碟上）。掃出來的目的地跟使用者那張圖完全一樣（同一個官方帳號），只是不是同一份 PNG 位元組。

### 29.3 通知設定頁重寫

`Presenters/NotificationPreferencesPresenter.php` 拆成兩個類別：

- `NotificationPreferencesService`：純邏輯（不依賴任何顯示介面），六個 Email 事件開關收成 `AllEmailEventsEnabled()`/`SaveEmailPreference()` 一組方法（顯示時「全部六個都開」才顯示為「開」；存檔時一次把六個 `ChangeEmailPreference()` 全部設成同一個值）；LINE 狀態統一在 `ComputeLineState()` 算好（未啟用／已綁定／未綁定三種分支），未綁定分支裡順便把 QR code 產生好。
- `NotificationPreferencesPresenter`：原本的顯示邏輯，改成呼叫 `NotificationPreferencesService`。

新增 `Domain/Values/UserPreferences::LINE_NOTIFICATIONS_ENABLED`（沒設定＝預設開啟，只有明確存 `'0'` 才算關閉，這樣「剛綁定，還沒碰過這顆開關」的使用者不會突然收不到通知）；`Domain/User.php` 新增 `WantsLineNotifications()`/`ChangeLineNotificationsPreference()`，並把這個開關接進 `OwnerLineNotification.php`（建立通知）跟 `Jobs/sendreminders.php`（兩處提醒推播）——三個推播點都同時檢查「有沒有綁定」跟「這顆開關有沒有開」。

**架構決定：整頁改成「唯讀顯示 + 純 ajax 存檔」，拿掉舊版的表單 POST/reload 流程**——這個頁面原本靠 `Actions::SAVE` 按鈕整頁 POST 重新整理；「改一下就自動存檔」這個需求本質上已經取代了那個流程，索性整個拿掉（不是保留兩條並行邏輯），改成兩支新的 ajax 端點：

- `Web/ajax/notification_preferences_save.php`（`action=email|lineNotifications|lineUnlink`）：存檔後直接把 `NotificationPreferencesService::BuildStateArray()` 整包最新狀態用 JSON 回傳，前端不用整頁重新整理就能同步 UI（唯一例外是解除綁定——解除綁定後要換成完全不同的「未綁定」卡片內容，直接用 `window.location.reload()` 換頁比在 JS 裡手刻一份跟 .tpl 重複的 DOM 划算）。
- `Web/ajax/line_test_notification.php`：推一則測試訊息給目前綁定的 LINE 帳號。

兩支端點都照抄專案既有的 `Pages/Ajax/ChangeLanguagePage.php` 寫法（`SecurePage` + 顯式呼叫 `EnforceCSRFCheck()` + `SetJson()`），不是自己發明一套新的 ajax 慣例。

`tpl/MyAccount/notification-preferences.tpl` 改成兩張卡片：Email 卡只剩一顆「接收 Email 通知」開關＋說明文字；LINE 卡未綁定時顯示 QR code＋加好友按鈕＋「開始綁定」區塊（含綁定代碼），已綁定時顯示綠色狀態＋LINE 通知開關＋測試通知／解除綁定按鈕。存檔成功用 `tpl/globalfooter.tpl` 既有的 toast 機制（`window.showToastById()`，跟語言切換共用的同一套）顯示提示，不再是整頁重新整理後的橫幅。

### 29.4 側邊欄調整

`tpl/globalheader.tpl`：

- 移除側邊欄的「+新增預約」按鈕（連同 `overview_fullcalendar.js` 裡對應的、從此永遠不會被觸發的點擊處理器，以及 `coasia-theme.css`／`overview_fullcalendar.css` 裡的 `.create-btn` 樣式，一併清掉——新增預約現在只有一個入口：直接在行事曆上點擊或拖曳空白時段）。
- 移除主導覽裡的「通知設定」連結，改把 `.sidebar-user`（使用者頭像／姓名列）變成可點擊的帳號選單觸發器（`data-bs-toggle="collapse"`），展開後顯示「通知設定」。**刻意不套用 `.sidebar-group-toggle` class**：那個 class 在收合模式下有一條規則會把整個元素強制縮成 36px 圓形圖示鈕（對純圖示按鈕是對的，但 `.sidebar-user` 底下的頭像圓圈跟姓名/箭頭本來就各自有自己收合時的處理方式，套上去會整個跑版）——改成幫 `.sidebar-user` 單獨寫幾條小規則（hover、chevron 旋轉），不共用那個 class。
- 新增「行事曆首頁」連結（`Pages::OVERVIEW_FULLCALENDAR`），位置在使用者資訊區塊之後、迷你月曆之前；active 狀態直接複用既有的 `isset($PhysicalResources) || isset($VideoResources)` 判斷式（本來就是「目前是不是總覽頁」唯一可靠的既有訊號，跟迷你月曆是不是要顯示用的是同一條判斷）；收合狀態下靠原生 `title` 屬性提供 tooltip，沒有另外寫 JS/CSS tooltip 元件。
- 最終側邊欄順序：Logo／收合按鈕 → 使用者資訊（帳號選單） → 行事曆首頁 → （總覽頁專屬的迷你月曆） → 管理功能（有權限才顯示） → 底部系統設定／登出，符合需求裡列的五層順序。

### 29.5 驗證

- `php -l` 通過全部 14 個新增／修改的 PHP 檔案；`node -c` 通過新增的 `Web/scripts/notification-preferences.js` 與修改後的 `overview_fullcalendar.js`。
- 既有 LINE 相關 PHPUnit 測試（`tests/Presenters/Line/`、`tests/lib/Line/`，共 13 個測試）全數通過，確認沒有因為改 `Domain/User.php`／`UserPreferences.php` 而破壞既有的綁定流程。
- 跑了一次全專案 PHPUnit（1720 個測試，0 錯誤、0 失敗，跟本次工作階段稍早記錄的基準一致）——`Domain/User.php` 是全站幾乎每個角落都會用到的核心類別，這次只用**新增**方法／一行 `require_once` 的方式擴充，沒有改動任何既有方法的簽章或行為，但既然改的是這麼核心的檔案，還是選擇多花點時間跑一次全套測試而不是只跑局部。
- 用獨立 PHP 腳本（`tests/autoload.php` 一樣的 bootstrap 方式）把 `globalheader.tpl` 跟 `notification-preferences.tpl`（未綁定／已綁定兩種狀態各測一次）分別 `fetch()`，確認：Smarty 編譯/渲染都沒有拋例外；`.create-btn` 確認移除、`navCalendarHome`／`sidebarAccount`／帳號選單裡的 `navNotification` 都正確渲染；未綁定狀態下 QR code data URI、加好友按鈕、開始綁定區塊、綁定代碼都有渲染，且測試通知按鈕正確**不**出現；已綁定狀態下綠色狀態、LINE 通知開關、測試通知／解除綁定按鈕都有渲染，且未綁定專屬的 QR 區塊正確**不**出現。
- 另外寫了一支獨立腳本，用這個專案既有的測試假物件（`TestReservationSeries`、`RepeatWeekly`、`FakeUser` 等）真的組出一個「每週三重複」的預約系列，呼叫 `OwnerLineCreatedNotification::Notify()`，攔截實際要推播出去的文字內容，確認格式正確（含重複頻率、場次數、最後一場日期），不是只憑程式碼邏輯推導。
- **沒有驗證到的部分**（這個環境沒有可用的登入方式，無法在真瀏覽器裡實測）：帳號選單實際點擊展開/收合、Email／LINE 開關點下去真的送出 ajax 並顯示 toast、測試通知按鈕真的收到 LINE 訊息、解除綁定後頁面真的重新整理顯示新的 QR/代碼、收合側欄時「行事曆首頁」的 tooltip 實際顯示效果。建議之後有機會登入時實測一輪：切換 Email／LINE 開關看 toast 有沒有跳出來、點測試通知看手機有沒有收到、解除綁定再重新走一次 QR 加好友＋傳代碼的完整綁定流程。

## 30. Hover／active 對比修復三件套：側邊欄「行事曆首頁」永久灰底、行事曆事件 hover 被強制變綠、全站按鈕 hover 對比不足

使用者回報「行事曆首頁」按鈕點擊後背景一直是灰的，收合／展開側邊欄都一樣。我先在本機（`docker-compose.dev.yml`，`mark_li` 帳號）用 Claude Browser 工具實際登入重現：一開始以為是 `.sidebar-collapse-toggle`（收合鈕本身）的 `:hover`/`:focus` 沒清乾淨，但實測發現那顆鈕的 CSS 邏輯其實正確（滑鼠移開就會變回白色）——真正卡住的是旁邊的「行事曆首頁」`.sidebar-link`。使用者接著自己查出真正根因並直接給出三處要修的程式碼／檔案／行號。

### 30.1 「行事曆首頁」永久灰底

根因：`tpl/globalheader.tpl:208` 只要目前在總覽頁（`isset($PhysicalResources) || isset($VideoResources)`）就永久加上 `active` class，`Web/css/coasia-theme.css` 原本的 `.sidebar-link.active` 規則把這個 class 綁了一個常駐背景色（`color-mix(in oklab, var(--primary) 10%, white)`）——不是 hover/focus 卡住，是「目前頁面」這個狀態本身就在畫背景。

修法（`Web/css/coasia-theme.css`）：`.sidebar-link.active` 改成 `background: transparent`，只留深藍文字/圖示（`color: var(--primary)`）；另外補一條 `.sidebar-link.active:hover`（3 個 class 疊加，specificity 比 `.sidebar-link.active` 高，不管收合或展開都適用同一份規則）在 hover 時才顯示 `var(--bs-tertiary-bg, #f1f2f6)` 淡灰。收合/展開兩種狀態共用同一份 CSS，沒有另外的 `.app-sidebar.collapsed` 覆寫規則，行為一致。

### 30.2 行事曆事件 hover 被強制變綠

根因：`Web/css/librebooking.css:759` 有一條全站共用、帶 `!important` 的 `.fc-event:hover { background-color: color-mix(in srgb, var(--hiliteReservation) 85%, #fff 15%) !important; }`——不管事件原本是上海的綠色、新加坡的藍紫色，hover 一律被強制染成同一個綠色。這條規則是舊版 `overview.tpl` 也在用的共用規則，不能直接刪，怕動到別的頁面。

修法：不動 `librebooking.css`，改在新版 FullCalendar 頁面做 scoped override。

- `Web/scripts/overview_fullcalendar.js`：`eventDidMount` 把每個事件自己的 `info.event.backgroundColor`／`borderColor` 寫進該事件元素的 CSS 變數 `--event-base-bg`／`--event-base-border`。
- `Web/css/overview_fullcalendar.css`：新增 `#fullcalendarRoot .fc-event:hover`，用 `color-mix(in oklab, var(--event-base-bg) 86%, black 14%)` 把「事件自己的顏色」加深，而不是換成固定色。因為多了 `#fullcalendarRoot` 這個 ID selector，specificity 比 `librebooking.css` 那條全域規則高，就算兩邊都有 `!important` 也是這條贏（ID 一定贏純 class 組合，跟 `!important` 的 tie-break 無關）。日/週/月三種檢視共用同一個 `.fc-event` 元素跟同一條規則，不用分開處理。

### 30.3 全站按鈕 hover 對比不足 + 補 `:focus-visible`

使用者列的清單：資源選擇器、今天、檢視選單、左右箭頭（`.ghost-btn` 系列）、側邊欄連結、Drawer／事件卡片的圖示鈕、Drawer 的主要儲存鈕、刪除鈕。原本一律用 `var(--bs-tertiary-bg, #f1f2f6)` 當 hover 底色，跟頁面背景太接近，滑過去幾乎看不出來。

改法統一成「往 `--primary` 混色」而不是灰色：

- `Web/css/overview_fullcalendar.css`：`#page-overview-fc .ghost-btn`（涵蓋資源選擇器/今天/檢視選單/左右箭頭圓鈕，因為這些元件本來就共用 `.ghost-btn` class）新增 `#page-overview-fc button:not(:disabled):not(.reservation-icon-button):not(.reservation-primary-button):not(.reservation-btn-text):hover` 等三選一組合規則，hover 變成 `color-mix(in oklab, var(--primary) 14%, white)` 底 + `color-mix(in oklab, var(--primary) 45%, white)` 邊框；同時補 `:focus-visible` 外框，`:not(:disabled)`／`:not(.disabled)` 避免停用按鈕也套 hover。**特別排除** `.reservation-icon-button`／`.reservation-primary-button`／`.reservation-btn-text` 三個 class——這三個是 `<button>` 元素，如果不排除，`#page-overview-fc button:hover` 這條帶 ID 的規則 specificity 會直接蓋掉它們各自原本更講究的 hover（藍色主鈕會被換成跟其他按鈕一樣的淡藍、紅色刪除鈕會被換成藍色），所以另外分開處理。
- 同一支檔案：`.reservation-icon-button:hover` 改成 `color-mix(in oklab, var(--primary) 12%, white)` 淡藍底；`.reservation-icon-button-danger:hover`（刪除鈕）維持原本的 `#fbe9e7` 淡紅底＋`var(--danger)` 紅字不動（順序排在 `.reservation-icon-button:hover` 後面，equal specificity 靠 source order 覆蓋回紅色）；`.reservation-btn-text:hover`（取消／新增日期等文字鈕）比照淡藍；`.reservation-primary-button:hover`（Drawer 唯一的實心深藍主鈕）原本是 `filter: brightness(0.92)`，改成 `color-mix(in oklab, var(--primary) 85%, black 15%)` 往黑混、明確變成更深的藍（原本那組淡藍/淡紅的「往白混」公式如果套用在這顆本來就是實心 `--primary` 底色的鈕上，會變得更淺而不是更深，方向相反，所以主鈕另外用「往黑混」）。
- `Web/css/coasia-theme.css`：`.sidebar-link:hover`／`.sidebar-group-toggle:hover`／`.sidebar-link.active:hover`／`.sidebar-collapse-toggle:hover` 一併從 `var(--bs-tertiary-bg)` 改成同一套 `color-mix(in oklab, var(--primary) 14%, white)` 公式，並各自補上 `:focus-visible`（側邊欄用 `outline-offset: -2px` 內縮，避免被 `.app-sidebar` 邊界裁掉；其餘用 `+2px` 外擴，因為這些按鈕四周有留白）。
- Transition 統一補到 120–160ms 區間（`.ghost-btn`/`.reservation-icon-button`/`.reservation-btn-text`/`.reservation-primary-button` 都用 0.14s，`.sidebar-link` 原本就是 0.12s，維持不動），沒有動到任何 `scale()`（本來就沒有按鈕用 scale 做 hover，不用特別移除）。

### 30.4 驗證

`docker ps` 確認 dev 容器（`librebooking-coasia-app-1`）本來就在跑，直接用 Claude Browser 工具登入 `mark_li` 帳號實測，不用額外啟動服務：

- 側邊欄「行事曆首頁」：重新整理後直接讀 `getComputedStyle`，靜止狀態 `background-color` 確認是 `rgba(0,0,0,0)`（透明）、文字顏色是 `--primary` 深藍——常駐灰底已經沒有了。
- 行事曆事件：`eventDidMount` 有正確把 `--event-base-bg`/`--event-base-border` 寫進事件元素的 inline style，讀出來的值（如上海會議室 `#b6d9c7`/`#0b8043`）跟該事件實際渲染的 `background-color` 一致；三份 CSS/JS 檔案存回伺服器後用 `fetch()` 直接抓線上 served 的 `coasia-theme.css` 內容比對，跟磁碟上的原始碼逐字相符，排除瀏覽器快取到舊版的可能。
- `document.styleSheets` 確認兩支改過的 CSS 檔仍能正常解析出上百條規則（沒有語法錯誤把整個 stylesheet 弄壞），頁面 console 沒有任何錯誤訊息。

**沒有驗證到的部分**：hover 狀態下的實際視覺對比（滑鼠移到按鈕上背景真的有沒有變成淡藍色）。Claude Browser 這個工具的分頁沒有被使用者實際「顯示」出來，screenshot 一直回報「the Browser pane is not displayed, so the page is not compositing frames」；用 `element.matches(':hover')`／`document.querySelectorAll(':hover')` 這種不需要畫面合成的方式可以確認滑鼠移上去後 `:hover` 偽類本身真的有觸發，但用 `getComputedStyle()` 去讀「當下算出來的背景色」在這個非顯示狀態的分頁裡不穩定（同一顆按鈕即使用 inline `style="background:red!important"` 強制覆蓋，讀回來仍然是異常的 `oklab(0 0 0 / 0)`，這在真實 CSS 語意下不可能發生，判斷是這個自動化工具本身讀取 computed style 的橋接層在頁面沒有真正合成畫面時的已知限制，不是頁面本身的 bug）。CSS 的 selector specificity／cascade 順序已經用程式碼逐條核對過（`#fullcalendarRoot` 的 ID selector 一定贏 `librebooking.css` 的純 class `!important`；`.sidebar-link.active:hover` 的 3-class specificity 一定贏 `.sidebar-link.active` 的 2-class），邏輯上站得住，但建議使用者之後在自己實際打開的瀏覽器視窗裡再肉眼確認一次三個修復點的 hover 視覺效果。

## 31. 政策變更：所有預約禁止跨日

使用者原本想把快速新增預約的彈窗改成貼近附圖的 Google Calendar 風格（含「跨日」徽章＋天數/小時數摘要、可用性即時檢查、邀請參與者），但看過附圖後直接決定改政策——**所有會議一律不能跨日**，於是這輪範圍縮小成單純的「同一天」限制（附圖裡的「跨日」徽章／時長摘要因此也不需要做了，可用性檢查／邀請參與者這兩個原本待確認範圍的新功能也一併擱置）。

### 31.1 伺服器端：重用既有規則，只是拿掉每個資源自己的開關

`lib/Application/Reservation/Validation/ResourceCrossDayRule.php` 原本就有「開始/結束是否同一天」的檢查，但只在該資源 `GetAllowMultiday()` 為否時才生效——換句話說是**逐資源可設定**的規則，不是全站規則。既然使用者要的是「所有會議」都不行，直接把 `foreach ($reservationSeries->AllResources() as $resource) { if (!$resource->GetAllowMultiday()) {...} }` 這層資源迴圈/開關拿掉，改成不管任何資源的 `AllowMultiday` 設定，一律做同一天檢查。這條規則本來就同時掛在 `PreReservationFactory::GetAddUpdateRuleProcessor()`（新建＋編輯都會跑），而新版 Quick Create／Drawer 的 ajax 端點跟舊版 `reservation.php` 表單本來就共用同一套 `ReservationValidationFactory` → `PreReservationFactory`，所以這一處修改就同時涵蓋所有建立/編輯預約的入口，不用個別修 3 支不同的表單。資源管理頁裡原本用來設定「允許跨日」的欄位/UI 沒有動——現在對跨日檢查沒有作用了，但完整移除牽涉到資料庫欄位／管理頁 UI，超出這次「調整成同一天」的範圍，先保留成閒置欄位。

`lang/zh_tw.php` 原本沒有 `MultiDayRule` 這個 key 的繁中翻譯（會 fallback 顯示 `en_us.php` 的英文，跟 §29.8 提過的翻譯繼承陷阱是同一種情況）——這條規則現在會被觸發得更頻繁，所以順手補上：「%s 不允許跨日預約，開始與結束時間必須在同一天。」

### 31.2 前端：乾脆讓「跨日」在 UI 上不可能發生，不是靠 validation 擋

只在後端擋的話，使用者填完整張表單、按下建立才會看到錯誤，體驗很差。改成 UI 層面直接拿掉「選不同結束日期」這個可能性：

- `tpl/Overview/overview_fullcalendar.tpl`：Quick Create 彈窗原本有 `#qcStartDate` 和 `#qcEndDate` 兩個日期欄位（中間用 `–` 分隔），拿掉 `#qcEndDate`，只留一個日期欄位＋開始/結束兩個時間下拉。Drawer（`更多選項`／編輯用的完整編輯器）原本也是兩排「日期＋時間」（`#rdStartDate`+`#rdStartTime`、`#rdEndDate`+`#rdEndTime`），改成一排日期（新的單一 `#rdDate`）＋一排開始時間–結束時間（中間重用 Quick Create 已經有的 `.qc-time-dash` 分隔線樣式，另外在 `overview_fullcalendar.css` 補了一條 `.reservation-row .qc-time-dash` 讓這個 class 在 Drawer 的排版脈絡下也有顏色/間距，因為它原本只 scope 在 `#quickCreateModal` 底下）。
- `Web/scripts/overview_fullcalendar.js`：
  - `pending`（Quick Create 的暫存狀態）跟 Drawer 的 `drawerStartEndValues()` 都改成 `endDate` 永遠等於 `startDate`（`openQuickCreate()`、`#qcStartDate` 的 change handler、`drawerStartEndValues()` 這幾個地方統一），不再有「使用者把結束日期調到比開始日期晚」這條路徑，原本處理這種分歧的程式碼（`refreshTimeUI()` 裡的 `sameDay` 分支、`startPicker` 的 duration-preserve 例外分支）一併刪掉，因為現在永遠是同一天，那些分支已經是死代碼。
  - 在行事曆格線上拖曳選取時段（`select` callback）原本容許使用者橫跨欄位拖出一個跨日範圍（例如週檢視裡從週三 22:00 拖到週四 02:00）並把兩個日期分別帶進 Quick Create——現在改成偵測到 `info.start`/`info.end` 落在不同日期時，直接把選取範圍收斂回開始那一天的 23:30 結束，不再把跨日的拖曳結果帶進彈窗。
  - `validateDrawerTimes()` 因為 `startDate`/`endDate` 保證同一天，簡化成只比較時間本身（`v.endTime <= v.startTime`），不用再比較完整的日期+時間字串。

### 31.3 驗證

- `docker exec ... php -l` 確認 `ResourceCrossDayRule.php`／`lang/zh_tw.php` 語法正確。
- 改完 `.tpl` 忘記先清 `tpl_c/`，第一次重新整理時頁面上 `#qcEndDate`／`#rdStartDate`／`#rdEndDate` 這些理論上已經拿掉的元素竟然還在——[[project-coasia-dev-gotchas]] 提過的老陷阱又踩了一次，`docker exec librebooking-coasia-app-1 sh -c "rm -rf /var/www/html/tpl_c/*"` 清掉編譯快取後重新整理，確認 `qcEndDate`／`rdStartDate`／`rdEndDate` 都已經從 DOM 消失、新的 `rdDate` 存在。
- 全站 grep 確認程式碼裡沒有任何檔案還在引用 `qcEndDate`／`rdStartDate`／`rdEndDate` 這三個舊 id。
- 頁面 console 沒有錯誤訊息（`overview_fullcalendar.js` 改動後仍正常解析執行，jQuery handler 有正常註冊，登入/導覽功能照常）。
- **沒有驗證到的部分**：實際在行事曆上拖曳出一個跨日選取，確認真的會被收斂成同一天結束於 23:30，而不是被擋掉或整個沒反應——這個環境的自動化瀏覽器分頁沒有被「顯示」出來（跟 §30.4 同一個限制），`left_click_drag` 這類需要真實像素座標的手勢動作在沒有先成功 screenshot 的情況下會直接被工具拒絕，嘗試改用 JS 手動 dispatch mousedown/mousemove/mouseup 合成事件也因為滑鼠落點常常對到既有的預約事件卡片而非空白格線，沒能穩定重現。這段 clamp 邏輯本身很單純（純字串日期比較＋條件覆寫），已經逐行看過程式碼確認邏輯正確，但建議使用者實際在自己瀏覽器裡拖一次跨日的時段，確認彈窗跳出來時真的被收斂在同一天、而不是顯示一個奇怪的時間範圍。

## 32. Quick Create 彈窗重構：Grid 版面、單一資源選擇器、參與者導流、統一空標題顯示

使用者上一輪先給了一張 Google Calendar 風格的 Quick Create 彈窗截圖，看過現況後決定「先只做同一天限制」（見 §31）；這輪回頭附上同一張截圖，但這次給的是逐項的 CSS/HTML 規格（不是要求評估），明確要求「直接重構 markup 和 CSS，不要繼續用 margin 或只刪欄位修補」。

### 32.1 Grid 版面取代 margin/padding 拼湊

`tpl/Overview/overview_fullcalendar.tpl` 的 `#quickCreateModal` 整塊重寫：每一列統一是 `.qc-grid-row`（`.qc-icon` 32px＋`.qc-content` 1fr 的兩欄 grid），標題列雖然沒有圖示，也放一個空的 `<div aria-hidden="true"></div>` 佔住圖示欄，讓標題底線、日期時間、資源選擇器、參與者、說明欄的左邊界完全對齊（用 `getBoundingClientRect()` 量過，全部 `left:438 right:880`，跟規格要求一致）。叉叉關閉鈕改成 `position:absolute` 完全脫離 grid（`Web/css/overview_fullcalendar.css`），標題輸入框拿掉原本因為要閃避叉叉而加的 `padding-right:40px`。

### 32.2 單一資源選擇器取代分頁籤

拿掉 `#qcResourceTabs`（實體／視訊兩顆分頁籤按鈕）＋舊的 `TimeDropdown` 陽春下拉，改成跟工具列左側「資源選擇器」完全同一套元件（`.resource-picker`／`.resource-picker-trigger`／`.resource-picker-panel`／`.resource-picker-item` 等 class，樣式已經在 `overview_fullcalendar.css` 的 `#page-overview-fc` 範圍內定義好，這個 Modal 本來就是 `#page-overview-fc` 的子孫，直接繼承那套樣式）：一個帶顏色圓點的觸發鈕＋下拉面板裡有搜尋框＋依「實體會議室／視訊會議」分組。因為是彈窗自己的獨立選擇（跟工具列目前正在看哪個會議室的行事曆是兩件事），JS 端另外寫了一份平行的 `initQcResourcePicker()`／`updateQcResourcePickerTrigger()`（`Web/scripts/overview_fullcalendar.js`），操作 `pending.resourceId`、不是工具列那份操作 `selectedResourceId` 的版本；`.qc-resource-picker` 這個外加 class 只覆寫寬度相關的樣式（全寬、44px 高、下拉面板貼齊觸發鈕寬度），其餘全部繼承共用樣式。

**過程中抓到一個真的的 bug**：資源選擇器選單裡點選一個新資源時，一開始只呼叫了 `updateQcResourcePickerTrigger()`（更新標籤/圓點），沒有連帶呼叫 `refreshResourceUI()` 把 `#qcMoreOptions` 的 `href`（純 fallback 用，正常點擊會被 JS 攔截走 Drawer，不會真的照 href 導航）一併更新——用瀏覽器實測「切換資源→檢查更多選項連結」這個流程時抓到 `rid=` 還停在切換前的舊資源，已經改成呼叫完整的 `refreshResourceUI()`。

### 32.3 標題留白、參與者導流、Cancel 文案：翻譯字串盤點

- 標題輸入框 placeholder 從共用的 `NewReservation`（新增預約）改用專門新增的 `AddTitle`（新增標題），避免跟 Drawer 標題欄共用的既有字串混在一起改壞其他地方。
- 建立後標題為空的顯示，原本三個管道各自用不同的臨時方案（`OverviewFullCalendarEventsPresenter.php` 內寫死中文字面量 `'未命名預約'`、Email／LINE 完全沒有處理，直接把空字串塞進範本），且既有的兩個「無標題」字串（`NoTitleLabel`＝「(沒有標題)」，用在 9 個舊頁面；`Untitled`＝「未命名預約」，只用在舊版 `overview.tpl`／`my-meetings.tpl`）文字都跟使用者這次指定的「（無標題）」不一樣、改任一個既有字串都會連帶影響沒被要求的其他頁面。改成新增一個專用字串 `NoReservationTitle`＝「（無標題）」，只接在使用者明確點名的四個地方：`OverviewFullCalendarEventsPresenter.php`（同時覆蓋行事曆色塊跟事件卡片，因為兩者讀的是同一個 `title` 欄位）、`lib/Email/Messages/ReservationEmailMessage.php`（確認信本文 + ics 附件 SUMMARY，各一處 fallback）、`lib/Email/Messages/ReminderEmail.php`（提醒信）、`lib/Application/Reservation/Notification/OwnerLineNotification.php`（LINE 推播，一般/重複兩種訊息共用同一個算好的 `$title`）。**刻意不改**`Domain/ReservationSeries.php::Title()` 這個 getter 本身——如果在那裡塞入顯示用的假字串，Drawer 編輯既有「無標題」預約時，`rdTitle` 輸入框會被這串假文字填滿而不是維持真正的空白＋placeholder，使用者得先手動刪掉「（無標題）」才能打真正的標題，資料庫裡存的東西也不該包含這段顯示用文字。
- 「新增參與者」：Quick Create 本來就不能存參與者（只有 Drawer 能，見 §21），這顆按鈕點下去不是無反應的假按鈕，而是把 `buildQuickCreateDraft()`（從原本 `#qcMoreOptions` 的點擊處理常式裡抽出來的共用函式，兩顆按鈕現在共用同一份 draft 組裝邏輯）目前填的內容原封不動帶進 Drawer 的新增模式，跟「更多選項」走的是同一條路。
- Footer 的「建立」改成「建立預約」（改用既有的 `CreateReservation` 字串鍵，沒有新增字串，也沒有動到別處共用的 `Create` 鍵）；新增的「取消」按鈕本來想直接用既有的 `Cancel` 鍵，但實測發現這個鍵在 zh_tw 裡其實是「退出」（整站沿用同一個鍵，包括叉叉關閉鈕的 `aria-label`），改用另一個既有鍵會連帶影響那些地方——另外新增一個 `CancelAction`＝「取消」專門給這顆按鈕，`.reservation-btn-text`（Drawer 已經在用的次要按鈕樣式）直接重用，沒有另外寫一份新樣式。

### 32.4 驗證

用 Claude Browser 工具實際登入 `mark_li`，這次改用「JS 直接 dispatch mousedown/mouseup 在正確座標」的方式在行事曆空白格上真的觸發 FullCalendar 的 `select` callback（不是只讀程式碼），端到端跑過一輪：

- 點擊空白格 → Quick Create 真的跳出來，日期/時間/資源全部照點擊位置正確帶入（例如點在「上海」欄的 20:00 格，跳出來的彈窗日期=該天、開始=下午8:00、結束=下午8:30、資源標籤=「上海 Shanghai」、圓點顏色=`#0b8043`）。
- 資源選擇器：點開 → 搜尋「taipei」正確過濾成只剩台北 → 點選後標籤/圓點正確換成台北的藍色、`#qcMoreOptions` 的 href 也正確換成台北的 `rid`（就是上面 32.2 抓到並修掉的那個 bug）。
- 「新增參與者」：填了標題/說明後點擊，Drawer 正確以新增模式打開，標題、說明、日期、資源全部從 Quick Create 帶過去，Quick Create 彈窗同時關閉。
- 標題留空、按「建立預約」：`ajax/reservation_save.php` 回 200，行事曆重新抓取事件後，這筆新預約跟原本畫面上其他好幾筆本來就是空標題的舊預約全部顯示「（無標題）」（confirms `OverviewFullCalendarEventsPresenter.php` 的 fallback 對所有空標題事件都生效，不只是新建的這筆）；點開事件卡片一樣顯示「（無標題）」；再點「編輯」進 Drawer，`rdTitle` 這個輸入框的實際 value 讀出來是空字串、不是「（無標題）」這串文字——確認資料庫裡存的還是乾淨的空值，只有顯示層套用了 fallback，跟 32.3 那條「不改 domain getter」的判斷一致。
- `document.styleSheets`／`php -l`（`OverviewFullCalendarEventsPresenter.php`／`ReservationEmailMessage.php`／`ReminderEmail.php`／`OwnerLineNotification.php`／`lang/zh_tw.php`／`lang/en_us.php`）全部語法正確；全程 console 沒有任何錯誤訊息。
- 新增的三個翻譯鍵（`NoReservationTitle`／`AddTitle`／`AddParticipantsAction`／`CancelAction`）第一次測試時全部顯示成「?」——不是打字打錯，是 §23.8 記錄過的同一個 opcache 陷阱（`opcache.validate_timestamps=Off`，改 `.php` 檔案存檔後容器不會自動重載），`docker compose -f docker-compose.dev.yml restart app` 重啟後才正確顯示中文；`.tpl` 的部分照樣先清一次 `tpl_c/`（§31.3 才踩過的同一個陷阱，這次沒有再漏）。

**沒有驗證到的部分**：整個彈窗的實際視覺效果（圓角、陰影、圖示對齊的視覺觀感、hover 狀態顏色）——原因跟 §30.4／§31.3 一樣，這個自動化瀏覽器分頁沒有被「顯示」出來，拿不到 screenshot。這次改用 `getBoundingClientRect()` 量過每一列的實際 left/right/width（見 32.1），數字上完全對齊規格要求，但那是「盒模型算對了」的證據，不是「看起來對不對」的證據，建議使用者自己在瀏覽器裡打開看一次實際畫面。

## 33. 側邊欄重新配色：CoAsia Blue 正式品牌色（展開／收合皆適用）

使用者提供正式品牌規格（背景 `#1F3159`、主要文字/圖示白色、次要文字 `#B8C2D6`、分隔線/hover/active 用不同透明度的白、focus 外框 `#9CB7E2`、選中日期維持綠色不變）跟一份新 Logo 檔案，要求側邊欄展開跟收合兩種狀態都改用這套配色，不要漸層。

### 33.1 Logo：新檔案＋新變數，不覆蓋既有的

使用者提供的 `D:\桌面\文件集\Logo\Logo\white.png`（238×78，帶透明背景的白色字標）複製進 `Web/img/custom-logo-white.png`——**沒有**覆蓋掉既有的 `Web/img/custom-logo.png`（那支是 `$LogoUrl` 目前在用的檔案，登入頁、維護頁、以及這個側邊欄以外的地方都靠它，蓋掉會連帶壞掉那些淺色背景頁面）。`Pages/Page.php` 比照 `$LogoUrl` 已經有的 `findCustomFile()` 呼叫方式，另外組一個新變數 `$SidebarLogoUrl`（找 `custom-logo-white.{png,gif,jpg}`，找不到則 fallback 回剛複製的檔案路徑），`tpl/globalheader.tpl` 的 `.sidebar-brand-logo-full` 改吃這個新變數，`$LogoUrl` 本身完全沒動。

### 33.2 CSS：新增一組 `--sidebar-*` token，取代整個側欄原本用的淺色系數值

`Web/css/coasia-theme.css` 的 `:root` 裡新增 7 個變數（`--sidebar-bg`／`--sidebar-text`／`--sidebar-text-secondary`／`--sidebar-divider`／`--sidebar-hover`／`--sidebar-active`／`--sidebar-focus`，數值照使用者給的規格），然後把側欄相關的每一條規則（`.app-sidebar`／`.sidebar-brand`／`.sidebar-brand-company`／`.sidebar-user`／`.sidebar-user-name`／`.sidebar-collapse-toggle`／`.sidebar-toggler`／`.sidebar-footer`／`.sidebar-link`／`.sidebar-group-toggle`／`.sidebar-chevron`／`.sidebar-sublink`／捲軸 thumb 顏色）原本寫死的淺色 hex（`#fff`／`#3c4043`／`#1c2333`／`#6c7488`／`#9aa1b3`／`#4b5165`／`#c7cbd6`）跟 `var(--primary)`／`var(--bs-tertiary-bg)`／`var(--gcal-border)` 全部換成新 token（全站 grep 過一次 `coasia-theme.css`，確認除了跟側欄無關的 `--primary-disabled` 定義本身以外，沒有殘留的舊色值）。`--gcal-today-accent`（迷你月曆選中日期用的綠色）刻意沒有改，維持原樣。

- `.sidebar-link.active`（「行事曆首頁」目前頁面的常駐狀態，§30 剛把它從「常駐灰底」改成「hover 才顯示」）這次再改一次：背景固定用 `--sidebar-active`（半透明白），文字/圖示走 `.sidebar-link` 的白色基礎規則,不用再額外覆寫顏色——是 §30 修的那個 bug 在新配色下的自然延伸,不是走回頭路。
- `.sidebar-user`（使用者頭像/名稱那顆按鈕）原本就沒有寫 `border`,使用者說的「黑色外框」實際上是瀏覽器對沒設定 `:focus-visible` 的 `<button>` 給的預設黑色 focus 外框——這次補上 `:focus-visible { outline: 2px solid var(--sidebar-focus); }`,順便解決。
- `.sidebar-divider`（迷你月曆跟下面選單中間那條分隔線的 `<div>`）**原本整個網站都沒有任何一條 CSS 規則真的畫出這條線**（只有收合時 `display:none` 的規則,沒有展開時的樣式),等於一直是個 0 高度的隱形空 div。既然使用者規格明確指定了分隔線顏色,順便把它補上真正的樣式（1px 高、`--sidebar-divider` 色)。
- 迷你月曆的樣式定義其實不在 `coasia-theme.css`,是在頁面專屬的 `overview_fullcalendar.css`（跟舊版 `overview.css` 有重複的一份,兩個頁面互斥載入,§16 就記錄過這個既有的重複)——這次只改了 `overview_fullcalendar.css` 那份（目前「行事曆首頁」實際在用的頁面),沒有動 `overview.css`——那個頁面的側邊欄現在沒有任何導覽連結指向它,是個孤兒頁面,維持專案既有「不動 overview.css」的慣例。非當月日期用 `color-mix(in oklab, var(--sidebar-text-secondary) 55%, var(--sidebar-bg))` 往深藍背景混,而不是隨便挑一個新的灰階,確保「降低亮度但仍可讀」是從既有色板算出來的,不是憑感覺加一個新色號。

### 33.3 收合寬度：規格要求 80–86px，原本是 72px

`.app-sidebar.collapsed { width: 72px; }` 是既有值,不是這次配色改動本身的一部分,但使用者這次明確要求「寬度約 80–86px」,改成 84px（連帶更新了兩處提到「72px」舊數字的註解,一處在這支 CSS,一處在 `globalheader.tpl`)。收合狀態下每個圖示（收合按鈕/頭像/行事曆首頁/登出）能不能維持同一條水平置中線,靠的是既有的 flex `align-items:center`／`justify-content:center` 規則,不是寫死的 margin 數字,所以寬度改變後不需要另外調整置中邏輯。

### 33.4 展開/收合完成後呼叫 FullCalendar `updateSize()`

原本的機制是 `notifyLayoutChanged()` 只 dispatch 一個合成的 `window resize` 事件,靠 FullCalendar 自己預設開著的 `windowResize` 監聽器去間接偵測並重新量測——這次改成`Web/scripts/overview_fullcalendar.js` 在 `calendar.render()` 之後多存一份 `window.overviewFullCalendarInstance = calendar;`,`notifyLayoutChanged()` 先直接呼叫 `window.overviewFullCalendarInstance.updateSize()`（如果存在的話),原本的合成 resize 事件保留不動（其他程式碼可能還依賴這個通用信號,留著無害)。在沒有行事曆的其他頁面上,`window.overviewFullCalendarInstance` 是 `undefined`,這段程式碼原本就有 guard,維持「在沒有行事曆的頁面上是無害 no-op」這個既有性質。

### 33.5 驗證

用 Claude Browser 工具登入 `mark_li` 實測,這次過程中遇到（也解開了）一個新的環境限定假象：切換收合狀態後量 `#appSidebar` 的寬度,`classList` 正確顯示 `collapsed`、CSS 規則本身用 `element.matches()` 直接確認過確實命中,但 `getBoundingClientRect().width` 一直讀回原本展開的 240px,像是規則完全沒生效。逐步排除了「規則被覆寫」「有更高 specificity 的規則」等可能性後,直接把 `transition` 強制設成 `none` 重新量一次,寬度立刻正確變成收合值——證實這是 §30/§31 那個「分頁沒有被顯示、不合成畫面」限制的另一種表現形式：**CSS transition 在沒有真正跑合成/繪製流程的分頁裡永遠不會真的播放完成**,量到的一直是動畫「起點」而不是「終點」的數值,不是側欄程式碼本身有問題。之後所有牽涉到收合/展開寬度變化的測試,都先注入一段 `* { transition: none !important; }` 跳過動畫,直接量測最終狀態:

- 展開狀態：`getComputedStyle(#appSidebar).backgroundColor` = `rgb(31, 49, 89)`（即 `#1F3159`,精確符合)。新 Logo 正確載入,顯示尺寸 `120×39.3px`（跟原始 238:78 比例完全吻合,沒有被全站共用的 `img.logo{max-height:36px}` 規則意外壓扁——這是過程中抓到的第二個真的 bug,已經在 `.sidebar-brand img.logo` 補上 `max-height:none` 覆寫掉那條全站規則)。Logo 右邊界（136px）跟收合按鈕左邊界（200px）之間有 64px 淨空,不重疊。迷你月曆文字/選中日期分別讀到白色跟原本的綠色 `rgb(52,168,83)`,分隔線 `rgba(255,255,255,0.12)` 高度 1px。
- 收合狀態：寬度確認 84px；收合按鈕、頭像、「行事曆首頁」圖示、「登出」圖示四個元素的水平中心點全部落在同一個 x 座標（42px,正好是 84÷2),Logo／使用者名稱／迷你月曆的 `display` 都正確變成 `none`。
- 切換收合/展開時 monkey-patch `window.overviewFullCalendarInstance.updateSize`,確認點擊收合按鈕會真的呼叫到它。
- `localStorage.getItem('sidebar-collapsed')` 在重新整理後正確維持上次的值（這段機制是既有的、這次沒有改動,只是確認沒有被連帶弄壞)。
- `php -l` 確認 `Pages/Page.php` 語法正確；全程 console 沒有任何錯誤訊息。

**沒有驗證到的部分**：跟前幾輪一樣,實際的視覺觀感（新 Logo 在深藍底上好不好看、白色文字/圖示的對比度讀起來是否舒服、圓角跟間距的視覺節奏)沒辦法用 screenshot 確認,只驗證了色值/尺寸/DOM 結構在數字上符合規格。也沒有跑「更多選項」相關的任何檢查（使用者這輪明確說暫時不處理),以及使用者要求的「只檢查側欄展開/收合/重新整理/行事曆尺寸」以外的功能（例如通知設定子選單、admin 專屬選單的視覺)沒有特別花時間逐一肉眼核對,只確認了色值 token 有正確套用到那些規則上。

## 34. 側欄再改一輪：固定尺寸變數化、恢復「＋新增預約」、通知設定移出帳號選單、淺色/深藍雙主題

使用者這次給了一份更完整的規格,把 §33 那輪「只做深藍一種配色」的側欄，改成「淺色/深藍兩套可切換的配色」，同時要求固定尺寸（用兩個 CSS 變數）、恢復先前拿掉的「＋新增預約」按鈕、把通知設定從帳號選單搬到側欄主列表。明確限定「這次只調整側欄，不要修改行事曆與預約邏輯」。

### 34.1 尺寸變數化

`Web/css/coasia-theme.css` 的 `:root` 新增 `--sidebar-expanded-width: 285px`／`--sidebar-collapsed-width: 85px`，`.app-sidebar`／`.app-sidebar.collapsed` 的 `width` 改成讀這兩個變數（原本分別是 240px／84px，84px 又是 §33 那輪從最早的 72px 改來的）。全站 grep 過 `calc(`／`240px` 的使用，確認除了 `.app-sidebar` 自己的寬度以外，沒有其他地方（含 `.app-content` 的主內容偏移）用固定數字或 calc() 算過側欄寬度——`.app-content{flex:1}` 本來就是純 flexbox 自動吃剩餘空間，沒有另外寫死的偏移量需要改。收合狀態下所有圖示按鈕（收合鈕、頭像、新增預約、行事曆首頁、通知設定、登出）尺寸從 36px 統一改成 44px，比對半徑後六個元素水平中心點都落在同一個 x 座標，符合「所有圖示水平置中、間距一致」。Logo 顯示寬度從 §33 的 120px 改成 108px（`height:auto` 維持比例不變，同一條 `max-height:none` 覆寫全站 `img.logo{max-height:36px}` 規則的作法沿用）。

Header／brand row 高度**維持原本的 64px，沒有改成規格提到的「約78px」**——這是一個判斷取捨：這個 64px 跟主畫面行事曆工具列（`overview_fullcalendar.css` 的 `.cal-toolbar`）共用同一條高度基準線做視覺對齊，改側欄這邊的高度會連帶讓兩者的橫向接縫對不齊，而使用者這次明確限定「只調整側欄，不要修改行事曆」——調整工具列去配合新高度會超出這次範圍。把「沿用原版」理解成「維持現狀不改」而不是「換成新的 78px 目標值」。如果使用者原意就是要真的改成 78px（連帶也要調整行事曆工具列），麻煩明確說一聲，下一輪再一起處理兩邊。

### 34.2 恢復「＋新增預約」

`git show` 挖出這顆按鈕在 §29 被拿掉前的原始 CSS（`.create-btn`：`height:var(--gcal-control-height)`即40px、`border-radius:100px` 膠囊、`padding:0 16px`、`width:100%` 沿用 `.sidebar-nav` 既有的 12px 左右內縮，這幾點都照抄，不是憑印象重新設計。唯一的差異：舊版顏色寫死 `background:var(--primary); color:#fff`；這次改成讀 `--sidebar-create-btn-bg`/`-text` 這兩個新 token（隨淺色/深藍主題切換，兩個顏色互換——淺色側欄深藍底白字、深藍側欄白底深藍字，正好符合使用者這次規格給的顏色）。

行為上刻意**沒有**照抄舊版直接連到 `reservation.php` 的行為——使用者這次明確要求「點擊後開啟目前同一個快速預約 Modal」。這顆按鈕本身活在 `globalheader.tpl`（全站共用），但 Quick Create Modal 只存在於行事曆首頁自己的頁面，兩者需要一座橋：`Web/scripts/overview_fullcalendar.js` 在 `calendar.render()` 之後多曝露一個 `window.overviewFullCalendarOpenQuickCreate` 函式（預設值＝今天、目前選定的資源、無條件捨去到下一個半小時整點——跟在行事曆空白處點一下會產生的輸入完全同一套），globalheader.tpl 的按鈕點擊時先檢查這個函式存不存在：存在就呼叫它並 `preventDefault()`；不存在（也就是在行事曆首頁以外的任何頁面點這顆按鈕）就讓原本的 `href="{$Path}{Pages::RESERVATION}"` 正常導航過去——等於保留了舊版「連到經典表單」的行為，只是變成「沒有快速建立彈窗時的 fallback」，不是唯一路徑。這是這次唯一碰到 `overview_fullcalendar.js` 的地方，其餘行事曆/預約邏輯完全沒動。

### 34.3 通知設定移出帳號選單

`#navNotification` 從帳號選單的 `#sidebarAccount` 子選單搬到主導覽，緊接在「行事曆首頁」後面，**共用同一個 `.sidebar-link` class**（不是另外寫一份長得很像的樣式）——列高/圖示欄寬/文字起點/字級/hover/active 全部自動保證一致，不需要另外比對數字。圖示用 `bi-bell`。帳號選單原本只有這一條連結，搬走之後改成只放新的淺色/深藍切換器（見 34.4），符合「帳號選單只新增這個外觀設定」。

### 34.4 淺色／深藍雙主題

把 §33 那輪定義在 `:root` 的單一組 `--sidebar-*` token，改成兩組分別掛在 `.app-sidebar.sidebar-theme-light` 跟 `.app-sidebar.sidebar-theme-dark` 底下（數值照使用者這次分別給的規格）——除了背景色本身，其餘所有引用 `var(--sidebar-bg)`／`var(--sidebar-text)` 等等的既有規則完全不用重寫，切換 class 就會整組換膚。額外加了 `--sidebar-create-btn-bg`/`-text` 兩個 token（給 34.2 的新增預約按鈕用，兩個主題故意跟主背景反色，讓這顆按鈕在任何一種主題下都還是視覺上最突出的那個）。

Logo 也是兩張圖同時放在 markup 裡（`.logo-theme-light` 用既有的 `$LogoUrl`／彩色 CoAsia logo，`.logo-theme-dark` 用 §33 已經複製好的 `$SidebarLogoUrl`／白色 logo），靠 `.app-sidebar.sidebar-theme-light .logo-theme-light{display:block}` 這類 CSS 規則二選一顯示，全程沒有用 JS 去改 `img.src`。帳號選單裡的切換器是一個真正的 segmented control（`.sidebar-theme-switcher-control` 當作淺灰/半透明白的軌道，`.sidebar-theme-option.active` 用 `--sidebar-active` 這個 token 當作選中底色——特別留意過不能直接用 `--sidebar-bg` 當選中底色，深色主題下 `--sidebar-bg` 本身就是側欄背景色，選中的那顆按鈕會直接融進背景裡看不見，改用 `--sidebar-active`（半透明白疊加）在兩種主題下都能穩定「比軌道亮」)。

`localStorage` 存取比照既有的收合狀態機制：`<aside>` 標籤內第一段 `<script>` 在任何內容渲染前就讀出 `sidebar-theme` 值並把對應 class 加到 `#appSidebar` 上（沒有記錄時不加任何 class，直接落回 `coasia-theme.css` 把 `.app-sidebar`／`.app-sidebar.sidebar-theme-light` 兩個選擇器綁在一起的預設值——也就是規格要求的「沒有紀錄時預設淺色」)；底部另一段 `<script>` 負責點擊切換＋寫回 `localStorage`＋同步兩顆按鈕的 `.active`/`aria-pressed`。全程只用 `classList.toggle()`，沒有任何一行手動寫 `element.style.xxx = ...`。

### 34.5 驗證

用 Claude Browser 工具登入 `mark_li`，比照 §33 學到的教訓，一開始就先注入 `* { transition: none !important; }` 跳過這個分頁量不到的動畫過渡：

- 展開＋淺色（無 localStorage 記錄的預設狀態）：`sidebarClass` 正確只帶 `sidebar-theme-light`；寬度 285px；背景 `rgb(255,255,255)`；「新增預約」／「行事曆首頁」／「通知設定」三個橫向導覽項目的 `left`/`right`/`height` 完全相等（`left:12 right:263 height:40`），證實共用同一個元件、不是分別調出來的數字；「新增預約」背景 `rgb(31,49,89)` 文字白色（符合淺色側欄規格）；頭像 38×38；彩色 Logo 顯示、白色 Logo 隱藏，Logo 渲染尺寸 108×36，跟來源檔案 591:197 的真實比例換算完全吻合（無壓扁）。
- 點擊深藍選項：`sidebarClass` 換成只帶 `sidebar-theme-dark`；背景變 `rgb(31,49,89)`；「新增預約」背景/文字反色成白底深藍字；兩張 Logo 顯示狀態互換；`localStorage.getItem('sidebar-theme')` 存成 `'dark'`；兩顆切換鈕的 `.active`/`aria-pressed` 正確互斥切換。重新整理後（不用再點一次）維持深藍，證實 pre-paint script 生效。
- 收合＋深藍：寬度 85px（精確符合）；收合鈕/頭像/新增預約/行事曆首頁/通知設定/登出六個元素的水平中心點全部是同一個 x 座標（43px，即 85÷2 四捨五入）；頭像/新增預約/行事曆首頁圖示尺寸全部 44×44；Logo／使用者名稱／迷你月曆的 `display` 都變成 `none`；「新增預約」跟「通知設定」的 `title` 屬性都有正確文字（原生瀏覽器 tooltip 靠這個，沒有另外做 tooltip 元件）。
- 點擊「新增預約」：實際觸發 `window.overviewFullCalendarOpenQuickCreate()`，Quick Create Modal 真的打開，日期/時間/資源正確帶入「今天、目前時間無條件進位到下個半小時整點、目前選定的資源」。
- 全站 grep 確認 `id="navNotification"` 不會在實際渲染的 DOM 裡重複出現（帳號選單裡搬走了；`tpl` 裡另一個同名 id 落在既有的 `{if false}` 死代碼區塊裡，本來就不會渲染，不是這次改動造成的重複)。
- `php -l` 確認兩支語言檔語法正確；全程 console 沒有任何錯誤訊息。

**沒有驗證到的部分**：實際視覺觀感（兩種主題切換時的過場動畫、色彩對比讀起來舒不舒服）一樣拿不到 screenshot，只驗證了色值/尺寸/DOM 結構在數字上符合規格；也沒有測試新增預約按鈕在「行事曆首頁以外的頁面」點擊時，fallback 導向 `reservation.php` 的路徑（邏輯上這段沒有依賴任何 `window.overviewFullCalendarOpenQuickCreate` 判斷式以外的東西，但沒有實際切到別的頁面點一次確認）。

## 35. 側欄只留淺色版本，拿掉深藍側欄跟外觀切換功能

§34 才剛做完淺色/深藍雙主題，使用者這次直接要求整組拿掉，只留淺色。範圍是徹底移除，不是先隱藏開關——把 §34 新增的東西逐一清乾淨：

- `Web/css/coasia-theme.css`：`.app-sidebar.sidebar-theme-light`／`.app-sidebar.sidebar-theme-dark` 兩組 `--sidebar-*` token 合併回 `:root` 一組（淺色那組數值），移除深藍那組；`.logo-theme-light`/`-dark` 的顯示切換規則拿掉，`.sidebar-brand img.logo` 改回單純 `display:block`；整段 `.sidebar-theme-switcher-label`／`.sidebar-theme-switcher-control`／`.sidebar-theme-option` 相關規則刪除；`.sidebar-user`／`.sidebar-user:hover`／`.sidebar-user:focus-visible`／`.sidebar-user .sidebar-chevron`／`.sidebar-user[aria-expanded]` 這些原本因為它是「帳號選單觸發鈕」而存在的互動樣式，也一併拿掉（原因見下）。
- `tpl/globalheader.tpl`：拿掉 `<aside>` 開頭那段讀 `localStorage.getItem('sidebar-theme')` 的 pre-paint script；Logo 從兩張圖（`$LogoUrl`＋`$SidebarLogoUrl`）改回一張（`$LogoUrl`）；帳號選單的 segmented control 整塊移除。移除之後 `#sidebarAccount` 這個子選單裡面**已經沒有任何內容**（§34.3 已經把通知設定移出去、這次又拿掉外觀切換，等於裡面空了）——沒有留一個「點了展開卻是空的」下拉，直接把 `.sidebar-user` 從「帶 `data-bs-toggle="collapse"` 的可點擊 `<button>`」改成純顯示用的 `<div>`（拿掉 chevron 圖示、拿掉 `#sidebarAccount` 整個 `<div>`、拿掉底部負責同步兩顆按鈕狀態＋寫 localStorage 的那段 `<script>`）。頭像/名字現在單純顯示「目前登入的是誰」，不能點。
- `Pages/Page.php`：`$SidebarLogoUrl` 這個 Smarty 變數指派整段刪除（不再有任何地方讀它）。
- `Web/img/custom-logo-white.png`：這個檔案（§33 從使用者提供的來源複製進來的白色 Logo）刪除——已經沒有任何程式碼引用，留著只是死檔案。
- `lang/zh_tw.php`／`lang/en_us.php`：`SidebarAppearance`／`SidebarThemeLight`／`SidebarThemeDark` 三個翻譯鍵移除（§34 新增、現在沒有任何地方 `{translate}` 它們）。

全站 grep 過 `sidebar-theme`／`SidebarLogoUrl`／`SidebarAppearance`／`logo-theme`／`custom-logo-white` 這些字串，確認除了 `COASIA-CHANGES.md`（歷史記錄本來就該留著）以外，只剩 `coasia-theme.css` 裡兩處刻意保留、語意不同的東西：`--sidebar-create-btn-bg`/`-text`（§34.2 恢復的「新增預約」按鈕自己的專屬底色/文字色 token，跟「淺色/深藍雙主題」是兩回事，繼續用淺色那組固定值)，順手把附近一句提到「兩個主題」的舊註解改成不再提「主題」。

### 35.1 驗證

沿用 §34 學到的做法，先注入 `* { transition: none !important; }` 避開這個分頁的動畫量測限制，用 Claude Browser 工具登入 `mark_li` 實測：

- `#appSidebar` 的 `className` 只剩 `app-sidebar`（沒有任何 `sidebar-theme-*` class），背景色固定讀回 `rgb(255,255,255)`。
- `.sidebar-user` 的 tagName 是 `DIV`（不是 `BUTTON`），沒有 `data-bs-toggle` 屬性；`document.querySelector('.sidebar-theme-switcher, #sidebarThemeLight, #sidebarThemeDark')` 跟 `document.getElementById('sidebarAccount')` 都回傳 `null`／不存在。
- `.sidebar-brand img.logo` 現在只有 1 個（不是 2 個），`display:block`。
- 「新增預約」背景/文字仍然正確（`rgb(31,49,89)` 底、白字、文字「新增預約」）——確認拿掉主題切換沒有連帶改壞這顆按鈕本身的樣式。
- 收合狀態：寬度仍然精確 85px，收合鈕/頭像/新增預約/行事曆首頁/通知設定/登出六個元素的水平中心點依然全部落在同一個 x（43px），Logo/使用者名稱該隱藏的都正確隱藏——確認拿掉帳號選單的可點擊行為沒有連帶弄壞收合狀態的置中邏輯。
- `php -l` 確認 `Pages/Page.php`／兩支語言檔語法正確；全程 console 沒有任何錯誤訊息。

**沒有驗證到的部分**：一樣沒有實際視覺（screenshot）可看，只驗證了 DOM 結構/色值/尺寸在數字上正確；也沒有特別去重新整理頁面確認「以前存過 `sidebar-theme=dark` 的舊 localStorage 值」會不會造成任何殘留影響——目前的 pre-paint script 已經整段刪除，所以就算瀏覽器裡還留著這個舊的 localStorage key，也不會再被讀取或用到，理論上無害，但沒有特地拿一個帶著舊值的分頁實測過。

## 36. Quick Create 逐項對照修正：拿掉「不重複」、資源靠左、真實可用性查詢、日期顯示格式、間距與底線行為

使用者拿確認過的示意圖跟目前畫面逐項比對，回報 8 個明確差異，並附上正確的內容順序、對齊方式、尺寸數字。這輪不是重新設計，是照著給的差異清單逐條修，包含一個要動到後端的項目（可用性查詢）。

### 36.1 移除「不重複」

`tpl/Overview/overview_fullcalendar.tpl` 的 Quick Create 直接拿掉 `bi-arrow-repeat` 那整個 `.qc-grid-row`（連同它綁的 `DoesNotRepeat` 字串顯示，§32 加的）。重複規則本來就只有 Drawer 的 `#rdRepeatType` 下拉在真正處理，這裡的「不重複」只是顯示用的靜態文字，拿掉不影響任何功能——符合使用者這輪重申的「快速預約永遠建立單次預約，重複設定只放在更多選項 Drawer」。日期時間列後面現在直接接資源選擇列。

### 36.2 資源名稱靠左（真的 bug，不是視覺調整）

根因找到了：`#quickCreateModal .qc-resource-picker .resource-picker-trigger` 這條 §32 加的規則用了 `justify-content: space-between`，但 `.resource-picker-label`（共用元件的既有規則）本身沒有 `flex-grow`，維持自己文字的自然寬度——三個 flex 子項（圓點／文字／箭頭）在 `space-between` 下會被等間距撐開，文字短的時候看起來就像被「置中」在圓點跟箭頭中間，不是真的置中對齊，是 flex 版面配置的副作用。修法：拿掉 `justify-content:space-between`，改成幫 `.resource-picker-label`（僅限 `#quickCreateModal` 範圍內覆寫，不影響工具列自己的資源選擇器）加 `flex:1 1 auto; text-align:left;`——文字自然占滿中間所有剩餘空間、靠左，箭頭被推到最右邊。

### 36.3 新增「此時段可用／不可用」——真的查資料庫，不是寫死

這是這輪唯一牽涉後端的項目。追出既有的預約衝突判斷邏輯：儲存時真正擋衝突的是 `ResourceAvailabilityRule` → `ReservationConflictIdentifier` → `ResourceAvailability`，最底層其實就是呼叫 `Domain\Access\ReservationViewRepository::GetReservations()` / `GetBlackoutsWithin()` 查詢資料庫，不需要一整個 `ReservationSeries` 物件才能查。照這個發現新增一支輕量、唯讀的 ajax 端點：

- `Web/ajax/overview_fullcalendar_availability.php`（三行載入器，照抄 `overview_fullcalendar_events.php` 的既有慣例）
- `Pages/Ajax/OverviewFullCalendarAvailabilityPage.php`：讀 `resourceId`／`start`／`end` 三個 querystring，直接呼叫 `ReservationViewRepository::GetReservations()` + `GetBlackoutsWithin()`，回傳 `{"available": true/false}`。Quick Create 只會建立全新的單次預約，沒有「排除自己這筆」的編輯情境要處理。

前端（`Web/scripts/overview_fullcalendar.js`）在 `refreshTimeUI()`（日期/時間變動）跟 `refreshResourceUI()`（資源變動）結尾都呼叫 `scheduleAvailabilityCheck()`——300ms debounce，避免使用者連續點時間下拉時打好幾次 request；`checkAvailability()` 實際發 request 時把當下的 resourceId/start/end 記下來，回應回來時比對「現在的 `pending` 狀態還是不是發 request當下那個」，不是的話直接丟棄——避免使用者手速快、舊的回應比新的晚到時蓋掉畫面上正確的新狀態。畫面上綠色打勾／紅色叉叉圖示用 class 切換（`.bi-check-circle-fill`/`.bi-x-circle-fill`），沒有用 CSS content 硬幹字型 glyph 那種脆弱寫法。

### 36.4 日期改用自訂格式顯示，不是原生 `<input type="date">` 的樣子

瀏覽器不允許直接改寫原生 `<input type="date">` 顯示出來的文字格式。做法：底下還是一個真的 `<input type="date">`（保留鍵盤輸入、原生選擇器、無障礙語意），只是把它自己的文字設成 `color:transparent`；上面疊一個 `pointer-events:none` 的 `<span id="qcStartDateDisplay">`，用 JS 算出「7月30日（四）」這種格式蓋在上面——`pointer-events:none` 是關鍵，點擊會穿透這層文字直接命中下面的真實 input，不會攔截互動。`formatQcDateLabel()`（`overview_fullcalendar.js`）重用既有的 `WEEKDAY_LABELS`／`weekdayLabel()`，每次 `refreshTimeUI()` 執行時（涵蓋開窗、切換日期、切換時間全部三種情境）都會重新算一次，不會跟 `pending.startDate` 脫節。

### 36.5 間距、Modal 尺寸、標題底線

`Web/css/overview_fullcalendar.css` 逐一對照數字改：`.modal-dialog` 560px→520px（加 `width:calc(100% - 32px)` 讓窄螢幕也不會頂到邊）；`.modal-body` padding 56/32/20→44/28/18；`.qc-grid-row` padding 10px 0→8px 0、column-gap 20→18px；`.qc-time-edit` 的欄寬比例 `150px/96/16/96`→`160px/92/14/92`、gap 12→10px。`.modal-content` 本來就沒有設固定 `height`/`min-height`（§32 已經是內容自然撐開），這輪沒有東西要拿掉，補了一句註解說明這是刻意維持的狀態。標題底線：原本無條件 `2px solid var(--primary)`，改成預設 `1px solid #D9E0EA`、只有 `:focus` 時才變 `2px solid #1F3159`（同時把 `padding-bottom` 從 8px 微調到 7px，抵銷邊框從 1px 變 2px 多出來的 1px，避免 focus 那一刻底下內容跳動 1px）。

過程中另外抓到一個 footer 高度對不上規格的小 bug：Bootstrap 5 的 `.modal-footer` 預設會給每個直接子元素（按鈕/連結）各邊加 4px margin（`--bs-modal-footer-gap` 機制，設計給「沒有自訂 padding 的 footer」用的），跟這裡自己控制的 `14px 24px` padding疊加後，footer 實測高度到 76.5px，超出規格要求的 68–72px。修法：`#quickCreateModal .modal-footer > * { margin: 0; }` 把 Bootstrap 那份 margin 歸零，改用 footer 自己的 `padding` + 新增的 `gap:12px` 控制間距，量出來變成 68.5px，落在範圍內。

「新增參與者」顏色從 `#6c7488`（灰）改成 `#1f3159`（跟深藍側欄同一個品牌色調），加上左側 `+` 圖示、hover 變暗一階＋底線、`:focus-visible` 外框。說明欄位 placeholder 從 `Description`（"說明"）改成新增的 `AddDescription` 鍵（"新增說明"）。

### 36.6 驗證

用 Claude Browser 工具登入 `mark_li`，這次因為新增了真正打後端的可用性查詢，額外做了「切換到一個確定沒人訂的時段，確認狀態真的從不可用翻成可用」這一步，不只是檢查 DOM 結構：

- 開啟 Quick Create（點側欄「新增預約」），確認畫面上完全沒有「不重複」這一列；`.modal-body > .qc-grid-row` 依序是：標題（無圖示）→ `bi-clock`（日期時間）→ `bi-building`（資源，含可用性）→ `bi-people`（參與者）→ `bi-text-left`（說明）——跟規格要求的順序完全一致。
- 資源選擇器量測：trigger 寬 412px，圓點在 469-477px，文字從 483px 一路占到 830px（347px 寬，`text-align:left`），箭頭在 836-847px——文字確實靠左並吃滿剩餘空間，不是擠在中間。
- 可用性查詢：一開始選到的時段（今天、上海會議室）顯示「此時段不可用」（紅字＋叉叉）；改選到當天晚上 11 點這種明顯沒人訂的時段後，重新查詢，正確變成「此時段可用」（綠字＋打勾），且 Network 分頁確認每次真的送出 `overview_fullcalendar_availability.php?resourceId=...&start=...&end=...` 並收到 200——證實是動態查詢而非寫死。
- 日期顯示：`#qcStartDate` 底層 input 的 `value` 是標準 `2026-07-31` 格式，但畫面上 `#qcStartDateDisplay` 顯示的是「7月31日（五）」，兩者分離、互不干擾。
- Modal 尺寸：`.modal-dialog` 寬度精確 520px；`.modal-body` padding 讀回 `44px 28px 18px`；一般列（非標題列）的 `padding` 讀回 `8px 0px`、`column-gap` 讀回 `18px`。
- 標題底線：靜止狀態讀回 `rgb(217,224,234) 1px`（即 `#D9E0EA`）；`:focus` 偽類本身在這個分頁裡量不準（跟本輪對話前面幾次 hover/transition 環境限制同一個成因——`document.activeElement` 正確指向該欄位，但 CSS `:focus` pseudo-class 的比對結果讀不出來），改用強制套用同一組 CSS 值的方式驗證，確認 `border-bottom` 數值本身是對的（`rgb(31,49,89) 2px`，即 `#1F3159`）。
- Footer：`border-top` 讀回 `1px solid rgb(230,234,240)`（即 `#E6EAF0`）；高度量測 68.5px，落在規格的 68–72px 區間；「更多選項」在左（398-454px），「取消」「建立預約」在右且順序正確（700-762px、774-868px）。
- 「新增參與者」文字顏色讀回 `rgb(31,49,89)`；說明欄位 placeholder 讀回「新增說明」。
- `php -l` 確認兩支新的後端檔案（`Pages/Ajax/OverviewFullCalendarAvailabilityPage.php`／`Web/ajax/overview_fullcalendar_availability.php`）語法正確；全程 console 沒有任何錯誤訊息。

補測了「切換不同資源、同一時段」這條路徑（先前以為沒測到）：從上海切到台北，`qcResourcePickerLabel` 正確變成「台北 Taipei」，`qcAvailabilityLabel` 重新查詢後顯示「此時段可用」，Network 分頁確認確實多打了一次 `resourceId=1`（台北）、時段不變的請求並收到 200——`refreshResourceUI()` 觸發 `scheduleAvailabilityCheck()` 這條路徑也驗證過了。

**沒有驗證到的部分**：跟以往一樣沒有實際視覺 screenshot，只驗證了數字/DOM/網路請求層面的正確性；沒有測試在較差網路延遲下 debounce／過期回應丟棄機制的實際效果，只確認了程式碼邏輯與正常網路狀況下的行為。

## 37. 「更多選項」Drawer 套用同一套設計概念

使用者：「同樣的概念請重新設計更多選項」——延續 §36 剛套用在 Quick Create 上的設計語言，這次輪到「更多選項」開啟的完整編輯器（Reservation Drawer，`tpl/Overview/overview_fullcalendar.tpl` 的 `#reservationDrawer`）。

這輪範圍判斷：Drawer 原本的 `.reservation-row`（flex + 固定寬度圖示）本來就已經達成跟 Quick Create 新版 `.qc-grid-row` 相同的「圖示欄對齊、內容共用左邊界」效果，只是實作機制不同（flex 固定寬度 vs. CSS grid），沒有必要為了機制一致而重寫——真正跟「同樣的概念」有明顯落差、值得動的，是這幾個具體項目：

### 37.1 資源選擇器：從分頁籤＋原生 `<select>` 換成同一套搜尋式元件

Drawer 原本的資源選擇是「實體/視訊分頁籤 + 原生 `<select>`」——正是 Quick Create 自己在 §32 淘汰掉的舊設計。這次把它換成跟 Quick Create／工具列同一套 `.resource-picker` 元件（圓點＋名稱＋箭頭，搜尋＋分組下拉），JS 端新增 `drawerResourceId`（平行於 Quick Create 的 `pending.resourceId`，Drawer 本來就沒有一個統一的 `pending`-like 物件，所以維持獨立變數，符合這個檔案既有的「不同元件各自管理自己狀態」慣例）、`initRdResourcePicker()`／`updateRdResourcePickerTrigger()`／`setDrawerResource()`，取代舊的 `setDrawerCategory()`／`populateDrawerResourceOptions()`／分頁籤點擊處理常式。

`#rdResource` 從可見的 `<select>`改成隱藏的 `<input type="hidden">`，由新的資源選擇器同步寫入——這樣 `buildCreateBody()`／`buildEditBody()` 原本讀 `$('#rdResource').val()` 的程式碼完全不用改，把改動範圍限制在「選資源」這一小塊，不去動送出邏輯。順手修正資源標籤置中的同一個 bug（§36.2 在 Quick Create 那邊發現的成因——`justify-content:space-between` 讓沒有 `flex-grow` 的文字視覺上被夾在圓點和箭頭中間），這裡直接補上跟 Quick Create 一樣的 `flex:1 1 auto; text-align:left;`。

### 37.2 新增「此時段可用」——並補上 Quick Create 沒有的「排除自己」

Drawer 原本完全沒有可用性提示。這次直接重用 §36.3 建的 `ajax/overview_fullcalendar_availability.php`，但過程中發現一個 Quick Create 用不到、Drawer 卻一定會踩到的洞：**編輯一筆已存在的預約時，那筆預約自己就佔用著那個時段**，如果查詢邏輯不排除它自己，畫面永遠會顯示「此時段不可用」，即使實際上完全沒有衝突。

修法：`OverviewFullCalendarAvailabilityPage.php` 新增一個可選的 `excludeReferenceNumber` 參數，查到的 `$reservations` 陣列先用 `array_filter` 排除掉這個參考編號再判斷是否為空；JS 端 `checkResourceAvailability()` 多一個可選參數，Drawer 的 `checkDrawerAvailability()` 只在 `drawerState.mode === 'edit'` 時傳入 `drawerState.referenceNumber`（`drawerState` 在進入編輯模式時已經同步設定好，早於非同步載入預約資料的那段程式碼跑完，時序上沒有問題）；Quick Create 那邊完全不用改——它永遠是建立全新預約，沒有「排除自己」這個情境，`checkResourceAvailability()` 呼叫時就不傳這個參數。

順手把原本寫死在 Quick Create 範圍內的 `checkAvailability()`／`scheduleAvailabilityCheck()` 拆成一個共用的 `checkResourceAvailability()`（純函式：resourceId／時間範圍／即時查詢當下狀態的回呼／結果回呼／可選的排除編號）＋`renderAvailability()`（更新圖示/文字），Quick Create 跟 Drawer 各自留一層薄薄的 wrapper（`checkAvailability()`／`checkDrawerAvailability()`）去讀自己的狀態來源。這次算是踩過先前「兩個獨立資源選擇器故意不共用實作」的既有慣例邊界——查可用性這段邏輯份量比較大、兩邊幾乎一模一樣，值得真的共用，跟資源選擇器那種「UI 元件各自獨立」的情況不同。

### 37.3 日期改用自訂格式顯示

比照 §36.4 的做法：`#rdDate` 底下還是真的 `<input type="date">`，文字設成透明，上面疊一個 `pointer-events:none` 的 `#rdDateDisplay` 顯示「7月30日（四）」。共用同一個格式化函式——順手把原本叫 `formatQcDateLabel()` 的函式改名成 `formatDateLabel()`（拿掉不再準確的 "Qc" 字首，因為現在两邊都在用）。視覺上沒有照搬 Quick Create 那種緊湊膠囊樣式，而是延續 Drawer 自己既有的「有邊框欄位」語言（跟 `.reservation-field` 一致），只是套用同一種「透明 input + 疊字」技術，不是照搬視覺樣式。

### 37.4 標題底線顏色對齊

`.rd-title-input` 原本用的是通用的 `var(--border)`／`var(--primary)` token（分別是 `#e4e7ee`／`#1a3f73`），跟 Quick Create 這次明確指定的 `#D9E0EA`／`#1F3159` 很接近但不是同一組數字。既然使用者要求「同樣的概念」，這裡直接改成跟 Quick Create 完全一樣的精確色號，而不是繼續用通用 token——兩個「同概念」的標題欄位現在是像素級一致，不只是色調相近。

### 37.5 驗證

用 Claude Browser 工具登入 `mark_li` 實測，這輪特別做了完整的建立→編輯→儲存端到端流程（不只是檢查 DOM）：

- 從 Quick Create 帶著標題/說明點「更多選項」開啟 Drawer：資料正確帶入（標題、說明、日期顯示「7月31日（五）」、資源標籤「上海 Shanghai」），且 Drawer 一開啟就自動查了一次可用性（`#rdCategoryTabs` 已確認從 DOM 消失）。
- 資源選擇器：搜尋切換到台北，隱藏欄位 `#rdResource` 跟顯示標籤都正確同步，且觸發了新一次的可用性查詢（Network 分頁確認 `resourceId=1` 的請求，跟原本 `resourceId=2` 的請求都拿到 200）；量測 trigger/label/chevron 位置，確認文字靠左、箭頭在最右（跟 §36.2 同一組驗證方式）。
- 完整建立流程：從 Quick Create 開 Drawer → 選台北 → 按「建立預約」→ 實際檢查行事曆（切換工具列資源篩選到台北）確認新預約真的出現在台北的行事曆上，標題正確——證實隱藏欄位同步機制沒有把送出邏輯弄壞。
- 可用性自我排除：點開這筆剛建立的預約進入編輯模式，一開始因為忘記重啟容器（同一個 opcache 陷阱又踩了一次，這次是編輯 `OverviewFullCalendarAvailabilityPage.php` 之後沒有立刻重啟）量到「此時段不可用」——加了暫時的除錯欄位、重啟容器、直接用 `fetch()` 打端點確認 `excludeReferenceNumber` 真的有把自己排除掉（`available:true`、`debugReservationRefs:[]`）後，拿掉除錯欄位、重新測過一次 Drawer 介面，確認「此時段可用」正確顯示，不是「編輯自己也顯示不可用」這種假陽性。
- 完整編輯流程：修改標題後按「儲存」，第一次在一筆**這次工作階段稍早建立的**舊測試預約上遇到「Not authorized to edit this reservation.」——另外用一筆全新建立的預約重新測過一次完整的建立→編輯→儲存，正確成功（`{"success":true,"errors":[]}`，Drawer 正確關閉），確認那次「未授權」是舊測試資料/session 殘留狀態造成的雜訊，不是這輪改動的迴歸——這次改動完全沒有碰任何權限/授權相關的程式碼。
- 標題底線：比照 §36 的驗證方式（強制套用同一組 CSS class 而非依賴這個分頁量不準的 `:focus` 偽類），確認靜止 `rgb(217,224,234) 1px`、focus 強制值 `rgb(31,49,89) 2px`，跟 Quick Create 完全一致。
- `php -l` 確認 `OverviewFullCalendarAvailabilityPage.php` 語法正確；全程 console 沒有任何錯誤訊息。

補測了重複規則區塊：切換 `#rdRepeatType` 到「每週」，正確展開星期選擇器跟結束日期兩列、無 console 錯誤——這塊本來就沒有動到，確認沒有被連帶弄壞。

**沒有驗證到的部分**：一樣沒有實際視覺 screenshot；參與者/訪客/附件/自訂欄位這幾個唯讀摘要區塊（本來就只在載入「已有資料」的既有預約時才會顯示內容）沒有特地找一筆有這些資料的預約來核對渲染，這些區塊的 markup/邏輯這次完全沒有觸碰，理論上不受影響。

## 38. Quick Create 微調：大標題、24 小時制、按鈕字型

使用者附上目前畫面截圖，確認整體 Modal 尺寸/版面已經接近，只列了 8 項要調整的細節，明確要求「不要重做整個 Modal」——這輪範圍嚴格限制在這 8 項，沒有動其他已經確認過的部分。

- 標題輸入框正上方新增真正的 `<h2 class="qc-heading">`（22px／600）＋副標 `<p class="qc-subheading">`（"快速建立會議室預約"），`#quickCreateModal` 的 `aria-labelledby` 從原本指向那個看不見的 `<label>` 改成指向這個新的可見標題——一個對話框的無障礙名稱本來就該指向真正的標題文字，不是一個使用者根本看不到的輸入框標籤；原本的隱藏 `<label>` 留著（`id` 從 `quickCreateModalLabel` 改成 `qcTitleLabel` 避免跟新標題的 id 混淆），繼續當作 `#qcTitle` 這個輸入框自己的無障礙名稱。
- 標題輸入框 placeholder 沿用既有的 `AddTitle` 翻譯鍵，只改它的字串內容（"新增標題"→"新增會議名稱"）——這個鍵目前只有這一個輸入框在用（Drawer 的標題欄位用的是另一個 `NewReservation` 鍵），改值不會波及其他地方。沒有另外加一個看得見的「會議名稱」欄位標籤（使用者第 4 點明確要求不要）。
- 關閉叉叉本來就是 `position:absolute`、獨立於標題/輸入框的版面之外（§32 就是這樣設計的），這次沒有额外改動，量測確認新標題的容器寬度沒有因為叉叉而縮窄。
- 時間格式：`formatTimeLabel()`（`Web/scripts/overview_fullcalendar.js`）原本是 12 小時制＋「上午/下午」前綴，改成單純 24 小時制 `HH:MM`。這個函式是共用的——不只 Quick Create 的時間下拉，Drawer 的 `#rdStartTime`/`#rdEndTime` 兩個 `<select>` 選項、事件卡片的時間範圍顯示（`#epTime`）全部一起變成 24 小時制，符合使用者「統一使用」的用字，不是只改 Quick Create 這一處、其他地方還留著舊格式。拿掉「上午/下午」前綴後文字變更短，日期／開始時間／分隔線／結束時間同一列不換行這件事更穩，原本 §36 就已經是同一個 CSS grid 列，這次沒有再改版面。
- 「建立預約」按鈕：`Web/css/overview_fullcalendar.css` 新增 `#quickCreateModal #qcSave { font-family: inherit; font-weight: 600; }`——量出來這顆按鈕原本的字型渲染跟頁面本文的 `--bs-body-font-family`（Roboto/Hind/新細明體那組疊字）不是同一個，猜測是 Bootstrap 的 `.btn` 重置沒有把 `<button>` 的字型完全繼承回頁面本文字型（部分瀏覽器對 `<button>` 預設用系統 UI 字型），這裡直接用一條明確、範圍夠窄的規則蓋掉，不去改全站共用的 `.btn` 規則。

### 38.1 驗證

用 Claude Browser 工具登入 `mark_li` 實測：

- 標題文字/字級/字重讀回 `新增預約`／`22px`／`600`；副標文字讀回 `快速建立會議室預約`；輸入框 `placeholder` 讀回 `新增會議名稱`。
- `#quickCreateModal label:not(.visually-hidden)` 查詢結果是空陣列——確認沒有多出一個看得見的欄位標籤。
- 日期欄位跟開始時間 pill 的 `getBoundingClientRect().top` 幾乎完全一致（誤差 <2px）——確認同一列、沒有換行。
- 開始/結束時間 pill 文字讀回 `12:00`／`12:30`（無上午/下午前綴）；切到 Drawer 後 `#rdStartTime` 的前三個選項讀回 `00:00`／`00:30`／`01:00`，確認共用格式化函式後兩邊一致。
- `getComputedStyle(#qcSave).fontFamily` 跟 `getComputedStyle(document.body).fontFamily` 逐字元比對完全相同；`fontWeight` 讀回 `600`。
- `#quickCreateModal` 的 `aria-labelledby` 屬性讀回 `quickCreateModalHeading`（新標題的 id），不是原本的隱藏 label id。
- 全程 console 沒有任何錯誤訊息。

**沒有驗證到的部分**：一樣沒有實際視覺 screenshot，只能確認數字/文字/DOM 屬性層面的正確性，沒辦法親眼確認新標題在視覺上跟叉叉、留白的實際觀感是否符合使用者原本附圖的比例感。

## 39. 標題列的空白圖示欄補上圖示

§38 新增大標題時，標題輸入框那一列的圖示欄還是原本 `.qc-title-row` 專用的空白 `<div aria-hidden="true"></div>`（純粹用來佔位置對齊，故意不放圖示）。這次直接把它換成跟其他列一樣的 `<div class="qc-icon"><i class="bi bi-card-heading"></i></div>`——重用同一個 `.qc-icon` class，不用額外寫樣式就自動跟時鐘／建築／人群圖示同尺寸同顏色。圖示選用 `bi-card-heading`（標題/名稱語意），跟已經用掉的 `bi-clock`（時間）／`bi-building`（資源）／`bi-people`（參與者）／`bi-text-left`（說明）不重複。

量測確認標題圖示（`bi-card-heading`）跟時鐘/建築/人群三個圖示的 `font-size`／`color`／實際渲染寬高／左邊界座標完全一致（`20px`／`rgb(108,116,136)`／`20×30px`／`left:408px`，四個圖示逐一比對數字相同）。全程 console 沒有任何錯誤訊息。

## 40. 「行事曆首頁」目前頁面不再有常駐的按下去背景

使用者回報：側欄「行事曆首頁」在自己就是目前頁面時，一直維持著 hover／點擊那種「按下去」的背景，沒有實際點擊卻一直看起來像被按著。追到 `Web/css/coasia-theme.css` 的 `.sidebar-link.active { background: var(--sidebar-active); }`——這是 §33 那輪使用者當時明確要求的「使用 Active 背景」規格（跟更早之前 §30 修過的「同一個 bug」不是同一次事故，是後來的雙主題大改版又重新加回常駐背景），現在使用者回頭要求拿掉這個常駐感。

修法：拿掉 `.sidebar-link.active` 的 `background`（連同專門為它寫的 `.sidebar-link.active:hover` 覆寫規則一併刪除），改成只把文字/圖示上色成品牌深藍 `var(--sidebar-create-btn-bg, #1f3159)`（也就是「新增預約」按鈕已經在用的同一個深藍色，不是另外發明一個新色號）——背景完全交還給既有的通用規則：`.sidebar-link:hover`（真的移過去才有淡色背景）跟 `.sidebar-link:active`（真的按下去那一瞬間才有 Active 濃度背景），跟其他所有側欄項目共用同一套互動語言，「目前頁面」這件事改成只靠文字/圖示顏色辨識，不再佔用「互動反饋」的視覺語彙。

### 40.1 驗證

用 Claude Browser 工具登入 `mark_li` 實測：

- 靜止狀態（沒有任何互動）：`getComputedStyle(#navCalendarHome).backgroundColor` 讀回 `rgba(0,0,0,0)`（透明），文字/圖示顏色讀回 `rgb(31,49,89)`（即 `#1F3159`）——確認常駐背景已經拿掉，但顏色仍然清楚標示「這是目前頁面」。
- 直接 `fetch()` 抓線上實際 served 的 `coasia-theme.css` 內容比對，確認 `.sidebar-link.active` 只剩顏色規則，沒有殘留任何 `background`／`.sidebar-link.active:hover` 規則——排除瀏覽器快取到舊版的可能。
- 全程 console 沒有任何錯誤訊息。

**沒有驗證到的部分**：跟過去幾輪同樣的環境限制，這個分頁沒有被顯示、也沒辦法可靠測 `:hover`／`:active` 偽類的即時渲染結果，只能透過讀取「哪些 CSS 規則實際存在、選中哪個元素」來間接確認邏輯正確，沒辦法用滑鼠移過去/按下去親眼確認實際觀感。

## 41. 修正「此時段可用」誤判前一場會議剛結束的時段為衝突

使用者回報：有一場會議 15:30 結束，卻沒辦法預約 15:30-16:00——系統把「結束時間＝下一筆的開始時間」這種緊接在一起、實際上沒有重疊的情況判斷成衝突。

追查後確認問題出在 §36 新增的 `Pages/Ajax/OverviewFullCalendarAvailabilityPage.php`，不是實際送出預約時真正在把關的邏輯（`ResourceAvailabilityRule`／`ReservationConflictIdentifier`）——那條路徑本來就是對的，這次也用 Claude Browser 工具實際跑了一次「訂 14:00-14:30，再訂緊接著的 14:30-15:00，都在同一個資源上」，兩筆都成功存進資料庫，證實**真正建立預約從頭到尾沒有被這個 bug 擋過**，使用者會「沒辦法預約」比較可能是因為看到 Quick Create 上顯示「此時段不可用」而不敢送出、或以為系統真的會擋。

根因：`ReservationViewRepository::GetReservations()` 底層的 SQL（`lib/Database/Commands/Queries.php` 的 `$DATE_FRAGMENT`）故意用寬鬆的 `>=`/`<=` 區間比對先把「這段時間窗口內*可能*相關」的預約整批撈出來，效能考量，不是最終判斷——真正判斷「有沒有真的重疊」是後面 `IReservedItemView::BufferedTimes()->Overlaps()`（`DateRange::Overlaps()`，這個方法本身就有明確排除「首尾剛好相接」的邏輯：`!$this->GetBegin()->Equals($dateRange->GetEnd()) && !$this->GetEnd()->Equals($dateRange->GetBegin())`）這一步做的，兩段式設計。§36 寫可用性查詢端點時，只做了第一階段（撈出候選清單），直接把「清單是否為空」當作「有沒有衝突」，漏掉了第二階段的精確判斷——15:30 結束的會議，被 15:30-16:00 這個查詢窗口的寬鬆 SQL 撈進候選清單，但兩者其實完全不重疊，卻被誤判成「不可用」。

修法：`OverviewFullCalendarAvailabilityPage.php` 撈出候選清單後，多一層 `array_filter`，用 `$item->BufferedTimes()->Overlaps($requestedRange)` 重新精確篩選一次——跟真正存檔時用的是同一個方法，不是自己另外寫一套判斷邏輯，確保這個提示指示燈跟真正的送出結果永遠一致。順手把 `$blackouts`（停用時段）也套用同一層精確篩選——原本也是只看「候選清單是否為空」，同一種寬鬆-vs-精確的落差，一併修正。

### 41.1 驗證

用 Claude Browser 工具登入 `mark_li`，這次除了看 API 回應，還真的走了一次完整流程：

- 建立「邊界測試A」14:00-14:30，「邊界測試B」14:30-15:00，同一個資源（台北）——兩筆都成功存檔（Modal 都正確關閉、沒有錯誤訊息），直接查行事曆事件 API 確認兩筆資料庫紀錄都存在、時間完全緊接（`14:00→14:30`、`14:30→15:00`）。
- 開第二個 Quick Create、時間設成 14:30-15:00（緊接在第一筆後面）時，畫面上「此時段可用」正確顯示（修好之前這裡會顯示「此時段不可用」）。
- 直接打端點測邊界情況：`13:30-14:00`（結束時間＝邊界測試A 的開始時間）回傳 `available:true`；`13:45-14:15`（真的重疊 14:00-14:30 那段）回傳 `available:false`——確認「首尾剛好相接」跟「真的重疊」這兩種情況現在有正確分開判斷，不是修過頭變成什麼都算可用。
- `php -l` 確認語法正確；全程 console 沒有任何錯誤訊息。

**沒有驗證到的部分**：沒有測試「resource 設定了緩衝時間（buffer time）」這種情況下的行為——`BufferedTimes()` 本來就有處理這個（有設定緩衝時間的資源，首尾相接的時段還是會被緩衝時間擋下來，這是預期行為，不是這次的 bug），但這個 dev 環境目前測試用的資源沒有設定緩衝時間，沒有實際驗證過那條路徑。

## 42. 下拉選單開啟時，觸發按鈕不該還套一圈明顯的 focus 藍框

使用者附了兩張截圖：Quick Create 的結束時間下拉打開時、資源選擇器下拉打開時，觸發用的按鈕（時間 pill／資源選擇器 trigger）外面都多了一圈很明顯的深藍色外框，看起來像是「按鈕還被按著」的樣子。

根因：`#page-overview-fc button:focus-visible` 這條通用規則（`Web/css/overview_fullcalendar.css`）幫整個頁面所有按鈕都加了 `outline: 2px solid var(--primary)`——這本身是之前為了鍵盤可及性特別加的（"所有可點擊項目需要明顯 hover 和鍵盤 focus 狀態"）。但這些下拉選單的觸發按鈕本身已經有一條 `[aria-expanded="true"]` 專屬的「開啟中」樣式（換底色、換邊框色、換文字色），點開後瀏覽器又把這個按鈕判定成 `:focus-visible`（不少瀏覽器對 `<button>` 元素本身就算滑鼠點擊也會顯示 focus 樣式，不是只有鍵盤導覽才會），兩層樣式疊在一起，才會變成「已經換色的按鈕外面又多包一圈粗藍框」這種視覺上很像 bug 的效果。

修法：新增一條更精確的規則，只在 `[aria-expanded="true"]` 這個「下拉已經打開」的狀態下，把 `:focus-visible` 的外框拿掉（`outline:none`）——因為開啟狀態本身的換色已經是足夠清楚的「這是目前操作中的按鈕」提示，不需要再疊一圈外框。屬性選擇器要求 `aria-expanded` 剛好等於 `"true"`，沒有這個屬性或值是 `"false"` 的按鈕完全不受影響，一般用 Tab 鍵導覽到其他按鈕（沒有下拉、不會展開）時，原本的 focus 外框機制原封不動，鍵盤可及性沒有被這次改動犧牲掉。

因為是通用規則（`#page-overview-fc button[aria-expanded="true"]:focus-visible`，沒有寫死 class），時間 pill（`.qc-time-pill`）、Quick Create／Drawer／工具列三處的資源選擇器 trigger、檢視選單、都是同一條規則一次涵蓋，不用個別修。

### 42.1 驗證

用 Claude Browser 工具登入 `mark_li` 實測：

- 開啟 Quick Create 的資源選擇器，確認 `aria-expanded="true"`；掃描 `overview_fullcalendar.css` 所有含 `outline` 的規則，用「拿掉 `:focus-visible` 後的結構」個別比對這個按鈕是否符合選擇器，確認新舊兩條規則都命中同一個元素——新規則（`button[aria-expanded="true"]:focus-visible`，specificity 比舊規則多一個屬性選擇器）specificity 更高，不管程式碼寫在檔案的哪個位置都會贏，`outline` 最終算出來是 `none`。
- 時間 pill（`#qcStartPill`）同樣方式確認：本身是 `<button>`，開啟時 `aria-expanded="true"`，符合同一條通用規則，不用另外為 `.qc-time-pill` 寫一條專屬規則。
- 全程 console 沒有任何錯誤訊息。

**沒有驗證到的部分**：這個環境沒辦法可靠重現「滑鼠真的點下去」觸發的 `:focus-visible` 即時渲染結果（過去好幾輪都有記錄這個限制），這次改用「比對 CSS 規則的 specificity 邏輯」取代肉眼看渲染結果，邏輯上站得住，但沒有像使用者截圖那樣親眼確認藍框真的消失了。

## 43. CoAsia 員工預建帳號匯入（AD 登入前預先建立可搜尋的帳號）

使用者提供 HR/AD 匯出的 `사용자목록2026-07-31_142216.xls`，要求讓從未登入過系統的 AD 員工也能立即被搜尋、加入會議參與者，第一次真的用 AD 登入時再自動接上這筆預建帳號，不能產生第二筆。

### 43.1 資料來源跟使用者原本假設的欄位對不上，先澄清才動工

實際用 Python/xlrd 讀出 Excel 的真實欄位後，發現跟使用者原始需求描述的欄位對應（Email→username、Office→組織資料）完全對不上：這份匯出**完全沒有 Email 欄位、也沒有 Office（實體地點）欄位**。實際欄位是：號碼、名字（常常是「原文姓名(英文名)」但格式不一致）、ID（AD 登入帳號，如 `ds.shin`）、Department path（完整部門路徑）、一個沒有標題文字的部門代碼欄、所屬（部門路徑最後一段）、職位、人員編號、Office Phone、手機號碼。而且是「一人一部門列」的匯出格式，同一人常常因為身兼多部門而出現在好幾列（例如 Jeff Cui 出現 2 次、Cassidy Chen 出現 3 次）。

在寫任何 migration/程式碼之前，先用 `AskUserQuestion` 跟使用者確認三個關鍵、會影響 schema 設計且有實際後果（寄錯邀請信、資料被錯誤覆蓋）的決策，而不是自己猜：
1. **Email 怎麼組出來**——沒有 Email 欄位，只有 ID。比對現有 DB 裡唯一已知的真人帳號 `shawn.admin`（真實 email `shawn_tsai@coasia.com`）發現這份 Excel 裡蔡尚峰（Shawn Tsai）那一列的 ID 剛好就是 `shawn_tsai`，強烈佐證 `email = {ID}@coasia.com`（注意跟 AD 登入本身用的 LDAP bind suffix `@coasia.com.tw`——`plugins/Authentication/ActiveDirectory/ActiveDirectory.config.php` 裡的 `account.suffix`——是不同網域，一個是登入用的 UPN suffix，一個才是真正收信的信箱網域）。使用者確認採用這個推論。
2. **Office 欄位怎麼處理**——確認來源真的沒有這個欄位後，使用者選擇乾脆不生造一個 Office 欄位，只保留 Department path／所屬／職位／人員編號。
3. **同一人多列怎麼合併**——使用者選擇「串接所有不重複的 Department path」而不是只留第一筆或最後一筆。

事後在真的跑過 `--dry-run` 之後，用 DB 裡既有的 `mark_li`（username=`mark_li`，email=`mark_li@coasia.com`）反向驗證了 `{ID}@coasia.com` 這個推論完全正確——這筆在 Excel 裡也存在（ID=`mark_li`），程式正確判定成「已存在、只更新」而不是新建。

### 43.2 Schema：`database_schema/upgrades/4.2/`

- `schema.sql`：`users` 新增可空的 `ad_object_guid VARCHAR(64)`＋唯一索引，用信通樣既有的 `information_schema.COLUMNS`／`STATISTICS` guard 模式（MySQL 8 不支援 `ADD COLUMN IF NOT EXISTS`，且 `phing-tasks/UpgradeDbTask.php` 每次都會整個重跑 upgrades 目錄）。這個欄位目前匯入程式跟現有的 AD 登入同步流程（`plugins/Authentication/ActiveDirectory`）都不會去寫——來源 Excel 沒有 objectGUID，真的要接上即時 LDAP 讀取的話會動到正式在跑的登入路徑，範圍已經超出這次「先專注完成匯入」的要求，先把欄位留著、明確記在 comment 裡當作已知的後續項目。
- `data.sql`：在 `custom_attributes`（`attribute_category=2`／USER）guarded 新增 5 個欄位：Employee ID、Chinese Name、Korean Name、Department Paths（多行）、AD Managed Account（checkbox）。全部 `admin_only=1`——這些都是 HR 來源、每次重跑匯入都會被刷新，不應該讓員工自己在個人資料表單裡改掉跟 HR 對不上。`custom_attribute_id` 是 auto-increment，匯入程式在執行期用 label 去查 id，不寫死數字，跨環境安全。
- 已經在 dev DB（`librebooking-coasia-db-1`）跑過兩次確認 idempotent：第二次重跑 `custom_attributes`／`dbversion` 都沒有重複新增。

### 43.3 `Jobs/import-coasia-employees.php`

新增 composer 依賴 `phpoffice/phpspreadsheet`（這個專案原本完全沒有能讀 .xls 的套件；`composer require` 確認沒有已知安全漏洞）——因為公司每隔一段時間就會重新匯出這份名單（`D:\Downloads` 底下已經有 06-08、07-15、07-31 三份），直接讓 Job 吃 `.xls` 比每次手動轉 CSV 更貼近實際會怎麼被使用。

核心邏輯刻意**沒有**直接呼叫既有的 `Registration::Synchronize()`（AD 登入本來在用的 create-or-update API）——雖然那支 API 表面上正是「找不到就建立、找到就更新」，但深入看了它的 `Register()`／`Synchronize()` 之後發現兩個跟這次需求衝突的地方：(1) 找到既有帳號時一定會呼叫 `notificationStrategy->NotifyAccountCreated()`／一律可覆寫密碼雜湊，直接拿來對 100+ 筆匯入會寄一堆通知信、也可能動到不該動的密碼欄位；(2) 找到既有帳號的分支完全沒有處理自訂屬性（custom attributes）——只有全新建立的分支才會呼叫 `ChangeCustomAttributes()`，代表既有員工（例如 `mark_li`）的部門/員工編號/中英韓名這些欄位永遠不會被這條路徑更新到。所以改成直接用 `UserRepository`／`User` domain object 手刻 create-or-update，複用同一個 `Queries::CHECK_USER_EXISTENCE`（username OR email 比對）當作判斷依據，但精確控制只更新姓名／組織／職位／自訂屬性，完全不碰密碼、狀態、群組權限。

安全重跑／不降權既有帳號的具體作法：
- 判斷新增 vs 更新：`UserRepository::UserExists($email, $username)`——比對到就 `LoadById()` 整包讀出來檢查「username 跟 email 是不是兩個都真的對得上」，只要有一個對不上就整筆跳過丟進「衝突」清單、不猜、不動，留給人工判斷。這條路徑在真實資料上抓到一個活生生的案例：Excel 裡蔡尚峰算出來的 username 是 `shawn_tsai`，但 DB 現有帳號是 `shawn.admin`（email 一樣），程式正確判定成衝突並跳過，沒有誤觸這個現有的 Application Administrator 帳號。
- 更新既有帳號時只呼叫 `ChangeName()`／`ChangeAttributes()`（保留原本 phone，只換 organization/position）／`ChangeCustomAttributes()`，從頭到尾不呼叫 `ChangeEmailAddress()`／`ChangeUsername()`／任何跟 `user_groups`／狀態相關的方法——`UserRepository::Update()` 送出的 `UpdateUserCommand` 是把整個 User 物件現在的欄位值整包寫回去，只要程式沒去動密碼/狀態/群組相關的 private 欄位，這些值就會原封不動跟著 `Load()` 進來的值一起寫回去，不會被意外重置。
- 新建帳號：`User::Create()` 預設 `status_id = ACTIVE`（不是 `CreatePending`），因為 `Pages/Ajax/AutoCompletePage.php::GetUsers()` 對一般（非管理員）使用者的參與者搜尋只查 `status_id = ACTIVE`，沒有查 `lastlogin`——確認了「從未登入」不是問題，「狀態不是 ACTIVE」才是；密碼用 `random_bytes(32)` 產生、雜湊後丟棄明文，不留、不共用，滿足「不建立共用或預設密碼」。
- `UserRepository::Add()` 會自動把新帳號加進 `groups.isdefault=1` 的預設群組（這是全站任何一種註冊都會發生的既有行為，不是這次新加的邏輯），匯入程式完全沒有主動指定任何群組，更不會給 IT 部門員工預設管理員權限。

報告內容涵蓋需求列出的每一項（Excel 總筆數／可新增數／已存在或更新數／缺少必要欄位／衝突／重複員工編號），另外多加了兩個從真實資料裡發現、值得讓人看一眼但不阻擋匯入的清單：「Name 欄位跟 ID 欄位完全相同」的疑似系統/服務帳號（抓到 `tw.foundry`／`erpuser`／`hr`／`tw_scan`／`pham_lee`／`sapien`／`cn_scan`／`oversea_scan` 共 8 筆——這個 heuristic 正確地沒有誤傷同樣掛在「Dummy」部門底下、但是真人的 `mark_li`／`stella_hsieh`），跟「抽不出英文姓名、退回用原文姓名或 ID 當顯示名稱」的 6 筆（`eyssiy`／`jy_kim`／`sean.joo`／`hazel.kim`／`emma_choi`／`safeworkplace`）。`--apply` 支援 `--exclude=id1,id2,...`，讓使用者看完 dry-run 報告後自己決定要不要排除特定帳號，而不是由程式自己猜哪些是「不該匯入」的。

### 43.4 使用者決定：排除整個 Dummy 部門，shawn_tsai 先跳過，其餘 apply

看過 dry-run 報告後，使用者的決定是：不要建立 Dummy 部門，Dummy 底下所有帳號都先排除；`shawn_tsai` 那筆衝突先跳過；其餘直接 apply。

「排除 Dummy 部門」原本的「Name 欄位與 ID 欄位相同」heuristic 抓不全——它抓到 8 個明顯的系統帳號（`tw.foundry`／`erpuser`／`hr`／`tw_scan`／`pham_lee`／`sapien`／`cn_scan`／`oversea_scan`），但漏掉 `mark_li`（「李宗憲(Mark Li)」≠「mark_li」）、`stella_hsieh`（「Stella Hsieh」≠「stella_hsieh」，大小寫/底線不同）、`kimi.jin`（「金英旭(kimi.jin)」≠「kimi.jin」）——這三個雖然名字跟 ID 不同，但部門路徑一樣是純 `Dummy`。所以另外加了一條獨立規則 `IsAllDummyDepartment()`：一個人合併後的 Department path 列表，如果**每一筆**的最外層節點都是 `Dummy`，整個人排除（不建立也不更新），跟原本「Name 欄位與 ID 欄位相同」的 heuristic 分開判斷、各自獨立回報。套用後：Dummy 部門排除數＝10（含 `mark_li`／`stella_hsieh`），可新增數從 121 降到 112，已存在／更新數從 1 降到 0（因為原本那 1 筆「更新」正是 `mark_li`）。`shawn_tsai` 本來就是「衝突」分類、`--apply` 本來就不會動它，不需要額外處理。

### 43.5 Apply 時發現並修掉一個嚴重 bug：`Pages` class 沒有 require，錯誤還被吞掉

第一次真的執行 `--apply` 時，程式印完 dry-run 報告後就整個停住，`=== Apply Results ===` 完全沒出現，`SELECT COUNT(*) FROM users` 確認 0 筆寫入、也沒有任何 exit code 非 0 或錯誤訊息——一開始完全看不出卡在哪。

加上逐筆 debug log 後追到：`User::Create()`（`Domain/User.php`）的第 9 個參數 `$homepageId` 預設值是 `Pages::DEFAULT_HOMEPAGE_ID`，但這支 Job 從沒有 `require` 過定義 `Pages` 這個 class 的 `Pages/Pages.php`——PHP 對「找不到 class」丟的是 `Error`（更精確地說是 `\Error`），不是 `Exception`，而我原本每筆的 `catch (Exception $ex)` 只抓 `Exception` 沒有抓 `Error`，所以這個致命錯誤沒有被任何一層 catch 住，直接讓整支 script 中止在第一筆使用者身上，而且沒有印出任何看得到的錯誤（容器 PHP 設定 `display_errors` 沒開）。修法：(1) `Pages/Pages.php` 本身沒有任何依賴、單純是一組常數 class，直接補上這行 require；(2) 把每一筆 create/update 迴圈裡的 `catch (Exception $ex)` 全部改成 `catch (Throwable $ex)`（外層 `main()` 的 try/catch 也一併改），這樣以後任何一筆資料觸發任何種類的致命錯誤，都只會讓那一筆進「錯誤」清單，不會讓整支批次匯入靜默中止在第一筆。修好後先重新用 `--dry-run` 確認報告數字沒變，才真的執行 `--apply`。

### 43.6 Apply 結果

第一次 `--apply`：新增 112、更新 0、錯誤 0，`users` 表從 2 筆變成 114 筆。緊接著重跑第二次 `--apply` 驗證安全重跑：新增 0、更新 112、錯誤 0，`users` 表仍然是 114 筆——確認同一批人不會被重複建立。

抽查驗證：
- `shawn.admin`（user_id=2）密碼雜湊／`Application Administrators` 群組完全沒變（這筆本來就在衝突清單，從頭到尾沒被寫入過）。
- `mark_li`（user_id=8）密碼雜湊沒變（這筆這次被 Dummy 規則排除，同樣沒被寫入）。
- 新建的 112 筆全部 `status_id=1`（ACTIVE）——對照 `Pages/Ajax/AutoCompletePage.php` 的搜尋邏輯，這代表一般（非管理員）使用者現在就能透過「新增參與者」搜尋框搜到這些從未登入過的員工。
- 抽查 `ds.shin`：`organization`="President"、`position`="社長"、自訂屬性 Employee ID="CZ2014011"、Chinese Name="申東洙"、Korean Name=""（空，正確——這個人是中文名）、Department Paths="President"、AD Managed Account="1"。
- 新建帳號（如 `ds.shin`）只被加進非管理員的預設群組「全體員工」，沒有任何一筆被加進 `Application Administrators` 或其他管理群組——包含 IT 部門的員工（`mars_huang`／`joe_zhong`／`lucia_huang` 等）也不例外。

**沒有做的部分**：沒有把 AD 登入路徑（`plugins/Authentication/ActiveDirectory`）改成會即時抓/寫 `ad_object_guid`——範圍評估後認為那是動到正式登入邏輯的獨立工作，跟這次「先完成匯入」的優先順序衝突，只留了欄位當作已知的後續項目。也沒有重做參與者搜尋/新增 UI，也沒有另外用瀏覽器實測「新增參與者」畫面——確認 `Pages/Ajax/AutoCompletePage.php`／`Web/scripts/reservation.js` 本來就是送真正的整數 `user_id`（不是 email），且新帳號的 `status_id` 已經在 SQL 層級確認符合搜尋條件，依照使用者「不要跑大量無關測試」的要求沒有再另外做 UI 端到端驗證。

## 44. 「新增參與者」真正落地：Drawer 加上參與者搜尋＋chip，Quick Create 只負責轉交＋聚焦

使用者回報：§37/§39 之前的說法（"Quick Create 本來就不能存參與者，所以按鈕直接轉交給 Drawer"）掩蓋了一個事實——轉交過去的 Drawer 那邊，參與者區塊本來就只有 §37 從 `data.participants` 畫出來的**唯讀**摘要（`#rdParticipantsSummary`／`#rdParticipantsList`），從頭到尾沒有搜尋框、沒有新增按鈕，`buildCreateBody()` 也從來沒有送過 `participantList[]`——所以整條路徑上沒有任何一個環節真的能加參與者，是道地的假功能。

這次要求明確只針對「建立預約時」，所以刻意把範圍切成：**Create 模式**（Quick Create 的「新增參與者」／「更多選項」開出來的 Drawer）拿到全新的搜尋＋chip 編輯器；**Edit 模式**（點開一筆已存在的預約）維持原本的唯讀摘要，完全沒動——沒有做出「看起來可以編輯、存檔卻默默沒生效」的另一個假功能。

### 44.1 參與者搜尋元件

`tpl/Overview/overview_fullcalendar.tpl` 在原本唯讀的 `#rdParticipantsSummary` 前面新增一個獨立的 `#rdParticipantsEditor` 區塊（預設 `d-none`，只在 `openReservationDrawer()` 的 create 分支才 `removeClass`），包含：一個一直顯示的搜尋輸入框（不是像 `.resource-picker` 那樣的「點了才展開」觸發鈕，因為搜尋沒有一個能收合回去的「目前值」）＋輸入時彈出的結果下拉，跟一列可移除的 `.reservation-chip`（複用 §37 `renderRepeatCustomDatesList()` 那組「chip + `btn-close`」pattern，不是另外發明一套）。

`overview_fullcalendar.js` 新增的邏輯（`renderParticipantChips()`／`addParticipant()`／`runParticipantSearch()`／`renderParticipantSearchResults()`）：輸入框 250ms debounce 後打 `ajax/autocomplete.php?type=user&term=...`——這正是 `Web/scripts/reservation.js`（舊版經典表單）本來就在用的同一支 `Pages/Ajax/AutoCompletePage.php::GetUsers()` 端點，不是另外寫一套搜尋邏輯；回傳結果按 `Id`（真正的 `users.user_id`）存進 `rdParticipants` 陣列，已選過的人從下拉結果裡濾掉避免選兩次。回應回來時比對輸入框當下的值是否還等於發出請求當下的字串，不一致就整包丟棄——避免打字很快時，一個較慢的舊回應蓋掉使用者已經在看的新結果。

CSS（`Web/css/overview_fullcalendar.css`）新增 `.participant-search*` 一組規則，視覺語彙照抄 `.resource-picker-search`／`.resource-picker-panel`／`.resource-picker-item`／`.resource-picker-empty`（同一批圓角/陰影/hover 底色 token），只是把「點擊展開的觸發鈕」換成「一直開著的輸入框」。

### 44.2 送出真正的 `user_id`

`buildCreateBody()` 在既有欄位後面，把 `rdParticipants` 陣列逐一 `body.append('participantList[]', p.id)`——`p.id` 是搜尋結果的 `Id`（`AutocompleteUser::$Id`，即 `users.user_id`），從頭到尾沒有經手過 email。伺服器端完全沒改：`Pages/Ajax/ReservationSavePage.php::GetParticipants()` 讀 `$_POST['participantList']`（PHP 對重複 `name[]` 欄位的標準陣列收集行為）、`ReservationSavePresenter.php` 呼叫 `ChangeParticipants($participantIds)`——這條路徑本來就存在、本來就吃整數 `user_id`，這次只是終於有東西真的送過去。

### 44.3 Quick Create「新增參與者」：轉交＋自動聚焦

`openReservationDrawer()` 新增一個可選的 `options.focusSelector`：等 Bootstrap Modal 的 `shown.bs.modal`（動畫真的播完，不是 `.show()` 呼叫完當下——那時候元素可能還沒吃得到 focus）才把焦點移過去。Quick Create 的 `#qcAddParticipants` 點擊處理常式改成呼叫 `openReservationDrawer({ mode: 'create', draft: draft, focusSelector: '#rdParticipantSearchInput' })`；旁邊的「更多選項」按鈕（`#qcMoreOptions`）完全沒改，沒有傳這個參數，維持原本沒有自動聚焦的行為——兩顆按鈕現在真的不一樣，不是文字不同、行為相同的兩顆按鈕。

### 44.4 途中修掉的一個環境問題：`AutoCompleteType` class 沒被載入

第一次套用後整頁變成 500 錯誤（`錯誤／未知錯誤`），查 container log 抓到 `Error: Class "AutoCompleteType" not found in .../tpl_c/....file_overview_fullcalendar.tpl.php:727`——這個 class 定義在 `Pages/Ajax/AutoCompletePage.php` 裡，`tpl/Reservation/create.tpl` 那邊能直接用 `{AutoCompleteType::User}` 是因為經典預約表單的 Page/Presenter 鏈路本來就會載入到那支檔案，但 `Pages/OverviewFullCalendarPage.php` 從來沒有這個依賴。修法：在 `Pages/OverviewFullCalendarPage.php` 頂端補一行 `require_once(ROOT_DIR . 'Pages/Ajax/AutoCompletePage.php')`——先確認過這支檔案裡沒有會跟既有 class 衝突的重複宣告（`AutocompleteUser`／`AutoCompleteType`／`XAutocompleteUser` 都是這支檔案獨有，唯一共用的 `FullName` 只是「使用」`Domain/Values/FullName.php` 的既有類別，不是重新宣告），確認安全後才加這行，而不是用字串硬寫 `type=user` 繞過去。

### 44.5 驗證

用 Claude Browser 工具登入 `mark_li`（這次因為要走「建立預約」這條真正碰資料庫寫入的路徑，且環境裡沒有任何已知密碼可用，先跟使用者確認過，由使用者提供了 `mark_li` 的密碼，才進行登入，沒有自己重設任何帳號密碼），做了完整的端到端流程，不只是看 DOM：

- Quick Create 點「新增參與者」→ Drawer 開啟後 `document.activeElement.id` 直接讀回 `rdParticipantSearchInput`，確認自動聚焦真的生效，不是猜測。
- 在參與者搜尋框輸入 `ds.shin`（這次匯入的員工之一，先前從未登入過）：Network 分頁確認確實打了 `GET ajax/autocomplete.php?type=user&term=ds.shin` 拿到 200，下拉結果正確顯示「DS／ds.shin@coasia.com」——證實剛匯入、還沒登入過的員工，現在真的能被搜到。換一次測試改輸入部分字串 `shin`（測子字串比對），同樣正確命中同一人。
- 點下拉結果：chip 正確出現「DS」＋可見的 Remove 按鈕，搜尋框清空、下拉關閉、焦點回到搜尋框（方便連續加人）。
- 填標題、不動日期時間欄位（維持 Quick Create 帶過來的當下時段），送出建立：Network 確認 `POST ajax/reservation_save.php` 回 200。直接查 `reservation_series`／`reservation_instances`／`reservation_users` 三張表確認新建的預約（`series_id=121`）底下 `reservation_users` 有兩筆：`user_id=8`（`mark_li`，等級 1＝建立者）跟 `user_id=9`（`ds.shin`，等級 2＝參與者）——`user_id=9` 正是資料庫裡 `ds.shin` 真正的主鍵，不是字串化的 email，證實 `participantList[]` 全程都在傳真正的 `user_id`。
- 測試過程中先用 `form_input` 直接改 Drawer 的時間 `<select>` 觸發過一次「開始時間和日期不正確」的伺服器端驗證錯誤——花時間確認過這是自動化工具繞過真實使用者互動改 `<select>` 值時的既有落差（跟這次改動的程式碼無關，`drawerStartEndValues()`／`buildCreateBody()` 的日期時間欄位這輪完全沒有觸碰），改用「不動時間欄位、維持 Quick Create 帶過來的預設時段」重測一次後正確存檔成功，排除了誤判。
- 驗證完後透過既有的 `ajax/reservation_delete.php` 端點（不是直接下 SQL DELETE）刪除測試預約，確認資料庫裡該預約 `status_id` 正確變成 `2`（Deleted，這個 app 本來就是軟刪除，不是這次改動造成的）；額外查證 `mark_li` 帳號本身的 `email`／`status_id` 全程沒有被這輪測試動到。
- `php -l` 確認 `Pages/OverviewFullCalendarPage.php` 語法正確；`node --check` 確認 `overview_fullcalendar.js` 語法正確（這個環境沒有內建的 JS 執行/瀏覽器 lint 工具鏈，用 Node 的語法檢查頂替）。

**沒有驗證到的部分**：一樣沒有實際視覺 screenshot（這個環境的 screenshot 工具本身逾時失敗，全程改用 DOM/Network 層級的資料驗證，跟過去幾輪同樣的限制）；沒有測試「同時輸入很快、連續打字觸發多次搜尋」下 debounce／過期回應丟棄機制在真實網路延遲下的行為，只確認了程式碼邏輯本身；沒有測試 Edit 模式（點開已存在預約）—— 這次刻意沒有改動那條路徑，維持原本的唯讀摘要顯示；沒有測試選了很多參與者（10+ 人）時 chip 換行／搜尋結果捲動的視覺呈現。

## 45. 登入頁重新設計（CoAsia Blue 極簡風格）

依照提供的 mockup，把 `tpl/login.tpl` 從原本的預設 LibreBooking 登入畫面改成 Google Calendar 風格的極簡版面：CoAsia Blue（`rgb(31, 49, 89)`）主色、置中卡片、移除「記住我」以外的次要欄位（原本畫面上的語言切換、註冊連結等視覺雜訊依 mockup 精簡）。改動集中在 `tpl/login.tpl` 與 `Web/css/coasia-theme.css`，後端登入邏輯（`Pages/LoginPage.php`／驗證流程）完全沒有更動，純視覺重製。

## 46. 「更多選項」全頁化：從 Drawer 快速編輯改成完整預約頁 + 5 項修正

Quick Create 的「更多選項」原本開的是 §37 那個 Drawer（右側滑出面板），這次要求改成導到完整的經典預約頁（`reservation.php`），理由是 Drawer 空間有限，複雜欄位（附件、提醒、重複規則細節等）在裡面很難操作。

### 46.1 資料轉交與返回狀態

Quick Create 已經填好的欄位（標題、日期時間、資源、參與者、說明）透過 `sessionStorage`（不是 URL query string，避免特殊字元跟長度限制）轉交給 `reservation.php`，該頁載入時讀取並帶入表單初始值。從完整預約頁按「取消」或送出成功後導回月曆，會恢復導出前的檢視狀態（哪個月份/週、Mine Only 篩選等），同樣透過 `sessionStorage` 存讀，而非重新導回月曆首頁重置畫面。

新增未儲存變更警告：使用者在完整預約頁動過欄位後，若嘗試離開（瀏覽器返回、關閉分頁），跳出 `beforeunload` 確認對話框，避免手滑遺失剛填的內容。

`tpl/Overview/overview_fullcalendar.tpl` 移除了整個 Drawer 相關 HTML／`overview_fullcalendar.js` 移除對應的 Drawer 開合/資料綁定邏輯——不是關閉入口而已，是整套 Drawer create 流程直接拔掉，`#qcMoreOptions` 改成單純的分頁跳轉。

### 46.2 完整預約頁視覺重製

`tpl/Reservation/create.tpl` 依照第二份 mockup 大改：Quick Create 風格的圖示列（icon-row）排列各欄位、760–900px 置中卡片，拿掉舊版經典表單裡的 Owner／Users 選擇器（這條路線本來就沒有代訂功能，見 §46.3）、View Availability 連結、重複出現的儲存按鈕。新增 `Web/css/reservation-page.css` 承載這版樣式，重用 Quick Create 既有的 `.resource-picker*`／`.participant-search*` class 名稱與視覺語彙，而非另外發明一套。`tpl/Reservation/edit.tpl` 的 `submitButtons` 區塊同步簡化，拿掉舊有的匯出/寄信/列印/PDF/複製下拉選單，只留 Check-in／Check-out／刪除／更新。

### 46.3 五項具體修正

1. **完整保留 Quick Create 帶過來的欄位**：標題／日期時間／資源／參與者／說明五項全部正確帶入完整預約頁的對應欄位，不再是只轉頁不轉資料。
2. **參與者搜尋共用 Quick Create 邏輯**：把 §37/§44 的參與者搜尋邏輯抽成獨立檔案 `Web/scripts/participant-picker.js`（`createParticipantPicker` factory），Quick Create 與完整預約頁（`tpl/Reservation/participation.tpl` 重寫後）現在呼叫同一份程式碼、打同一支 `ajax/autocomplete.php?type=user` 端點，因此中英文姓名／帳號／Email／員工編號都能搜到（`AutoCompletePage::GetUsers()` 本來就支援這些欄位比對，這次只是讓完整頁也用得到同一支）。抽出共用檔後 `tpl/Overview/overview_fullcalendar.tpl` 需要多載入一個 `{jsfile src="participant-picker.js"}`——第一次套用時漏了清 `overview_fullcalendar.tpl` 的 Smarty 編譯快取，導致瀏覽器主控台噴 `createParticipantPicker is not defined`，清快取後修正。
3. **資源改成可搜尋、可切換**：完整預約頁的資源欄位從唯讀顯示改成跟 Quick Create 一樣的 `.resource-picker` 下拉，切換資源後會重新打可用性查詢。**已知限制**：查過 dev 資料庫，CoAsia 目前 12 個資源分屬 12 個不同的 schedule，每個 schedule 剛好只有 1 個資源（`SELECT schedule_id, COUNT(*) FROM resources GROUP BY schedule_id` 全部等於 1），代表這個下拉選單目前實際上永遠只會顯示 1 個選項——除非之後有需求要做「跨 schedule 切換資源」的更大改動，這次沒有動這塊，只是把元件做對。
4. **標題欄位**：placeholder 改成「新增會議名稱」，空白送出時後端／模板統一顯示為「（無標題）」（`NoReservationTitle` 語言字串，跟既有 Quick Create 行為一致）。
5. **補回 `requestId`／`expectedVersion`**：完整預約頁的建立流程原本完全沒有 idempotency／樂觀併發保護（這兩項本來只有已拔掉的 Drawer 有）。新增：`ExistingReservationPage`／`ReservationComponentBinder` 補上 `SetVersion()`，`ReservationUpdatePresenter` 送出時帶上 `SetExpectedVersion()`，`Pages/Ajax/ReservationUpdatePage.php` 新增 `catch (ReservationVersionConflictException)` 回傳 HTTP 409。過程中發現前端 jQuery Form Plugin 的 `ajaxSubmit` 只有在成功時 (`success` callback) 才會把回應內容灌回 `target`，故意送一個過期的 `expectedVersion` 觸發 409 後，畫面上 `#result` 完全沒反應——因為 `ajaxOptions` 原本沒定義 `error` callback。修正：補上 `error` callback，手動把 `xhr.responseText` 塞進 `#result` 並呼叫既有的 `reservation.showResponse()`，之後重測確認 409 會正確顯示錯誤訊息。

## 47. 全站主要字體統一為 Noto Sans TC

原本各頁面字體混雜（新細明體／Roboto／Hind 視哪條 CSS 規則先套用而定）。改成單一字體堆疊：`'Noto Sans TC', 'Microsoft JhengHei', 'PingFang TC', Arial, sans-serif`（`Web/css/coasia-theme.css` 的 `--bs-body-font-family`），Windows／macOS 上即使 Noto Sans TC webfont 沒載入成功，也會退回對應 OS 原生的正體中文字體而不是新細明體。字重統一：一般內容 400（瀏覽器/Bootstrap 預設，沒有每處重新宣告）、操作元件（按鈕／表單標籤／導覽連結）500、標題 h1–h6 600。

## 48. Email／LINE 通知版面重新設計 + 視訊會議主持人資訊整合

依照提供的 mockup 重新設計預約通知信與 LINE 通知的版面，並串接視訊會議資源的主持人登入資訊（Zoom／Google Meet 的共用主持人帳號密碼），依權限決定誰看得到什麼。

### 48.1 匯入 5 個視訊資源的真實連線資訊

從使用者提供、只存在本機的 `coasia-video-conference-secrets.local.ini`（Zoom TW／CN／Oversea、Google Meet1／2 各自的會議連結／Meeting ID／會議密碼／共用主持人帳號密碼）寫了一支一次性匯入腳本 `Jobs/import-video-conference-resources.php`，比照 §43 的 `--dry-run`／`--apply` 慣例：連結／Meeting ID／密碼寫進既有的 `video_url`／`video_meeting_id`／`video_passcode` 自訂屬性（跟其他資源屬性一樣明文存放，這幾個欄位本來就是要顯示給參與者看的）；主持人帳號密碼則寫進新表 `resource_video_conference_host`（`database_schema/upgrades/4.3/schema.sql`），密碼用 AES-256-CBC 加密後才落地（`lib/Application/Resources/VideoConferenceCredentialEncryption.php`），加解密金鑰放在 `config/config.php`（gitignored，不進 git）新增的 `reservation.video.conference.host.encryption.key`。**全程遵守「不得把帳密寫入原始碼、migration、log、測試快照或 Git」**：匯入腳本的 dry-run／apply 報表只印 `(set)`/`(empty)` 遮罩，從未印出實際帳密；驗證解密是否成功只比對字串長度，沒有印出明文。

匯入時發現一個既有的資料設定問題：`video_url`／`video_meeting_id`／`video_passcode` 這三個自訂屬性原本被限定（透過 `custom_attribute_entities` 表）只套用在 `resource_id=1`（一間實體會議室「台北 Taipei」）——顯然是這幾個屬性剛建立、還只有一個測試資源時留下的設定，從來沒有跟著擴大到後來新增的 5 個真正的視訊資源。`ReservationSavePresenter` 原本顯示預約成功橫幅用的視訊資訊查詢方式繞過了這個限制（直接用屬性 ID 查值，沒經過 `CustomAttribute::AppliesToEntity()` 的資源範圍檢查），所以橫幅本來看起來正常，但這次新寫的 `ReservationEmailTemplateContext::VideoConferenceResources()`（見 48.2）重用了既有、會做範圍檢查的 `Resources()` method，因此對 5 個真實視訊資源全部回傳空陣列。修正：把這三個屬性的 `custom_attribute_entities` 範圍從 `[1]` 改成 `[8,9,10,11,12]`（5 個真正的視訊資源 ID）。

### 48.2 Email 版面

`tpl/Email/reservation-notification-body.tpl`（`ReservationCreatedEmail`／`ReservationUpdatedEmail`／`ReservationApprovedEmail`／`ReservationDeletedEmail` 共用的內文樣板）依 mockup 順序重排：1. 預約狀態 2. 會議名稱 3. 日期與時間 4. 視訊會議資源（或會議室，兩者互斥顯示）5. 預約人 6. 全寬「加入視訊會議」按鈕 7. 全寬「主持人登入資訊」區塊 8. 「查看預約詳情」文字連結。CoAsia 主色 `rgb(31, 49, 89)` 抽成 `$CoasiaPrimary`，跟既有的成功/待審/取消狀態色分開（品牌色跟語意色是兩件事）。

新增 `ReservationEmailTemplateContext::VideoConferenceResources()` 的 `$includeHostCredentials` 參數，以及 `ReservationEmailMessage::IsRecipientOwner()`（預設 `false`，只有確定收件人一定是預約人本人的類別才覆寫成 `true`：`ReservationCreatedEmail`／`UpdatedEmail`／`ApprovedEmail`／`DeletedEmail`；這幾個類別底下會把收件人改成別人的子類別——`GuestDeletedEmail`／`InviteeRemovedEmail`——各自明確覆寫回 `false`，而不是靠「不覆寫就繼承 true」這種容易漏掉的預設）。主持人登入資訊區塊只在 `$IsRecipientOwner` 為真且該資源真的有存主持人帳密時才顯示；參與者／訪客收到的信永遠不會拿到主持人帳密（連解密查詢都不會發生，不是查出來又不顯示）。

套用後發現一個版面 bug：「共用主持人帳號」「主持人登入密碼」這兩個 5～7 個中文字的標籤，比表格原本設定的固定欄寬（90px）還寬，被瀏覽器/信件用戶端硬拆成兩行。修正：拿掉固定寬度，改用 `white-space:nowrap`，讓欄寬依內容自動撐開。

### 48.3 LINE Flex Message

`lib/Line/ILineService.php`／`LineService.php` 新增 `PushFlex()`，發送 LINE 官方的 Flex Message（卡片式版面）而非純文字。`OwnerLineCreatedNotification`（`lib/Application/Reservation/Notification/OwnerLineNotification.php`）重寫，用同一份 `ReservationEmailTemplateContext` 邏輯組出跟 Email 一致順序的 Flex 卡片：狀態／標題／時間／視訊資源或會議室／預約人／加入視訊會議按鈕／主持人登入資訊／查看預約詳情連結。這個通知類別本來就只會推播給預約人本人，跟 Email 的 `IsRecipientOwner` 分流是同一個安全前提。

主持人登入資訊在 LINE 上的呈現方式來回調整了兩次：
1. 最初做法：卡片裡放一個「主持人登入資訊」按鈕，連到一個新建的、需要登入且限定預約人才能開啟的網頁（`Pages/Reservation/VideoConferenceHostInfoPage.php`）——因為 LINE Flex Message 本身不支援真正的展開/收合互動。
2. 使用者實際看到效果後改變主意：不要跳出網頁，直接把內容顯示在 LINE 訊息裡即可（畢竟這則訊息本來就只有預約人自己看得到，多一層網頁登入反而增加操作步驟）。改成把 Meeting ID／會議密碼／共用主持人帳號／主持人登入密碼直接排版進 Flex 卡片本體的一個灰底區塊裡。因為方案 1 建的整組網頁（Page class／路由常數／樣板／CSS／對應語言字串）確定不再被任何地方引用，直接整組刪除，沒有留著養蚊子。
3. 使用者接著發現 Flex Message 裡的文字**無法長按複製**（LINE Flex 卡片的文字元件本來就不支援選取複製，跟一般純文字訊息不同），但主持人帳密是要真的貼進 Zoom/Meet 登入畫面用的。最終做法：卡片本身維持完整排版不變，額外**追加一則純文字訊息**（緊接在卡片後面推播），內容是同樣四項資訊，純文字訊息在 LINE 裡本來就能長按複製，兩則訊息互補（卡片負責好看、純文字負責可複製）。

### 48.4 SMTP 環境切換

過程中把 dev 環境的寄信設定從正式內部 SMTP（`10.1.3.140:25`）暫時改指向本機測試信箱 Mailpit（`mailpit:1025`），避免測試時真的寄信給 `mark_li@coasia.com`／`shawn_tsai@coasia.com` 等真人信箱；驗證完成後已改回正式 SMTP。這只是 `config/config.php`（gitignored）裡的一個值變動，不影響任何進 git 的程式碼。

## 49. 側邊欄圖示排版系統化：拿掉逐項 margin，改成共用格線變數

延續 §34 的收合尺寸統一，這輪把整個側邊欄（展開＋收合）的排版邏輯改成完全由 `Web/css/coasia-theme.css` 頂部幾個 CSS 變數驅動，不再有任何一個項目自己另外設 margin：

- 展開版：`--sidebar-row-padding-left`(20px) + `--sidebar-icon-col`(40px 圖示欄) + `--sidebar-row-gap`(12px)。頭像（縮小成 40×40px，跟其他圖示共用同一個欄寬）、「新增預約」、「行事曆首頁」、「通知設定」、管理相關項目的圖示欄與文字起始位置，全部量測確認落在同一條垂直線上。
- 收合版：所有項目外層統一 `--sidebar-icon-box`(44px)，容器一律 `display:flex;align-items:center;justify-content:center`，彼此間距統一 `--sidebar-icon-gap`(16px)。拿掉舊版用 `font-size:0` 藏文字＋絕對定位置中圖示的技巧（因為文字是裸文字節點沒有自己的元素可以藏），改成把每個連結的文字包成 `<span class="sidebar-link-label">`（`tpl/globalheader.tpl`），收合時直接 `display:none` 讓它整個退出版面，圖示自然被 flex 置中，不再需要額外的定位技巧。頭像／「新增預約」原本是全 44px 實心圓，使用者看過後覺得偏大，改成外層熱區維持 44px（保持跟其他項目一致的間距與可點擊範圍），但畫出來的圓形用 `padding` + `background-clip:content-box` 內縮成 40px。
- 頭像原本是側邊欄自己獨立的一個區塊（跟主導覽是分開的兩個 flex 容器，各自的 padding 沒有對齊），這輪把它搬進 `<nav class="sidebar-nav">` 內、變成跟「新增預約」「行事曆首頁」同一個 flex-column 的第一個子項目，這樣它們之間的間距就是同一份 `gap`，而不是分別調兩個容器的 padding 湊出視覺上差不多的數字。**副作用**：頭像原本在手機版是永遠顯示、不受漢堡選單收合影響的獨立區塊，搬進主導覽區塊後，手機版現在會跟其他導覽項目一起被漢堡選單收合/展開控制——如果這個手機版行為變化不是預期中的，需要另外處理。

套用過程中修掉兩個連帶 bug：(1) 頭像原本用 `flex:0 0 40px` 撐開寬度，收合模式只覆寫了 `width:44px` 卻沒覆寫 `flex-basis`，導致寬度依然卡在 40px（flex-basis 在主軸方向的優先權高於 `width`）；(2) 「新增預約」按鈕跟頭像原本用 16px 左右內距，跟其他導覽項目的 12px 不一致，圖示起始位置差了 4px，統一成同一組 `--sidebar-row-padding-*` 變數後對齊。全程用瀏覽器 `getBoundingClientRect()` 直接量測每個圖示的中心點座標／間距，不是肉眼比對——展開版確認所有圖示 X 座標相同、所有文字 X 座標相同；收合版確認所有中心點 X 座標相同（同一條垂直中心線）、任兩個相鄰圖示的中心點垂直距離全部剛好 60px（44px 容器＋16px 間距）。

## 50. 後台管理介面重整：資訊架構＋共用版型（先於配色），新增管理總覽儀表板

使用者要求「先處理資訊架構與共用版型，不要只換顏色」，把原本散落在側邊欄兩處、都叫「程式管理」的入口整併成一個「管理中心」，並依功能分類，同時導入一套跟新版行事曆同語彙的共用視覺系統。

### 50.1 資訊架構：兩個「程式管理」合併成一個「管理中心」

原本 `tpl/globalheader.tpl` 有兩個獨立的展開式選單，都顯示「ApplicationManagement」：一個是主導覽的扳手圖示（預約／時間表／資源／使用者／自訂屬性／群組），一個是側欄底部的齒輪圖示（系統設定／通知範本／佈景設定／匯入／伺服器設定／資料清理）。使用者回報「全部顯示在側欄很詭異」——把兩個攤平清單直接合併成一個巢狀選單後，展開時整個側欄被撐得極長，六、七個分類的連結一次全部攤開。

第一版先合併成一個帶分類標籤的巢狀選單，第二版依使用者要求改成完全不同的模式：「管理中心」在主側欄變成一個**直接連結**（不再展開），點下去直接進「管理總覽」；分類導覽（預約管理／會議室與資源／使用者與權限／開放時間與規則／通知與信件／系統維護）搬到一個**獨立的第二層側邊欄**（`tpl/Admin/admin-nav.tpl`＋`Web/css/admin.css` 的 `.admin-side-nav`），顯示在主側欄和頁面內容之間，只在管理頁面裡才出現，一般頁面的側欄完全不受影響、高度不會變。目前選中的項目會反白（比對 `$AdminCurrentScript`，在 PHP 端算好傳給樣板，因為這個專案的 Smarty 組態不允許把任意 PHP 函式如 `basename()` 當 modifier 用）。

分類調整：群組（Manage Groups）併入「使用者與權限」；自訂屬性（Custom Attributes）併入「系統維護」。改名：Manage Email Templates→「通知與信件」、Import→「匯入行事曆（ICS）」、Server Settings→「系統診斷」、Data Cleanup→「資料清理」（移到系統維護最底部，用紅色警示樣式標示高風險）。佈景設定（Look and Feel）從導覽中隱藏，頁面本身仍在、仍可直接連結進入，只是不再從管理選單導覽過去。

已知限制：第二層側邊欄在手機寬度（≤992px）目前直接隱藏（版面塞不下三欄），手機上進入管理頁面後沒有其他方式切換分類——已明確告知使用者，需要的話後續要再做一輪手機版設計。

### 50.2 新增「管理總覽」儀表板

新頁面 `Pages/Admin/ManageOverviewPage.php`／`tpl/Admin/Overview/manage_overview.tpl`，作為管理中心的預設首頁，直接下 SQL 查詢（沒有另外包一層 repository，因為都是一次性、不重用的查詢形狀）顯示：

- 今日預約：今天開始時間的所有未刪除預約，含標題、資源、時間、預約人。
- 會議室狀態：依 `resources.status_id` 分組計數（可使用／不可使用／已隱藏），並列出目前「不可使用」的資源清單與原因。
- 近期使用者：依 `date_created` 排序的最新 8 筆帳號。
- 系統警告：目前實作三項檢查——Email 通知整體關閉、LINE 通知已啟用但沒有設定 Channel Access Token、有預約正在等待審核（數量>0 才顯示）。

### 50.3 共用視覺系統：`Web/css/admin.css`，透過 `$IsAdminPage` 自動套用到全部管理頁面

不是逐一改樣板，而是在 `Pages/Admin/AdminPage.php`（`AdminPageDecorator::PageLoad()` 與 `AdminPage::Display()` 兩個既有的公共進入點）統一設定 `$IsAdminPage = true`，`tpl/globalheader.tpl` 偵測到這個變數就自動載入 `admin.css`——新舊約 25 個管理頁面完全不用個別修改樣板就自動套用新樣式。`admin.css` 的作法：

- 每個管理頁面本來就已經把內容包在 `<div class="admin-page">` 裡（這個 class 之前完全沒有對應的 CSS 規則，是個空殼 hook）——直接在這個既有 hook 上定義卡片圓角/陰影、標題字重、`.btn-primary` 藥丸形狀、表單欄位圓角、表格密度，25 個既有頁面完全不用碰 HTML 就統一升級。
- 新增元件（`.admin-card`／`.admin-grid`／`.admin-stat`／`.admin-list`／`.admin-warning`）給像管理總覽這種要從零打造的新頁面用，不用直接套用 Bootstrap 預設。
- 設計token（`--surface`／`--border`／`--text-primary`／`--control-radius` 等）刻意跟 `overview_fullcalendar.css` 的 `#page-overview-fc` 那組同名同值——不是同一份 CSS（那份是綁定特定頁面 ID 的 scoped 規則，直接套用會連帶吃到大量跟行事曆本身相關、不相干的規則），而是各自宣告、數值一致，讓兩邊視覺上是同一套系統但不互相干擾。`.ghost-btn`／`.btn-primary` 等按鈕元件同理，是各自 scope 底下的平行副本。

### 50.4 過程中發現並修掉的 bug

- **Smarty modifier 不存在**：`admin-nav.tpl` 原本想用 `{$smarty.server.SCRIPT_NAME|basename}` 判斷目前頁面做反白，但這個專案的 Smarty 組態不接受任意 PHP 函式當 modifier，編譯直接失敗（`unknown modifier 'basename'`）。改成在 PHP 端（`AdminPage.php`）算好 `$AdminCurrentScript` 再傳給樣板。
- **連帶暴露的既有 bug**：上面那個編譯錯誤觸發框架的全域例外處理／`RedirectToError()`，而 `AdminPage::Display()` 原本的邏輯會**無條件**在樣板名稱前面加上 `Admin/`——導致它去找一個根本不存在的 `Admin/error.tpl`，變成一次在例外處理過程中又拋出新例外的「二次錯誤」，使用者看到的是原始、未經樣式化的 PHP fatal error，而不是這個 app 平常的錯誤頁。這個 bug 在此之前一直潛伏著沒被踩到，因為原本會直接繼承 `AdminPage` 的頁面（`ServerSettingsPage`／`DataCleanupPage`）從來沒有真的觸發過 `RedirectToError()`。修法：在 `AdminPage` 覆寫 `RedirectToError()`，直接呼叫 `parent::Display('error.tpl')`（不經過會加前綴的 `AdminPage::Display()`）。修完後用 CLI 搭配一個假造 Admin `UserSession` 的 harness，把管理總覽跟另外 7 個既有管理頁面全部完整渲染一次，全部正常，也確認 `RedirectToError()` 現在真的會顯示正常的錯誤頁。
- **開發環境 log 檔案權限**：診斷上面兩個 bug 時發現 `/var/log/librebooking/log/app.log` 的擁有者是 `root`（先前用 CLI 手動測試時以 root 身分建立），導致以 `www-data` 身分執行的真實網頁請求完全無法寫入、錯誤被 Monolog 自己的例外靜默吞掉——這也是為什麼真正在瀏覽器上發生的例外完全沒被記錄下來。修正檔案擁有者後，之後同類問題可以直接查 log 而不必每次都重建 CLI 測試腳本。

### 50.5 刪除側邊欄迷你月曆（一般使用者與管理頁面皆是）

使用者要求把側邊欄的迷你月曆整個刪掉，不管一般使用者頁面還是管理頁面都要拿掉。因為兩者共用同一份 `tpl/globalheader.tpl`，拿掉一次就對兩邊都生效：

- `tpl/globalheader.tpl`：移除 `.mini-cal` 整段 HTML（原本只在 `$PhysicalResources`／`$VideoResources` 有值時顯示；這兩個變數本身還留著，因為「行事曆首頁」的當前頁反白判斷也在用同一組變數）。
- `Web/scripts/overview_fullcalendar.js`、`Web/scripts/overview.js`：各自移除一份自己獨立實作的迷你月曆邏輯（月份切換、日期點擊跳轉）——這兩份 JS 各自維護一份幾乎一樣但不完全相同的實作（新版用 FullCalendar 的 `datesSet` 事件同步當週範圍，舊版用伺服器端渲染的起始日期），拿掉時两份都要處理。
- `Web/css/coasia-theme.css`（收合側欄的隱藏規則清單裡移除 `.mini-cal` 這一項）、`Web/css/overview.css`、`Web/css/overview_fullcalendar.css`（各自的 `.mini-cal-*` 規則整段刪除）。

瀏覽器實測：`document.getElementById('miniCal')` 回傳 `null`，主控台沒有任何殘留的 JS 錯誤。

## 51. 預約管理／會議室管理／開放時間管理三頁重新設計，使用者管理拿掉 DataTables，新增「會議室使用分析」

延續 §50 的共用視覺系統，這輪把管理中心裡還停留在舊版 LibreBooking 畫面（Accordion、DataTable、Inline Edit、舊版 Modal）的三個核心頁面整個重做，並在過程中額外處理了使用者管理的分頁機制、精簡了兩頁的篩選欄位，最後新增一個全新的「會議室使用分析」報表頁。

### 51.1 會議室管理：Accordion 列表 → 表格 + 右側 Drawer

`tpl/Admin/Resources/manage_resources.tpl` 原本每個資源是一個 Bootstrap accordion item，展開後把所有欄位（狀態、時間表、類型、容量、Access、Duration、自訂屬性…）全部塞在同一個展開區塊裡，用 x-editable 逐欄位 inline 編輯。改成：

- 列表區改成 `.admin-clean-table` 純表格，只顯示 名稱／類型／地區／容量／狀態 五欄，點一整列開啟右側 Drawer。
- 每個資源原本的 accordion body 整段抽成獨立檔案 `tpl/Admin/Resources/manage_resource_drawer_content.tpl`（內容逐字保留，包含所有 x-editable 欄位與子對話框觸發點——只換外層容器，從 accordion-collapse 換成 offcanvas body），複雜設定（Access／Duration／Credits／Capacity／ResourceGroups）收進一個 `<details class="admin-drawer-advanced">` 進階設定收合區。
- 篩選列從 9 個欄位＋5 個自訂屬性（Test Number／Test String／video_*）的長表單，砍成「搜尋、類型、狀態」三個欄位的單行工具列（使用者實際回饋：「篩選不用這個多，也沒幾個」），其餘欄位（時間表／容量／需審核／自動授權／可跨日）從畫面上拿掉——後端 filter 邏輯不動，只是 UI 不再暴露這些欄位。
- 刪除確認改用共用的 `AdminConfirm`（`tpl/Admin/admin_confirm_dialog.tpl`＋`Web/scripts/admin/admin-confirm.js`）取代舊版 `#deletePrompt` Modal。
- 移除 DataTables，改用共用的 `Web/scripts/admin/admin-table-paginate.js`（純前端依 index 切頁，不做 AJAX）。

### 51.2 開放時間管理：整個重新設計成「簡單規則頁」，拿掉舊版 Layout／Peak Time 編輯器

這頁改動幅度最大，因為使用者明確要求「不要再顯示舊 Schedule Layout、Peak Time 等複雜介面」——原本一個時間表可以设定「每天不同時段」「自訂日曆版面」「尖峰時段加價」，介面是一整組分頁式 textarea＋FullCalendar 拖拉編輯器。新版：

- 列表改成 `.admin-clean-table`：名稱／適用會議室／開放時間（7 個小圓點，滑鼠移上去顯示該天時段或「休息」）／狀態（預設／使用中／未使用）。
- 每個時間表的 Drawer 裡放一個全新的「每週開放時間」editor：7 天各一列，開關 checkbox + 開始/結束時間（`<input type="time">`），取代原本的多時段 textarea 版面。
- **關鍵設計決定：完全不改後端。** 儲存時把 7 天的開/關＋時間換算成原本 `ActionChangeLayout` 端點吃的格式（`reservableEdit_{day}`／`blockedEdit_{day}` 兩個隱藏欄位，值是 `"09:00 - 18:00"` 這種字串；關閉的那天寫 `blockedEdit_{day} = "00:00 - 00:00"`，避免踩到 `ScheduleLayout::ParseDaily()` 「不能兩者都空」的檢查），直接沿用既有的 `changeLayoutForm`／`ManageSchedules::ActionChangeLayout` 提交，`usesSingleLayout` 固定關閉。舊版的分頁式 Layout Dialog／Peak Time Dialog／FullCalendar 自訂版面 Dialog 的 HTML／JS 全部保留在頁面最下方（沒有刪除任何一行後端邏輯），只是新版畫面完全沒有任何連結會觸發它們顯示——降低風險（後端 0 改動）優先於刪掉「用不到但沒壞」的舊 code。
- 進階設定收合區保留：星期起始／可見天數／並行預約上限／每筆預約會議室上限／ICS 訂閱／刪除。

### 51.3 預約管理：延續前一次未完成的重構，補齊 CSS

`tpl/Admin/Reservations/manage_reservations.tpl` 在本輪工作開始前其實已經大部分改完（`.admin-clean-table`＋右側唯讀詳情 Drawer＋`AdminConfirm`＋`AdminTablePaginate` 都已經接好），只是它所依賴的三個 CSS class（`.admin-clickable-row`／`.admin-drawer-advanced`／`.admin-clean-table-card`）當時只有在樣板裡使用、`admin.css` 從來沒有補上對應規則，等於完全沒套用到樣式。這輪跟著會議室管理一起把這三個 class 定義好之後，這頁不用改樣板就一併正常顯示。篩選列同樣依使用者回饋從 13 個欄位（日期區間／用戶／時間表／資源／狀態／參考數字／標題／說明／Resource Status／理由／Missed Checkin／Missed Checkout／自訂屬性）砍到只顯示「日期區間／用戶／資源／狀態」四項；其餘欄位改成 `d-none` 隱藏而非刪除——因為 `reservations.js` 的 `filterReservations()` 是直接用 id 讀這些欄位的值組出查詢字串（不是走原生表單送出），拿掉元素本身會讓對應 `.val()` 變成 `undefined`，隱藏可以保留原本的查詢能力（例如帶著舊的分享連結進來時，那些欄位仍保有原始值）同時不佔畫面。

### 51.4 使用者管理：拿掉 DataTables，換成跟其他三頁一樣的簡易分頁

使用者管理原本用完整的 DataTables（搜尋／排序／分頁都由它處理），視覺上跟其餘三頁改版後的簡潔分頁列風格明顯不搭（「第 1 頁，共 3 頁」＋「每頁顯示」那條資訊列用的是 DataTables 內建的 Bootstrap 5 主題樣式，字級與間距跟新版系統對不起來）。第一輪先嘗試單純加 CSS 覆寫 `.dataTables_*` 元件，使用者實測後回饋「更醜，請跟其他頁面相同」，因此改成第二輪方案：整個拔掉 DataTables，換成跟其他三頁一樣的 `admin-table-paginate.js`。

- 排序下拉選單原本呼叫 `$('#userList').DataTable().order([...]).draw()`，改成純前端依 `data-name`／既有的 `data-order`（最後登入時間）屬性重新排序 `<tr>` DOM 順序，再呼叫分頁元件的 `refresh()`。
- 搜尋框（「搜尋姓名、帳號或Email」）原本只接了 `userAutoComplete()`（打字時彈出建議清單、選了才會跳轉到該使用者頁面），並不會即時過濾清單本身——確認過現有 code 之後發現這是既有行為，不是本次改動造成的缺口，但既然現在把 115 個使用者切成每頁 20 筆分頁顯示，維持「打字完全不過濾清單」的體驗會比改版前更不好用，所以順手補上即時過濾：`admin-table-paginate.js` 新增支援一個獨立的 `.admin-search-hidden` marker class（跟分頁元件自己用來切頁的 `d-none` 分開，`refresh()` 時排除掉，避免兩套隱藏邏輯互相覆蓋），搜尋框 `input` 事件比對每列的 `data-search`（姓名＋帳號＋Email 小寫）屬性即時標記，再呼叫 `refresh()`。
- **過程中抓到一個會直接讓整頁互動失效的 bug**：`user.js` 所有列操作（編輯／刪除／停權／變更角色…）都是委派綁定在 `elements.userList = $('#userList_wrapper')` 上——這個 `_wrapper` 是 DataTables 初始化時自動產生的包裹 div，拔掉 `{datatable}` 呼叫之後這個 div 根本不會存在，所有委派事件會安靜地完全失效（不會報錯，只是點什麼都沒反應）。改成直接綁在 `#userList`（表格本身）上，同一個 bug 樣式在稍早改會議室管理／開放時間管理時也各踩了一次（`#resourceList`／`#schedulesTable_wrapper`），都是同一個原因：DataTables 拔掉後，任何綁定在它自動產生的 `_wrapper` id 上的既有 JS 全部要重新指向一個保證存在的元素。
- 使用者管理自己另一個表格（權限指派 Modal 裡的 `#permissionsFormFilter`）仍然是 DataTables，這次沒有動——只拔了主列表那一個 `{datatable}` 呼叫，`{datatablefilter}` 跟 DataTables 函式庫本身都繼續載入。

### 51.5 新增「會議室使用分析」頁面

全新頁面，`Pages/Admin/ManageUsageAnalyticsPage.php`／`tpl/Admin/UsageAnalytics/manage_usage_analytics.tpl`／`Web/admin/manage_usage_analytics.php`，導覽放在側邊欄「開放時間設定」與「系統設定」之間。做法比照 §50.2 的管理總覽：不建 repository，一次性原始 SQL（`ServiceLocator::GetDatabase()`＋`SqlCommand`／`Parameter`），四個查詢分開跑：

| 區塊 | 查詢方式 |
|---|---|
| 預約總數／總時數／平均會議時長 | `COUNT(*)`＋`SUM(TIMESTAMPDIFF(MINUTE,...))`，對 `reservation_instances` JOIN `reservation_series`（排除 `status_id = ReservationStatus::Deleted`）JOIN `reservation_resources`（`resource_level_id = ResourceLevel::Primary`，避免同一筆預約因為掛多個資源被重複計算）JOIN `resources` |
| 熱門預約時段 | 同一組 JOIN 加 `GROUP BY HOUR(ri.start_date)`，PHP 端補滿 0～23 小時（沒預約的小時補 0，不是直接跳過），前端用 Chart.js（`chart.js/4.4.9`，這個 app 在 `tpl/Reports/chart.tpl` 已經有引入過，不是新加的依賴）畫長條圖 |
| 各會議室使用排行 | 同一組 JOIN 加 `GROUP BY r.resource_id`，依預約數排序 |
| 各城市使用情況 | 同一組 JOIN 加 `GROUP BY r.location`（`location` 是 NULL 或空字串的資源歸進「未指定」一組，不會整列被漏掉不計） |

篩選（日期區間／城市／會議室）沿用跟預約管理同一種「JS 讀欄位值組 query string 後 `window.location` 導頁」寫法，而不是原生表單送出——原因跟 §51.3 一樣，date picker 控件的顯示值跟底層真正要送出的值不一定同步，這個專案既有頁面全部走 JS 組 URL，沒有例外，這次直接沿用而不是另創一套。

匯出 Excel 用 `phpoffice/phpspreadsheet`（`composer.json` 已有這個依賴，先前只在 `Jobs/import-coasia-employees.php` 用來讀 `.xls`，這是這個專案第一次真正拿它來寫 `.xlsx`），四個分頁（總覽／熱門時段／會議室排行／城市排行）對應畫面上的四個區塊，經 `Content-Disposition: attachment` 直接串流下載，不落地暫存檔。

驗證方式：四條 SQL 直接對 dev DB（`docker exec` 進 MySQL 容器）用真實資料跑過一次，確認 join 條件、`ReservationStatus::Deleted` 實際數值（`2`，不是想像中的 `3`，用常數而非硬編碼數字避免這類筆誤流進正式程式碼）、`location` 為 NULL 時的分組行為都符合預期；PHP 檔案跑過 `php -l`；`PhpOffice\PhpSpreadsheet\Spreadsheet`／`Writer\Xlsx` 用 `class_exists()` 確認容器內 autoload 找得到。畫面本身（Drawer、圖表實際渲染、Excel 實際下載）尚待使用者在瀏覽器裡確認。

### 51.6 過程中發現並修掉的環境問題

- **Smarty 樣板編譯快取（`tpl_c/`）**：這個 dev 容器的 `cache.templates` 設定是 `true`（`config/config.dist.php` 預設值），代表 `SmartyPage` 的 `compile_check`／`force_compile` 都被關掉——樣板檔案改了，Smarty 完全不會重新編譯，永遠吃 `tpl_c/` 裡舊的編譯結果，改幾次都看不到效果。這不是這次改動造成的，是既有的效能設定，但過程中花了不少來回才確認到這是「看起來畫面沒更新」的真正原因（一開始懷疑是自己改錯地方）。之後每次改完 `.tpl` 都要清 `tpl_c/*.php` 才看得到效果。
- **PHP OPcache（`opcache.validate_timestamps = Off`）**：跟上面同一類問題，但影響範圍是 `.php` 檔案（語言檔、Page class）——改了 `lang/zh_tw.php` 之後，`{translate key=...}` 全部顯示成 `?`（`Resources::GetString()` 對找不到的 key 就是回傳字面上的 `?`），一度誤以為是語言檔本身寫錯，後來直接 CLI `php -r` 重新 `require` 語言檔類別驗證字串確實存在、只是網頁那邊的 opcache 沒重新載入——改完 `.php` 檔案要 `docker restart` 容器才會生效，光清 `tpl_c/` 不夠。
- **`ScheduleLayout->GetSlots($day)` 對「標準版面」時間表丟例外**：§51.2 新的每週時數摘要／編輯器一開始不分青紅皂白一律呼叫 `GetSlots($day)`，但這個方法在時間表是「標準版面」（`UsesDailyLayouts()` 為 false，也就是所有資源預設的那種）時，只要傳了 `$day` 參數就直接 throw exception（`$dayOfWeek cannot be provided when using single layout`）——反過來，`UsesDailyLayouts()` 為 true 時不傳 `$day` 又會丟另一個相反的例外。修法是先判斷 `UsesDailyLayouts()`，true 才逐天呼叫 `GetSlots($day)`，false 則呼叫一次不帶參數的 `GetSlots()`，7 天共用同一份結果。
- **星期縮寫全部顯示成同一個字**：開放時間管理列表原本想拿 `$DayNames[$day]`（「星期一」「星期二」…完整名稱）用 `|truncate:1:""` 截前 1 個字當小圓點的縮寫文字，結果全部都是「星」（因為中文星期名稱共同開頭都是「星期」）。改成直接吃這個 app 語言檔裡本來就有、也已經正確在地化的 `GetDays('abbr')`（一二三四五六日 / Sun Mon Tue…），`ManageSchedulesPage.php` 多綁一個 `$DayAbbr` 給樣板用，不用另外自己兜縮寫邏輯。
- **`AdminResourceAdvancedSettings`／`AdminScheduleWeeklyHours` 等好幾個語言字串鍵值根本不存在**：會議室管理／開放時間管理的樣板在前一輪工作中已經在用（`{translate key=AdminResourceAdvancedSettings}` 等），但三個語言檔裡從來沒有真的加上對應的 `$strings[...]` 這一行——沒有編譯錯誤（Smarty 對找不到的 key 就是印出 `?`），純粹肉眼看畫面才會發現。這次一併補齊，順便新增本輪新畫面用到的所有字串鍵值（`AdminScheduleInUse`／`AdminScheduleUnused`／`AdminResourceNoResults`／`AdminUsageAnalytics*` 系列等）。

## 52. 參與者元件重構、行事曆首頁工具列重新設計、CoAsia 共用 Design Token 系統、管理總覽儀表板擴充

這輪橫跨四塊互相獨立的工作，共用的主線是「先讀既有實作，抽出/對齊管理中心（`admin.css`）已經在用的真實數值，再套用到其他頁面」，而不是憑印象另外訂一套規格。

### 52.1 參與者元件：快速預約與完整預約共用同一套邏輯

`Web/scripts/participant-picker.js`（`createParticipantPicker()`）原本就是快速預約與完整預約共用的內部使用者搜尋＋Chip 元件，這輪擴充成同時支援外部（非系統帳號）Email：

- 同一個搜尋欄位：打字時即時打 `ajax/overview_fullcalendar_participant_search.php` 顯示內部員工搜尋結果（姓名／帳號／公司 Email 皆可比對）；輸入的字串是合法完整 Email 格式、且沒有內部帳號符合時，下拉選單改顯示「按 Enter 邀請此外部 Email：{email}」提示。
- **外部 Email 必須按下 `Enter` 才會加入清單**——單純打字不會自動加入，避免使用者還在輸入中途就被誤判成確認。點下拉裡的提示列一樣可以加入（保留滑鼠操作路徑），但主要且唯一「保證有效」的方式是 `Enter`；新增了一個 `keydown` 監聽，`Enter` 命中時呼叫跟點擊提示列相同的 `addExternal()`，並 `e.preventDefault()` 避免觸發表單送出。
- 選取後的內部使用者與外部 Email 顯示在同一組 Chip 清單裡，外部收件人多一個「外部」徽章，視覺上不分兩組。
- 後端收到的資料仍然分開：內部使用者送 `participantList[]`/`invitationList[]`（user id），外部 Email 送 `guestParticipationList[]`/`guestInvitationList[]`（Email 字串）——這個分流邏輯是 JS 端依 `type: 'internal' | 'external'` 在送出時決定要 append 到哪個表單欄位，前端看起來是同一個元件，後端完全沒有新增資料表或欄位。
- 「選擇性參與者」（非必要參與者）這輪從快速預約（Quick Create 彈窗）整個拿掉，只留在完整預約表單裡；快速預約要新增選擇性參與者，走既有的「更多選項」導去完整預約頁。連帶拔掉 Quick Create 裡對應的第二個 picker 實例（`qcInviteePicker`）與相關 markup/CSS。
- `Allow Participants To Join` checkbox 先隱藏（`d-none`），欄位跟送出邏輯都還在，只是暫時不給使用者看到。

### 52.2 行事曆首頁工具列重新設計

原本工具列（`tpl/Overview/overview_fullcalendar.tpl`＋`Web/css/overview_fullcalendar.css`）是三欄 `1fr auto 1fr` 網格，中間放「今天」文字連結＋前後箭頭＋月份、右側是「只看我的」切換開關＋「週」下拉。這輪重做成單行三欄、禁止用 `position:absolute` 定位月份的版本：

- `grid-template-columns: minmax(150px,1fr) auto minmax(220px,1fr)`，中央欄的日期切換器（`.date-switcher`）把上一頁／月份／下一頁包進同一個白色圓角外框，內部用淡色垂直分隔線分開，月份區固定寬度（避免切月份時整組寬度跳動），箭頭 hover 只highlight 該箭頭自己的格子，不是整組外框一起亮。
- 右側改成三個等高（40px）控制項：`📅` 圖示回到今天按鈕（Tooltip「回到今天」，已經在本週時仍保留版位、不讓右側整組跳動）、「全部⌄」篩選（取代原本的切換開關，選項「全部／只看我的」）、「週⌄」檢視切換（日／週／月），三者尺寸、圓角、邊框、hover/focus 完全一致。
- 行事曆容器（`.fc-frame`）改用跟管理中心一致的圓角、邊框色、極淡陰影，跟工具列間距固定 12px；FullCalendar 內部格線顏色調淡、預約事件圓角從 4px 改成 8px。
- 過程中發現既有的 hover 邏輯（`.fc-event:hover` 用各會議室自己的顏色加深，不會全部變成同一個綠色）跟今天欄位的淡綠底色其實都已經正確實作，沒有再重覆處理。

### 52.3 CoAsia 共用 Design Token 系統

使用者明確要求「除了真圓形元件外，所有有框元件都要共用管理中心的規格，不要每個頁面各自寫一套」。作法是先完整讀過 `Web/css/admin.css` 實際在用的數值（不是憑印象），抽成 `Web/css/coasia-theme.css` `:root` 底下的一組新 token：

```
--coasia-border: #e4e7ee            (= admin.css 的 --border)
--coasia-radius-control: 10px       (= admin.css 的 --control-radius)
--coasia-radius-surface: 16px       (= admin.css 的 --dialog-radius)
--coasia-shadow-surface / --coasia-shadow-elevated / --coasia-shadow-drawer
--coasia-control-height: 40px
```

再把這組 token 接到 `coasia-theme.css` 既有的全域 Bootstrap class 覆寫（`.btn`／`.form-control`／`.form-select`／`.card`／`.modal-content`／`.dropdown-menu`／`.toast`／`.flatpickr-calendar`）——這幾個 class 是每一頁都會載入的通用類別，改一處就對登入頁、快速預約、完整預約、通知設定、所有 Modal/Drawer/Dropdown 同時生效，不用逐頁修改樣板。另外新增 `.coasia-control`／`.coasia-icon-button`／`.coasia-surface`／`.coasia-dropdown`／`.coasia-modal`／`.coasia-drawer` 給之後要直接照這套系統蓋新元件的地方用。

過程中順便修掉兩個不一致：

- `admin.css` 自己的按鈕（`.ghost-btn`／`.btn-primary`／`.btn-danger`）原本是 `border-radius:100px` 藥丸形，剛好違反「除圓形元件外禁止膠囊」的新規則，改成跟其他控制項一樣的 `--control-radius`。
- 側邊欄（`coasia-theme.css`）的頭像、新增預約按鈕、導覽圖示原本收合時是正圓（`border-radius:50%`），展開/收合時圖示欄寬度也不一致（展開 40px、收合 44px）。這輪統一成：展開時所有列高 44px（頭像／新增／首頁／通知／管理／設定／登出一致），收合時外層可點擊區塊 48×48、內部圖示容器/色塊 44×44（`padding:2px`+`background-clip:content-box` 內縮），圓角統一 12px（不是直接套用 `--coasia-radius-control` 的 10px，是刻意取跟它同比例、對應側欄較大尺寸放大後的值：12/48 = 10/40 = 0.25）。收合狀態下側欄收合鈕／頭像／新增／各導覽項目全部靠同一個 `align-items:center` 對齊到同一條垂直中心線；「新增預約」這顆按鈕因為是實色填滿，額外改用 `margin`（不是 `padding`）內縮，讓色塊本身的邊界對齊 Logo 左邊，不是只有裡面的圖示對齊而已。左側所有列的起始位置也統一改成跟 Logo 同一個 16px 左邊界（原本 `.sidebar-nav`／`.sidebar-footer` 自己還有一層 12px 內距疊加在每列自己的 20px 上，等於圖示比 Logo 多縮了 16px）。

### 52.4 管理總覽儀表板擴充

`Pages/Admin/ManageOverviewPage.php`／`tpl/Admin/Overview/manage_overview.tpl`（§50.2 建立的版本）這輪從「統計卡片＋今日預約清單＋依地區的可用率長條」擴充成四排：

1. 今日預約／進行中／一小時內即將開始／停用或維修中會議室（4 張卡片，取代原本那組，不是疊加）。
2. 今日預約清單（每筆改成可點擊連到 `reservation.php?rn=<預約編號>`）＋**即時會議室狀態**：不再是依地區分組的比例長條，改成逐間顯示空閒（附下一場開始時間）／使用中（附結束時間）／維修中（附原因）——新增 `GetLiveResourceStatus()`，一條 SQL 抓出每間會議室「現在正在進行或最近的下一場」預約（依 `resource_id, start_date` 排序後在 PHP 端取每個 resource 的第一筆），不是逐間下查詢。
3. 近七日預約趨勢（純 CSS 長條圖，7 天缺值補 0，長條高度是 PHP 端算好的百分比，不在 Smarty 樣板裡塞內聯運算式）＋熱門會議室排行前 5 名（沿用 §51.5「會議室使用分析」`GetUsageByResource()` 的查詢寫法，改成固定抓最近 7 天）。
4. 需要處理的異常：沿用既有邏輯（待審核預約數／停用中會議室清單／Email 通知關閉／LINE 設定不完整／缺 Email 帳號數），這輪每一項都加上可點擊連結（分別連到預約管理／會議室管理／通知紀錄／使用者管理，沒有連去不存在的「維運紀錄」頁面）。

過程中在既有語言檔（`lang/en_us.php`、`lang/zh_tw.php`）發現一句 `AdminOverviewSystemStatusSummary = "AD 同步正常 · Email 正常 · LINE 正常"`，會在「沒有異常」時顯示——但 AD 同步跟 Email 健康狀態這個系統完全沒有真實資料來源在背後（`Pages/Admin/AdSyncStatusPage.php`／`NotificationLogPage.php` 都是刻意做成誠實空狀態的殼頁面，沒有排程、沒有送信紀錄表），這句字串等於是憑空宣稱三個系統都正常。已整段移除（中英文語言檔都刪），不用「不確定狀態」之類的字樣取代，就是不再顯示任何整體健康度宣稱。

沒有 PHP CLI／登入權限的情況下，這輪主要靠：`php -l` 等價的手動大括號配對檢查（PHP／Smarty／CSS 各自寫小腳本數 `{`/`}` 是否平衡）、`curl` 直接打頁面確認回傳 302（轉登入頁）而不是 500、容器 log 沒有 fatal error，作為程式碼正確性的替代驗證；畫面實際長相仍待使用者在瀏覽器裡確認。

## 53. 快速預約彈窗關閉鈕（`.qc-close`）：滑鼠移入 X 消失的問題

快速預約彈窗（行事曆首頁 FullCalendar 版與舊版共用 `#quickCreateModal` markup）右上角關閉鈕原本是 Bootstrap `.btn-close`——一顆用 `background: transparent url("data:image/svg+xml,...")` 畫出來的黑色 X。滑鼠移上去 X 會整個消失、只剩一塊灰底，根因有兩層：

1. `#quickCreateModal .qc-close:hover` 原本寫的是 `background: var(--bs-tertiary-bg, #f1f2f6);`——`background` 是簡寫屬性，會整個蓋掉 `.btn-close` 本來用同一個簡寫畫出來的 SVG 圖層，等於 hover 時把圖示本身砍掉，只留下純色背景。改成只設 `background-color`，不動 `background-image` 這一層，圖示才會在 hover 時繼續存在。
2. 光改上面這條還不夠：`overview_fullcalendar.css` 裡本來就有一條頁面級的通用按鈕 hover 規則 `#page-overview-fc button:not(:disabled):not(.reservation-icon-button):not(.reservation-primary-button):not(.reservation-btn-text):hover`（`#quickCreateModal` 在 DOM 上是 `#page-overview-fc` 的子孫，這條規則吃得到它），5 個 `:not()` 疊出來的 specificity 比 `#quickCreateModal .qc-close:hover` 高，會整組蓋掉後者的 hover 樣式。修法是在這條規則的排除清單裡多加 `:not(.qc-close):not(.btn-primary)`，跟既有的 `.reservation-icon-button` 等排除項用同一套模式，讓 Quick Create 彈窗裡的關閉鈕跟「建立預約」主按鈕維持自己的 hover 樣式，不再被頁面級規則搶走。

順便把關閉鈕從 Bootstrap 的 SVG 背景圖示換成純文字 `×` 字元（`tpl/Overview/overview.tpl`／`overview_fullcalendar.tpl` 的 `<button class="qc-close">×</button>`）——不再依賴背景圖片這一層，之後要調顏色/尺寸只需要改 `color`/`font-size`，也徹底排除同一種「某個屬性簡寫蓋掉圖示」的問題再發生的可能。目前定案的樣式（`overview_fullcalendar.css`）：點擊區 36×36px、圓角 8px、位置右上 16px、預設字色 `#6C7488`（24px）、hover 字色 `#1F3159`＋底色 `#F1F4F8`、active 底色 `#E4E9F1`，`:focus-visible` 保留 2px 藍色外框。`overview.css`（舊版頁面）目前仍是原本的 50% 圓角／12px 定位／`var(--gcal-control-height)` 尺寸，尚未套用同一組數值，之後如果要讓兩個版本視覺一致需另外處理。

## 54. 取消預約時，「選擇性」參與者（內部 Invitee／外部 Invited Guest）沒有收到取消信

使用者要求盤點「參與者元件擴充外部 Email、寄信身分、ICS 產生器、建立/修改/取消串接」這條主線目前的完成度。逐一對照 §52.1（picker 擴充）、§25.3（SEQUENCE 接 `reservation_series.version`）、`iCalendarReservationView`/`ical.tpl`（UID／METHOD REQUEST-CANCEL／STATUS）、`AddReservationNotificationService`／`UpdateReservationNotificationService`／`DeleteReservationNotificationService` 三支串接服務後，發現前三塊其實都已經做完，真正還缺的只有 `DeleteReservationNotificationService`（取消預約時要通知誰）這一塊裡的一個不一致：

- 「必要」參與者（內部 Participant／外部 Participating Guest）取消時本來就會收到取消信（`ParticipantDeletedEmailNotification`／`GuestDeletedEmailNotification`）。
- 「選擇性」參與者這條線，新增／修改時都會正確收到通知信（`InviteeAddedEmailNotification`／`InviteeUpdatedEmailNotification`／`GuestUpdatedEmailNotification` 都有處理 Added/Unchanged/Removed 三種情況），但整筆預約直接被取消（不是被移出參與者清單，而是整場會議消失）時：
  - 內部選擇性參與者（Invitee）完全沒有對應的 `InviteeDeletedEmailNotification`，`DeleteReservationNotificationService` 裡也沒有掛這一個通知——`InviteeRemovedEmail` 這個訊息類別其實老早就存在（`lib/Email/Messages/InviteeAddedEmail.php`），但只有「從進行中的預約裡被移除」這個情境會用到它，「整場預約被取消」這個情境完全沒有路徑會建立它。
  - 外部選擇性受邀者（Invited Guest）雖然 `GuestDeletedEmailNotification` 有掛，但內容只跑 `UnchangedParticipatingGuests()`（必要外部訪客），漏了 `UnchangedInvitedGuests()`（選擇性外部訪客）這一條。

同時盤點了使用者原本擔心的另外兩塊，確認其實都已經完成、不需要再動：

- **寄信身分（Sender/Organizer 分離）**：`EmailService::Send()` 本來就會把信件的實際寄件人（envelope/header From）統一覆寫成後台設定的系統帳號（目前是 `coasiaerp@coasia.com`／擎亞會議室預約系統），而 `EmailMessage::ReplyTo()` 預設等於各訊息類別自己的 `From()`（也就是預約建立者本人），ICS 附件的 `ORGANIZER` 也是直接寫預約建立者的 email（`iCalendarReservationView::$OrganizerEmail`）——三者組合起來剛好就是「寄件人＝系統帳號、回覆會回到真人、行事曆事件的主辦人＝真人」，不需要另外加欄位或分支。
- **防重送**：新增預約的防重複已經是既有的 idempotency key 機制（§22.6，P0-C）；修改／取消則是靠 `reservation_series.version` 的樂觀鎖（`ReservationRepository::Update()`／`DeleteSeriesEventCommand::Execute()`）——版本不符時整支 `Execute()` 會直接丟 `ReservationVersionConflictException`，通知服務只會在寫入成功後才被呼叫到，同一筆修改/取消的重複送出（雙擊、網路重試）本來就不會跑到兩次通知。

### 修法

新增 `lib/Application/Reservation/Notification/InviteeDeletedEmailNotification.php`，寫法跟 `ParticipantDeletedEmailNotification` 對稱：對 `CurrentInstance()->UnchangedInvitees()` 裡的每一個內部使用者，組一封 `InviteeRemovedEmail` 送出去；掛進 `DeleteReservationNotificationService` 的通知清單，並在 `Notification/namespace.php` 補上對應的 `require_once`。`GuestDeletedEmailNotification::Notify()` 多加一段迴圈跑 `UnchangedInvitedGuests()`（跟原本就有的 `UnchangedParticipatingGuests()` 迴圈並列），兩段都送 `GuestDeletedEmail`，不需要新的訊息類別。

新增兩個測試：`InvitationEmailNotificationTest::testSendsReservationDeletedEmailsToExistingInvitees()`（驗證 `InviteeDeletedEmailNotification` 對兩個既有 invitee 各送一封 `InviteeRemovedEmail`）、`GuestEmailNotificationTest::testSendsReservationDeletedEmailsToInvitedGuests()`（驗證 `GuestDeletedEmailNotification` 現在也會對既有的 invited guest 送 `GuestDeletedEmail`），跑法比照同檔案裡既有的「必要參與者」對照組。全專案 PHPUnit（1725 個測試，含這次新增的 2 個）只有 1 個既有 Error（`ResourceCrossDayRuleTest`）＋1 個既有 Failure（`OverviewFullCalendarEventsPresenterTest`，語言字串斷言），兩者都跟這輪改動的檔案無關，跑這輪改動前就已經存在。

### 還沒動的部分

外部信箱端對端寄送這次沒有驗證——使用者確認公司內部 SMTP Relay（`10.1.3.140:25`）寄到內部信箱（`@coasia.com`）沒問題，但外部信箱（例如私人 Gmail）目前不確定 relay 是否放行，這件事屬於 relay/IT 端的網路層設定，不是這次動的程式碼能決定的，留待 relay 打通後另外用一筆真的帶外部 Email 的預約端對端驗證。

## 55. Quick Create 彈窗：時間／資源下拉選單的尺寸、定位、層級

使用者提出一份具體的 CSS 規格，要求「主版面不要重做，只修正所有 dropdown 的尺寸、定位與層級」，逐項對照 `Web/css/overview_fullcalendar.css` 既有的 `#quickCreateModal` 樣式後套用：

- 結束時間選單（`#qcEndDropdown`）加 `left:auto; right:0`，右側對齊、不超出 Modal；開始時間選單維持原本的左側對齊不動。
- `.qc-time-dropdown` 從 `min-width:180px` 改成固定 `width:170px`（`max-height:240px` 原本就對）；`.qc-option` 改用 `display:flex; align-items:center; min-height:40px`（原本 `padding:8px 12px` 實際只有約 32px，沒有真的達到 40px）。
- `.qc-resource-picker .resource-picker-panel` 加 `max-height:280px`；新增專屬的 `.resource-picker-list` 覆寫把清單本身限制在 230px 內捲動——搜尋框本來就不在捲動區裡面（結構上早就是「固定在頂部」），不用額外處理。
- `.qc-close` 字級 24px → 22px。
- `#quickCreateModal .modal-content` 的 `overflow:hidden` 改成 `overflow:visible`，讓 dropdown 可以合法地超出 Modal 本身的邊界（往上展開、或資源清單較長時）而不被裁切——確認過這個 Modal 沒有滿版底色的 header/footer 貼齊邊緣，拿掉 `hidden` 不會讓四個角變成方形。
- 兩個 dropdown 的圓角統一用 `var(--coasia-radius-control, 10px)`，陰影統一用 `var(--coasia-shadow-surface, ...)`——這兩個 token 是 §52.3 建的，`--coasia-shadow-surface` 本身就是管理中心 `.admin-card` 在用的陰影，符合「陰影與管理中心卡片一致」的要求。`z-index` 都拉到 `50`。

### 向上展開：JS 量測，不是 CSS 猜

新增 `positionDropdownVertically($trigger, $dropdown)`（`Web/scripts/overview_fullcalendar.js`），在每次開啟 dropdown 當下用 `getBoundingClientRect()` 量 trigger 下方在 viewport 裡的剩餘空間，跟 dropdown 實際渲染出來的高度（`offsetHeight`）比較——dropdown 目前是用 `opacity`/`visibility` 切換顯示，不是 `display:none`，所以在還沒加上 `.show`/`.open` 之前就能量到真實尺寸。空間不夠時加上 `.open-upward`（`top:auto; bottom:calc(100% + Npx)`），掛進三個開啟點：開始時間、結束時間、資源選單。工具列自己的資源選單（`#resourcePicker`，跟 Quick Create 的 `#qcResourcePicker` 是兩份獨立實例）沒有動，維持原樣，符合「只修 Quick Create」的範圍。

## 56. 管理中心操作邏輯統一：編輯按鈕常駐、刪除進「⋯」選單、查看與編輯分開

使用者要求四個管理清單頁（預約管理／會議室管理／使用者管理／開放時間設定）統一同一套操作邏輯：可編輯項目要有常駐可見的「編輯」按鈕（不是 hover 才出現、也不是靠整列可點這種沒有視覺提示的方式），刪除等次要操作收進每列右側「⋯」選單，純資訊欄位不可呈現成可點擊狀態。系統設定首頁本身已經是合格的卡片式導覽（純連結＋既有 hover 樣式），這次確認不用動；子頁面（信件範本／自訂欄位／AD 同步狀態／伺服器設定／資料清理）不在這次範圍內。

盤點四頁現況（`.admin-clickable-row` 的說明註解原文正好就是在描述要修的反模式：「signals it's clickable without adding a visible per-row view button」）：預約／會議室／開放時間三頁都是整列可點開一個側邊 Drawer；使用者管理已經有每列「⋯」選單，但「編輯」是選單裡的第一項而不是獨立按鈕，姓名連結又重複做一樣的事。

### 共用元件

`Web/css/admin.css` 新增 `.admin-row-actions`（flex 容器）＋ `.admin-row-edit-btn`（鉛筆圖示＋「編輯」文字，常駐顯示），視覺上刻意比照既有的 `.admin-row-menu-btn`（同樣的 32px 高、border/radius/背景色、hover 色）讓兩顆按鈕讀起來像同一組控制項；kebab 本身沿用既有的 `.admin-row-menu-btn`／`.admin-row-menu-disabled`，沒有新增樣式。移除 `.admin-clickable-row`（改完之後三頁都不再用到，確認站內沒有其他頁面引用才刪）。

### 逐頁改法（跟使用者確認過的取捨）

- **使用者管理**：姓名從 `<a class="update edit admin-user-name-link">` 改成純文字 `<span class="admin-user-name">`（不再可點）；Actions 欄新增 `.admin-row-edit-btn`，直接掛上原本就在姓名連結上的 `update edit` 兩個 class——`Web/scripts/admin/user.js` 的 `.update`／`.edit` 委派事件本來就是讀 `closest('tr')`，跟掛在哪個子元素上無關，**完全不用改 JS**；kebab 選單裡原本重複的「編輯」項目拿掉。
- **預約管理**：使用者決定拿掉現有的唯讀檢視 Drawer（`#reservationDetailDrawer`），只留「編輯」（直接連到 `reservation.php` 完整表單，沿用既有的 `viewReservation()`）＋「⋯」（`RequiresApproval` 時才顯示「核准」、一定顯示「取消預約」，都沿用既有的 `approveReservation()`/`showDeleteReservation()`，只是改成從列上的按鈕直接帶 `data-refnum` 呼叫，不用先開 Drawer）。連帶清掉 Drawer 專屬的 JS 綁定跟 `openReservationDetail()` 函式本體，以及只被這個函式用到的 5 個欄位（title/description/resourceName/ownerName/timeRange）跟 `noTitleText`/`statusPendingText`/`statusConfirmedText` 三個 option。
- **會議室管理／開放時間設定**：「編輯」按鈕＝打開既有的 Drawer（Drawer 裡各欄位維持原本的就地編輯鉛筆圖示不動，這次沒有另外做唯讀檢視畫面）；kebab 加「刪除」，直接重用 `openResourceDrawer()`/`openScheduleDrawer()`（本來就會呼叫 `setActiveResourceId()`/`setActiveScheduleId()`）跟 `showDeletePrompt()`/`showDeleteDialog()`（原本就是靠 active id 判斷要刪誰，現在改成 kebab 點擊時先手動 `setActiveXxxId()` 再呼叫）。開放時間設定的預設排程不能刪，kebab 比照使用者管理「AD 帳號不可刪除」的既有寫法（`.admin-row-menu-disabled` + `title` 提示），新增 `AdminScheduleDefaultCannotDelete` 翻譯字串（`lang/en_us.php`／`lang/zh_tw.php`）。
- **新增按鈕**：四頁本來就都透過 `Admin/admin_page_shell.tpl` 的 `HeaderActions` 固定在右上角，結構本來就一致；唯一不一致的是開放時間設定的新增按鈕多了一個 `btn-sm`，拿掉讓四頁尺寸統一。文字沿用既有翻譯字串，沒有改字詞。

### 驗證

`node --check` 三支改動的 JS（`reservations.js`/`resource.js`/`schedule.js`）語法都過；四支改動的 `.tpl` 用大括號計數法（比照 §52.4）確認 Smarty 標籤沒有配對錯誤。全專案 PHPUnit（1725 個測試）維持跟改動前一樣的 1 個既有 Error＋1 個既有 Failure（`ResourceCrossDayRuleTest`／`OverviewFullCalendarEventsPresenterTest`，都跟這輪動到的檔案無關），沒有新增的失敗——這輪本來就沒有改任何 PHP 邏輯，只動 tpl/js/css/lang。畫面實際操作（整列不再可點、編輯按鈕預設可見、kebab 內容正確、姓名不再可點、四頁新增按鈕尺寸一致）需要使用者自己登入後端逐頁確認，沒有帳密無法在瀏覽器裡代為驗證。

## 57. 完整預約表單：切換會議室只有 1 個選項、拿掉「選擇性參與者」

使用者從「更多選項」（Quick Create 導去完整預約表單）截圖回報兩個問題，逐一查證：

### 57.1 切換會議室下拉選單只列出 1 個選項

查資料庫確認根因：這個環境裡每個資源（12 個會議室/視訊資源）都各自綁定自己專屬的一個排程（`schedules` 表跟 `resources` 表 1:1，Google Meet1 自己一個排程、台北會議室自己一個…）。`ReservationComponentBinder.php` 的 `ReservationResourceBinder::Bind()` 原本用 `$this->resourceService->GetResourceGroups($requestedScheduleId, ...)->GetAllResources()` 餵 `create.tpl` 的切換會議室下拉選單——這個查詢本來就是照排程分組（用來支撐「在同一筆預約裡加開額外資源」那個獨立功能，這兩個功能共用同一個 `$AvailableResources` 變數），在這個「每個資源各自一個排程」的環境下，切換選單自然永遠只列得出 1 個選項。跟使用者確認：改成列出使用者可存取的「全部」資源，不分排程（比照 Quick Create 自己的資源選單，本來就是不分排程的）。

修法：新增一條獨立的綁定路徑（`BindSwitchableResources()`），從 `IReservationPage`／`IReservationComponentInitializer` 兩層介面各加一個方法，`ReservationPage.php` 實作成 `Set('SwitchableResources', ...)`，`ReservationInitializerBase.php` 委派給 `basePage`；`ReservationResourceBinder::Bind()` 改用 `$this->resourceService->GetAllResources(false, $user)`（跟 `SearchAvailabilityPresenter`／`OverviewFullCalendarPresenter` 已經在用的同一支不分排程查詢）餵這條新路徑，`create.tpl` 的切換會議室 foreach 改讀 `$SwitchableResources`。刻意不動原本的 `$AvailableResources`／`ShowAdditionalResources`——「加開額外資源」那個功能繼續維持排程內限定，只有「整個換一個會議室」這個動作不再受排程邊界限制。兩個測試 fake（`FakeExistingReservationPage`、`GuestReservationPresenterTest` 裡的 `FakeGuestReservationPage`）補上對應的空實作，否則會直接 fatal error（介面多了一個沒實作的抽象方法）。

### 57.2 拿掉「選擇性參與者」

使用者確認完整表單上方那個收合的「選擇性參與者」區塊（`participation.tpl` 的 `<details id="rpOptionalParticipantsDetails">`）不是 bug——必要參與者確實會透過 `saveQuickCreateDraft()`/sessionStorage 從 Quick Create 帶過來，「選擇性參與者」本來就只能在完整表單新增（Quick Create 從 §52.1 起就沒有這個欄位），顯示 0 是預期行為——而是根本不想要這個功能了，要求整個拿掉。

拿掉 `participation.tpl` 裡整個 `<details>` 區塊（搜尋框＋chip 清單＋隱藏欄位容器），`create.tpl` 對應拔掉 `rpInviteePicker` 的建立／`reset()`／`conflictsWith` wiring、`qcDraft.optionalParticipants` 的 sessionStorage 讀取分支，`reservation-page.css` 刪掉變成孤兒的 `.rp-optional-*` 樣式規則。範圍刻意只到「新增」這個入口——沒有動 `Domain/Reservation.php` 的 `Invitees()`/`InvitedGuests()`、§54 剛加的 `InviteeDeletedEmailNotification` 等後端通知邏輯、`view.tpl`/`availability.tpl`（唯讀顯示既有邀請對象）、`reservation-pdf.js`（PDF 匯出既有邀請對象）——這些都是「顯示/處理已經存在的邀請資料」，跟這次拿掉的「新增入口」是兩回事，留著不影響任何人；資料庫目前也確實只有 1 筆既有的選擇性受邀者測試資料。

順手發現 `Web/scripts/reservation.js` 裡還留著一套更早期的邀請對話框實作（`#inviteeDialog`/`#promptForInvitees`/`participation.addInvitee()` 等），對應的 DOM 元素在 `participation.tpl` 這輪重構前就已經不存在了，是這輪之前就有的死程式碼，這次沒有一併清——範圍比較大，之後有空再處理。

### 驗證

`ReservationPage.php`／`ReservationInitializerBase.php`／`ReservationComponentBinder.php`／兩個 fake 都過 `php -l`；`create.tpl`／`participation.tpl`／`reservation-page.css` 用大括號計數法確認沒有配對錯誤。全專案 PHPUnit（1725 個測試）維持原本那 1 個既有 Error＋1 個既有 Failure，沒有新增失敗，12 個既有 Warning 也逐條核對過都是跟這輪完全無關的檔案（`SmartyPage.php`／`ReminderNotice.php`／`ScheduleRepository.php` 等既有的 PHP 8.2 array-key 警告）。畫面實際操作（切換會議室看得到全部選項、「選擇性參與者」區塊不見了）一樣需要使用者自己登入確認。

## 58. 開發環境 Smarty 編譯快取排查、切換會議室顏色比照 Quick Create、拿掉「其他設定」整塊

使用者回報 §57 的修正在畫面上沒生效，自己先查出根因：`config/config.php` 的 `cache.templates` 是 `true`，`tpl_c/` 底下還留著 8/5 的舊編譯檔（`participation.tpl.php`/`create.tpl.php`），Smarty 沒有重新編譯就直接吃了舊版。照使用者指示處理：清空 `tpl_c/`（用 `find -type f -not -name index.html -delete`，目前這個安裝實際上沒有 `index.html` 可保留）、`cache.templates` 改成 `false`（dev-only，`config/config.php` 是 gitignored，改的是 host 端 bind-mount 的檔案，不進 git）、重啟 app 容器清 PHP opcache。事後驗證：抓重啟後新編出來的 `participation.tpl.php` 確認不含 `rpOptionalParticipantsDetails`，證實真的生效了。之後這輪每次改完 tpl/PHP 都比照這個流程（清 `tpl_c` + 重啟容器）驗證。

### 58.1 切換會議室顏色要跟 Quick Create 一致

使用者盯著畫面發現：完整表單的切換會議室選單，色點幾乎都是同一種灰色，跟 Quick Create 資源選單裡五顏六色、每個資源一個顏色的樣子對不起來。查出原因：`create.tpl` 原本直接用 `$resource->GetColor()|default:'#5c6b84'`——資源沒有手動設定顏色時一律 fallback 成同一個灰色；Quick Create 用的是 `Presenters/OverviewFullCalendarPresenter.php::AssignColors()`，沒設定顏色時改成照列表順序從一組 11 色的固定調色盤裡循環選一個，讓每個資源看起來都不一樣（`OverviewPresenter.php` 也有一份幾乎一樣的 `AssignColors()`，是既有的重複）。

抽出 `lib/Application/Schedule/ResourceColorAssigner.php`（靜態方法 `Assign()`，內容跟前述兩份 `AssignColors()` 一致）——這是第三份要用到同一段邏輯的地方，直接複製第三次沒必要，抽成共用類別讓三個地方以後對得起來，但沒有動既有那兩份重複（不在這次要求範圍內，風險/效益不划算）。`ReservationComponentBinder.php` 的 `ReservationResourceBinder::Bind()` 算出 `$switchableResources` 後，接著用 `ResourceColorAssigner::Assign()` 算顏色，`BindSwitchableResources()` 介面加一個 `$colors` 參數（`IReservationPage`／`IReservationComponentInitializer` 兩層都要跟著改，兩個測試 fake 也要補上新參數，否則會直接因為抽象方法簽章對不上而 fatal error）。`create.tpl` 的色點跟 `data-color` 都改讀 `$SwitchableResourceColors[$resource->GetId()].bg`。

### 58.2 拿掉「其他設定」整塊

使用者截圖問的另一個問題：「其他設定」收合區塊裡面實際包了 4 樣東西（加開額外資源、配件/設備、服務條款勾選、自訂欄位），確認過不是「這個資源剛好都是空的」這種顯示問題，是使用者決定整塊功能永久不要了。動手前查了一下這 4 樣東西在這個環境裡的真實使用狀況：`terms_of_service` 表是空的（0 筆，沒有任何排程設定過服務條款）、`accessories` 表雖然有 3 筆但名字是「accessory limited to 10」「unlimited accessory」這種明顯是 LibreBooking 內建 demo/seed 資料，不是 CoAsia 真的在用的配件；「加開額外資源」則是 §57.1 就已經查出來的、這個環境「每個資源各自一個排程」的資料形狀下本來就恆為 false（`ShowAdditionalResources` 需要同排程內 `NumberAccessible > 1`）——四樣裡沒有一樣在這個環境裡真的有在用，拿掉不影響任何現有資料或使用情境。

`create.tpl` 整段刪掉 `<details class="rp-collapse-row">`（`OtherSettings`/`PermissionsAndCustomFields`）以及它唯一會打開的兩個 modal（`#dialogResourceGroups`、`#dialogAddAccessories`，觸發按鈕都在剛刪的區塊裡，變成沒有任何路徑可以打開，一併清掉）。刻意沒有動的部分：`Web/scripts/reservation.js` 裡對應這兩個 modal 的 JS（`AddResources()`、accessory 相關 handler）——DOM 元素沒了之後這些 handler 自然不會再被觸發，跟 §57.2 處理 `reservation.js` 舊版邀請對話框的方式一致，範圍比較大，這次沒有一併清理原始碼裡的函式本體。

### 驗證

新增的 `ResourceColorAssigner.php` 過 `php -l`；`create.tpl` 大括號計數法確認沒有配對錯誤；全專案 PHPUnit（1725 個測試）兩輪都維持原本那 1 個既有 Error＋1 個既有 Failure，沒有新增失敗。每次改完都清 `tpl_c`＋重啟容器，log 裡沒有 fatal error。

## 59. 統一「快速預約」與「更多選項」——第一步：日期／時間選擇器

使用者要求把 Quick Create 彈窗跟「更多選項」（完整預約表單）統一成同一套體驗，以 Quick Create 為唯一基準，範圍包含欄位順序/圖示/配色一致、資料不遺失、重複預約收合、共用資料模型。使用者決定先做其中最具體、風險最可控的一塊：**兩邊的日期／時間選擇器換成同一套元件**。

盤點落差：完整表單原本是 jQuery UI 文字輸入日期＋兩個 `<select>`（選項來自 `$StartPeriods`/`$EndPeriods`，即該會議室排程實際設定的時段，顆粒度不保證是 30 分鐘，可能因排程而異），Quick Create 則是原生 `<input type="date">`＋自訂顯示＋固定 00:00–23:30、每 30 分鐘一格的兩個自訂下拉選單。查證過兩邊送出的欄位其實相容：`BeginPeriod`/`EndPeriod` 的值本來就是 `Time::ToString()`＝`"HH:mm:ss"`（`lib/Common/Time.php`），跟 Quick Create 自己的 `minutesToHHMMSS()` 輸出格式一致；`BeginDate` 原本的 `DatePickerSetupControl` 用的日期格式也是 `Y-m-d`（`Controls/DatePickerSetupControl.php:122`），跟原生 `<input type="date">` 的值格式完全相同——這代表可以直接把新元件的值寫進同樣 id/`{formname}` 的欄位，不用改任何 PHP 或 `recurrence.js`/`reservation.js` 的讀取邏輯。後端還有 `SchedulePeriodRule`（`lib/Application/Reservation/Validation/`）在送出時驗證時段合法性，所以前端換成「不管排程、固定 30 分鐘格」不會讓人繞過營業時間限制——跟 Quick Create 現在的行為本來就一樣（Quick Create 也沒有前端排程時段過濾，一直是靠這條規則 + 事後可用性檢查）。

### 做法

新增 `Web/scripts/reservation-datetime-picker.js`，把原本寫死在 `overview_fullcalendar.js` 裡的 `TimeDropdown()` 工廠函式、`minutesToHHMMSS`/`formatTimeLabel`/`formatDuration`/`formatDateLabel`/`positionDropdownVertically`（這次 Quick Create dropdown 那輪寫的「往上展開」量測邏輯）搬成全域函式（跟 `participant-picker.js` 一樣的做法，不是 ES module）；另外新增 `buildStartTimeOptions()`/`buildEndTimeOptions()` 把 `refreshTimeUI()` 裡原本兩段內嵌的 for 迴圈也收進共用檔案。`overview_fullcalendar.js` 改成呼叫共用版本並刪掉自己的重複定義，`TimeDropdown()` 新增第 4 個參數 `onBeforeOpen`（取代原本寫死呼叫 `closeAllDropdowns()`），讓這個工廠函式不用綁定特定頁面的 dropdown 註冊表就能被兩邊各自的 `closeAllDropdowns`/`rpCloseAllDropdowns` 重用。

`create.tpl` 的日期/時間區塊拆成 `{if $LockPeriods}...{else}...{/if}`：`$LockPeriods`（編輯重複預約單一次時鎖定時段的既有情境）分支完全不動，原封不動保留舊的 `<select>`/文字輸入；沒鎖定的一般情況換成跟 Quick Create 完全同款的原生日期輸入＋兩個 `.qc-time-picker`/`.qc-time-dropdown`（沿用 `qc-` 這組既有 class，不另外發明 `rp-` 版本），新增的 IIFE 用共用函式產生選項、選到值後寫回 `#BeginDate`/`#BeginPeriod`/`#EndPeriod`/`#EndDate`（原本的 id／`{formname}`／POST 欄位名稱完全不變）並 `.trigger('change')`，讓既有的 `scheduleRpAvailabilityCheck` 監聽跟 `recurrence.js` 的結束日期自動計算都不用改就能繼續動作。同時拿掉 `#BeginDate`/`#EndDate` 的 `DatePickerSetupControl` 呼叫（jQuery UI datepicker 不再需要，`EndRepeat`/`RepeatDate` 那兩個保留）。`$StartPeriods`/`$EndPeriods` 的 PHP 端綁定完全沒動，只是模板不再用它們畫 `<select>`。

`Web/css/reservation-page.css` 在 `#page-reservation` 底下新增一份跟 `overview_fullcalendar.css` 對應 `#quickCreateModal .qc-*` 規則逐項相同數值的複製（含這次 Quick Create dropdown 那輪的 170px 寬度／10px 圓角／`--coasia-shadow-surface` 陰影／`open-upward`／右對齊等最新版本），因為這兩頁各自載入不同的 CSS 檔、沒有共用機制。

### 驗證

`node --check` 新的共用 JS 檔＋改動過的 `overview_fullcalendar.js`；`create.tpl`／`reservation-page.css` 大括號計數法確認沒有配對錯誤；編譯後的 `create.tpl.php` 抓出來確認 `LockPeriods` 分支還留著舊的 `<select id="BeginPeriod">`（預期行為，只有這個分支該留）、非鎖定分支正確含 `rpStartDropdown`。全專案 PHPUnit（1725 個測試）維持原本那 1 個既有 Error＋1 個既有 Failure，沒有新增失敗——這輪沒有動任何 PHP 邏輯。過程中 Docker Desktop 整個掉線一次（不是單純容器停止，是 daemon 本身斷線），啟動 `Docker Desktop.exe`、等 daemon 恢復後重新清 `tpl_c`＋跑完整套驗證。

### 還沒做的部分

使用者原本列的其餘四項（欄位順序/圖示/文案配色其餘部分逐項比對、雙向資料不遺失含「返回快速預約」目前完全不存在的機制、重複預約收合成單一下拉選單、其餘部分共用資料模型）留待下一輪；已經在 `C:\Users\shawn_tsai\.claude\plans\moonlit-launching-ullman.md` 記錄過查證結果（`$ReturnUrl` 只看 Referer/redirect 參數，沒有任何草稿回帶機制；`saveCalendarReturnState()`只還原行事曆日期/檢視，不是預約草稿）方便之後接續規劃。

### 上線後修的兩個問題

畫面實際測試時發現兩個問題，當場修掉：

1. **整頁「未知錯誤」**：新增的日期/時間選擇器那段 JS 註解裡，英文說明剛好寫成「still {formname}-bound to the same POST keys」——`{formname}`（沒有帶 `key=` 參數）被 Smarty 當成真的模板函式呼叫，`FormKeys::Evaluate(NULL)` 內部用 `eval()` 組字串時直接語法錯誤，整頁掛掉。註解裡混寫 Smarty 模板語言 + 英文說明時要避開任何 `{小寫英文字}` 這種剛好符合 Smarty 標籤語法的片語；改成不含花括號的措辭。順手全檔搜了一輪確認沒有其他地方誤觸發（`{* *}` Smarty 註解裡的 `{formname}` 是安全的，不會被解析）。
2. **日期/時間沒有並排**：Quick Create 原本用 `.qc-time-edit`（`display:grid`，固定四欄寬度）把日期欄＋開始時間＋dash＋結束時間包成一列，複製 CSS 時漏掉複製這層容器規則，也漏掉在 `create.tpl` 加對應的包裹 `<div>`——這幾個元素掉進外層 `.rp-datetime-fields` 的 flex-wrap，每個都撐滿 100% 寬度變成直的一排。補上 `.qc-time-edit` 包裹 div 跟對應的 CSS grid 規則後恢復並排。

## 60. 完整預約表單重複預約：收成一個下拉選單、選項精簡、切回不重複要清乾淨

使用者原本統一快速預約/更多選項那份需求裡的第 3 項，這輪單獨拿出來做。四個小要求逐一核對：

- **預設只顯示一個「不重複」下拉選單**：`Controls/RecurrenceDiv.tpl`（`{control type="RecurrenceControl"}` 這個共用元件，`tpl/Reservation/create.tpl`／`tpl/SearchAvailability/search-availability.tpl`／`tpl/Admin/Blackouts/manage_blackouts*.tpl` 都在用）本來就沒有把整組間隔/星期/結束日期包在一個有邊框的容器裡，子區塊預設都是 `d-none`，也沒有任何外層 box 樣式——本來就已經符合「預設只看得到一個下拉選單」，不用改。
- **選項為不重複/每天/每週/每月/自訂**：既有的 `RepeatOptions`（`Controls/RecurrenceControl.php`）多一個「每年」，但這個共用元件也給 `search-availability.tpl`／管理中心的封鎖時段（blackouts）在用，後者留著「每年」可能有實際用途（例如每年固定假日封鎖某會議室）——沒有動共用檔案本身，只在 `create.tpl` 自己的 inline script 裡多一行 `$('#repeatOptions option[value="yearly"]').remove();`，只拔掉這一頁看到的選項；如果正在編輯的是一筆本來就已經設成「每年」的既有預約（`$RepeatType === 'yearly'`），保留該選項不拔，讓既有資料還能正常顯示/選取。
- **選一種才展開對應設定**：`Web/scripts/recurrence.js` 的 `ChangeRepeatOptions()` 本來就是「選什麼類型只顯示對應的 `.recur-toggle` 子區塊」的邏輯，已經符合，沒有改。
- **切回不重複要立即收起並清除設定**：這是唯一真的缺的一塊——原本切回「不重複」只有隱藏子區塊（`hide()`），星期勾選、月重複類型、結束日期、自訂日期清單的實際值都還留著沒清，切回其他類型時舊值會原封不動跑出來，甚至可能連著送出。新增 `ClearRepeatSettings()`，切到 `none` 時：間隔重設回 1、星期勾選全部取消、月重複類型重設回預設的「每月第 X 天」、結束日期清空（含 flatpickr 元件本身的 `.clear()`，`terminationDateSetManually` 旗標也重置，讓之後重新選重複類型時 `AdjustTerminationDate()` 還會自動建議一個結束日期）、自訂日期清單清空重繪。這段加在共用的 `ChangeRepeatOptions()` 裡（沒有另外包一份 create.tpl 專用版本）——判斷只在使用者明確選「不重複」時才觸發，其他既有頁面（search-availability、blackouts）只會多這個好處，不影響原本在用的其他類型間切換行為。

### 驗證

`node --check` 過 `recurrence.js`；`create.tpl` 大括號計數法沒有配對錯誤；PHPUnit（1725 個測試）維持原本那 1 個既有 Error＋1 個既有 Failure，沒有新增失敗——這輪同樣沒有動任何 PHP 邏輯，`RecurrenceControl.php`／`RecurrenceDiv.tpl` 完全沒改。清過 `tpl_c`＋重啟容器，log 沒有 fatal error。畫面實際操作（選重複類型只展開對應設定、切回不重複後星期/結束日期是不是真的清空、下拉選單裡沒有「每年」）需要使用者自己登入確認。

### 追加：兩段式「不重複／重複」

使用者實際看畫面後回饋：希望不是單一個「不重複／每天／每週／每月／自訂」五選一下拉選單，而是先問「重複還是不重複」，選了「重複」才跳出第二個下拉選單讓你選頻率——預設狀態一樣只看得到一個下拉選單。

在 `create.tpl` 裡新增 `#rpRepeatGate`（不重複/重複兩選一）＋ `#rpRepeatFrequency`（每天/每週/每月/自訂，預設 `d-none`）這兩個純前端的 `<select>`，`RecurrenceControl` 原本的 `#repeatOptions` 保留在 DOM 裡當唯一的真實資料來源（`recurrence.js`／表單送出都還是讀它），只是把它自己那一列（label＋select）用 CSS 藏起來，兩個新選單改成用 JS 寫回 `#repeatOptions` 的值再 `.trigger('change')`，讓既有的 `ChangeRepeatOptions()`／`NotifyChange()` 邏輯完全不用改。隱藏用的 CSS 選擇器故意沿用既有那條 `.rp-recurrence-wrap .repeat-div .form-group > .d-flex:first-child`（原本只是拿來蓋 `margin-bottom`），只有這個組合選擇器才確保只在 `create.tpl` 生效、不影響共用這個控制項的 `search-availability.tpl`／管理中心封鎖時段兩個頁面。

**中間踩了一個坑**：第一版只寫 `display: none`，畫面上那一列還是照樣顯示——因為 Bootstrap 自己的 `.d-flex` 工具類別是 `display: flex !important`，`!important` 不管選擇器再精確都贏。改成 `display: none !important` 才真的蓋掉。

### 驗證（追加這段）

CSS 大括號計數法沒有配對錯誤；直接 `curl` 抓 `reservation-page.css` 確認伺服器吐出來的內容真的含 `!important`（排除是瀏覽器快取沒刷新的可能性）。清過 `tpl_c`＋重啟容器，log 沒有 fatal error，PHPUnit 維持原本基準沒有新增失敗。

## 61. 文案/品牌小改：完整表單標題、系統名稱改成「CoAsia Booking」

兩筆小改動，都是使用者看畫面後直接要求的文字調整：

- `tpl/Reservation/create.tpl` 的頁面標題（`CreateReservationHeading`）從「建立一個預約」改成「新增預約」，副標「新增完整的會議室預約」（`CreateFullReservationSubtitle`）不變——只改 `lang/zh_tw.php` 這一個字串，確認過這個翻譯 key 只有這一頁在用。
- 系統名稱從 LibreBooking 改成 CoAsia Booking：`config/config.php`（gitignored，不進 git）的 `app.title` 改成 `'CoAsia Booking'`——這一個設定值會同時驅動瀏覽器分頁標題（`Pages/Page.php` 的 `$Title`/`$AppTitle`）跟全站 185 個信件範本裡用到 `{$AppTitle}` 的地方（歡迎信、邀請信等），不用逐一改檔案。登入頁 logo 下方的說明文字（`RoomBookingSystem`）從「會議室預約系統」改成「CoAsia 會議室預約」，同樣只動 `lang/zh_tw.php`。`README.md` 的 CoAsia Fork 說明段落也一併補上新名稱。

### 驗證

`curl` 直接打首頁確認 `<title>CoAsia Booking - Log In</title>` 已經生效；登入頁標語的中文字串沒有直接用 curl 驗證（預設語系是 en_us，需要登入 session 的語系 cookie 才會顯示中文版，跟前幾輪其他 `lang/zh_tw.php` 字串改動一樣的驗證限制，使用者自己的瀏覽器 session 本來就是中文）。清過 `tpl_c`＋重啟容器，log 沒有 fatal error，PHPUnit 維持原本基準沒有新增失敗。`config/config.php` 的改動不進 git（gitignored），只有 `lang/zh_tw.php`／`README.md` 會進版控。

## 62. 「我的帳號」重新設計：Profile 頁面改版、語言下拉選單、側邊欄簡化

使用者反映登入後的語言、時區設定完全找不到入口。查了才發現這些功能其實早就做好了（`Domain/User.php` 的 `$timezone`／`ChangeTimezone()`、`Web/ajax/change_language.php` 整條路徑都是活的），只是在先前簡化導覽時被 `{if false}` 藏起來，從來沒有連結能點進去。

- `tpl/globalheader.tpl`：「我的帳號」從一個展開式子選單（含 Profile／變更密碼／參與邀請／點數）簡化成單一直接連結，直接連到 Profile 頁面；子選單裡原本重複的「通知設定」連結拿掉（本來就有獨立的常駐入口，避免同一個 `#navNotification` id 出現兩次）
- 側邊欄底部原本被藏起來的語言切換子選單整個拿掉，改成在 Profile 頁面本身加一個語言下拉選單，跟時區放在一起，選了立即透過既有的 `ajax/change_language.php` 存檔＋重新整理頁面
- `tpl/MyAccount/profile.tpl` 從舊版純 Bootstrap 卡片整個重新設計成跟「通知設定」頁一致的 `shell`/`page-head`/`np-card` 視覺語言（新增 `Web/css/profile.css`），分成「帳號」「個人資料」「偏好設定」三張卡片。電話/組織/職稱這三個欄位原本考慮拿掉（覺得像是沒人用的欄位），查了程式碼才發現其實有被預約確認信（`lib/Email/Messages/ReservationEmailMessage.php`）跟參與者彈窗（`tpl/Ajax/user_popup.tpl`）用到，所以全部保留
- 側邊欄收合（icon-only）模式下，剛恢復的「我的帳號」跟語言切換兩個項目文字沒有正確隱藏、疊字溢出——原因是沒有包在 `.sidebar-link-label` 這個 span 裡（收合的 CSS 規則只認這個 class），補上就正常了
- 補上三個語言檔案（en_us／zh_tw／zh_cn）都缺的翻譯字串：`PersonalInformation`、`Preferences`；`zh_tw.php` 額外缺了 `ChangeLanguage`（這也是「切換語言」選單一直顯示英文字的原因，即使使用者介面其他部分都是繁中，因為 Smarty 的 `{translate}` 找不到 key 時會 fallback 回英文）

## 63. 新帳號語言預設值改成繁中，並一次性修正既有 116 個帳號

改完上面 §62 才發現一個更深的問題：登入頁（未登入狀態）看起來是繁體中文，但登入後整個介面卻變回英文，要手動去設定調整。

**根本原因**：登入頁（`Presenters/LoginPresenter.php::SetSelectedLanguage`）跟登入後所有頁面（`lib/Common/Resources.php::GetLanguageCode`）用的是兩條不同的語言判斷邏輯——登入頁有 4 層 fallback，最後一層是瀏覽器 `Accept-Language`；但登入後只有 3 層（cookie → 使用者資料庫存的 `Language` 欄位 → 設定檔預設值），**完全沒有 `Accept-Language` 這一層**。兩個帳號建立流程（`Jobs/import-coasia-employees.php` 批次匯入、AD 首次登入自動建立）都把新帳號的 `Language` 欄位寫死成 `en_us`，這個資料庫值會直接蓋掉瀏覽器語言判斷，變成登入後看到的是「帳號建立時寫死的英文」，不是「瀏覽器實際語言」。

- `Jobs/import-coasia-employees.php` 的 `DEFAULT_LANGUAGE` 常數改成 `zh_tw`，之後再跑批次匯入不會再有這個問題
- 因為語言切換功能在 §62 之前完全沒有 UI 入口，可以確定沒有任何人手動選過語言——安全地跑了一次性 SQL，把所有還是 `en_us` 的帳號（113 個）改成 `zh_tw`（3 個原本就是 `zh_tw` 的內建帳號不受影響），資料庫現在 116 個帳號全部是 `zh_tw`
- AD 首次登入自動建立帳號那條路徑（`plugins/Authentication/ActiveDirectory/ActiveDirectory.php`）目前仍然讀取 `Configuration::Instance()->GetKey(ConfigKeys::DEFAULT_LANGUAGE)`（設定檔的 `LB_DEFAULT_LANGUAGE`），沒有改程式碼，是靠正式環境的 `app.env` 設定值間接修正——這個設定檔不進版控，記在 `COASIA-DEPLOYMENT.md` 而不是這裡

## 64. 修復三個資源的開放時間資料（Zoom TW／Google Meet2／台北 Taipei）

使用者在「開放時間設定」發現 Zoom TW 的每週開放時間全部顯示「上午 12:00 - 上午 12:00」（起訖時間相同，等於完全沒開放）。查資料庫（`time_blocks` 表）確認不是顯示問題，是資料真的存錯：Zoom TW、Google Meet2、台北 Taipei 這三個時間表，星期一到六每天都只有一筆 `start_time=00:00:00, end_time=00:00:00` 的紀錄，而其他 9 個正常的時間表（上海、新加坡、Zoom CN、Google Meet1 等）用的是另一種「整天切成 30 分鐘一格、`day_of_week` 為 NULL」的正常格式。台北時間表還是被設成系統「預設」的那個，影響範圍不小。

確認過三個都應該是「24 小時全天可訂」之後，沒有手動猜時間格式重寫，而是直接把同類型、已經確認正常運作的資源設定整組複製過去（Taipei ← 上海 Shanghai 的 layout；Zoom TW ← Zoom CN 的 layout；Google Meet2 ← Google Meet1 的 layout），降低格式猜錯的風險。開發環境測過確認無誤後，同一套 SQL 套用到正式環境——過程中發現正式環境「台北 Taipei」的 `layout_id` 是 16，跟開發環境的 13 不一樣（兩邊資料庫在這個 ID 上有落差，其餘資源的 layout_id 兩邊一致），套用前有先在正式環境重新查一次實際的 ID 對應，避免改錯資料。

這是直接對資料庫跑的 SQL 修正，不是程式碼變更，不會出現在 git 歷史裡，記錄在這裡以免之後忘記發生過這件事。

## 65. 管理後台兩個 UI 問題：預約列表排序、開放時間編輯的儲存按鈕被蓋住

使用者做 UI 檢查時發現的兩個問題：

- **預約管理列表**：預設排序是最舊的在最前面，使用者預期應該是最新的在前面。`Presenters/Admin/ManageReservationsPresenter.php` 呼叫 `LoadFiltered()` 時，沒有 `sort`/`dir` querystring 的情況下直接把 `null` 傳給排序參數，底層 SQL 樣板（`Queries.php` 的 `GET_RESERVATION_LIST_TEMPLATE`）預設是 `ORDER BY start_date ASC`。這個樣板同時被好幾個地方共用（行事曆檢視、待審核清單、忘記簽到清單），所以不能直接改樣板本身，而是在這個 presenter 裡把 `null` 換成明確的預設值（`start_date` / `desc`），只影響這一個頁面。這個列表本身沒有可點擊排序的欄位標題，改預設方向不會跟任何既有的互動排序功能衝突。

- **開放時間編輯抽屜的儲存按鈕「消失」**：使用者回報怎麼找都找不到儲存按鈕，畫面上唯一看到的是一顆藍色圓形「?」按鈕，懷疑那顆才是儲存鈕。查了才發現那顆「?」其實跟這個抽屜完全無關——是每個後台頁面右下角都有的「回到頂部」浮動按鈕（`tpl/globalfooter.tpl` 的 `#button-up`），它固定在畫面右下角，剛好跟開放時間編輯抽屜（同樣是右側面板）的內容重疊，真正的儲存按鈕（`tpl/Admin/Schedules/manage_schedules.tpl` 裡的 `button.saveScheduleHours`）其實就在被蓋住的那個位置，只是完全看不到也點不到。修法：`Web/scripts/phpscheduleit.js` 監聽 Bootstrap 的 `shown.bs.offcanvas`/`hidden.bs.offcanvas` 事件，任何抽屜打開時就把這顆浮動按鈕隱藏，關閉後再依照當下捲動位置決定要不要顯示回來。

## 66. 會議室管理依資源類型簡化、系統設定重做成系統診斷儀表板、使用分析 Excel 匯出修復

三個獨立子任務，同一輪做完。

### 會議室管理抽屜：實體會議室只留名稱，線上會議改顯示預約後要給使用者的資訊

使用者看著「會議室管理」的編輯抽屜（時間表/地區/聯繫/說明/筆記/進階設定/自訂屬性/Public 一大串欄位）覺得對實體會議室來說根本用不到，而線上會議（Zoom/Google Meet）真正該讓管理員編輯的，是預約成功後要傳給使用者的資訊（連結/會議ID/密碼/主持人登入帳密），不是這堆通用欄位。

- **新增 ResourceType 判斷依據**：資料庫裡原本 12 個資源的 `resource_type_id` 全是 NULL，只能靠有沒有 `video_url` 自訂屬性值間接推測是不是視訊資源（新資源在填值前無法判斷）。新增 `database_schema/upgrades/4.4/schema.sql`，建立「實體會議室」「線上會議」兩個 ResourceType（比對既有的 `GET_RESOURCE_TYPE_BY_NAME` 查詢寫法，用名稱比對而非新增獨立欄位），並把 12 個既有資源分類過去（1-7 號實體、8-12 號線上）。`Domain/ResourceType.php` 新增 `IsVideoConference()`，用名稱比對「線上會議」。
- **抽屜三種分支**（`tpl/Admin/Resources/manage_resource_drawer_content.tpl`）：
  - 實體會議室：只留名稱＋狀態（狀態开关有跟使用者確認過要保留，避免管理員無法停用房間），其餘全部隱藏
  - 線上會議：名稱＋狀態＋新的「線上會議資訊」區塊——會議連結/會議ID/密碼沿用既有的自訂屬性 inline 編輯機制（`Admin/InlineAttributeEdit.tpl` 的模式），只是改用專屬翻譯字串當標籤，不再顯示屬性本身的原始 label（`video_url` 這種內部字串不能改成好看的中文，因為 `ReservationEmailTemplateContext.php` 靠它精確比對）
  - 尚未設定 ResourceType 的資源：維持顯示原本全部欄位，讓新資源在分類前還能設定
- **主持人登入帳密（全新功能）**：`resource_video_conference_host` 這張表跟加密邏輯（AES-256-CBC）本來就有，但完全沒有 admin 介面可以寫入，唯一寫入方式是一支一次性 CLI 匯入腳本。新增 Presenter action `ChangeVideoConferenceHost`＋`dr=videoHost` 讀取端點，搭配一個新的全域 Modal。使用者一開始要求帳密要點擊編輯才顯示（避免每次打開資源列表，5 個視訊資源的主持人密碼就全部解密寫進頁面原始碼），後來看畫面覺得應該跟連結/會議ID一樣直接顯示，改成 `PageLoad()` 批次解密（只針對當前頁面上的線上會議資源，範圍跟列表本身一致）直接綁進抽屜初始 HTML。
- 過程中漏掉兩個 `require_once`（`ActiveDirectoryConfigKeys.php`、`LineWebhookPresenter.php`），造成 AD／LINE 兩張診斷卡片 500——這是下一段系統診斷儀表板留下的坑，一併記在這裡。

### 系統設定 → 系統診斷：從除錯資訊改成真正的狀態儀表板

原本的「系統設定」是一個 5 張卡片的啟動頁（通知信件/AD同步/自訂屬性/系統診斷/資料清理），「系統診斷」本身只有伺服器時間、上傳目錄權限、樣板快取路徑、外掛清單——對日常維運沒有實際用處。

- 側邊欄「系統設定」項目直接改名「系統診斷」，連結改指向 `server_settings.php`（原本的 5 卡片啟動頁 `system_settings.php` 保留在磁碟上，只是不再連結，沒有刪除任何頁面程式碼）
- `ServerSettingsPage.php` 新增 4 張即時健康檢查卡（AD／SMTP／LINE／檔案權限），透過 `dr=checkAd`/`checkSmtp`/`checkLine`/`checkFiles` 各自回傳 JSON，頁面載入時用新的 `Web/scripts/admin/server-settings.js` 平行觸發 AJAX（不同步阻塞頁面——AD/SMTP 都是真的網路連線，對方掛掉可能要等 timeout），「重新檢查全部」按鈕可重新觸發
  - AD：比對 `ConfigKeys::PLUGIN_AUTHENTICATION` 是否為 `'ActiveDirectory'`，是的話用既有的 `AdLdapWrapper::Connect()` 做服務帳號 LDAP bind 測試（失敗會拋例外）
  - SMTP：比對 `EMAIL_ENABLED`／`PHPMAILER_MAILER`，複製 `EmailService` 建構子組 `PHPMailer` 設定的邏輯，呼叫其內建但先前從未被呼叫過的 `smtpConnect()` 做連線測試
  - LINE：比對 `LINE_ENABLED`，用 `Unirest\Request::get()` 打 LINE 的 `/v2/bot/info` 驗證 channel access token 是否有效。「最後收到 webhook」這個時間點，目前完全沒有任何記錄機制（只有等級可能被設定過濾掉的一般日誌），改在 `LineWebhookPresenter::HandleEvents()` 開頭寫入一個時間戳記到 `LOGGING_FOLDER` 底下的標記檔（不新增資料庫表，這個目錄本來就是必須可寫的）——使用者原本要一併加上排程服務（cron）的執行紀錄追蹤，後來決定拿掉這張卡片，成本效益比太差（完全沒有任何現成的執行紀錄機制）
  - 檔案權限：比照既有的 `fileperms()` 邏輯與 `lib/preflight.php` 的檢查手法，檢查 `tpl_c`／圖片上傳目錄是否存在且可寫入
- 新增兩個文件頁面，都比照 `AdSyncStatusPage` 的最小 shell 模式：「系統架構圖」（`system_architecture.php`，內容現場重寫，含手繪 inline SVG 流程圖——這個 app 沒有載入 Mermaid.js，不為了一張圖新增前端相依套件）、「維運文件」（`operations_doc.php`，直接讀取並用 `<pre>` 顯示 `COASIA-DEPLOYMENT.md` 原始內容，不重寫也不加 Markdown 渲染套件，避免這份持續在維護的部署文件分裂成兩份要各自更新）
- 使用者一開始想把架構圖／文件連結指到公司 GitLab，後來改口要求現場重寫內容直接放進系統裡，不連外部連結
- 「其他工具」那排連結（通知信件/AD同步/自訂屬性/資料清理）先加進儀表板，使用者看畫面後又要求整排拿掉

### 使用分析 Excel 匯出：closure 忘記 `use` 外層變數

使用者點「匯出 Excel」直接跳「未知錯誤」頁，畫面上還連帶印出一行 PHP Warning。查 `ManageUsageAnalyticsPage.php::ExportExcel()`，四個要寫入分頁的 `array_map()` 裡有一個（`$locationSheet` 那個，把 `location === '__unassigned__'` 的資料轉成翻譯字串的那段）用到外層的 `$resources`，但傳進去的匿名函式沒有 `use ($resources)`，PHP 視為 undefined variable，接著呼叫 `->GetString()` 在 null 上直接掛掉。同一個方法裡另外兩個 `array_map()` 剛好都沒有在 closure 內用到 `$resources`，所以沒有踩到這個問題，只有這一處需要補 `use ($resources)`。

`vendor/phpoffice/phpspreadsheet` 本身在 Docker 開發環境裡是正常安裝、可以運作的（獨立測試過產生 xlsx 檔案成功），純粹是這一行程式碼的 bug，不是套件安裝問題。

### 驗證

會議室管理、系統診斷兩個功能都在使用者本機的 Docker dev 環境裡實測：套用 `database_schema/upgrades/4.4/schema.sql` 並確認冪等（重複執行不出錯）、資源類型正確分類並反映在列表與抽屜、線上會議資訊區塊顯示的資料與既有截圖吻合、主持人登入帳密的讀取/加密儲存/解密回顯全流程驗證過、實體會議室抽屜維持只顯示名稱＋狀態。系統診斷儀表板由使用者自己登入實測，抓到並回報了兩個遺漏的 `require_once`，當場修復重驗。使用分析 Excel 匯出的根因（closure 缺少 `use`）也是使用者實測「未知錯誤」頁面截圖直接定位到的，不是靠猜。

## 67. 使用者管理「最後登入」時間顯示錯誤：預設時區寫死是 Europe/London

使用者發現「使用者管理」列表的「最後登入」時間跟實際差 7 小時（顯示 04:31，應該是台北時間 11:31），推斷正式站可能在用 `Europe/London` 夏令時間（UTC+1），跟台北（UTC+8）剛好差 7 小時。

- **根因確認**：`ConfigKeys::DEFAULT_TIMEZONE`（`lib/Config/ConfigKeys.php`）的預設值原本就是 LibreBooking 上游沿用下來的 `'Europe/London'`，這個 fork 從來沒改過。`.env.example`／`config/config.dist.php` 也是同一個值。
- **兩處修正**：
  1. `ConfigKeys::DEFAULT_TIMEZONE` 的 `'default'` 改成 `'Asia/Taipei'`，用專案既有的 `scripts/generate-env-example.php --write`／`scripts/generate-config-dist.php --write` 重新產生 `.env.example`／`config/config.dist.php`（不是手改，避免跟 CI 的 `env-example:check`／`config-dist:check` 一致性檢查對不上）。跑產生器時順便發現這兩份範本檔案早就漏掉 LINE 相關設定跟視訊主持人加密金鑰（先前幾輪加了對應的 ConfigKeys 但沒重新產生範本檔），一併補齊。
  2. `Pages/Admin/ManageUsersPage.php` 原本固定讀系統設定的 `$config->GetDefaultTimezone()`，改成讀「目前登入管理員自己的 session 時區」（`$this->server->GetUserSession()->Timezone`）——這是「預約管理」列表頁（`ManageReservationsPage.php`）本來就在用的既有正確模式，照抄而已，不是新發明的邏輯。使用者列表本身用的 `{format_date ... timezone=$Timezone}` Smarty 語法完全沒變，只改了 `$Timezone` 這個變數的來源。
- **沒有動資料庫**：使用者特別交代不要改資料庫裡的 UTC 原始時間，這次全程只動顯示層的時區轉換依據，資料庫裡儲存的時間戳記完全沒有觸碰。
- **正式環境需要額外一步**：`config/config.php` 不進版控（gitignored），程式碼層的預設值修正不會影響正式站已經明確設定過的值，需要使用者自己登入正式站確認並手動把 `default.timezone`（或 `app.env` 的 `LB_DEFAULT_TIMEZONE`）改成 `Asia/Taipei`——這份文件記一筆，實際操作記在 `COASIA-DEPLOYMENT.md`。

### 驗證

開發環境的 `config/config.php` 本來就已經手動設過 `Asia/Taipei`（跟正式站不同步，這正是使用者只在正式站發現問題的原因），所以這輪主要靠讀程式碼＋比對既有正確頁面的寫法定位問題，而非在開發環境重現時區差異。`php -l` 確認 `ManageUsersPage.php`／`ConfigKeys.php` 語法正確，重新產生的 `.env.example`／`config.dist.php` 內容經人工比對只有預期中的欄位變動。

### 部署這兩輪時踩到的坑

§66／§67 實際手動部署到正式站時，連續踩了兩個問題，都直接促成了下面 §68 的 CI/CD 自動化：

1. **`docker compose restart app` 不會套用新程式碼**：正式環境的 `app` 服務是用 `Dockerfile.prod` 把程式碼 `COPY` 進 image（不像開發環境用 bind mount），`git pull` 只更新了 VM 上的原始碼資料夾，沒有重新 build image，`restart` 只是重啟同一個舊 image。要看到新程式碼必須 `docker compose build app` 再 `up -d`。
2. **套 `database_schema/upgrades/4.4/schema.sql` 忘記帶 `--default-character-set=utf8mb4`**：導致新插入的「實體會議室」「線上會議」兩個 `resource_type_name` 值被錯誤轉碼，網頁上顯示亂碼（不是終端機顯示問題，是資料庫裡實際存的位元組壞了）。新增 `database_schema/upgrades/4.4/fix-encoding.sql`（刻意不叫 `schema.sql`，不會被升級腳本自動重跑）用正確編碼把這兩筆資料 UPDATE 回去修復。

## 68. 新增 GitLab CI/CD：push tag 自動測試/build/部署到正式環境（已於 §70 移除）

上面這兩個部署失誤直接促成這次的自動化——`restart` vs 重新 build 搞混、忘記資料庫連線字元集，都是「人工照著文件打指令」這個流程本身容易出錯的地方，用 pipeline 把這些步驟寫死可以直接消除這整類問題。README 的架構圖裡本來就寫著「未來：Tag 觸發 CI/CD」，這次做出第一版。

- **`.gitlab-ci.yml`**：三個 stage，`test`（push `develop` 或 push tag 都會跑：`composer test`／`phpstan`／`config-dist:check`／`env-example:check`，用 `Dockerfile.dev` 建一次性映像檔跑，不含 GitHub Actions 那邊上游遺留的完整 lint 陣列）→ `build`（只有 tag：`docker compose -f docker-compose.prod.yml build app`）→ `deploy`（只有 tag：套 migration、`up -d`、`composer preflight` + `curl` 驗證）。
- **Runner 拓樸**：直接裝在正式環境 VM 上、shell executor，理由是 Runner 要能直接呼叫 VM 本機已經在跑的 `docker compose`，不需要額外處理跨機器 SSH 或 docker-in-docker 的複雜度，風險等級跟現在手動 SSH 上去操作是一樣的，不是新增的信任面。**Runner 的 `builds_dir` 必須設定成 checkout 到既有的 `~/coasia-booking` 目錄**（不是 Runner 預設自己管理的 build 資料夾），因為 `docker-compose.prod.yml` 用相對路徑掛載 `config/config.php`／`app.env`／`Caddyfile` 這些不進版控、只存在於這個目錄的實體檔案，checkout 到別的路徑會直接找不到這些檔案。
- **`deploy/apply-migrations.sh`（新增）**：邏輯照抄 `phing-tasks/UpgradeDbTask.php` 的資料夾掃描順序（`database_schema/upgrades/<version>/`，依版號排序，只認 `schema.sql`／`data.sql` 這兩個檔名，`fix-encoding.sql` 這種一次性 hotfix 不會被自動重跑），但用純 bash 迴圈實作，不需要額外裝 phing/phive 工具鏈。資料庫帳密直接讀 VM 本機的 `.env`（Runner 就在同一台機器上，不需要透過 GitLab CI/CD Variables 傳密碼，少一個要維護的密鑰）。**每一條 `mysql` 指令都寫死帶 `--default-character-set=utf8mb4`**——直接對應上面 §67 附錄記錄的那次編碼事故，之後不會再有人手動下指令時忘記加這個參數。
- **安全性**：部署觸發完全靠 push tag，唯一的守門機制是 GitLab 的 Protected tags 設定（只有 Maintainer 角色能建立/推送 tag）——這一步需要使用者自己在 GitLab 專案設定裡開，這份文件沒辦法幫忙做。
- **已知限制（刻意留到之後）**：`deploy` job 沒有自動回滾，驗證步驟失敗只會讓 pipeline 顯示失敗（GitLab 預設會通知），新版本已經在跑不會自動切回舊版；要回滾得手動 checkout 前一個 tag 重新跑一次 pipeline。

### 驗證

`.gitlab-ci.yml` 用 Python 的 `yaml.safe_load` 驗證過語法正確；`deploy/apply-migrations.sh` 用 `bash -n` 驗證過語法正確，並且用 `git update-index --chmod=+x` 標記成可執行檔。Runner 註冊、`builds_dir` 設定、Protected tags 這幾項需要使用者自己在 VM／GitLab 專案設定裡操作，沒辦法在這個對話裡直接驗證——第一次 push tag 觸發完整 pipeline 時才會是真正的端對端測試。

## 69. 三個獨立 bug 修復（預約編輯頁時間錯誤／開放時間「開啟」儲存失敗／新增預約進入點）＋新增「我的會議」頁面

同一輪工作，四件事彼此獨立，合併記錄。

### 69.1 編輯預約時，時間被時間表的區段起訖時間覆蓋

使用者回報：同一筆台北預約，行事曆跟詳細卡片都正確顯示 `09:30–10:30`，但點鉛筆進「編輯預約」後變成 `00:00–00:30`，且不只是畫面顯示問題——表單準備送出的值也已經是 `00:00:00`／`00:30:00`，只改標題按更新就會把原本的時間改掉。

**根因**：`tpl/Reservation/create.tpl` 組出 `BeginPeriod`／`EndPeriod` 這兩個隱藏欄位（以及對應的 JS 時間選擇器初始值）時，用的是 `$SelectedStart->Begin()` / `$SelectedEnd->End()`——時間表**區段**的起訖時間，不是這筆預約**本身**的起訖時間。台北的時間表是「全天單一區段」（`00:00 → 00:00`，星期日關閉），這個區段的 `Begin()`/`End()` 永遠是 `00:00:00`/`00:30:00`，跟預約實際幾點開始完全無關；真正帶正確時分秒的 `$StartDate`/`$EndDate`（已經由 `ExistingReservationInitializer::SetSelectedDates()` 正確從 `reservationView->StartDate/EndDate` 設定好）在表單裡卻只被拿來取「日期」部分。

**修法**：`create.tpl` 的隱藏欄位初始值與 JS 時間選擇器初始分鐘數，一律改成從 `$StartDate`/`$EndDate` 本身取時分秒（`{formatdate ... format='H:i:s'}`），不再用時間表區段的 `Begin()`/`End()`。新增預約流程不受影響，因為那邊 `$StartDate`/`$EndDate` 本來就已經帶著正確時間。

### 69.2 開放時間管理：「開啟」某天並儲存會靜默失敗，「關閉」永遠成功

使用者發現「開放時間設定」的每週開放時間編輯器，關閉某天再儲存沒問題，但開啟某天（例如把星期日從關閉切成 09:00–18:00）儲存卻完全沒反應，畫面上看不到任何錯誤訊息。

**根因**：`Web/scripts/admin/schedule.js` 的 `saveScheduleHours()` 組出送出資料時，「開啟」某天只寫了 `reservableEdit_{day}`（例如 `09:00 - 18:00`），`blockedEdit_{day}` 留空字串。但後端 `LayoutValidator` 要求每一天的時段合起來必須完整覆蓋 24 小時、中間不能有空隙（第一段要從 `00:00` 開始、最後一段要在 `00:00` 結束）——只填 `09:00–18:00` 這一段，`00:00–09:00` 跟 `18:00–00:00` 完全沒有任何 reservable 或 blocked 紀錄，等於留了空隙，驗證直接失敗。而且這個失敗訊息是寫進舊版對話框（`#changeLayoutDialog`）裡一個新版畫面完全不會顯示出來的 `.validationSummary` 區塊，所以使用者完全看不到任何錯誤，就是「按下去沒反應」。「關閉」永遠成功是因為固定寫 `blockedEdit_{day} = "00:00 - 00:00"`，單獨一段就完整覆蓋全天，天生不會有空隙。

**修法**：比照同一個檔案裡 `createQuickLayout()` 本來就有的模式，開啟某天時自動把該時段前後的空隙補成 blocked（`00:00` 到開始時間、結束時間到 `00:00`），確保送出的每一天永遠完整覆蓋 24 小時。

### 69.3 側邊欄「新增預約」在非行事曆頁面會用錯的預設時間開完整表單

使用者發現在「通知設定」「我的帳號」「管理中心」等頁面點側邊欄「新增預約」，時間預設值是原始的「現在時間」（例如 `15:06`），不是像快速預約那樣「無條件進位到下一個半點」；並認為這個按鈕應該一律開快速預約，不是完整表單。

**根因**：這顆按鈕原本設計成在行事曆頁面呼叫 `overview_fullcalendar.js` 暴露的 `window.overviewFullCalendarOpenQuickCreate()`（開快速預約，預設時間已經有「進位到下一個半點」邏輯），在其他頁面因為行事曆沒被載入、這個 hook 是 `undefined`，退回到連到 `reservation.php` 完整表單的 fallback——而完整表單在沒有帶任何日期時間 querystring 時，後端 `ReservationDateBinder::Bind()` 直接用 `Date::Now()` 當開始時間，完全沒有進位邏輯。

**修法**：不修表單本身的預設時間邏輯，改成讓這顆按鈕在任何頁面點擊都一律走快速預約——在其他頁面點擊時改成導到行事曆頁面並帶 `?openQuickCreate=1` 標記，行事曆頁面載入完成後偵測到這個標記就自動呼叫跟在行事曆上點擊時完全相同的 `overviewFullCalendarOpenQuickCreate()`，然後用 `history.replaceState` 把網址上的標記清掉，避免使用者重新整理或返回時又跳出一次。

### 69.4 新增「我的會議」頁面：下一場會議倒數卡片＋完整清單

新功能，使用者要求側邊欄空白區加一個「我的會議」入口，顯示下一場尚未開始的會議（含倒數、查看詳情、線上會議加入連結）跟接下來幾筆預約。

第一版做成側邊欄內嵌小工具（`Controls/MyMeetingsSidebarControl.php` 直接渲染進 `globalheader.tpl`），使用者實測後回饋「這樣很醜」，要求改成跟「通知設定」一樣——純導覽連結，點進去才看得到內容。整個內嵌小工具（Control／Presenter／獨立樣板／側邊欄專用 CSS）全部撤掉，改成：

- **沿用既有但一直被 `{if false}` 藏起來的 `my-meetings.php`**（`Pages/MyMeetingsPage.php`／`tpl/MyMeetings/my-meetings.tpl`），只是把導覽連結恢復顯示，位置放在「行事曆首頁」和「通知設定」中間（第一次復原時位置沒放對、跑到「我的帳號」下面，使用者發現後才移到正確位置）。
- **`Presenters/MyMeetingsPresenter.php` 新增「下一場會議」邏輯**：從既有的 `Upcoming` 清單挑出時間最早的一筆，額外查該筆會議室的 `video_url` 自訂屬性（跟預約確認信「加入會議」用的同一個屬性）決定要不要顯示「加入會議」按鈕。下一場會議一樣會出現在下方完整清單的第一列，不特地排除——一個頁面內重複顯示同一筆是常見、可接受的「置頂摘要＋完整清單」模式，不用另外做一份「接下來 3–5 筆」的精簡清單。
- **`tpl/MyMeetings/my-meetings.tpl` 加一張卡片**：標題、倒數（純前端 `setInterval` 每 30 秒重新計算，不用整頁重新整理）、日期時間、會議室、查看詳情、（若有）加入會議；沒有下一場會議時卡片不會整個消失變成孤零零一行文字，而是同一個白色卡片外殼裡置中顯示行事曆圖示＋「近期沒有安排會議」，跟有內容時是同一套視覺系統。
- 三個語言檔（zh_tw／en_us／zh_cn）補上 `NextMeeting`／`JoinMeeting`／`NoUpcomingMeetingsSoon`／倒數用的分鐘/小時/天數後綴字串，其餘沿用既有的 `MyMeetings`／`ViewDetails`／`Untitled`／`AdminOverviewInProgress`（「進行中」）。

**過程中又踩到一次 §51.6 記過的老坑**：改完 `lang/zh_tw.php` 之後畫面上新字串全部顯示成「?」——這個 dev 容器的 `opcache.validate_timestamps=Off`，改 `.php` 檔案要 `docker restart` 容器才會生效，光改檔案不夠。`docker restart` 之後重新驗證過三個語言檔的新字串都正確載入。

### 驗證

四項都用容器內 CLI 直接驅動實際程式碼驗證，沒有登入帳密沒辦法在瀏覽器裡實測：

- 69.1／69.4：用真實帳號 `shawn.admin`（id=2）跑過 `MyMeetingsPresenter`／`ExistingReservationInitializer` 的實際查詢，正確抓到使用者當時那筆 `2026-08-11 09:30–10:30`「test」預約，`Presenters/MyMeetingsPresenter.php` 的 `BuildNextMeeting()` 與 `tpl/MyMeetings/my-meetings.tpl` 整段樣板透過 `SmartyPage` 直接渲染，確認無 PHP/Smarty 錯誤且輸出內容正確（含空狀態、繁中字串）。
- 69.2：讀 `Domain/ScheduleLayout.php`／`lib/Common/Validators/LayoutValidator.php` 原始碼確認「必須覆蓋整天」的驗證邏輯與失敗時錯誤訊息寫進哪個（畫面不會顯示的）DOM 節點，`php -l` 確認 JS 對應的 PHP 端沒有變動、修改僅限 `schedule.js`。
- 69.3：追過 `NewReservationPage::GetStartDate()`／`ReservationDateBinder::Bind()` 的呼叫鏈確認 `Date::Now()` 無進位邏輯，比對 `overview_fullcalendar.js` 既有 `window.overviewFullCalendarOpenQuickCreate` 的進位算法（`Math.ceil(nowMinutes/30)*30`），新增的重導向邏輯呼叫的是同一支函式，非另外複製一份。
- 每次改完 `.tpl`／`.php`／`lang/*.php` 都清過容器內 `tpl_c/` 編譯快取（`cache.templates=true` 時 Smarty 不會自動偵測檔案變更），語言檔額外 `docker restart` 過一次確認 OPcache 問題解決。畫面實際觀感（顏色/間距/是否好看）由使用者登入後親自確認並回饋了三輪，本節最終版本已反映所有回饋。

## 70. 移除 GitLab CI/CD，回到手動部署

§68 做的第一版 pipeline 在 GitLab 上一直卡在「擱置中」——`test`/`build`/`deploy` 三個 job 都指定了 `tags: [coasia-vm]`，但正式環境 VM 上從沒實際完成過 Runner 註冊這個一次性手動步驟，所以 job 永遠找不到 Runner 可以撿走執行。權衡下決定不繼續維護這條路，撤掉 `.gitlab-ci.yml`，部署流程退回 §68 之前的手動 SSH 操作。

- 刪除 `.gitlab-ci.yml`。
- **保留 `deploy/apply-migrations.sh`**：這支腳本本身不依賴 GitLab CI/CD，純粹是把 §66／§67 踩過的 `--default-character-set=utf8mb4` 坑寫死成腳本，手動部署時執行 `./deploy/apply-migrations.sh` 一樣能避開那個編碼問題，比純手動下 `mysql` 指令安全，所以留著繼續用。
- **同步修正文件**：`README.md` 移除 GitLab CI/CD 相關的異動亮點、技術棧表格列，架構圖的部署箭頭改回「手動 git pull」；`COASIA-DEPLOYMENT.md` 原本就還沒更新成「已有 CI/CD」的狀態（一直寫著「還沒有自動化的 CI/CD pipeline」），這次不用改，本來就跟現況一致。
- Protected tags、Runner 相關的 GitLab 專案設定沒有動（本來就是 §68 提到「需要使用者自己在 GitLab 專案設定裡操作」的部分），之後如果要重新啟用 CI/CD，`.gitlab-ci.yml` 的內容還在 git 歷史裡（§68 那次 commit）可以直接找回來參考。

### 驗證

`git status`／`git diff` 確認只刪了 `.gitlab-ci.yml`、改了 `README.md`／`COASIA-CHANGES.md` 兩份文件，沒有動到 `deploy/apply-migrations.sh` 或任何應用程式程式碼。

## 71. 新增首次登入導覽與使用手冊

使用者要求規劃兩層教學：登入後的系統內快速導覽，以及一份完整使用者手冊，且一般使用者／管理員內容要分開。先產出規劃文件（導覽步驟文案、手冊目錄、截圖清單、入口位置建議、預計異動檔案），使用者確認後才動手實作。

### 71.1 首次登入導覽

新增 `Web/scripts/onboarding-tour.js`／`Web/css/onboarding-tour.css`：純 vanilla JS 寫的聚光燈＋提示卡元件（沒有另外引入 intro.js／shepherd 之類的第三方套件，風格比照 `overview_fullcalendar.js` 既有的自製 Quick Create 彈窗），「已看過」狀態存在 `localStorage`（`lbTourSeen_v1`），沒有做成資料庫欄位——換瀏覽器或清快取會重新看到導覽，是刻意接受的 MVP 取捨。

導覽只掛在行事曆首頁（`tpl/Overview/overview_fullcalendar.tpl`），因為所有 spotlight 目標元素都只存在於這一頁。內容原本規劃 6 步，使用者實測後回饋「側邊欄新增預約也是一種建立方式」「側邊欄每一項都要說明」，追加一步變成 7 步：

1. 歡迎（無指向元素，置中彈窗）
2. 認識左側導覽列（`#appSidebar`，逐項條列 +新增預約／行事曆首頁／我的會議／通知設定／我的帳號／幫助／管理中心）
3. 選擇會議室（`#resourcePickerTrigger`）
4. 查看空閒時段（`.toolbar-col-right`，涵蓋日/週/月切換＋只看我的）
5. 拖曳建立預約（`#fullcalendarRoot`，補充側邊欄「+新增預約」是同一個視窗的另一個入口）
6. 編輯或取消預約（`#fullcalendarRoot`）
7. 我的會議（`#navMyMeetings`）

重播入口兩處：側邊欄「幫助」下拉選單裡的「操作教學」（`?tour=1` querystring，導到行事曆頁後強制重播，離開該頁前先用 `history.replaceState` 清掉標記避免重整又跳一次），以及行事曆頁工具列右側新增的「？」圖示按鈕（跟既有 `#fcTodayBtn` 同一套 40×40 `.coasia-control` 樣式）。

### 71.2 使用手冊頁面

新增 `Web/user-guide.php`／`Pages/UserGuidePage.php`／`tpl/Help/user-guide.tpl`（沒有沿用上游既有的 `Pages/HelpPage.php`，那支是伺服器診斷/credits 頁，跟這次要做的操作手冊性質不同），左側是 sticky 章節導覽（`Web/scripts/user-guide.js` 做 scroll-spy 高亮目前章節），右側是內容。一般使用者 10 章、管理員 8 章，管理員區塊整段包在 `{if isset($CanViewAdmin) && $CanViewAdmin}` 裡——沿用全站既有的權限判斷慣例（`Pages/Page.php` 對每一頁都會 assign 這個變數），不是額外自己刻一套，一般使用者連 DOM 都不會拿到管理員章節的內容。

側邊欄「幫助」下拉的「使用手冊」連結（`Pages::USER_GUIDE`）指過去。

### 71.3 中英雙語

使用者要求中英文都做（沒有預設先做中文）。全部字串（導覽 7 步的標題/內文/按鈕文字、手冊 18 章標題/內文、選單標籤）都同步寫進 `lang/zh_tw.php` 和 `lang/en_us.php`——因為 `zh_tw extends en_us`，`_LoadStrings()` 是「先繼承 en_us 全部字串，再覆寫指定 key」，只寫其中一邊會導致另一個語言顯示錯誤語言的文字，不會直接報錯，容易被忽略，所以每次改文案都刻意兩邊一起改。

### 71.4 開發過程中修的三個問題

**a) `Fatal error: Undefined constant Pages::USER_GUIDE`**：`Pages/Pages.php` 明明已經加了這個常數，但畫面還是噴錯。根因跟 §69.4 記過的老坑一樣——這個 dev 容器 `opcache.validate_timestamps=Off`，容器已經跑了 11 天，PHP 端的 `.php` 檔案變更不會被自動偵測到，要 `docker restart` 容器（清 opcache）才會生效，光改檔案不夠。這次額外確認了一次：純靜態的 `.js`／`.css` 檔案改完不需要重啟，只有 `.php`／`lang/*.php` 需要，之後每次改完 PHP 都直接固定跑一次 `docker restart librebooking-coasia-app-1`。

**b) `ArgumentCountError: 3 arguments are required, 2 given`（使用者自己在 log 裡查出根因）**：`TourStepOf` 語言字串原本是 `'第 %s / %s 步'`，但模板呼叫 `{translate key='TourStepOf'}` 沒有帶 `args`。`lib/Common/SmartyPage.php` 的 `SmartyTranslate()` 在沒有 `args` 時，仍然會呼叫 `Resources::GetString($key, '')`，而 `GetString()` 內部 `!is_array($args)` 會把這個空字串包成 `['']`——PHP 的 `empty(['']) `是 `false`（陣列有一個元素，即使該元素是空字串），所以照樣會跑進 `sprintf()` 分支，用 1 個空字串參數去餵一個需要 2 個 `%s` 的格式字串，PHP 8 直接丟 `ArgumentCountError`。修法：把語言字串裡的 `%s %s` 改成不含 `%` 的純文字佔位符 `{n}`／`{total}`，`sprintf()` 遇到格式字串裡沒有任何 `%` 指令時，多餘的參數會被忽略、不會噴錯；`onboarding-tour.js` 改用 `.replace('{n}', ...)`／`.replace('{total}', ...)` 自己組字串。

**c) 導覽卡片跑到畫面外**：第 5、6 步的 spotlight 目標是 `#fullcalendarRoot`，高度幾乎撐滿整個視窗，原本的定位邏輯只會嘗試放在目標「下方」或「上方」，兩個方向都放不下時仍然照放（下方優先），卡片因此被推到視窗底部之外，完全看不到。改成依序嘗試下、上、右、左四個方向，最後不管落在哪個方向都強制夾在視窗範圍內（`Math.min(Math.max(...))`），確保卡片一定完整可見；順便修正另一個角落案例——同一個目標元素若跟起點正好貼著午夜邊界（凌晨 00:00），置中運算不會把它誤判到前一天。

### 驗證

`node --check` 過 `onboarding-tour.js`／`user-guide.js`／改動的 `overview_fullcalendar.js`；容器內 `php -l` 過 `Pages.php`／`UserGuidePage.php`／`user-guide.php`／`lang/zh_tw.php`／`lang/en_us.php`，皆無語法錯誤。未登入狀態下 `curl` 測試 `user-guide.php`／`overview_fullcalendar.php?tour=1` 均乾淨導向登入頁（302），確認路由層沒有壞掉。實際登入畫面驗證（導覽 7 步逐步操作、手冊頁面渲染、管理員區塊顯示/隱藏）由使用者 `shawn.admin` 本人操作，過程中回報並確認修好了上述 (a)(b)(c) 三個問題，以及兩輪內容調整（「說明」文案改成實際存在的「幫助→操作教學」路徑、追加側邊欄逐項說明步驟）。

## 72. §71 遺留的兩個問題：翻譯字串跳脫在 JS 內完全沒作用、zh_cn 從未實際補齊

英文／簡體中文的「操作教學」點了完全沒反應，使用者自己在瀏覽器 Console 抓到根因並提出診斷，跟後續獨立驗證完全吻合。

### 72.1 `{translate key=...|escape:'javascript'}` 的 `escape` 其實作用在 `key` 參數上，不是翻譯後的輸出

寫了一個獨立的 Smarty 測試腳本（不透過完整 app，直接 `new Smarty\Smarty()` 註冊一個假的 `translate` function plugin），印出編譯後的 PHP 直接證實：`{translate key='TourDoneButton'|escape:'javascript'}` 編譯出來的程式碼是 `getFunctionHandler('translate')->handle(array('key'=>strtr((string)'TourDoneButton', [轉義表])), ...)`——`escape:'javascript'` 這個修飾詞鏈是綁定在 `key` 這個屬性的字面值上（一個從來不需要跳脫的常數字串），完全沒有套用到 `translate` function 實際回傳的翻譯內容。輸出因此是完全沒跳脫的原始文字：`done: 'Done, let's go'`——中文版本一直沒事只是因為所有繁中字串剛好從來沒出現過會弄壞 JS 字串的字元（引號、反斜線），直到這次新增的英文 `let's` 才第一次撞到。

這個寫法在 `tpl/Overview/overview_fullcalendar.tpl` 裡出現了 40 次左右（§71 新增的 `coasiaTourConfig` 區塊 19 次、更早就存在的 `overviewFullCalendarOpts.translations` 區塊 21 次），是全站性的潛在問題，不只是把 `let's` 改掉能解決的——照使用者要求做根本修正：

- `lib/Common/SmartyPage.php` 新增 `{translate_js key=...}` function plugin（`SmartyTranslateJs()`），內部呼叫既有的 `SmartyTranslate()` 拿到翻譯內容後用 `json_encode($value, JSON_UNESCAPED_UNICODE)` 輸出——JSON 字串跳脫規則本來就是合法的 JS 字串字面值語法，順便自動補上外層引號，呼叫端不用再自己包 `'...'`。
- 兩個 `<script>` 區塊裡所有 `'{translate key='X'|escape:'javascript'}'` 全部換成 `{translate_js key='X'}`（拿掉多餘的手動引號）。
- 修好後重新跑一次同一支 Smarty 測試腳本驗證：輸出變成合法的 `done: "Done, let's go"`。

### 72.2 `lang/zh_cn.php` 完全沒有這次新增的 61 個 key

之前回報「英文／簡中翻譯 key 都完整」是誤判——當時的比對只做了 zh_tw 對 en_us，根本沒有另外檢查 zh_cn。使用者直接在 `lang/zh_cn.php` 裡 grep 這些 key 名稱，一個都找不到，才發現這個疏漏。補上全部 61 個 key 的簡體中文翻譯（不是直接抄 zh_tw 的字，用詞比照 `zh_cn.php` 檔案裡既有的慣例，例如「日曆」統一用「日历」而不是「行事历」的簡繁轉換，「設定」「帳號」等維持跟既有 CoAsia 字串一致的用字）。`zh_tw`／`en_us`／`zh_cn` 三個檔案的 61 個 key 現在完全對齊（各自用 `grep -oE` 抓出 key 名稱排序比對，零缺漏零多餘）。

### 驗證

獨立 Smarty 測試腳本（`Smarty\Smarty` 直接 fetch 一個最小 `.tpl`，印出編譯後的 PHP 原始碼）分別驗證了修正前會產生未跳脫輸出、修正後產生合法的 `json_encode` 輸出，測完即刪除，沒有留在專案裡。容器內 `php -l` 過 `SmartyPage.php`／`zh_cn.php`，無語法錯誤。`docker restart` 套用 opcache。三語言檔的 61 個 key 用 `comm -23`／`comm -13` 雙向比對確認零差異。

## 73. 簡化「我的帳號」的語言與時區選項

使用者要求：語言選單只保留 `zh_tw`／`zh_cn`／`en_us` 三種，且登入頁與個人帳號頁清單要一致，既有使用者若設定成其他語言要安全回退成英文；時區選單從幾百個 IANA 城市名稱簡化成以 GMT 偏移量為主、預設 `(GMT+08:00)`，同一偏移量只留一個代表選項，但後端仍要儲存合法 IANA timezone 以正確處理夏令時間。需同步檢查個人帳號頁、註冊頁、管理員編輯使用者頁、系統預設值、後端接受度、既有資料相容性。

### 73.1 語言限制——純設定檔改動，沒有動任何樣板

動手前先確認：`lib/Common/Resources.php` 的 `LoadAvailableLanguages()` 早就支援用 `enabled.languages`（`config/config.php`，逗號分隔）過濾語言清單，而且登入頁（`Pages/LoginPage.php` 的 `$Languages`）跟個人帳號頁（`Pages/Page.php` 對每一頁都會 assign 的 `$AvailableLanguages`）**兩邊本來就是讀同一個已過濾清單**——所以不需要改任何樣板，只要把 `config/config.php`（本機開發用，未進版控）與 `config/config.dist.php`（進版控的範本）的 `enabled.languages` 從空字串改成 `'zh_tw,zh_cn,en_us'`。

「既有使用者設定成其他語言要安全回退英文」這個需求也已經是內建行為，不用額外寫程式碼：`Resources::GetLanguageCode()` 依序檢查 cookie → 使用者自己的 `LanguageCode` → 系統預設值，每一步都會過 `IsLanguageSupported()`（檢查是否在**過濾後**的清單裡），全部不符合才落到寫死的 `en_us`。個人帳號頁的語言切換 AJAX 端點（`Presenters/ChangeLanguagePresenter.php`）跟登入頁切換語言都一樣，送出前会呼叫同一個 `IsLanguageSupported()`，不在允許清單內的語言代碼會直接被拒絕、不會存進資料庫，後端已經天生只接受簡化後的值。

用資料庫直接查過目前本機開發環境的既有資料：115 個帳號全部是 `Asia/Taipei`、113 個 `en_us`／2 個 `zh_tw`，沒有任何帳號的既有語言值會被這次限制擋到，风险很低。

### 73.2 時區簡化——新增一個共用的「代表時區」清單，不動全站唯一的時區來源

`config/timezones.php` 用 `DateTimeZone::listIdentifiers()` 把 PHP 認得的全部（約 400 個）IANA 時區塞進 `$GLOBALS['APP_TIMEZONES']`，個人帳號頁／註冊頁／管理員新增與編輯使用者頁的時區下拉選單，原本都是直接把這個全域變數整包丟進 `<select>`。刻意**沒有**改 `config/timezones.php`／`$GLOBALS['APP_TIMEZONES']` 本身——這個全域變數還有另外兩個沒在這次需求範圍內的用途（`ManageSchedulesPresenter`／`ManageConfigurationPage`，排程時區、系統預設時區設定），改了會波及沒被要求的畫面。

新增 `lib/Common/SimplifiedTimezones.php`：手工整理一份約 32 個「代表時區」的清單（西到東固定排序，不會因為夏令時間換算而重新洗牌排序），每個 GMT 偏移量只保留一個代表 IANA 時區（例如 +08:00 只留 `Asia/Taipei`，不會同時列 `Asia/Shanghai`）。`FormatLabel()` 用 `DateTimeZone::getOffset()` **即時**算出目前的 GMT 偏移量組成顯示字串，不是寫死數字——這代表有夏令時間的地區（例如 `America/New_York`）會隨季節自動在 `(GMT-05:00)` 和 `(GMT-04:00)` 之間正確切換，不需要額外維護。**儲存的值永遠是清單裡挑的那個真正合法的 IANA 字串**（不是自創的偏移量代碼），所以預約引擎既有的 `Date`／`DateRange` 時間換算邏輯完全不受影響。

使用者看過第一版（每個偏移量只帶一個城市名，例如單純「Taipei」）後，提供了一張參考截圖，希望改成每個偏移量列出幾個知名城市（例如「GMT+08:00 (北京，新加坡，臺北)」）。這代表標籤文字不能再是 PHP 裡寫死的英文——這個系統現在有三種語言，城市清單也必須跟著 `zh_tw`／`zh_cn`／`en_us` 走。改成清單裡每一列存的是一個翻譯 key（例如 `TzTaipei`）而不是字面文字，`FormatLabel()` 內部呼叫 `Resources::GetInstance()->GetString($labelKey)` 取得目前語言的翻譯（可以直接這樣呼叫，因為這是後端 PHP 程式碼本身在用 `Resources`，不是透過 Smarty 樣板），32 個 key 的三語言翻譯（例如 `TzTaipei`＝「北京、新加坡、台北」／「Beijing, Singapore, Taipei」）都寫進三個 `lang/*.php`。

`GetOptions($currentValue)` 額外接受「這個使用者目前已經存的時區值」，如果不在代表清單裡（理論上的舊資料或跟清單裡某個時區偏移量相同但名稱不同的合法時區），會被附加成清單最後一個額外選項，確保「沒改時區、只改別的欄位再存檔」不會把使用者的時區資料靜默改掉；已經在清單裡的話則不會重複加一次。

串接四個地方（前三個原本就已經分別各自跑一份 `foreach ($GLOBALS['APP_TIMEZONES'] as ...)`，改成呼叫同一個 `SimplifiedTimezones::GetOptions()`）：

- `Presenters/ProfilePresenter.php`（個人帳號頁）——`$currentValue` 帶目前登入者自己的時區。
- `Presenters/RegistrationPresenter.php`（自助註冊頁）——`$currentValue` 帶 postback 後的值，因為頁面上「偵測我的時區」按鈕可能透過瀏覽器 `Intl` API 帶入任何一個真實 IANA 時區、不一定在代表清單裡；同步修正 `tpl/register.tpl` 那顆按鈕的 JS，偵測到的時區不在下拉選單裡時先動態補一個 `<option>` 再選取，不然瀏覽器會因為 `.val()` 對不到任何選項而整個顯示空白。
- `Pages/Admin/ManageUsersPage.php` 的 `ProcessPageLoad()`（管理員「新增使用者」表單，`$currentValue` 帶目前登入管理員自己的時區當預設值）與 `ShowUserUpdate()`（管理員「編輯使用者」頁，`$currentValue` 帶該使用者目前的實際時區）——這兩處原本用同一個陣列同時當 `<option>` 的 value 跟顯示文字，改成 `Timezones`（value）／`TimezoneLabels`（顯示文字）兩個平行陣列，`tpl/Admin/Users/manage_users.tpl`／`tpl/Admin/Users/user-update.tpl` 的 `{html_options}` 呼叫同步從 `output=$Timezones` 改成 `output=$TimezoneLabels`。

**順手清掉一個既有但死掉的 bug**：`ManageUsersPage.php` 原本在這兩個地方都有 `$this->Set('Languages', $GLOBALS['APP_TIMEZONES']);`——把時區清單指派給一個叫 `Languages` 的樣板變數，明顯是複製貼上打錯。確認過 `tpl/Admin/Users/manage_users.tpl`／`user-update.tpl` 兩個樣板完全沒有用到 `$Languages`（管理員新增／編輯使用者頁本來就沒有語言欄位，只有時區），這行是無害但錯誤的死程式碼，直接刪掉，沒有另外造一個真正能用的管理員語言下拉選單，因為需求裡沒有要求新增這個功能，只要求檢查既有的。

### 73.3 系統預設值

`default.timezone` 本來就是 `'Asia/Taipei'`（config.php 與 config.dist.php 皆是），符合「預設 `(GMT+08:00)`」的要求，沒有改動。`default.language` 維持 `'en_us'`——沒有被要求改動，而且已經在新的 `enabled.languages` 允許清單內，不會觸發任何 fallback 警告；實際新帳號的語言判斷邏輯（瀏覽器語言偵測優先）是更早的 §62 修的，這次沒有動。

### 驗證

獨立寫了一支測試腳本直接呼叫 `SimplifiedTimezones::GetOptions()`／`FormatLabel()`（不經過完整 app），確認：32 個代表時區全部正確輸出當下的 GMT 偏移量標籤（`America/New_York` 在測試當下——8 月，北美夏令時間中——正確顯示 `(GMT-04:00) Eastern Time`，不是寫死的 `-05:00`）；帶入一個不在清單裡的值（`Europe/Berlin`）會被正確附加一筆、且格式化正常；帶入清單裡已有的值（`Asia/Taipei`）不會重複出現。改成翻譯 key 之後，另外寫了一支測試腳本直接用反射呼叫 `en_us`／`zh_tw`／`zh_cn` 三個語言類別的 `_LoadStrings()`，確認 5 個抽樣 key（`TzTaipei`／`TzSeoul`／`TzLondon`／`TzDateLineWest`／`TzKiritimati`）在三個語言裡都有值、且是對應語言的翻譯而非英文預設值。兩支測試腳本都測完即刪除，沒有留在專案裡。容器內 `php -l` 過所有改動的 `.php` 檔案（`SimplifiedTimezones.php`／`namespace.php`／`ProfilePresenter.php`／`RegistrationPresenter.php`／`ManageUsersPage.php`／`config.php`／`config.dist.php`／三個 `lang/*.php`），皆無語法錯誤。`docker restart` 套用 opcache 與新的 `enabled.languages` 設定。

**需要使用者自己動手的部分**：`config/config.php` 沒有進版控，這次只改了本機開發環境跟 `config.dist.php`（範本），正式環境的 `config/config.php` 需要手動加上同一行 `'enabled.languages' => 'zh_tw,zh_cn,en_us',` 才會生效，這份文件沒辦法幫忙做。三種語言的實際畫面（登入頁、個人帳號頁語言/時區下拉、註冊頁、管理員新增/編輯使用者頁）與 `(GMT+08:00)` 下建立/顯示/編輯預約時間是否一致，需要使用者登入後親自逐一確認。

## 74. 週／日行事曆的預約色塊加上預約者名字

要求：色塊裡標題、時間之下再加一行預約者的名（只要 `firstName`，不要姓氏／全名／Email），粗體、跟前兩行一樣左對齊、名字前面不要圖示或空白。

`Presenters/OverviewFullCalendarEventsPresenter.php` 組 FullCalendar 事件資料時，原本只把 `GetUserName()`（`FullName`，姓名合併）放進 `extendedProps.ownerName`，這個欄位在事件小卡片（popover）裡本來就用來顯示全名，不能直接拿來用在色塊上。改成額外多送一個 `extendedProps.ownerFirstName`，值來自 `ReservationItemView::$OwnerFirstName`（這個屬性本來就有、資料庫查詢也早就撈回來了，只是先前完全沒有任何地方讀取它），跟既有的隱私規則走同一條路——使用者看不到這筆預約詳情時（`$canViewDetails` 為否），`ownerFirstName` 跟 `ownerName` 一樣回傳空字串，不會透過這個新欄位洩漏是誰訂的。

`Web/scripts/overview_fullcalendar.js` 的 `eventContent()` 在既有的 `.tt`（標題）／`.rr`（時間）之後，同一個 `arg.view.type !== 'dayGridMonth'` 判斷式裡（月檢視的方塊本來就沒有第二行時間、這次也一併不加第三行，符合「週／日行事曆」的要求範圍）多加一個 `.fn` div，`textContent` 直接設成 `extendedProps.ownerFirstName`（空字串時完全不建立這個 div，而不是建立一個空的三行）。`Web/css/overview_fullcalendar.css` 新增 `.fn` 規則：`font-weight: 700`，跟 `.tt`／`.rr` 一樣沒有額外的 icon／padding，左對齊沿用父層 `.evc` 預設的區塊排版。沿用 `.rr` 既有的 CSS container query 漸進顯示模式（事件方塊矮到放不下時間行就先隱藏 `.rr`）——三行比兩行需要更多高度，`.fn` 用一個比 `.rr` 的 32px 更高的門檻（46px）才顯示，避免方塊太矮時被截斷或跟其他行擠在一起。

### 驗證

`node --check` 過 `overview_fullcalendar.js`、容器內 `php -l` 過 `OverviewFullCalendarEventsPresenter.php`，皆無語法錯誤。跑了這支 presenter 既有的 PHPUnit 測試（16 個測試，45 個斷言），除了 1 個跟這次改動完全無關的既有失敗（`testEmptyTitleFallsBackToDefaultLabelWhenViewable`——單獨用 `--filter` 執行時 `Resources::GetInstance()->GetString('NoReservationTitle')` 沒有解析成中文字串，是這個測試在單獨執行時的資源載入問題，不是這次新增的 `ownerFirstName` 欄位造成的，diff 裡完全沒有動到標題相關的邏輯）之外全數通過。`docker restart` 套用 opcache。畫面上三行是否確實左對齊、粗體正確、矮方塊下的漸進顯示門檻是否好看，需要使用者登入後在真正的週/日行事曆上確認。

## 75. 修復多人使用測試表（GPT 產出）裡的確定失敗項目

使用者提供一份 GPT 執行的多人使用測試表（T01–T35，涵蓋併發、隱私、版本衝突、多語言、負載等面向），要求全盤了解後修補問題，並要求另外新增一份文件記錄測試內容與結果——見新增的 [COASIA-TEST-REPORT.md](COASIA-TEST-REPORT.md)，這份 CHANGES 條目只記錄程式碼層面的根因與修法，逐項的測試結果對照表在那份文件裡維護。

### 75.1 T01–T09（併發測試）、T08：根因是測試帳號不存在，不是產品 bug

專案裡本來就有一套完整的雙真實 MySQL 連線併發測試框架（`tests/concurrency/`，9 個情境，刻意獨立於 PHPUnit 之外，因為 PHPUnit 全程式在同一個 process 裡跑、只能用 FakeDatabase，沒辦法真正驗證 InnoDB row lock／transaction isolation）。GPT 執行時 9 個情境全部因為「You do not have permission to access one or more of the requested resources.」失敗——追查後發現框架寫死 `'userId' => 1`，但這個開發資料庫裡 `user_id=1`根本不存在（現存最小的是 `user_id=2`／`shawn.admin`），一個不存在的使用者對任何資源自然都沒有權限授權紀錄。

修法：`tests/concurrency/bootstrap.php` 新增 `ct_findTestUserId()`，直接查資料庫抓一個真實存在、`status_id=1`（啟用中）的使用者 id，取代寫死的 `1`；`run_concurrency_tests.php` 裡全部 20 處 `'userId' => 1` 與 `ct_session(1)` 都改成這個動態值。改完重跑，9 個情境**全數通過**——包含 T08（同一筆預約重複送出）：一筆成功、一筆正確回報 `inProgress`，資料庫裡確認只有一筆、沒有重複參考編號，證明 GPT 原本回報的「兩個請求都回傳處理中」單純是同一個權限問題的連帶效應（兩邊都還沒真正拿到 idempotency claim 就先被權限擋下，殘留的 in-progress 狀態讓兩邊都落到那個分支），底層的 `IdempotencyService` 機制本身沒有問題。

### 75.2 T10：行事曆完全沒有自動更新機制

`Web/scripts/overview_fullcalendar.js` 之前只有在「這個使用者自己」建立/編輯/刪除完預約之後才會呼叫 `calendar.refetchEvents()`，沒有任何輪詢或推播——A 訂走一個時段之後，B 的畫面要手動整理頁面才看得到。

沒有做 WebSocket／SSE 這種即時推播（範圍/成本都遠超過這次要修的問題），改成最單純的作法：分頁保持在前景時每 30 秒呼叫一次 `refetchEvents()`，另外監聽 `visibilitychange`，分頁重新變成前景時立刻補刷新一次（不用等到下一次 30 秒），背景分頁則完全不會觸發，避免不必要的請求。完全沒有動後端——events API 本來就支援隨時被重新查詢，只是之前只有使用者自己的動作會觸發而已。

### 75.3 T12：Quick Create 從來沒有真正拿到 idempotency 保護

`create.tpl`（完整表單）裡有一段註解說「Overview 頁面自己的 quick-create/quick-edit 已經在用同一套機制」，但實際去 grep 整個 `Web/` 目錄，`requestId` 只出現在 `create.tpl` 這一個地方——Quick Create（`overview_fullcalendar.js`）送出預約時的 POST body 完全沒有帶這個欄位。後端 `ReservationSavePresenter::HandleReservation()` 的 idempotency 檢查是 `if (!empty($requestId))` 才會啟動，沒收到就直接跳過，等於 Quick Create 上線以來這個保護一直是沒有生效的死角——網路逾時後重新點擊「建立」，理論上真的可能建出兩筆一樣的預約。

修法：`overview_fullcalendar.js` 新增 `generateUuid()`（跟 `create.tpl` 完全同一套邏輯：優先用 `crypto.randomUUID()`，否則手刻一個 RFC4122 風格的 fallback），在 `openQuickCreate()` 每次開啟視窗（也就是每一次新的建立嘗試）時產生一個新的 UUID 存進 `pending.requestId`，同一次嘗試在視窗開著的狀態下重送（例如逾時後又點一次建立）都會帶著同一個 id；送出時把它加進 POST body。完全沒有動後端或任何樣板——Quick Create 跟完整表單本來就打同一支 `ajax/reservation_save.php`／同一套 `ReservationSavePresenter`／同一套 `save_successful.tpl`／`save_failed.tpl`，成功重播、失敗、`inProgress`（409 + 「另一個相同的預約請求正在處理中，請稍後再試」）這幾種既有的回應情境，Quick Create 原本的 `#reservation-created`／`#reservation-failed` 偵測邏輯已經能正確處理，不用另外加程式碼。

### 75.4 T23／PHPUnit 既有 error：`ResourceCrossDayRule` 對 null 呼叫 `GetTimezone()`

`ResourceCrossDayRule::Validate()` 無條件呼叫 `$this->scheduleRepository->LoadById($reservationSeries->ScheduleId())->GetTimezone()`；但 `ScheduleRepository::LoadById()` 在找不到對應資料列時就是回傳純 `null`（不像同一個 class 裡的 `LoadByPublicId()` 用 `Schedule::Null()` 這種 null-object），一筆資源指向不存在／已刪除時間表的預約（孤兒資料，或這次測試遇到的舊測試資料）會讓這個規則直接對 `null` 呼叫方法、整個請求 500。這同時也是先前幾輪 CHANGES 裡一直提到、PHPUnit 全專案跑起來的那個既有 error 的真正根因。

修法：`$schedule` 是 `null` 時退回 `Configuration::Instance()->GetDefaultTimezone()`（這個環境是 `Asia/Taipei`）而不是直接崩潰，跨日檢查仍然會用一個合理的時區繼續執行。順便處理了 `tests/Application/Reservation/ResourceCrossDayRuleTest.php` 裡那個一直在噴 error 的舊測試——它原本要驗證「資源設定 `AllowMultiday` 時視為合法」，但這條規則在更早一輪 CoAsia 客製化（見規則本身的既有註解）就已經改成無條件都要檢查時間表時區、不再看 `AllowMultiday` 這個旗標，舊測試的 mock 完全沒有配置 `LoadById()`（因為原本的程式碼路徑根本不會走到那裡），改了程式碼之後這個舊測試就變成每次都在踩這個 null 崩潰。已經把它改寫成直接驗證這次新增的容錯行為（`testFallsBackToDefaultTimezoneWhenScheduleCannotBeLoaded`）。

### 75.5 T24：不是產品 bug，是測試自己斷言錯

`tests/Presenters/OverviewFullCalendarEventsPresenterTest.php::testEmptyTitleFallsBackToDefaultLabelWhenViewable` 斷言空標題會顯示 `'未命名預約'`，但單元測試環境裝的是 `tests/fakes/FakeResources.php`，它的 `GetString($key, $args)` 一律直接回傳 `$key . $argstring`（原始 key 本身，從來不會真的翻譯）——這個假物件的行為本來就跟整份測試檔案裡其他測試的既有慣例一致（例如同一個檔案裡 `'忙碌'` 那個斷言能過，是因為那個字串是直接寫死在 presenter 程式碼裡、根本沒有經過 `Resources`，不是同一種情況）。程式碼本身呼叫 `Resources::GetInstance()->GetString('NoReservationTitle')` 完全正確，走的是正常的三語言翻譯機制，瀏覽器裡的真實使用者看到的就是正確翻好的文字。修法：把斷言改成比對 `'NoReservationTitle'`，符合 `FakeResources` 一直以來的行為。

### 75.6 T29：舊測試沒跟上這次刻意的時區清單簡化

`tests/Presenters/RegisterPresenterTest.php::testLoadsAllTimezones` 斷言註冊頁時區下拉選單的數量要等於 `$GLOBALS[GlobalKeys::TIMEZONES]` 的完整數量（418 個）——這是 §73 刻意改掉的行為（改用 `SimplifiedTimezones::GetOptions()`，約 32 個代表時區），這個失敗完全是預期中的「測試沒跟著功能一起更新」，不是新 bug。改成斷言 `count(SimplifiedTimezones::GetOptions())`，測試名稱同步改成 `testLoadsCuratedTimezoneList` 反映新行為。

### 驗證

全部修復完成後，容器內完整跑一次 `vendor/bin/phpunit`：**1725 個測試、7744 個斷言、0 error、0 failure**（13 個既有 warning／6 個 deprecation／1 個 skipped，皆與這輪異動無關，跑之前就存在）——這是這個專案目前為止第一次全專案 PHPUnit 完全乾淨，先前每一輪 CHANGES 都還帶著「1 個既有 error + 1 個既有 failure」這句話。另外完整重跑一次 `tests/concurrency/run_concurrency_tests.php`：**9 個情境全數通過**。逐項對照表與哪些項目仍需要人工登入/多帳號/壓測工具驗證，見 [COASIA-TEST-REPORT.md](COASIA-TEST-REPORT.md)。

## 76. 月檢視補上 hover 預覽效果

使用者自評請求後，`COASIA-GOLIVE-CHECKLIST.md` 裡列了很久的「月檢視拖曳建立、hover 預覽格子：舊版有、新版目前沒做」被要求補齊。先查證這句話的前半段——實際去讀 `overview_fullcalendar.js` 的 `select` handler 才發現月檢視點擊/拖曳日期格子早就會開 Quick Create（固定預設 09:00–09:30，彈窗裡可以再調整），也早就不能拖曳跨日（跟週/日檢視共用同一個 `selectAllow`，§72 那次修好之後就統一套用到所有檢視，不分月/週/日）。回頭查舊版 `overview.tpl`／`overview.js`，發現月檢視根本是全新功能，舊版從來沒有過——這句話真正在講的其實只有「hover 預覽格子」這半段：週/日/月三種檢視的空白區塊點下去都有作用，但滑鼠移過去完全沒有任何視覺回饋，使用者不會知道這裡可以點/拖曳。

問過使用者要補哪一種（新增 hover 效果／點擊直接切到日檢視／預設時間改智慧進位，三選一），選的是新增 hover 效果。`Web/css/overview_fullcalendar.css` 新增兩條規則：月檢視 `.fc-daygrid-day-frame:hover` 與週/日檢視 `.fc-timegrid-slot-lane:hover` 都疊一層很淡的 `--primary` 色調背景（`rgba(26, 63, 115, 0.05)`）。月檢視的規則疊在 `.fc-daygrid-day-frame`（`<td>` 底下的子層）而不是 `<td>` 本身，因為今天／週日格子的背景色是直接寫在 `<td>` 上並帶 `!important`，疊在子層上可以不用跟那個 `!important` 搶優先權，還是能正常疊出 hover 色。週/日檢視的規則只套用在背景格線本身（`.fc-timegrid-slot-lane`），不會影響格線裡已經有事件方塊的部分——事件方塊完全蓋在上面，hover 色只會在事件周圍的空白處看得到。

### 驗證

純 CSS 改動，寫了一個小腳本數大括號配對確認語法沒錯（282 對全部配對）；本機沒有裝 `stylelint`（`npm install` 沒跑過），沒有跑完整的 lint。是靜態檔案，`curl` 直接確認伺服器已經在吐新內容，不需要重啟容器。畫面上的 hover 觀感（顏色深淺、跟今天/週日既有底色疊起來好不好看）需要使用者自己在瀏覽器裡確認。

## 77. Overview 行事曆會議室選單改成兩層 Accordion，重寫預設會議室邏輯

要求把左側／工具列的會議室選單，從「實體／線上」兩段式扁平清單，改成依地區分組的兩層可展開清單：台北／深圳／上海各自的實體會議室＋線上會議室（跨地區，不依地區重複顯示），並重新定義「預設會議室」的判斷順序。

### 77.1 資料模型：沿用既有 `resource_groups`，新增地區與線上兩類分組

`resource_groups`／`resource_group_assignment` 這兩張表本來就存在（`Domain/ResourceGroup.php` 的 `ResourceGroupTree`），但這個部署原本只有「台北會議室」「Video Conference」兩組、12 個資源。新增 `database_schema/coasia-resources-regions.sql`：把台北那組改名成「台北實體會議室」、Video Conference 改名成「線上會議室」，並且新增深圳（5 間：深圳／上海／首爾／台北／新加坡）、上海（4 間：上海／首爾／深圳／台北）兩個實體辦公室的會議室——這 9 間是全新資源，套用台北同名會議室的既有設定（時段表、容納人數等）當範本各自建一份獨立的 schedule/layout/resource（同一個房間名字在不同辦公室是不同的實體資源，不能共用同一張時段表）。`resources.location` 欄位另外用 `database_schema/coasia-resources-location.sql` 回填（見 §78），跟 `resource_groups` 是兩個獨立但保持一致的地區來源：前者驅動這個選單的分組顯示，後者驅動管理中心的地區篩選／排序。

`Presenters/OverviewFullCalendarPresenter.php` 原本用 `video_url` 自訂屬性把資源硬分成「physical／video」兩個扁平陣列（`SetResources($physicalResources, $videoResources, ...)`），改成改讀 `IResourceRepository::GetResourceGroups()`，依 `GROUP_DISPLAY_ORDER`（台北→深圳→上海→線上）排序後組成 `$ResourceGroups`（`SetResourceGroups($groups, $resourceColors, ...)`），每組帶 `isVideo` 旗標（沿用 `video_url` 屬性判斷，只是現在用來標記整組而不是單一資源）。`tpl/globalheader.tpl` 判斷「行事曆」導覽項目 active 狀態原本檢查 `isset($PhysicalResources) || isset($VideoResources)`，這兩個變數已經不會再被設定，同步改成檢查 `isset($ResourceGroups)`，不然這個 active 高亮會永久失效。

### 77.2 前端：Accordion 行為

`tpl/Overview/overview_fullcalendar.tpl` 的資源選單（工具列版本＋Quick Create 彈窗內的副本）都改成 `{foreach from=$ResourceGroups}` 兩層結構：第一層是地區群組（含展開/收合 toggle、數量），第二層是實際會議室清單。`Web/scripts/overview_fullcalendar.js` 新增 `toggleResourcePickerGroup()`：四個群組**預設全部收合**，點擊某一組會先把同一份清單裡其他已展開的組收合，再展開被點的這組——同一時間只能有一組是展開的（accordion 單開，不是各自獨立的展開/收合），這是使用者看過第一版（各自獨立展開）之後追加的要求。搜尋框過濾時，符合搜尋字的群組會暫時強制展開（`.force-expanded`），清空搜尋字後恢復原本的收合狀態。

### 77.3 預設會議室：四層優先序，跟展開/收合完全脫鉤

使用者明確要求：群組的展開/收合狀態，跟「目前預設顯示哪個會議室」是兩件完全獨立的事——不能因為預設會議室是線上會議室，就自動展開線上會議室那組；使用者手動展開某組之後，才會在那組裡看到打勾的目前選取項目。

`Web/scripts/overview_fullcalendar.js` 的 `resolveDefaultResourceId()` 依序判斷：① 使用者自己點過「設為預設」的會議室（新功能，見下）② 這個瀏覽器上次選過的會議室（`localStorage`，沿用既有邏輯）③ 使用者在舊版 Schedule 頁面既有的「預設 schedule」設定（`Domain/User.php` 的 `default_schedule_id`，這個部署裡每個資源對應唯一一個 schedule，所以能反查回對應的資源）④ 以上都沒有就顯示「請選擇會議室」的 placeholder，**不會**像舊版那樣自動挑第一個實體資源當預設。整個判斷鏈都不會去動任何 `.collapsed` class，符合「兩件事分開」的要求。

新增「設為預設」：資源選單每一列滑鼠移過去（或該列本身就是目前的預設）才會顯示一顆星星，點擊會呼叫新的 `Web/ajax/overview_fullcalendar_set_default_resource.php`（`Pages/Ajax/OverviewFullCalendarSetDefaultResourcePage.php` → `Presenters/OverviewFullCalendarSetDefaultResourcePresenter.php`），把選擇寫進既有的 `user_preferences` key-value 表（`Domain/Values/UserPreferences.php` 新增 `OVERVIEW_DEFAULT_RESOURCE_ID` 常數，沿用跟 LINE 綁定同一套機制，見 §（帳號綁定那次），不需要新資料表）；再點一次同一顆星星會清掉這個偏好。伺服器端會驗證這個資源使用者是否真的有權限存取（`CanAccessResource()`），拒絕偽造的資源 id。選擇「設為預設」本身**不會**連動切換目前行事曆顯示的資源、**不會**自動關閉選單——這是獨立的「釘選」動作，跟點選會議室本身（會立即切換顯示、更新行事曆、關閉選單，但不會自動變成永久預設）是兩個分開的互動。

### 77.4 選單字體與點擊範圍放大

依要求放大 `.resource-picker-group-toggle`／`.resource-picker-item`／搜尋框的字級與 padding，並把面板最小寬度從 280px 加到 320px 避免文字被截斷；`.view-picker-item`（月/週/日切換，跟這個選單共用部分 CSS class）刻意排除在外，維持原本的緊湊尺寸。

### 77.5 意外發現並修正：容器 opcache 卡舊版邏輯

實作「設為預設」時，星星點了完全沒反應——查伺服器 log（`docker logs`）才發現請求其實有正確送達後端，但噴出 `Undefined constant UserPreferences::OVERVIEW_DEFAULT_RESOURCE_ID` 500 錯誤：PHP-FPM 的 opcache 快取了新增這個常數**之前**編譯好的 `UserPreferences.php`，而先前這次工作階段所有的 CLI 診斷腳本（`php -r "..."`）都是獨立行程、不共用 opcache，一直測試正常，造成「CLI 測試過了但瀏覽器還是舊行為」的誤判——跟 §16.4 記錄過的快取地雷是同一個模式（也再次確認了那條文件裡「改完 PHP 之後要對網頁請求重啟容器，CLI 測試不算數」的教訓）。修法：`docker compose restart app`，重啟後 log 裡的 500 錯誤消失，改成正常的未登入導向。

### 驗證

容器內 `php -l` 過所有新增/修改的 `.php` 檔案、`node --check` 過 `overview_fullcalendar.js`，皆無語法錯誤。寫了多支一次性 CLI 診斷腳本（測完即刪）直接呼叫 `OverviewFullCalendarPresenter::PageLoad()`／`OverviewFullCalendarSetDefaultResourcePresenter::PageLoad()`，用真實 shawn.admin 帳號對真實開發資料庫驗證：四個分組的名稱、順序、每組會議室數量／順序都跟規格一致；建立一個一次性測試帳號（測完連同其 `user_resource_permissions`／`user_preferences` 一起刪除）驗證「設為預設」的設定/清除/越權阻擋三種情境都正確；整頁 render 測試確認 Smarty 樣板編譯無誤、accordion 的 `collapsed` class 在工具列與 Quick Create 彈窗兩份清單都正確套用（第一輪漏了 Quick Create 那份，`replace_all` 因為兩處縮排不同沒吃到第二處，事後补上）。實際畫面上的視覺呈現、hover/點擊互動手感需要使用者登入後在瀏覽器裡確認。

## 78. 管理中心新增「地區」分類，跨五個頁面＋匯入匯出

延續 §77 的地區分組概念，要求管理中心比照辦理：會議室管理、預約管理、開放時間、管理總覽與使用分析、CSV 匯入匯出都要能依地區（台北／深圳／上海／線上會議室＝跨地區）分類，沿用既有但這個部署從未使用過的 `resources.location` 欄位（LibreBooking 上游本來就有這個 varchar 欄位跟完整的 domain/repository/表單存取，只是這個 fork 一直沒有填值、沒有 UI）。

### 78.1 資料與白名單

`database_schema/coasia-resources-location.sql` 把 21 間會議室的 `location` 回填成四個固定值：實體會議室存辦公室簡稱（`台北`／`深圳`／`上海`），線上會議室存 `跨地區`——刻意用簡短值而不是「台北實體會議室」這種完整詞面，因為要直接拿來組「地區・會議室」這種配對顯示格式。新增 `Domain/Values/ResourceLocation.php` 定義這四個值的白名單常數＋`IsValid()`／`SortRank()`（依「台北→深圳→上海→跨地區」排序用），取代原本的自由輸入文字欄位——會議室管理的 drawer 就地編輯、批次編輯、CSV 匯入這三個寫入路徑都加上白名單檢查，避免手滑打錯字就在使用分析頁面憑空多一個「幽靈地區」。篩選/新增介面上的完整詞面（「台北實體會議室」「線上會議室（跨地區）」）只存在 `lang/zh_tw.php`／`lang/en_us.php` 的翻譯字串裡，不是實際存進資料庫的值。

### 78.2 逐頁改動

- **會議室管理**（`Pages/Admin/ManageResourcesPage.php`／`Presenters/Admin/ManageResourcesPresenter.php`／`tpl/Admin/Resources/manage_resources.tpl`）：工具列新增地區篩選下拉；清單依地區＋名稱排序（同地區的房間會排在一起，不再散落各處）；drawer 就地編輯、批次編輯的地區欄位從自由輸入文字改成固定四選一下拉。
- **預約管理**（`Pages/Admin/ManageReservationsPage.php`／`Presenters/Admin/ManageReservationsPresenter.php`／`lib/Application/Admin/ReservationFilter.php`）：新增地區篩選（直接在既有的 SQL filter 機制上加一個 `resources.location` 條件，跟 `resourceId`／`scheduleId` 篩選走同一套機制），會議室欄位顯示格式改成「地區・會議室」，CSV 匯出同步加地區欄。`ReservationItemView` 加一個 `$Location` 屬性（不是透過建構子，是查完之後在 presenter 端用 resourceId 對照表補上去——這個類別在整個專案裡被非常多其他地方共用，不想動共用的 SQL/建構子簽章）。地區篩選刻意**不**存進既有的 `ReservationFilterPreferences`（跨頁面記住上次篩選條件的機制）——每次都從網址參數重新讀，不像 resourceId/scheduleId 那樣跨次造訪還會記得。
- **開放時間**（`Presenters/Admin/ManageSchedulesPresenter.php`／`tpl/Admin/Schedules/manage_schedules.tpl`）：這頁原本沒有任何篩選工具列，維持現狀，只在「資源」欄位每個會議室名稱前面加地區標籤；列表排序也改成依地區（每個資源對應唯一一個 schedule，用這個資源的 `location` 決定 schedule 的排序位置）。
- **管理總覽**：「即時會議室狀態」清單本來就已經 `ORDER BY r.location, r.name`，只是沒有視覺分組，加上地區小標題（`admin-list-group-label`）。
- **使用分析**：這頁其實原本就是地區感知的分析頁（含 Excel 匯出），地區篩選、依地區統計、Excel 分頁都已經存在；唯一的改動是把「動態抓資料庫裡有哪些 DISTINCT location」改成固定的四值白名單，避免舊的自由輸入髒資料污染統計結果。
- **CSV 匯入匯出**：`resources_csv.tpl`／`import_resource_template_csv.tpl`／`ResourceImportCsv.php` 這幾個地方其實老早就支援 `location` 欄位（LibreBooking 上游原生功能），這次只補上前述的白名單驗證。

「熱門會議室排行」（管理總覽）、「依會議室／依地區使用量排行」（使用分析）這幾個依使用量高低排名的清單刻意不改成地區優先排序——這些本來就是排行榜，不是單純的清單，硬套地區排序會讓排名失去意義。

### 78.3 追加要求：全站排序統一改成地區優先

使用者用一張「開放時間設定」的實際截圖回報：同名但不同辦公室的會議室（例如三個都叫「上海 Shanghai」但分屬台北／深圳／上海辦公室）因為清單依名稱字母排序而交錯出現，難以辨識。要求全部排序都改成地區優先、同地區內才依名稱排序。除了 §78.2 已經處理的會議室管理／開放時間，另外補上：預約管理的會議室篩選下拉、使用分析的會議室篩選下拉（原本 `ORDER BY name`，改成抓回 `location` 之後在 PHP 端用 `ResourceLocation::SortRank()` 排序，不直接下 `ORDER BY location`，因為 MySQL 預設 collation 不保證四個地區字串會照「台北→深圳→上海→跨地區」這個語意順序排）、會議室管理批次編輯的「移至時間表」下拉（時間表名稱其實就是房間名稱，有一樣的交錯問題，改成先查每個 schedule 對應資源的地區再排序）。

### 驗證

容器內 `php -l` 過全部新增/修改的 `.php` 檔案，皆無語法錯誤。用真實 shawn.admin 帳號、真實開發資料庫，寫多支一次性 CLI 診斷腳本（測完即刪）直接 render 五個頁面（會議室管理、預約管理、開放時間、管理總覽、使用分析），確認：地區篩選下拉存在且選項正確、篩選後的資料筆數符合預期（例如預約管理篩選「深圳」只回傳深圳資源的預約，用真實 141 筆預約資料驗證台北/跨地區兩種篩選互不污染）、開放時間頁改版後清單順序確認四個地區完全不再交錯（原本一輪測試漏了驗證這點，使用者用截圖回報後才補測）、預約管理與使用分析的資源下拉都確認台北七間排在最前面、批次編輯的時間表下拉同樣確認排序正確。第一輪 render 測試也意外抓到一個真的 bug：Quick Create 彈窗的資源清單漏了套用 accordion 預設收合（§77 的 `replace_all` 只吃到工具列版本），當場補上。第一次驗證預約管理排序修正時，測試腳本因為猜錯 `ManageReservationsPage` 建構子相依（跳過 `SecurePage` 的認證流程手動組 presenter）導致完全沒有輸出、誤判成錯誤，改用更輕量、只測核心 SQL filter 邏輯的方式重測才抓到問題其實出在測試腳本本身，不是產品程式碼。實際畫面上的視覺呈現需要使用者登入後在瀏覽器裡確認。

## 79. Overview 會議室選單比照設計稿：地區配對標籤、開啟時自動展開目前選取的群組、選中列淡色背景

使用者附了一張選單示意圖，要求把工具列的會議室選單（§77 那次改成的兩層 accordion）補齊四個尚未做到的細節：①觸發按鈕的文字要顯示「地區 · 會議室」（例如「上海 · 首爾 Seoul」），不能只顯示房間名稱；②每次打開選單，要自動展開「目前選取的會議室」所在的那個地區群組；③其餘群組維持收合，同一時間仍然只能有一組展開（沿用 §77 既有的 accordion 規則，不變）；④選單清單裡目前選中的那一列要有淡色背景，不能只靠打勾符號辨識。搜尋、彩色圓點、「設為預設」星星這三個既有功能維持不動。

### 79.1 地區配對標籤：用會議室自己的 `location`，不是群組名稱

一開始考慮直接拿 `resourceGroups[].name`（例如「上海實體會議室」）去掉「實體會議室」字尾湊出「上海」，但這個群組名稱是 §77 的 `resource_groups` 分組概念，跟 §78 新增、專門給「地區・會議室」配對顯示用的 `resources.location` 欄位（`Domain/Values/ResourceLocation.php` 四值白名單：台北／深圳／上海／跨地區）其實是兩個獨立但目前剛好一致的地區來源——直接沿用群組名稱等於在 JS 端重新發明一次已經在 §78 做好的簡短地區標籤，兩處未來萬一分家（例如同一地區群組底下混進另一地區的資源）也會跟著錯。改成在 `tpl/Overview/overview_fullcalendar.tpl` 新增一份 `opts.resourceLocations`（`{resourceId: 資源自己的 location}`），跟既有的 `resourceNames`／`resourceColors` 同樣的建構方式；`Web/scripts/overview_fullcalendar.js` 新增 `resourcePickerTriggerLabel()`，優先組成「`location` · `name`」，沒有 `location`（尚未分類的資源）就退回單純房間名稱。

### 79.2 開啟選單時自動展開目前選取項目所在的群組

§77 把「展開/收合狀態」跟「目前預設顯示哪個會議室」刻意設計成互不連動（見 §77.3），這個要求延續同一個原則、只是換了觸發時機：不是「選了某個預設就自動展開」，而是「每次打開選單這個動作本身，都重新計算一次目前的選取項目在哪個群組、展開那組」——選取狀態改變本身仍然不會主動展開/收合任何東西,只有「打開選單」這個動作會。新增 `expandResourcePickerGroupForSelection($list, resourceId)`：先把清單裡所有群組收合，再找出包含 `[data-resource-id=resourceId]` 的那個群組展開；`resourceId` 是 `null`（尚未選擇會議室，§77 優先序第四層）或指向清單裡不存在的資源時，`$target` 篩不出任何群組，結果就是四組全部收合，同時滿足「沒有選取會議室時，四個群組才全部收合」這個條件。工具列選單、Quick Create 彈窗各自的 `open()` 都呼叫這個函式（分別帶入 `selectedResourceId`／`pending.resourceId`），沿用同一份 accordion 規則，避免兩份清單的展開邏輯各寫一次、日後改一邊漏改另一邊。

### 79.3 選中列淡色背景

`Web/css/overview_fullcalendar.css` 原本 `.resource-picker-item.selected` 只有變色＋加粗文字，新增背景色沿用這個檔案裡其他「選中/focus」狀態同一套 `color-mix(in oklab, var(--primary) 12%, white)` 公式（例如 Quick Create 日期輸入框 focus 狀態），跟 `.view-picker-item.active`（月/週/日切換，共用部分基底樣式但沒被要求加背景）分開成獨立規則，避免誤傷後者。

### 79.4 意外發現並修正：`$resource->GetLocation()` 呼叫在一個不是 `BookableResource` 的物件上

第一輪改完，使用者實際登入瀏覽器測試，畫面直接噴「未知錯誤」。查容器 log（`docker compose logs app`）抓到真正的例外：`Call to undefined method ResourceGroupAssignment::GetLocation()`。原因是 79.1 想錯了資料型別——`tpl/Overview/overview_fullcalendar.tpl` 的 `{foreach from=$group.resources item=resource}` 裡，`$resource` 其實是 `Domain/ResourceGroup.php` 的 `ResourceGroupAssignment`（`OverviewFullCalendarPresenter::PageLoad()` 呼叫 `IResourceRepository::GetResourceGroups()`／`BuildResourceGroupTree()` 專門給群組樹狀結構用的輕量投影類別，只帶 color／schedule／admin-group 這些欄位），不是完整的 `BookableResource`，本來就沒有 `GetLocation()` 這個方法可以呼叫。

修法：不去動 `ResourceGroupAssignment`（上游共用類別，牽動的呼叫點太多，風寫不划算），改成在 `OverviewFullCalendarPresenter` 新增 `GetResourceLocations()`：另外呼叫一次 `IResourceRepository::GetResourceList()`（拿到真正的 `BookableResource[]`，才查得到 `GetLocation()`），組出一份 `resourceId => location` 對照表，`PageLoad()` 把這份表透過 `SetResourceGroups()` 傳給頁面（新增 `$resourceLocations` 參數，`IOverviewFullCalendarPage` 介面、`OverviewFullCalendarPage::SetResourceGroups()` 同步更新簽章）。`.tpl` 改成查 `$ResourceLocations[$resource->GetId()]`，跟原本 `$ResourceColors[$resource->GetId()].bg` 同一套查表模式，不再假設 `$resource` 身上有完整的 domain 方法可呼叫。

### 79.5 追加需求：會議室改用固定地區色系，不再每間不同色

使用者反應會議室數量變多之後，每間各自一個顏色（`FALLBACK_COLORS` 依索引輪流指派）畫面太雜亂，要求改成依地區固定一種主色：台北＝藍色、深圳＝紫色、上海＝橘色、線上會議室＝青綠色，同地區的會議室共用同一個主色；選取狀態不能只靠圓點顏色判斷，要維持 79.3 已經做的淡色背景＋打勾符號＋（`var(--primary)`）較深文字這三個線索；工具列選單目前選取的會議室、選單清單裡的圓點、行事曆事件本身的顏色，這三處要保持一致。

發現「保持一致」這個要求牽涉到兩個各自獨立的顏色指派邏輯，不是改一個地方就好：`OverviewFullCalendarPresenter::AssignColors()` 驅動選單（觸發按鈕圓點、清單圓點），`Presenters/OverviewFullCalendarEventsPresenter.php`（`Web/ajax/overview_fullcalendar_events.php` 的 JSON feed，行事曆事件本身的來源）有自己另一份幾乎一樣但獨立維護的 `AssignColors()`／`FALLBACK_COLORS`，兩邊各自照自己的資源順序輪色——這是 §77 那次評估階段刻意留下的重複（doc comment 當時寫「Kept identical... so all three surfaces agree on colors during the side-by-side evaluation」），只要其中一份漏改，選單跟行事曆事件的顏色就會兜不起來。

不想把同一組色碼在兩個 presenter 裡各複製一份（未來只改一邊、兩邊分家的風險太高），改成在 `Domain/Values/ResourceLocation.php`（§78 已經是台北／深圳／上海／跨地區四值白名單的唯一權威來源）新增 `Color($location)` 靜態方法，回傳固定色碼（`#039be5` 藍／`#8e24aa` 紫／`#f4511e` 橘／`#00897b` 青綠，未分類則回傳中性灰 `#616161`），兩個 presenter 都改成呼叫這一個方法：

- `OverviewFullCalendarPresenter::AssignColors()` 簽章從「依 `$resources` 陣列的索引輪 `FALLBACK_COLORS`」改成吃 79.4 剛做好的 `$resourceLocations` 對照表，逐一資源查 `ResourceLocation::Color()`；`FALLBACK_COLORS` 常數整個刪除。
- `OverviewFullCalendarEventsPresenter::AssignColors()` 原本會優先採用會議室自己在管理中心設定的自訂顏色（`$resource->HasColor()`／`GetColor()`），沒設定才退回 `FALLBACK_COLORS` 輪色——這次改成不管有沒有自訂顏色都一律用地區固定色，因為這裡的資源本來就是完整 `BookableResource`，直接呼叫 `$resource->GetLocation()` 即可，不需要像 79.4 那樣另外查表；`FALLBACK_COLORS` 常數同樣整個刪除。

事件本身的淺色底、深色邊框（`PastelColor()` 把地區色跟白色以 7:3 混色）邏輯本身沒有變，只是輸入的 `$colors['bg']` 現在來自固定地區色而不是輪替色，效果自然跟著換成地區配色。選單圓點（`.resource-picker-dot` 的 `--dot-color`）跟事件顏色都讀同一個 `opts.resourceColors`／`AssignColors()` 家族，本來就是同一份資料流出來的，不需要另外改前端程式碼。

### 驗證

容器內 `php -l` 過這次修改到的四個 `.php` 檔案（`Domain/Values/ResourceLocation.php`／`Presenters/OverviewFullCalendarPresenter.php`／`Presenters/OverviewFullCalendarEventsPresenter.php`／`Pages/OverviewFullCalendarPage.php`），`node --check` 過 `overview_fullcalendar.js`，皆無語法錯誤；`grep` 全專案確認沒有其他地方還在引用剛刪掉的 `OverviewFullCalendarPresenter::FALLBACK_COLORS`／`OverviewFullCalendarEventsPresenter::FALLBACK_COLORS`。跑 `OverviewFullCalendarEventsPresenterTest`（16 個測試，涵蓋權限過濾／隱私遮蔽／編輯權限／`mineOnly` 等既有案例）全部通過，確認拿掉 `HasColor()`／`GetColor()` 分支沒有動到事件本身的其他欄位；再跑整個 `presenters` test suite（375 個測試）確認沒有連帶弄壞其他頁面，唯一一個失敗（`ManageReservationsPresenterTest::testUsesTwoWeekSpanWhenNoDateFilterProvided`）跟這次改動的檔案無關（這次完全沒碰 `ManageReservationsPresenter.php`），是既有的、與本次修改無關的既存問題。79.4 的 bug 修完後清了 `tpl_c/` 快取、重啟開發容器（本機開發容器 opcache `validate_timestamps=0`，改完 PHP 一定要重啟容器才會反映在瀏覽器），麻煩使用者重新整理瀏覽器確認：①選單觸發按鈕文字有地區前綴且不再報錯、②打開選單自動展開目前選取的群組、③選中列有淡色背景，以及這次新增的④會議室依地區顯示固定主色（台北藍／深圳紫／上海橘／線上青綠）、⑤選單圓點與行事曆事件顏色一致。

## 80. 移除逐間會議室的星星，改成選單底部單一「設為預設會議室」按鈕，新增第一次登入預設值

使用者附了同一張選單示意圖，要求把 §77 那次做的「每間會議室各自一顆 hover 才出現的星星」整個換掉：①移除所有會議室右側的星星；②選單底部（清單捲動區域之外）新增單一「設為預設會議室」按鈕，作用對象是目前選取／顯示在行事曆上的那間會議室；③設定成功後按鈕文字換成「✓ 目前預設會議室」，同時跳出成功提示（toast）；④第一次登入（使用者沒有設過預設、瀏覽器也沒有選過任何會議室、也沒有舊版「預設時間表」設定）預設選擇「台北 · 台北 Taipei」，取代原本「顯示請選擇會議室」的空白狀態；⑤優先順序改成三層明講＋一層隱含：使用者預設 → 上次選擇 → 系統預設（③④都延續 §77.3 已經定案、經使用者驗證過的四層鏈，只是這次多講了一次前三層、外加把原本「無 → 顯示 placeholder」的第四層換成有東西可以退回的「台北 · 台北 Taipei」）；⑥一般點選會議室（切換行事曆顯示）不能改變永久預設，只有按下底部按鈕才會寫入；⑦同一地區的會議室維持 §79.5 剛做好的固定色系（本節沒有再改動這部分，純粹重申需求沒有被這次改動破壞）。

### 80.1 移除星星、扁平化清單項目

`tpl/Overview/overview_fullcalendar.tpl` 原本每間會議室是 `.resource-picker-item-row` 包住兩個並排 `<button>`（選取用的 `.resource-picker-item` ＋ 設為預設用的 `.resource-picker-item-star`，`<button>` 不能互相巢狀所以用並排寫法）——這次拿掉整個 `.resource-picker-item-star` 按鈕跟它的 wrapper，`.resource-picker-item` 直接變回清單項目本身（跟 Quick Create 彈窗裡本來就沒有星星的那份清單同一種寫法）。`Web/scripts/overview_fullcalendar.js` 同步移除 `.resource-picker-item-star` 的點擊委派、`filterResourcePickerList()` 裡「隱藏整個 `.resource-picker-item-row` 而不是只隱藏按鈕本身」那段特判（不再需要，改成直接隱藏 `.resource-picker-item`）；`Web/css/overview_fullcalendar.css` 移除 `.resource-picker-item-row`／`.resource-picker-item-star` 相關規則。

### 80.2 選單底部的單一按鈕

在 `#resourcePickerList`（可捲動清單）之後、`#resourcePickerPanel` 內新增一個 `.resource-picker-footer` 區塊，放單一 `#resourcePickerSetDefault` 按鈕——是清單的**同層兄弟元素**，不在捲動區域裡面，捲再多會議室都固定釘在面板最下方。`overview_fullcalendar.js` 新增 `updateResourcePickerDefaultButton()`（取代舊的 `updateResourcePickerStars()`）：依「目前選取的 `selectedResourceId` 是否等於 `opts.explicitDefaultResourceId`」決定按鈕文字／圖示／`.is-default` 樣式，沒有選取任何會議室時整顆按鈕 `disabled`。`setResourcePickerDefault()` 沿用 §77.3 就有的既有端點（`ajax/overview_fullcalendar_set_default_resource.php`）跟「再點一次同一間會自動取消」的 toggle 行為，只是呼叫端從「這一列的 `data-resource-id`」改成永遠是 `selectedResourceId`（按鈕不再跟著清單裡某一列走，而是固定對應「目前顯示在行事曆上的房間」）。

### 80.3 成功提示（toast）

`tpl/globalfooter.tpl`（每一頁都會 include 的共用頁尾，`window.showToastById()` 這個共用 helper 定義在同樣每頁都載入的 `Web/scripts/phpscheduleit.js`）新增一個 `#resourcePickerDefaultSetToast`，跟既有的 `#defaultSetToast`（舊版「預設時間表」用，文案是「這是你預設的時間表」，語意是時間表不是會議室，故意沒有直接重複使用免得文不對題）用同一套 Bootstrap Toast 標記／CSS class。新增語言字串 `DefaultResourceSet`（zh_tw「這是你預設的會議室」／en_us "This is now your default meeting room"，跟 `DefaultScheduleSet` 同樣的措辭風格，`lang/zh_tw.php`／`lang/en_us.php` 一起改）、`CurrentDefaultResource`（按鈕在「已是預設」狀態下的可見文字「目前預設會議室」，前面另外接一個 `bi-check-lg` icon 湊出「✓ 目前預設會議室」）；把舊的 `SetAsDefaultResource` 字串內容從短版「設為預設」（星星 tooltip 用）直接改成使用者這次要求的完整版「設為預設會議室」（星星整個刪掉了，這個 key 現在只剩底部按鈕在用，改內容不會影響到別的地方）；`RemoveDefaultResource`（「取消預設」）維持原樣，繼續當作「已是預設」狀態下按鈕的 `title`/`aria-label`（可見文字是「✓ 目前預設會議室」，但滑鼠移過去或螢幕報讀器讀到的說明文字講「再點一次會取消」）。JS 端只有在「設定成功」這個方向（`isSetting === true`）才彈 toast，取消預設不彈——照原始需求「設定成功後顯示...與成功提示」字面上只講了設定這個方向。

### 80.4 第一次登入預設「台北 · 台北 Taipei」

新增 `OverviewFullCalendarPresenter::ResolveFirstLoginDefaultResourceId()`，作為 §77.3 四層優先序的第四層——只有在使用者沒有設過「設為預設」、瀏覽器 localStorage 沒有歷史選擇、也沒有舊版「預設時間表」設定（三層都落空）時才會用到，實務上就等於「這是一個全新使用者」。這個房間刻意**不是**硬寫死一個 resource id（換一個部署、或這行資料被刪掉重建，id 會變），而是用「名稱 `台北 Taipei` ＋ 自己的 `location` 是 `台北`」兩個條件一起比對——因為 `database_schema/coasia-resources-regions.sql` 讓每個辦公室群組底下都各自有一間同名的「台北 Taipei」房間（深圳／上海群組裡也各有一間叫這個名字的房間，只是屬於那個辦公室），只比對名稱會抓錯間；查不到就回傳 `null`，`overview_fullcalendar.js` 的 `resolveDefaultResourceId()` 第四層就跟以前一樣直接落空，顯示「請選擇會議室」placeholder，不會噴錯。`IOverviewFullCalendarPage::SetResourceGroups()`／`OverviewFullCalendarPage::SetResourceGroups()` 簽章加一個參數把這個 id 傳給樣板，`.tpl` 比照既有的 `ExplicitDefaultResourceId`／`SystemDefaultResourceId` 寫法多加一行 `firstLoginDefaultResourceId`。

### 80.5 意外發現並修正：點會議室會直接收合選單，來不及按「設為預設」

80.2 做完，使用者實際操作後回報一個真的很直覺的問題：清單裡的 `.resource-picker-item` 點擊處理常式沿用了 §77 以來就有的「選完立刻 `close()`」行為（跟 view-picker／scope-picker 那兩個單選下拉選單一樣的既有慣例）——這代表使用者想「點一間會議室、順便把它設為預設」時，選單在點下去那一瞬間就收合了，底部的「設為預設會議室」按鈕根本來不及點到，得先關掉再重新打開選單才按得到，等於這次新做的兩步驟流程（選會議室→設預設）在同一次開啟裡完全走不通。

修法：拿掉工具列這份清單（`#resourcePickerList`，`initResourcePicker()` 裡）點擊項目時的 `close()`，讓選單在選完會議室後留在原地——checkmark／底部按鈕文字（可能還沒是預設）都會立刻更新，使用者可以緊接著按下同一個已經開著的選單裡的「設為預設會議室」，不用重新打開。選單還是會在點擊外部（既有的 `closeAllDropdowns()`）或再點一次觸發按鈕時關閉，只是不再「選完自動關」。刻意只改這一份清單——Quick Create 彈窗裡的另一份 `.resource-picker-item` 清單（`#qcResourcePickerList`）沒有「設為預設」這個後續動作，維持點選即關的原行為；view-picker／scope-picker 這兩個單選下拉也維持原行為，避免這次修法波及不相關的既有互動。

### 驗證

容器內 `php -l` 過改動到的四個 `.php` 檔案（`Presenters/OverviewFullCalendarPresenter.php`／`Pages/OverviewFullCalendarPage.php`／`lang/zh_tw.php`／`lang/en_us.php`），`node --check` 過 `overview_fullcalendar.js`，皆無語法錯誤；`grep` 全專案確認 `.resource-picker-item-star`／`.resource-picker-item-row`／`updateResourcePickerStars` 三個名字在 `.js`／`.css` 裡都已經不存在，沒有殘留的死引用；另外確認 QC 彈窗（`#qcResourcePickerList`）跟 view-picker／scope-picker 三處的 `close()` 呼叫都還在，80.5 只改到工具列那一份清單。寫了一支一次性 CLI 診斷腳本（測完即刪，跟 §77/§78 用的是同一種手法——直接組 `OverviewFullCalendarPresenter` 帶一個假的 `IOverviewFullCalendarPage` 去接 `SetResourceGroups()` 的參數，不經過 `SecurePage` 的 HTTP 認證流程），用真實 shawn.admin 帳號、真實開發資料庫呼叫 `PageLoad()`：確認四個分組筆數正確（台北 7／深圳 5／上海 4／線上 5）、`resourceColors` 逐一資源查出來的色碼跟地區完全對應（上海四間 `#f4511e`、台北兩間 `#039be5`，抽樣印出來人工比對）、`firstLoginDefaultResourceId` 正確解析成 resource_id 1（名稱「台北 Taipei」、`location` 為「台北」），沒有拋出例外。額外用一個不需要登入的頁面（登入頁本身，`http_status=200`）驗證這次改到的 `tpl/globalfooter.tpl`（每一頁都會 include 的共用頁尾）加了新的 toast 標記後沒有連帶弄壞全站共用樣板——這個檔案改錯的影響範圍是整個網站，特別多測一次。手上依然沒有這個開發資料庫裡任何帳號的密碼，這次同樣沒能直接登入瀏覽器操作按鈕點擊/toast 彈出的實際畫面效果，麻煩使用者重新整理瀏覽器確認：①星星都不見了、②選單最下方有「設為預設會議室」按鈕、③點擊後文字變成「✓ 目前預設會議室」並跳出成功提示、④切換到別的會議室看行事曆不會動到這個永久預設、⑤（需要一個全新測試帳號才能看到）第一次登入直接顯示「台北 · 台北 Taipei」而不是空白的請選擇會議室、⑥點會議室之後選單不會自動收合，可以緊接著按底部的設為預設按鈕。

## 81. 整理 AD 登入流程：緊急管理員旗標、Session 撤銷、AD 帳號狀態回收、部門同步、復職重啟

使用者要求把 AD 登入流程整理成 9 條行為規格。開工前先做了一輪唯讀調查（直接讀 `plugins/Authentication/ActiveDirectory/` 全部原始碼＋`lib/Application/Authentication/Registration.php`＋`Pages/Page.php` 的認證流程），發現：IT 建帳號後直接登入、首次登入自動建檔＋一般權限、AD 密碼改了立刻生效、AD 只管身分驗證（LibreBooking 管授權）這四條**現況已經成立**，不用動；真正缺的是帳號狀態同步（AD 停權/離職→LibreBooking 沒有任何機制會停用）、Session 撤銷（整個系統目前完全沒有「強制登出」）、部門同步（只有姓名/Email 會同步）、復職重啟（不會建重複帳號，但也不會自動恢復）。更嚴重的是**正式環境當下的 `ActiveDirectory.config.php` 有一個活著的安全漏洞**：`database.auth.when.ldap.user.not.found=true` 是全域、不分帳號的開關，任何有本機密碼的帳號在 AD 連不上、或這次登入被 AD 拒絕時都能繞過去，不是只限緊急管理員。

範圍偏大，先進 plan mode 完整規劃過一輪，使用者中途一度覺得範圍太大想先擱置（那一輪只做了唯讀調查，沒有動任何檔案），後來決定繼續，並拍板三個關鍵設計：**本機緊急管理員＝新增使用者專屬旗標**（不是全域 config 開關）、**Session 撤銷＝輕量版強制重新登入**（不做完整 session store）、**部門同步＝抓 AD 自己的 `department` 屬性**（不是沿用既有但完全獨立、不在登入時跑的 HR 匯入腳本）。

### 81.1 資料庫：`users` 表新增四個欄位

`database_schema/upgrades/4.5/schema.sql`（比照 4.2 的 `information_schema` 冪等寫法，`phing-tasks/UpgradeDbTask.php` 每次都會重跑整個 upgrades 目錄，這裡本機直接用 `mysql` 灌進開發資料庫驗證過冪等性——重複執行第二次全部變成 `SELECT 1` no-op，沒有報錯）：

- `is_local_auth_only`：本機緊急管理員旗標。
- `is_ad_managed`：這個帳號有沒有真的透過 AD 同步過——取代原本只有 HR 匯入腳本會設、AD 登入本身完全沒設的舊版「AD Managed Account」custom attribute（涵蓋不完整，漏掉直接靠首次 AD 登入建檔的帳號），改成 `ActiveDirectory::Login()` 每次成功同步都補上，第 5 節的回收 job 靠這個欄位篩選範圍。
- `department`：AD 的 `department` 屬性。
- `credentials_invalidated_at`：Session 撤銷用的時間戳，塞進 `Domain/User.php` 的 `Deactivate()` 內部——所有會呼叫 `Deactivate()` 的地方（既有的手動停用、第 4 節新的回收 job）都自動連帶撤銷 Session，不用每個呼叫端各自記得設。

`Domain/User.php`（`IsLocalAuthOnly()`／`ChangeIsLocalAuthOnly()`、`IsAdManaged()`／`MarkAsAdManaged()`、`CredentialsInvalidatedAt()`、`UserAttribute::Department` 常數、`FromRow()` 補讀四個新欄位）、`lib/Database/Commands/ColumnNames.php`／`ParameterNames.php`（四個新常數）、`Domain/Access/UserRepository.php`（`Add()`／`Update()` 串接，新增 `GetCredentialsInvalidatedAt($userId)` 給第 4 節用的輕量單欄位查詢）都跟著改。刻意**沒有**把三個旗標型欄位加進 `UpdateUserFromLdapCommand`／`Queries::UPDATE_USER_BY_USERNAME`（AD 同步專用的窄 UPDATE，只加了 `department`）——這條路徑本來就不該去動旗標，旗標由 `ActiveDirectory::Login()` 自己的程式碼路徑管理，維持「AD 同步管不到這些」的既有邊界（這條 UPDATE 本來就沒有動 `status_id`，同一個道理）。

`ChangeAttributes($phone, $organization, $position, $department = null)` 這個既有方法要小心一個坑：如果單純把新參數的預設值設成 `null`，現有那些還在用 3 個參數呼叫它的地方（個人帳號自己編輯、CSV 匯入）每次存檔都會把 `department` 靜默清空——改成用 `func_num_args() >= 4` 判斷第 4 個參數有沒有真的被傳進來，沒傳就完全不動 `department` 這個欄位的既有值。

### 81.2 `ActiveDirectory::Validate()`：全域 fallback 換成逐帳號旗標

`plugins/Authentication/ActiveDirectory/ActiveDirectory.php` 新增 `GetUserRepository()`（照抄既有 `GetRegistration()` 的懶初始化寫法）。`Validate()` 一進來先用 `LoadByUsername()` 查本機帳號（查不到會拿到安全的 `User::Null()`，`IsLocalAuthOnly()` 自然是 `false`，不會擋到全新 AD 員工的第一次登入）：有 `is_local_auth_only` 旗標的帳號完全跳過 LDAP、直接查本機密碼；其餘帳號拿掉原本的兩個 `RetryAgainstDatabase()` fallback 分支，LDAP 連不上或帳密被拒絕一律直接 `return false`（fail closed），不再退回本機密碼。副作用：原本「連不上又沒開 fallback 就丟出未捕捉 Exception」的當機路徑（§26.4 記錄過的舊 bug）也一併消失，改成單純回傳 `false`。`ActiveDirectoryOptions::RetryAgainstDatabase()`／`database.auth.when.ldap.user.not.found` 這個 config key 沒有刪，只是不再被 `Validate()` 讀取，`.config.dist.php` 補了註解說明已被取代。

`Synchronize()` 裡「要不要把使用者剛打的密碼鏡射進本機 DB」的判斷（原本也綁在 `RetryAgainstDatabase()` 上）拿掉條件式，改成永遠用隨機密碼——AD 帳號現在不可能再走本機密碼 fallback，繼續鏡射真實密碼進本機 DB 已經沒有意義，純粹是白白多一個曝險面（本機 DB 外洩＝AD 密碼外洩）。

### 81.3 復職重啟＋標記 AD 管理，合併成一次登入後管家

`Login()` 在 `Synchronize()` 之後、真正呼叫 `authToDecorate->Login()` 之前，新增 `ReactivateAndMarkAsAdManaged()`：`StatusId() == INACTIVE` 才 `Activate()`（刻意只判斷 INACTIVE，不是「!= ACTIVE」，避免連帶把 `AWAITING_ACTIVATION` 的帳號也啟用，比照的是 §77.3 那套四層優先序的精神，不是無條件比照 `plugins/Authentication/Ldap/Ldap.php` 那種無條件 Deactivate 再 Activate 的寫法）；`!IsAdManaged()` 才 `MarkAsAdManaged()`。兩件事合併成一次 load/mutate/update，不讓一次登入多打兩次資料庫。

### 81.4 部門同步：整條鏈比照 organization/position 現有寫法

`ActiveDirectoryUser.php`（新增 `department` 屬性＋`GetDepartment()`）→ `ActiveDirectoryOptions::AttributeMapping()`（預設 attribute 陣列補 `department=department`）→ `ActiveDirectory::Synchronize()`（`AuthenticatedUser` 建構子多帶一個參數）→ `AuthenticatedUser.php`（新增 `$department` 屬性＋`Department()`）→ `Registration::Synchronize()`（update 分支的 `UpdateUserFromLdapCommand`、insert 分支的 `$additionalFields` 都補上）→ `Registration::Register()`（`ChangeAttributes()` 呼叫補第 4 個參數）。全程沒有新機制，純粹是姓名/Email/組織/職稱這條既有管線多接一個欄位。

### 81.5 後台使用者管理介面

`tpl/Admin/Users/user-update.tpl` 新增「部門」文字欄位（照抄旁邊既有的組織/職稱欄位）跟「本機專屬登入（緊急管理員）」checkbox（照抄 `tpl/Admin/Resources/manage_resources.tpl` 的 `requiresApproval` checkbox 寫法，`{if $User->IsLocalAuthOnly()}checked="checked" {/if}` 這個條件式寫法照抄 `tpl/Controls/Attributes/Checkbox.tpl` 既有的自訂屬性 checkbox）。`Pages/Admin/ManageUsersPage.php`／`lib/Server/FormKeys.php` 補對應的存取方法／表單鍵。`Presenters/Admin/ManageUsersPresenter.php::UpdateUser()` 的 `$extraAttributes` 陣列多塞一個 `UserAttribute::Department`；本機專屬登入旗標新增 `ApplyLocalAuthOnly()`，完全比照既有的 `ApplyRole()`（各自獨立 load/mutate/update，不是塞進同一個 `ManageUsersService::UpdateUser()` 呼叫）。這裡抓到一個原本規劃漏掉的細節：`ManageUsersService::UpdateUser()` 內部的 `ChangeAttributes()` 呼叫本來就只帶 3 個參數，即使外面的 `$extraAttributes` 陣列多塞了 `department`，不改這裡的呼叫依然不會真的存進去——寫程式當下對照原始碼才發現，補上了第 4 個參數。

### 81.6 Session 撤銷：`Page::IsAuthenticated()` 加一次輕量查詢

`lib/Application/Authentication/Authentication.php` 登入時本來就會把 `LoginTime`（登入當下的時間字串）快取進 `UserSession`，只是從來沒被拿來做任何驗證用途。`Pages/Page.php::IsAuthenticated()` 原本只信任 `$_SESSION` 裡快取的 `UserSession` 物件、完全不查資料庫，這是這個 codebase 第一次在「已登入請求」的路徑上加一次資料庫查詢——改成先做原本的檢查，再用新增的 `UserRepository::GetCredentialsInvalidatedAt($userId)`（單欄位、PK 查詢，不是整個 `User::FromRow()` hydrate）跟 `LoginTime` 比較，`credentials_invalidated_at` 比登入時間晚就視為未登入，強制重新登入。`Domain/User.php::Deactivate()` 已經在 81.1 保證每次停用都會設這個時間戳，這層完全不用另外呼叫任何東西——手動停用、AD 回收 job 停用，都會自動撤銷已經開著的瀏覽器 Session。`Pages/Page.php` 頂部補了 `require_once(ROOT_DIR . 'Domain/Access/namespace.php');`，不依賴「剛好某個 Page 子類別自己有 require 到」這種不保證成立的隱含關係。

### 81.7 新增 AD 帳號狀態回收 job

AD 是 bind 式整合，不會主動推事件過來，只能定期反查，這是唯一沒辦法用「登入當下順便做」解決的部分。`plugins/Authentication/ActiveDirectory/IActiveDirectory.php` 新增 `GetAccountStatus($username)` 介面方法＋`AdAccountStatus`（`ACTIVE`／`DISABLED`／`NOT_FOUND` 三個常數），`AdLdapWrapper.php` 實作：直接呼叫底層 adLDAP 函式庫的 `infoCollection($username, ['samaccountname','useraccountcontrol'])`（這個函式庫的 `info()`/`infoCollection()` 支援任意欄位查詢，不限於登入路徑自己的 attribute mapping），解析 `userAccountControl` 的標準 AD 位元（`ACCOUNTDISABLE`＝bit 2、`LOCKOUT`＝bit 16，同一套位元在這個函式庫自己寫入帳號時也有用到，`classes/adLDAPUsers.php` 看得到）。查不到帳號＝`NOT_FOUND`（通常是離職）、查到但 disabled/locked＝`DISABLED`。

新增 `Jobs/ad-reconcile-account-status.php`：撈 `is_ad_managed=1 AND is_local_auth_only=0 AND status_id=ACTIVE` 的帳號（`SqlFilterFreeForm` 直接組條件，`UserRepository::GetList()` 傳 `null, null` 取全部不分頁），每個帳號各自包一層 try/catch（照抄 `Jobs/import-coasia-employees.php::ApplyImportPlan()` 的錯誤隔離寫法，不是 `sendreminders.php`／`autorelease.php` 那種整個 job 共用一個外層 try/catch——一個人查詢失敗不該讓其他人都不檢查），查到非 `ACTIVE` 就 `Deactivate()`。支援 `--dry-run`／`--apply`（照抄 `import-coasia-employees.php` 既有慣例），**沒有預設值、強制二選一**，避免忘記帶旗標就意外大量誤停用真實員工帳號。真正排進 crontab 是 VM／部署層的事，這個 repo 目前完全沒有任何 cron 機制（`docker-compose.prod.yml` 沒有 cron container，`COASIA-DEPLOYMENT.md` 提到的 cron 只有備份用途），這次只把 script 寫好、在檔案開頭比照慣例留了建議的 crontab 範例（改用 `0,15,30,45 * * * *` 而不是 `*/15 * * * *`，純粹是後者的 `*/` 字面量會提早把 PHP 的 `/* */` 註解區塊截斷），排程本身留給使用者之後在 VM 上處理。

### 驗證

容器內 `php -l` 過全部新增/修改的 `.php` 檔案，皆無語法錯誤。這次全程用真實開發資料庫、真實開發環境的 AD 連線設定（`ActiveDirectory.config.php` 真的連得到公司 AD）做端到端驗證，寫了多支一次性 CLI 診斷腳本（測完即刪）：①`is_local_auth_only` 帳號在 AD 明確拒絕的情況下依然能用本機密碼登入成功、且完全沒有嘗試連 LDAP；②一般帳號在模擬 AD 斷線、以及 AD 明確拒絕這兩種情況下，都確認被拒絕登入、且 fallback 到本機密碼的分支完全沒被呼叫到；③一個手動設成 INACTIVE 的帳號，模擬一次成功的 AD 登入後，確認 `status_id` 真的變回 ACTIVE、`is_ad_managed` 真的被標記、且原本要接手的 `authToDecorate->Login()` 依然有被呼叫到；④`credentials_invalidated_at` 在 `Deactivate()` 之後正確寫入非空時間戳，並且直接複製 `IsAuthenticated()` 內部同一套比較邏輯驗證：Session 建立時間早於停用時間會判定要強制登出、晚於停用時間（例如停用後又重新登入）則維持已登入；⑤`Jobs/ad-reconcile-account-status.php` 對一個真實建立、標記 `is_ad_managed=1` 的測試帳號（AD 裡當然查不到這個假帳號，正好驗證了「查不到＝視為離職」這條路徑）分別跑過 `--dry-run`（確認只報告不寫入）跟 `--apply`（確認真的停用、`credentials_invalidated_at` 真的被蓋章），再跑一次 `--dry-run` 確認已停用的帳號不會再被撈到，測完帳號整個刪除乾淨；也確認了 `--dry-run`／`--apply` 都不給或都給時會被擋下來。額外用不需要登入的頁面（登入頁本身，`http_status=200`）驗證 `Pages/Page.php` 這個所有頁面共用的基底類別改完之後沒有連帶弄壞全站——這個檔案改錯的影響範圍是整個網站，容器 log 裡也確認沒有新增的 error/fatal。

手上沒有真實帳號的密碼，沒辦法在瀏覽器裡實際登入操作後台使用者管理介面新增的欄位／checkbox，麻煩使用者登入後確認：①編輯使用者頁面看得到「部門」欄位跟「本機專屬登入（緊急管理員）」checkbox、②把 `shawn.admin`（或指定的緊急帳號）勾選這個 checkbox 存檔後，該帳號在 AD 故障時仍能用本機密碼登入。另外，正式環境的 `ActiveDirectory.config.php` 是 bind mount 的機密設定檔、不在版控裡，這次沒有幫使用者去改那個檔案——`database.auth.when.ldap.user.not.found=true` 這個設定在正式環境目前仍然是 `true`，但已經不再被程式碼讀取，實際的緊急備援管道要靠使用者自己去後台把對應帳號勾選新的「本機專屬登入」——**部署這次改動之前務必先做這一步**，否則正式環境的 AD 一旦有任何問題，會變成連原本的緊急管理員都登不進去。

### 81.1 部署當下意外發現並修正：新的 migration 少了 `data.sql`，`dbversion` 沒有跟著跳

使用者實際照著部署步驟在正式環境跑完 `database_schema/upgrades/4.5/schema.sql`、重建 `app` 容器之後，`composer preflight` 印出「schema version: 4.2」，不是預期的 4.5。查了才發現：這次新增的 `4.5/` 遷移資料夾只寫了 `schema.sql`（處理 `ALTER TABLE` 本身），漏掉每個 upgrade 資料夾理論上都該有的 `data.sql`——`dbversion` 這張版本追蹤表**不是** `schema.sql` 負責寫的，是 `data.sql` 裡一行 `INSERT INTO dbversion ... WHERE NOT EXISTS (...)`（比照 `database_schema/upgrades/4.2/data.sql:49-56` 的既有寫法，`COASIA-ARCHITECTURE.md` 也有記這個慣例）寫的，這次一開始漏掉了。

實際影響：`schema.sql` 那四個欄位本身確實有正確建立（`ALTER TABLE` 有真的執行），只是 `dbversion` 沒跟著跳版號，`composer preflight`／未來的 Web 安裝精靈會誤判「這個環境還停在 4.2」。修法：補上 `database_schema/upgrades/4.5/data.sql`，只做一件事——冪等地把 `dbversion` 補一筆 `4.5`。在開發環境重新驗證過：套用後 `composer preflight` 正確顯示「schema version: 4.5」，重複套用第二次也確認冪等（`WHERE NOT EXISTS` 防呆，不會重複插入或報錯）。

**正式環境如果已經照原本的步驟只跑過 `schema.sql`**，補救方式是額外再跑一次這個新的 `data.sql`（跟 `schema.sql` 一樣冪等，不影響已經跑過的部分）：
```bash
docker compose -f docker-compose.prod.yml exec -T db mysql -uroot -p'<MYSQL_ROOT_PASSWORD>' librebooking < database_schema/upgrades/4.5/data.sql
```

## 82. 會議室管理編輯抽屜「顯示一大堆舊欄位」：根因是複製資源時漏標 Resource Type

使用者回報「會議室管理」的編輯抽屜看起來像舊版、欄位一大堆（截圖是 ResourceId 16「台北 Taipei」，深圳地區）。一開始以為要重新設計這個抽屜，但查了 [manage_resource_drawer_content.tpl](tpl/Admin/Resources/manage_resource_drawer_content.tpl) 才發現 §66 早就做過簡化：有設定 Resource Type 的實體會議室只顯示名稱＋狀態，線上會議顯示連結/密碼；**只有 `resource_type_id IS NULL` 的資源會 fallback 顯示全部舊欄位**（刻意保留，讓新資源在分類前還能設定）。

查本機開發資料庫，`resources` 表裡有 9 筆 `resource_type_id IS NULL`：全部是先前用「複製」功能把台北的 7 間實體會議室複製到「深圳」「上海」兩個地區群組時產生的複本（`上海/台北/新加坡/深圳/首爾` 各兩份）。`BookableResource::AsCopy()`（`Domain/BookableResource.php:1698`）本身不會清掉 `resource_type_id`，但這批複本顯然是在來源資源自己都還沒有類型的時候複製出來的，之後來源資源被 [4.4/schema.sql](database_schema/upgrades/4.4/schema.sql) 的遷移補上類型時，這批已經存在的複本沒有被涵蓋到——4.4 的遷移是 hardcode `resource_id IN (1..12)`，只顧到最初的 12 筆種子資料，後來才用複製功能新增的資源完全沒被觸及。這不是程式邏輯壞掉，是資料一直缺一角。

**修法**：新增 `database_schema/upgrades/4.6/`，延續 4.4 的兩個既有慣例——用 `resource_type_name` 查找（不寫死 ID）、用是否有 `video_url` 自訂屬性值分辨線上會議跟實體會議室——但這次不 hardcode resource_id，改成無條件處理所有 `resource_type_id IS NULL` 的資源：有 `video_url` 值的設成「線上會議」，其餘設成「實體會議室」。跟 4.4／4.5 一樣全程用 `IS NULL` 當 guard，天然冪等，不會動到已經有類型或被 admin 手動改過的資源。`4.6/data.sql` 照 4.5 的既有樣板補 `dbversion`（上一節 §81.1 才剛踩過漏掉這步的坑，這次沒有漏）。

過程中意外發現一個跟這次改動無關但會擋路的 `.gitignore` 破洞：第 81 行 `*.sql` 整條規則會擋掉所有 SQL 檔（原意是防止誤 commit 含真實員工資料的 DB dump），但 `database_schema/upgrades/` 底下從 2.1 到 4.5 的所有既有遷移檔案其實都是用 `git add -f` 硬塞進版控的——這代表以後任何人新增遷移資料夾，只要習慣用 `git add .`／`git add -A` 就會被靜默漏掉，而且不會有任何錯誤訊息提醒，跟上一節「漏了 `data.sql`」是同一種「看起來做完了、其實少一塊」的風險。順手補了兩條 negation 規則（`!/database_schema/**/*.sql`、`!/tests/load_test.sql`），讓這整個目錄底下的檔案回到正常的 git 追蹤行為，不用再靠 `-f`。

### 驗證

- 本機開發資料庫直接套用 `4.6/schema.sql` + `4.6/data.sql`：9 筆資源的 `resource_type_id` 全部變成「實體會議室」的 id，`dbversion` 新增一筆 `4.6`
- 重跑一次同樣兩支 SQL，確認冪等（無錯誤、無重複列、值不變）
- 對照程式碼確認邏輯閉環：`BookableResource::HasResourceType()`（`Domain/BookableResource.php:1530`）只檢查 `resource_type_id` 是否非空，補上類型後 `manage_resource_drawer_content.tpl` 的 `{if !$isTypedResource}` 分支會自然不再命中，改走簡化版
- `docker compose -f docker-compose.dev.yml restart app` 清 opcache 後，沒有帳密可以實際登入這個環境本身已經串接公司 AD 的開發資料庫，畫面上的實際確認麻煩使用者自行登入複查
- 正式環境比照既有部署流程（git pull + rebuild），下次部署時這個遷移會自動套用；因為是用 `IS NULL` 篩選、不依賴具體 resource_id，不需要知道正式環境實際的 ID 是多少

## 83. 線上會議資源的「線上會議資訊」改成單一 Modal 編輯（比照使用者管理）

§82 修完資料後，使用者追加：即使是已分類、已經簡化過的「線上會議」資源，畫面還是舊的——拿「使用者管理」的編輯 Modal（十幾個欄位一次擺在一個表單、一次送出）跟「Google Meet 1」抽屜對比，後者的會議ID／密碼／連結／主持人登入資訊還是每個欄位各自一支鉛筆圖示、各自獨立編輯（三個用 `Admin/InlineAttributeEdit.tpl` 那套 x-editable 行內編輯，主持人帳密另外開一個 `videoHostDialog` 彈窗），四個編輯點才能改完一筆資源的完整資訊。

查證後範圍比預期小：實體會議室類型（§66 已簡化成只剩名稱＋狀態）不受影響；真正要改的只有線上會議類型資源的這個區塊。**沒有**照抄使用者管理那種整頁 offcanvas 表單，而是比照這個檔案裡 `durationForm`／`accessForm`／`statusForm` 早就在用的既有慣例——一個 pencil 開一個 Modal、裡面好幾個欄位、一個 Update 按鈕——把原本 4 個分散的編輯點收成 1 個，這樣改動幅度小，也跟這頁其他設定區塊的既有寫法一致。

- `tpl/Admin/Resources/manage_resources.tpl`：新增 `videoConferenceInfoDialog`（取代 `videoHostDialog`），一個表單裡放會議ID／密碼／連結三個文字框（`name` 直接用 `{FormKeys::ATTRIBUTE_PREFIX}{$attribute->Id()}`，迴圈 `$AttributeList` 找 label 是 `video_url`/`video_meeting_id`/`video_passcode` 的三個屬性——這三個屬性 ID 是全站固定的屬性定義 ID，不是每個資源各自不同，所以可以在這個共用 Modal 裡渲染一次）加上既有的主持人帳號／密碼兩個欄位，全部送到新的 `ActionUpdateVideoConferenceInfo`。
- `tpl/Admin/Resources/manage_resource_drawer_content.tpl` 的線上會議分支、新增的 `tpl/Admin/Resources/manage_resources_video_info.tpl`：拿掉三個獨立 pencil／x-editable span，改成純唯讀顯示 5 個值＋區塊標題旁一個編輯 pencil。這個唯讀 fragment 同時也是存檔後 AJAX 用來刷新畫面的回應內容（跟 `manage_resources_duration.tpl`／`ChangeDuration()` 的既有模式一樣：presenter 存完直接 `Display()` 這個 fragment，JS 把回應塞回 `.videoConferenceInfoPlaceHolder`），不用整頁重新整理。
- 刪除已經沒人用的 `tpl/Admin/Resources/manage_resources_video_attribute.tpl`。
- `ManageResourcesPresenter.php`：`ActionChangeVideoHost`/`ChangeVideoConferenceHost()` 改成 `ActionUpdateVideoConferenceInfo`/`UpdateVideoConferenceInfo()`，內部直接重用既有的 `GetAttributeValues()`（`ChangeAttribute()` 本來就在用的同一支）跟 `GetVideoHostAccount()`/`GetVideoHostPassword()`（原本 `ChangeVideoConferenceHost()` 在用的同一組）——沒有新的資料存取邏輯，純粹把兩個既有動作合併成一次呼叫。`ProcessDataRequest` 的 `case 'videoHost':`（開 Modal 時抓目前值用的既有 AJAX）多帶三個屬性值一起回傳。`ActionChangeAttribute`/`ChangeAttribute()` 本身沒有動——未分類資源的自訂屬性區塊、其他一般自訂屬性欄位還在用同一支。
- `Web/scripts/admin/resource.js`：`showVideoHostPrompt`→`showVideoConferenceInfoPrompt`，用 `data-video-field` 屬性通用地把 AJAX 回傳的三個屬性值 populate 進對應輸入框（不依賴屬性 ID 這種每個環境可能不同的值），新增 `onVideoConferenceInfoSaved` 比照 `onDurationSaved` 的既有模式刷新 placeholder。

### 驗證

- 容器內 `php -l` 通過修改的 PHP 檔案，`node --check` 通過 `resource.js`
- 確認舊的 `videoHostDialog`/`ActionChangeVideoHost`/`manage_resources_video_attribute.tpl` 等名稱在全專案（除了這份記錄本身）沒有殘留引用
- 同 §82，開發資料庫串真實 AD 沒有已知測試帳密，沒辦法在瀏覽器裡實際點開 Modal 操作，麻煩使用者登入後確認：線上會議資源的「線上會議資訊」只剩一個編輯 pencil，點開的 Modal 五個欄位都在、改完存一次全部生效；實體會議室資源的抽屜完全沒變化

## 84. 會議室管理側邊視窗整個表單化，比照使用者管理分區呈現

§83 才把「線上會議資訊」收成一個 Modal，使用者這次把需求講得更完整：整個側邊視窗要比照「使用者管理」的編輯介面——表單化、依「基本資料」「線上會議資訊」分區、統一欄位標籤／間距／按鈕位置。這代表 §83 的 Modal 方案這次直接拆掉重做，不是回頭路，是這次才真正把範圍講清楚（一個表單、分區顯示，不是「4 個編輯點先收成 2 個」這種中繼方案）。

只動**已分類**資源（實體會議室／線上會議）；未分類資源的 fallback 畫面（§66／§82 定案）完全不動，還是走原本的 x-editable＋Modal。

### 84.1 已分類資源的抽屜內容整個換成一個表單

`tpl/Admin/Resources/manage_resource_drawer_content.tpl`：標題列（名稱＋重新命名／複製／刪除）跟狀態顯示原本是未分類／已分類共用的程式碼，拆成兩個分支——未分類分支原封不動；已分類分支的標題只留純文字名稱＋複製／刪除兩個 icon（拿掉重新命名，名稱改進表單裡編輯），狀態顯示整個搬進新表單。已分類資源的主體改成一個 `<form ajaxAction="{ManageResourcesActions::ActionUpdateResourceInfo}">`：「基本資料」區塊（名稱、狀態、原因、通知使用者）＋線上會議資源多一個「線上會議資訊」區塊（會議ID／密碼／連結／主持人帳號／密碼），版面（`row gy-2`／`col-sm-12 col-md-6`／`label.fw-bold`）比照 `tpl/Admin/Users/user-update.tpl`，一個 Update 按鈕全部一次送出。§83 新增的 `videoConferenceInfoDialog`（獨立 Modal）跟著拆掉，欄位直接內嵌進表單；`manage_resources_video_info.tpl` 也一併刪除。

主持人密碼本來就已經在唯讀畫面明文顯示（§83 做的），這次直接當表單欄位的預設值渲染進 HTML，沒有比現狀曝露更多，不需要再像 §83 那樣用 AJAX 延遲抓取。

### 84.2 後端：抽出共用的狀態變更邏輯

`ManageResourcesPresenter.php`：把 `ChangeStatus()` 原本的邏輯（原因建立＋`$resource->ChangeStatus()`＋通知信迴圈）抽成 `private function ApplyStatusChange($resource)`，`ChangeStatus()`（未分類資源的 `statusDialog` 還在用）跟新增的 `UpdateResourceInfo()`（已分類資源的統一表單，`ActionUpdateResourceInfo`）都呼叫它，避免兩條路徑各自維護一份通知信邏輯。`UpdateResourceInfo()` 依序：設定名稱→套用狀態變更→（沿用既有 `GetAttributeValues()`）套用自訂屬性→`Update()`→如果是線上會議類型（`LoadResourceType()->IsVideoConference()`）才存主持人帳密。實體會議室的表單沒有 `attribute_*` 欄位，`GetAttributeValues()` 天然回傳空陣列，不需要另外判斷。

§83 為了 Modal + AJAX 預填加的 `ActionUpdateVideoConferenceInfo`／`UpdateVideoConferenceInfo()`、`ProcessDataRequest` 的 `case 'videoHost':`、`SetVideoConferenceInfoJson`／`BindUpdatedVideoConferenceInfo` 這次全部移除——表單改成直接渲染初始值，不需要再繞一次 AJAX 抓值。`GetResourceName()`／`GetStatusId()`／`GetStatusReasonId()`／`GetNewStatusReason()`／`SendStatusChangeMessage()`／`StatusChangeDays()`／`GetStatusChangeMessage()` 這些 Page getter 全部沿用既有的，新表單欄位 `name` 直接用同一組 `FormKeys` 常數，後端幾乎零新增欄位存取邏輯。

### 84.3 前端：狀態原因/通知使用者的 JS 從「綁一次在固定 id 上」改成事件代理

原本「狀態」欄位背後的原因下拉聯動、切換新增原因輸入框、通知使用者 checkbox 展開/收合，這幾支邏輯都只綁在 `statusDialog` 這唯一一份共用元素上（`showStatusPrompt()` 每次開 Modal 還會 `.unbind()` 再重新綁一次）。這次已分類資源變成 N 份永遠顯示的表單（不是開 Modal 才存在），改成 `bindStatusReasonBehavior()`：用 `$(document).on('change'/'click', '.statusId'/'.addStatusReason'/'.notifyUsersToggle', ...)` 綁一次、用 `$(this).closest('form')` 找範圍，`statusDialog` 跟 N 份 `resourceForm{id}` 共用同一套邏輯，`showStatusPrompt()` 也跟著精簡（拿掉重複的 unbind/rebind，只留初始值 populate）。新增 `initializeResourceFormReasons()`，頁面載入時用既有的 `resources[id]` 快取（`statusId`/`reasonId`，`resourceManagement.add()` 早就存了）幫每份表單的原因下拉做初始 populate。每份 `resourceForm{id}` 存檔成功後 `window.location.reload()`，比照 `Web/scripts/admin/user.js` 既有的存檔後整頁重新整理慣例，不用另外處理「表單存完要怎麼讓標題／列表同步」。

`lang/zh_tw.php`／`lang/en_us.php` 新增 `BasicInformation`（基本資料／Basic Information）。

### 驗證

- 容器內 `php -l`／`composer phpstan`／`composer phpcsfixer:lint` 通過所有修改的 PHP／語言檔，`node --check` 通過 `resource.js`
- `composer phpunit` 全跑一次：1729 個測試，跟改動前一樣只有 1 個既有、無關的失敗（`ManageReservationsPresenterTest`）
- 全專案搜尋確認 §83 的 `videoConferenceInfoDialog`／`ActionUpdateVideoConferenceInfo`／`SetVideoConferenceInfoJson` 等名稱沒有殘留引用
- 同 §82／§83，開發環境串真實 AD 沒有已知測試帳密，沒辦法在瀏覽器裡實際操作驗證，麻煩使用者登入後確認：已分類資源的抽屜呈現「基本資料」＋（線上會議資源多一個）「線上會議資訊」兩個分區、欄位樣式跟使用者管理一致；改名稱／狀態／通知使用者／線上會議資訊任一項存檔後整頁重新整理、新值生效；未分類資源畫面沒有變化

## 85. 開放時間管理側邊視窗大幅精簡：拿掉時間表管理員、設為默認、整個進階設定

使用者看著「開放時間管理」（時間表）的編輯抽屜——這是更早一輪（§51.2）做的，跟這次會議室管理的改版是不同頁面——直接回饋：「時間表管理員」不該出現、「設為默認的」這種功能不該做、名稱不該在這裡改、「進階設定」整區根本不需要。追問「進階設定」（星期起始/可見天數、Availability 日期範圍、並發預約上限、每筆預約會議室上限、顯示樣式、訂閱日曆 Atom/iCal、刪除時間表）要留哪些，使用者選擇全部拿掉，只留名稱（唯讀）＋每週開放時間編輯器。

動手前先派一個 agent 確認：`.deleteScheduleButton`（進階設定裡的刪除連結）拿掉後，列表那排本來就有的「⋯」選單 `.admin-row-delete-action` 是不是完整獨立可用的刪除路徑——確認是（`schedule.js` 兩個 trigger 共用同一個 `showDeleteDialog()`/`#deleteDialog`），所以刪除功能沒有真的消失。另外確認要拿掉的這些欄位背後的 JS 綁定跟後端 action 有沒有被別的地方共用：`ManageScheduleService`（`Presenters/Admin/ManageSchedulesPresenter.php`）除了這個 presenter，`Presenters/ViewSchedulesPresenter.php` 也有用，但只用到 `GetList`/`GetSourceSchedules`/`GetLayout` 這三個沒有要動的方法，要刪的那批（`Rename`/`ChangeSettings`/`MakeDefault`/`EnableSubscription`/`DisableSubscription`/`ChangeAdminGroup`/`DeleteAvailability`/`UpdateAvailability`/`ChangeDefaultStyle`/`ChangeConcurrentReservationMaximum`/`ChangeResourcesPerReservation`）確認沒有其他呼叫端。

- `tpl/Admin/Schedules/manage_schedules.tpl`：抽屜內容從「名稱(可編輯)＋時間表管理員＋狀態/設為默認＋每週開放時間＋`<details>進階設定</details>`」砍成只剩「名稱(純文字)＋每週開放時間」，`availabilityDialog`／`concurrentMaximumDialog`／`resourcesPerReservationDialog` 三個因此失去唯一觸發點的 Modal 一併移除，內嵌 `<script>` 裡的 `setUpEditables()`（原本專門初始化這批欄位的 x-editable widget，拿掉後整個函式空了）跟著刪除。
- `Presenters/Admin/ManageSchedulesPresenter.php`：`ManageSchedules` 常數類拿掉對應的 11 個 Action 常數，`AddAction` 少了同樣 11 條路由，presenter 跟 `ManageScheduleService` 兩層各自的 action 方法本體也一併刪除——不是只拔路由留著死方法，是真的移除（先用上面的交叉檢查確認沒有其他呼叫端）。`Domain/Schedule.php` 的 domain 層 setter（`SetIsDefault`／`SetAdminGroupId`…）完全沒動，這些是實體本身的方法，不是這次要清的 view/service 膠水層。
- `Pages/Admin/ManageSchedulesPage.php`：對應的 `Get*`／`Bind*`／`Display*` 介面宣告與實作一併移除（`BindGroups`／`GetAvailableAllYear`／`GetAvailabilityBegin`／`GetAvailabilityEnd`／`DisplayAvailability`／`GetMaximumConcurrentReservations`／`GetIsUnlimitedConcurrentReservations`／`GetMaximumResourcesPerReservation`／`GetIsUnlimitedMaximumResourcesPerReservation`），連帶 `PageLoad()` 裡呼叫 `groupViewRepository->GetGroupsByRole()` 再 `BindGroups()` 那次多餘查詢也拿掉；`IcsEnabled` 這個只服務被刪掉的訂閱區塊的 Set 呼叫一併移除。**沒有動**建構子注入的 `$groupViewRepository`（有兩個地方在 new 這個 presenter，還有測試檔用位置參數直接建構，動建構子簽章風險/效益不成比例，留著一個沒在用的私有屬性換取不動 DI 介面）。
- `Web/scripts/admin/schedule.js`：刪掉對應的點擊/change 綁定、`showAvailabilityDialog`／`refreshAvailability` 這兩個因此變成孤兒的函式、`elements` 物件裡對應的一批選取器。過程中順手確認了幾個「看起來也很像但其實跟這次無關」的既有殘留（`.toggleConcurrent`、`#daysVisible`、`#concurrentForm` 這幾個選取器在目前的樣板裡本來就對應不到任何元素）——這些是既有的技術債，不在這次要清的範圍內，沒有動。

### 驗證

- 容器內 `php -l`／`composer phpstan`／`composer phpcsfixer:lint` 通過兩支修改的 PHP 檔案
- `node --check`／`npx eslint` 通過 `schedule.js`（`resource.js` 有一個 `AdminConfirm is not defined` 的既有警告，跟這次改動無關，`git diff` 確認沒有動到那行）
- `composer phpunit` 全跑一次：1729 個測試，跟改動前一樣只有 1 個既有、無關的失敗（`ManageReservationsPresenterTest`）；另外單獨跑 `--filter ManageSchedules` 4 個測試全過
- 全專案搜尋確認拿掉的 11 個 `ManageSchedules::Action*`／`ChangeAdminGroup` 常數、`showAvailabilityDialog`、`BindGroups` 等名稱沒有殘留引用
- 開發環境沒有已知登入帳密，沒辦法在瀏覽器裡實際操作，麻煩使用者登入後確認：時間表編輯抽屜只剩名稱＋每週開放時間、列表「⋯」選單的刪除功能正常

## 86. 管理中心資料入口整合：每種資料只留一個主要修改入口（Phase A 起手：使用者管理）

使用者要求對整個管理中心做一次架構性盤點，核心原則：「每一種資料只能有一個主要修改入口，其他頁面只能顯示結果，或導向該主要入口」。派三個 agent 平行盤點使用者/群組/權限、會議室/時間表/分組、CSV 匯入與管理總覽三個範圍，發現的不只是「兩處都能改」，還有實際的功能缺口與歷史事故根因（會議室 CSV 匯入的 `autoAssign` 設定錯誤就是先前「10 間會議室沒有預設權限」事故的根因；預約 CSV 匯入的 `UnsafeAdd()` 完全繞過鎖定/驗證/通知，是 §22.4 已經記錄過的已知缺口，這次確認維持現狀、另外開一輪處理，不在這個系列裡）。

盤點出的重複入口跟使用者確認的四項決定：
1. 會議室權限（誰能訂哪間會議室）：目前使用者/群組/會議室三邊都寫入同一套 `ResourcePermissionService`，確認為**會議室管理**唯一入口
2. 授權管理群組（會議室管理員/時間表管理員）：確認為**群組管理**唯一入口（時間表這邊 §85 已經是如此，這次要把唯讀顯示補上）
3. 會議室所屬開放時間：已分類會議室的抽屜本來就不能改時間表，但「批次修改」卻可以改——確認**批次修改拿掉這個選項**，維持已分類會議室不能自己改時間表（符合 §66 原始設計）
4. 使用者屬於哪些群組：使用者管理頁面的操作其實呼叫群組管理同一套後端方法，確認**強制單一入口（群組管理）**

這節先做範圍最小、風險最低的兩項（A1、A2），其餘（會議室權限、授權管理群組、開放時間批次修改、會議室分組、CSV 匯入對齊、管理總覽深連結）留在後續章節逐項處理，比照這個系列一路的節奏——每項改完各自驗證，不是一次全改完才驗證。

### 86.1 使用者屬於哪些群組（A1）

查證後發現這個「重複」其實只剩一半是真的：`tpl/Admin/Users/manage_users.tpl` 裡有一整組 `groupsDialog`／`addedGroups`／`removedGroups`／`addGroupForm`／`removeGroupForm`（呼叫群組管理同一套 `AddUser`/`RemoveUser` action），但全專案搜尋找不到任何會觸發它的 `.changeGroups` trigger——使用者列表的「⋯」選單裡只有 Permissions／Reservations／Change Password／Credit History／Color／Deactivate／Delete，沒有「Groups」這個選項。這組 Modal 跟對應的 `Web/scripts/admin/user.js` 綁定（`changeGroups()`、`changeGroupUrlCallback()`、`elements.groupsDialog` 一整組選取器）其實是先前某輪改版留下的死程式碼，不是真的重複入口，順手整個清掉。

`tpl/Admin/Users/user-update.tpl` 原本已經有的「所屬群組」唯讀清單維持不動，補一行「前往群組管理」連結（沿用既有的 `$ManageGroupsUrl` = `Pages::MANAGE_GROUPS`）。

### 86.2 系統管理員權限（A2）

`user-update.tpl` 的角色下拉（`ROLE` 欄位）拿掉，改成唯讀 badge（顯示目前是否為系統管理員）＋既有的「所屬群組」清單＋「前往群組管理」連結。`ManageUsersPresenter::ApplyRole()`（原本假設剛好有一個帶 `APPLICATION_ADMIN` 角色的群組，透過切換該群組成員資格來授予/收回管理員權限，找不到就靜默失敗記 log）連同它在 `AddUser()`／`UpdateUser()` 的兩個呼叫點一起移除；`Pages/Admin/ManageUsersPage.php::GetRole()`／介面宣告一併拿掉。`ManageGroupsPresenter::ChangeRoles()`（真正寫入角色-群組綁定的地方）完全沒動。

過程中發現並修正一個連帶的小 bug：`ShowUserUpdate()`（`dr=update` AJAX 端點，`ActionPage::PageLoad()` 會走 `ProcessDataRequest()` 分支、不會執行 `ProcessPageLoad()`）本來沒有自己設定 `ManageGroupsUrl`，這個變數只在 `ProcessPageLoad()` 裡設過——代表新加的「前往群組管理」連結透過 AJAX 抽屜開啟時會拿到空值。補上讓 `ShowUserUpdate()` 自己也 `Set('ManageGroupsUrl', Pages::MANAGE_GROUPS)`。

測試檔 `tests/Presenters/Admin/ManageUsersPresenterTest.php` 對應拿掉三個純測 `ApplyRole()` 行為的測試（`testAddsUserWithRegularRoleRemovesFromAdminGroupInCaseTheyWerePreviouslyAdmin`／`testAddUserWithNoRoleSubmittedLeavesGroupsUntouched`／`testUpdateUserPromotesToAdminGroupWhenAdminRoleSubmitted`），`testAddsUser` 裡混雜的角色相關斷言也一併拔掉，不是留著測一個已經不存在的功能。

### 驗證

- 容器內 `php -l`／`composer phpstan`／`composer phpcsfixer:lint` 通過所有修改的 PHP 檔案，`node --check`／`npx eslint` 通過 `user.js`
- `composer phpunit` 全跑一次：1726 個測試（比改動前少 3 個，對應拿掉的三支測試），跟改動前一樣只有 1 個既有、無關的失敗（`ManageReservationsPresenterTest`）；`--filter ManageUsers` 46 個測試全過
- 全專案搜尋確認 `ApplyRole`／`FormKeys::ROLE`／`GetRole()`／`groupsDialog`／`changeGroupUrlCallback` 沒有殘留引用（除了測試檔裡無害的 fake page 殘留欄位、跟已知會重新編譯的 `tpl_c` 快取）
- 開發環境沒有已知登入帳密，麻煩使用者登入後確認：編輯使用者看到的是唯讀角色 badge＋所屬群組清單＋前往群組管理連結，點連結能正確導到 `manage_groups.php`

## 87. 新增企業微信（WeCom）推播通知：LINE 之外的第二條通知管道

主管指示優先做「WeCom」。來源是一份 IT（Mars Huang）2025-11-25 的簡報 `wecom_api_20251125.pptx`，說明 CoAsia 內部的 WeCom 推播閘道（`wecoms.coasia.app`）——注意簡報的截圖是**另一套派車／司機調度系統**（欄位有「司機選擇／車輛選擇／乘客／行程」），不是這套會議室系統。跟使用者確認後拍板：**這套 LibreBooking 也要接**、用**明文端點**（`POST /wecom/send_wecom_msg.php`，只帶 `touser`＋`content`，無 token）、觸發範圍是**所有有填 WeCom ID 的人**（不看地區——簡報 slide 7 原本的規則是「行程地區為深圳/上海才發」，這裡放寬）。

整體做法是把現有的 LINE 整合當範本平行複製一份，**不需要資料庫遷移**——WeCom 收件人 ID 跟 `LineUserId` 一樣塞在 `user_preferences`（`!sep!` 串接字串），不是欄位。發送規則是「兩條管道各自獨立」：LINE 維持原樣，WeCom 在使用者有 `WecomUserId` 且沒把開關關掉（`WantsWeComNotifications()`，比照 LINE「沒設過＝視為開啟，只有明確 `'0'` 才關」）時發送——兩者都符合的人兩邊都會收到（對應簡報 slide 7 的「都發」）。

### 87.1 設定

- `lib/Config/ConfigKeys.php`：新增 `WECOM_ENABLED`（`wecom.enabled`，預設 `false`）、`WECOM_API_URL`（`wecom.api.url`，預設 `https://wecoms.coasia.app/wecom/send_wecom_msg.php`），section `wecom`（比照 `line` 沒有進 `ConfigKeysMeta::SECTION_TITLES`，走 `ucwords` fallback）。
- `config/config.dist.php`／`.env.example` 用 `composer config-dist:generate`／`env-example:generate` 重生。**注意**：這兩支 `:check` 在乾淨的 HEAD 上本來就是紅的（committed `config.dist.php` 的 `enabled.languages` 是 `'zh_tw,zh_cn,en_us'`，generator 產出的是 `''`，是既有的 repo drift），這次只保留了那個既有的手改值、其餘照 generator 產出，沒有順手把那個既有問題一起改掉（會把不相關的語言選單行為變更混進來）。
- 因為 `Configuration::GetKey()` 找不到值會 fallback 到 `ConfigKeys` 裡的 `default`，正式環境的 `config.php` 不加 `wecom` 區塊也能安全運作（預設 disabled，`ServiceLocator` 回傳 `NullWeComService`）。要啟用時在 VM 的 `config.php` 加 `'wecom' => ['enabled' => true]`，或用 `app.env` 的 `LB_WECOM_ENABLED=true`（`app.env` 的非空值會蓋掉 `config.php`，見 `COASIA-DEPLOYMENT.md` 第六節）。

### 87.2 服務層

- `lib/WeCom/{IWeComService,WeComService,namespace}.php`：`WeComService::Push($wecomUserId, $text)` 用 `Unirest\Request\Body::form(['touser' => ..., 'content' => ...])` POST 到閘道，fire-and-forget（連線失敗記 log 吞掉，不擋預約流程），比照 `LineService` 的錯誤處理寫法。`NullWeComService` 是 disabled 時的 no-op 實作。
- `lib/Common/ServiceLocator.php`：`GetWeComService()` 依 `wecom.enabled` 回傳 `WeComService` 或 `NullWeComService`；`SetWeComService(?IWeComService)` 刻意收 nullable（比 `SetLineService` 進一步），讓測試 teardown 能重設成 null、下次呼叫重新依 config 解析。

### 87.3 網域層

- `Domain/Values/UserPreferences.php`：新增 `WECOM_USER_ID = 'WecomUserId'`、`WECOM_NOTIFICATIONS_ENABLED = 'WecomNotificationsEnabled'`。
- `Domain/User.php`：`WantsWeComNotifications()`／`ChangeWeComNotificationsPreference()`，逐行比照既有的 `WantsLineNotifications()`／`ChangeLineNotificationsPreference()`。

### 87.4 通知

- `lib/Application/Reservation/Notification/OwnerWeComNotification.php`：`OwnerWeComCreatedNotification implements IReservationNotification`，比照 `OwnerLineCreatedNotification`——同樣用 `ReservationEmailTemplateContext` 抓視訊資源＋主持人登入資訊（owner-only，理由跟 LINE 版一樣：這個通知只會送到預約人本人），但因為 WeCom 明文閘道只吃純字串，輸出的是一則多行純文字（狀態／會議名稱／時間／會議室或視訊資源／預約人／查看連結／🔒 主持人登入資訊），不是 LINE 的 Flex bubble。重複預約的措辭處理（`match` RepeatType → 場次數 → 末場日期）照抄 LINE 版，只是把 LINE 版寫死的 CJK suffix 改成語言字串 `WeComRecurringSuffix`。
- `lib/Application/Reservation/Notification/AddReservationNotificationService.php`：建構子的通知陣列加一個 `OwnerWeComCreatedNotification`（緊接在 `OwnerLineCreatedNotification` 後）。基底 `ReservationNotificationService::Notify()` 本來就每個通知各包一層 try/catch，WeCom 送失敗不會弄壞建立預約。
- `Jobs/sendreminders.php`：`$wecomEnabled` 加進「三者皆關就 return」的短路判斷；start／end 兩個 notice 迴圈原本 `if ($lineEnabled)` 才 load owner，改成 `if ($lineEnabled || $wecomEnabled)` load 一次、裡面 LINE／WeCom 各自獨立判斷（各自檢查 `WecomUserId` 非空 + `WantsWeComNotifications()`），用新的 `WeComReminderStartMessage`／`WeComReminderEndMessage` 字串。

### 87.5 後台使用者管理：新增「企業微信 ID」欄位

WeCom ID 跟 LINE 不同——LINE 是掃 QR + webhook 綁定，WeCom 是管理員在後台直接填（簡報 slide 6「增加企業微信ID欄位: 帳號角色管理」）。

- `tpl/Admin/Users/user-update.tpl`：部門欄位旁加一個文字框（照抄部門欄位的寫法），`{formname key="WECOM_USER_ID"}`＋`{translate key="WeComUserId"}`＋一行 hint。
- `lib/Server/FormKeys.php`：新增 `WECOM_USER_ID = 'wecomUserId'`。
- `Pages/Admin/ManageUsersPage.php`：`GetWeComUserId()` 介面宣告＋實作（`GetForm(FormKeys::WECOM_USER_ID)`）；`ShowUserUpdate()` 多 `Set('WeComUserId', $user->GetPreferences()->Get(UserPreferences::WECOM_USER_ID))`（把 `UserPreferences` 常數留在 PHP，比照旁邊 `IsUserAdmin` 的既有理由），並在頂部補 `require_once` `Domain/Values/UserPreferences.php`。
- `Presenters/Admin/ManageUsersPresenter.php`：`UpdateUser()` 尾端呼叫新的 `ApplyWeComUserId($userId, $wecomUserId)`，做法完全比照旁邊的 `ApplyLocalAuthOnly()`——各自獨立 load/mutate/update（`ChangePreference(WECOM_USER_ID, trim(...))`），不塞進 `ManageUsersService::UpdateUser()` 的 `$extraAttributes` bag（那個 bag 是給 phone/organization/position/department 這種 user attribute 用的，WeCom ID 是 preference）。清空欄位就存 `''`，通知端 `empty()` 判定為「沒填」。

### 87.6 語言檔

`lang/en_us.php`／`lang/zh_tw.php`／`lang/zh_cn.php` 三個都加：`WeComUserId`／`WeComUserIdHint`（後台欄位）、`WeComReservationCreatedTitle`／`WeComFieldMeetingName`／`WeComFieldDateTime`／`WeComFieldMeetingRoom`／`WeComFieldVideoResource`／`WeComJoinVideoConference`／`WeComFieldOwner`／`WeComViewReservationDetails`／`WeComHostLoginInfo`／`WeComRecurringSuffix`（通知內文）、`WeComReminderStartMessage`／`WeComReminderEndMessage`（提醒）。zh_cn 之前完全沒有 LINE 那批字串（一路 fallback 到 en_us），但 WeCom 使用者本來就是中國地區（深圳/上海）會用簡中介面，所以這次三語言都補齊。

### 87.7 部署驗證時踩到的兩件事

**a. FortiGuard 網頁過濾擋掉 `coasia.app`（新網域「尚未分級」）。** 正式機部署後 `curl` 閘道回「憑證不受信任」，`curl -k` 印出的是一頁 FortiGuard「Web Page Blocked / Category: 尚未分級」——`coasia.com`／`coasia.com.tw` 是已收錄的公司網域，但 `coasia.app` 是全新的，FortiGuard 還沒分類，而公司過濾政策把「尚未分級」設成封鎖；封鎖頁本身用 FortiGate 自簽憑證回應，所以先冒出來的是 TLS 錯誤。修法是在 FortiGate 加一筆 **Web 評級覆蓋**：`coasia.app` → 信任分類（不是 SSL inspection 豁免，跟 SSL 檢查無關）。加完 container 就拿到 Cloudflare 的真憑證（Google Trust Services），閘道回 `HTTP 200`。這是防火牆設定，不在版控裡。

**b. 閘道失敗時也回 HTTP 200。** 實測 `touser=__ping__` 回 `{"success":false,"errcode":81013,"errmsg":"user & party & tag all invalid",...}`，HTTP 狀態碼卻是 200；成功則是 `{"success":true,"errcode":0,"errmsg":"ok","msgid":"..."}`。原本 `WeComService::Push()` 比照 `LineService` 只看 `$response->code >= 300` 才記 `Log::Error`——但 LINE API 失敗會回非 2xx，這個閘道不會，所以「`touser` 填錯、訊息沒送達」會被當成成功、log 裡看不出來。改成多解析回應 body：新增 `decodePayload()`（把 Unirest 可能回的 parsed object／array／raw string 都正規化成陣列），`success` 為 false 或 `errcode` 非 0 就記 `Log::Error`。用 deck 裡的 `marshuang`（Mars Huang 本人的成員帳號）實測 `{"success":true,...,"msgid":...}`，訊息真的送達他的企業微信，整條路（防火牆 → TLS → 閘道 → 企業微信 API → 送達）確認打通。

**待辦（非技術）：** `touser` 是企業微信自訂的成員帳號（範例 `marshuang`，看起來是拼音，非 email／AD／工號），要跟閘道維護者（Mars）拿「員工 → 企業微信帳號」對照表，管理員再逐一填進後台的「企業微信 ID」欄位。收通知的人也要在該應用的「可見範圍」內，否則一樣回 `81013`。

### 驗證

- 容器內 `php -l` 過全部新增/修改的 `.php`；`composer phpcsfixer:lint`／`composer phpstan` 對這次的檔案全過（既有的 4 個 phpcsfixer flag——`import-coasia-employees.php`、concurrency 測試檔、`IdempotencyService.php`——跟這次無關）
- `composer phpunit` 全跑：1733 個測試（比改動前多 3 個 = 新增的 `OwnerWeComNotificationTest` 3 支），跟改動前一樣只有 1 個既有、無關的失敗（`ManageReservationsPresenterTest::testUsesTwoWeekSpanWhenNoDateFilterProvided`，`usort(null)`，在乾淨 HEAD 上就是紅的）
- 新增測試：`tests/fakes/FakeWeComService.php`（記錄 `_Pushed`）＋註冊進 `tests/fakes/namespace.php`；`tests/Application/Reservation/OwnerWeComNotificationTest.php`（有填 ID + 想收 → 推一則含會議室名稱/預約人的訊息；沒填 ID → 不推；關掉開關 → 不推）；`tests/Domain/User/UserTest.php` 加 `WantsWeComNotifications()` 的「預設開／`'0'` 關／再開」語意測試。`tests/Presenters/Admin/ManageUsersPresenterTest.php` 的 `FakeManageUsersPage` 補 `GetWeComUserId()` stub（不然 `IManageUsersPage` 變 abstract）。
- 開發環境沒有已知登入帳密，且 `wecom.enabled` 預設 false，麻煩使用者在啟用後確認：後台編輯使用者看得到「企業微信 ID」欄位、填入後建一筆預約會收到 WeCom 推播、`Jobs/sendreminders.php` 的提醒也會分流。另外要確認正式機 `BookingServer` 能對外連 `wecoms.coasia.app:443`（目前只有 cloudflared 是主動連出）。

## 88. LINE 官方帳號換成中量級方案 `@516khkux`，換完後預約通知不推的排查

### 背景：為什麼換 OA

全公司上線後 LINE 推播量會暴增，原本接的官方帳號 `@883belmh` 是輕量級方案，免費訊息額度撐不住。改接公司的中量級 OA `CoAisa擎亞電子`（`@516khkux`）。

### 換 OA 做了什麼（純設定，不進版控）

`config/config.php` 的 `line` 區塊三個值換成新 channel 的：`channel.secret`、`channel.access.token`、`official.account.id => '@516khkux'`。LINE Developers Console 該 channel 設 Webhook URL `https://booking-hook.coasia.com/Web/line-webhook.php` + 開 Use webhook。`config.php` 是 bind mount 的機密檔、不在 git 裡，改完 `docker compose -f docker-compose.prod.yml up -d --force-recreate app` 生效（PHP opcache `validate_timestamps=0`，且單檔 bind mount 換 inode 都要靠重建容器才會重讀）。

排查過程踩到一個坑：用外部編輯器（SFTP/本機副本）改 `config.php` 存到了別的路徑，VM 上真正被 compose 第 24 行 `./config/config.php:...:ro` 掛載的那個檔完全沒動。確認方式是 `docker compose ... exec app grep -n 'official.account.id' /var/www/html/config/config.php` 直接看容器實際讀到的內容，不要只信編輯器畫面。正解是在 VM 上直接 `nano ~/coasia-booking/config/config.php` 改。

驗證 token 有效且屬於正確 OA，不把 token 印出來的做法：

```bash
docker compose -f docker-compose.prod.yml exec -T app php -r '
$c = require "/var/www/html/config/config.php";
$t = $c["settings"]["line"]["channel.access.token"];
$ch = curl_init("https://api.line.me/v2/bot/info");
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>1, CURLOPT_HTTPHEADER=>["Authorization: Bearer $t"]]);
echo curl_exec($ch), "\n";'
```

回 `{"basicId":"@516khkux","displayName":"CoAisa擎亞電子",...}` 代表 token 正確且是這個 OA。

### 問題：換完 OA、重新綁定後，測試通知會收到，但建立真實預約 Email 有、LINE 沒有，而且完全沒有錯誤紀錄

### 根因：`user_preferences` 裡 `LineNotificationsEnabled = '0'`，加上兩條通知路徑的檢查條件不一致

- **測試通知**（`Presenters/LineTestNotificationPresenter.php`）只檢查 `LineUserId` 是否存在，**不看** `WantsLineNotifications()`，所以照送。
- **建立預約通知**（`lib/Application/Reservation/Notification/OwnerLineNotification.php:45`）是 `if (empty($lineUserId) || !$owner->WantsLineNotifications()) { return; }`。`Domain/User.php:548` 的 `WantsLineNotifications()` = `preferences->Get('LineNotificationsEnabled') !== '0'`，值為 `'0'` 就 `return` —— **沒呼叫 `PushFlex`、沒 LINE API request、沒例外、沒 log**。
- **Email 通知**是完全獨立的一支，不受 LINE 開關影響，所以照送。

三者合起來就是「Email 有、LINE 沒、零紀錄」，且跟這次的 OA 換版、token、Flex payload、防火牆全都無關 —— OA 切換做的都是對的。這筆 `LineNotificationsEnabled='0'` 是測試帳號 `shawn.admin` 本來就有的舊設定（某次在通知設定頁把「接收 LINE 通知」關掉），剛好在端到端測 LINE 時撞出來。

排查時另一個誤導點是 **正式環境 logging 完全沒作用**（見下方「待處理」），讓「錯誤被吞掉」看起來像「有錯但看不到」，實際上這個 case 根本沒有錯誤 —— 是提早 `return`。

`user_preferences` 是 key/value 表（`user_preferences(user_id, name, value)`，`lib/Database/Commands/Queries.php:952`），排查 SQL（密碼留在 `db` 容器的 `$MYSQL_ROOT_PASSWORD`，不出現在指令）：

```bash
docker compose -f docker-compose.prod.yml exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot librebooking' <<'SQL'
SELECT u.user_id, u.username, p.name, p.value
FROM users u JOIN user_preferences p ON p.user_id = u.user_id
WHERE p.name IN ('LineUserId','LineNotificationsEnabled')
ORDER BY u.username, p.name;
SQL
```

### 修法

刪掉那筆偏好（「沒這筆 = 視為開啟」，比留 `'1'` 乾淨）：

```bash
docker compose -f docker-compose.prod.yml exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot librebooking -e "DELETE FROM user_preferences WHERE user_id=2 AND name=\"LineNotificationsEnabled\";"'
```

DB 值即時讀取，不用 recreate。`shawn.admin` 重新建一筆預約，LINE Flex 通知即收到，確認修復。

### 待處理（這次挖出來、還沒解決）

1. **正式環境 logging 無效** —— `config`/`app.env` 都指定 `logging.folder = /var/log/librebooking/log`，但這個目錄在容器裡不存在、也不是 volume，`Log` 寫不進去就默默不寫（連「建不出 log 檔」本身的例外也被吞）。結果是正式環境完全沒有可用的錯誤紀錄，任何問題都看不到。上線前必須修：建立該目錄並在 `docker-compose.prod.yml` 掛成具名 volume（或把路徑改到已存在且持久的位置），確認建立預約 / LINE 推播失敗時真的會寫檔。已列進 `COASIA-GOLIVE-CHECKLIST.md`。
2. **`gary.admin`（user_id 122）的 `LineUserId` 是舊 `@883belmh` channel 的死值** —— 換 OA 後推給他會失敗。要他重新加 `@516khkux` 好友並重新綁定，或直接清掉那筆 `LineUserId`。上線前應該做一次「清空所有舊 channel 的 `LineUserId`、請大家重綁」。
3. **切換過程中 `@516khkux` 的 channel secret / access token 曾出現在螢幕分享／截圖** —— 建議在 LINE Developers Console 對該 channel reissue secret 與 access token，再更新 `config.php`。舊 `@883belmh` 的憑證同樣曝光過，該 OA 棄用前也一併處理。

## 89. 修正式環境 logging 無效（§88 待處理項 1）

### 根因

`lib/Common/Logging/Log.php` 用 Monolog 的 `StreamHandler` 寫 `<logging.folder>/app.log`（`logging.folder = /var/log/librebooking/log`，`config.php`／`app.env` 都是這個值）。Monolog 第一次寫入時才開檔，開檔前會嘗試 `mkdir` 目標目錄——但 `/var/log` 是 root 擁有、`www-data` 沒權限在底下建目錄，`mkdir` 失敗 → `StreamHandler` 丟例外 → 被 `Log::Error()` 的呼叫端吞掉。結果正式環境 `app.log` 從來沒被建出來，任何錯誤都不留紀錄（§88 的 LINE 問題就是因此完全沒有 log 可看）。

開發環境沒踩到，是因為 dev 容器裡這個目錄剛好存在（`Aug 3` 建的，來源不明，可能手動 `mkdir` 過），不是 `Dockerfile.dev` 建的。

### 修法

- **`Dockerfile.prod`**：把 `/var/log/librebooking/log` 加進既有的 `mkdir -p` + `chown -R www-data:www-data` + `chmod -R 770` 那行，讓 image 本身就有這個目錄、且 `www-data` 可寫。
- **`docker-compose.prod.yml`**：`app` 服務新增 `lb_prod_logs:/var/log/librebooking/log` 具名 volume（＋頂層 `volumes:` 宣告）。第一次掛載時從 image 裡那個已 chown 的目錄 seed 權限，之後 `up -d --force-recreate app` 不會弄丟 log。比照既有的 `lb_prod_uploads*` volume 寫法。
- **`Dockerfile.dev`**：也補一行 `mkdir -p /var/log/librebooking/log && chown www-data`，讓全新 dev build 不會靜默無 log（dev 是 bind mount `.`，這個絕對路徑不受影響，不需要 volume）。

`config.php` / `app.env` 的 `logging.folder` 值不動——路徑本身沒問題，問題是目錄不存在。

### 部署

```bash
cd ~/coasia-booking && git pull
docker compose -f docker-compose.prod.yml build app
docker compose -f docker-compose.prod.yml up -d --force-recreate app
```

### 驗證

```bash
# 1. 目錄存在且 www-data 可寫
docker compose -f docker-compose.prod.yml exec -T app sh -c \
  'ls -ld /var/log/librebooking/log && su -s /bin/sh www-data -c "touch /var/log/librebooking/log/.w && rm /var/log/librebooking/log/.w && echo WRITABLE"'

# 2. 暫時開 debug、載入登入頁、確認 app.log 有東西、再改回 error
#    (app.env: LB_LOGGING_LEVEL=debug -> up -d --force-recreate app)
docker compose -f docker-compose.prod.yml exec -T app sh -c 'tail -20 /var/log/librebooking/log/app.log'
```

看到 `app.log` 有寫入 → logging 真的活了。`app.env` 記得改回 `LB_LOGGING_LEVEL=error`。

## 90. 「本機專屬登入」旁邊直接加本機密碼欄位（補 §81.5）

§81 把 AD 登入改成 fail-closed（AD 連不上一律擋），破窗登入靠 §81.5 的「本機專屬登入（緊急管理員）」旗標。但缺一塊：§81.2 讓 `ActiveDirectory::Synchronize()` 永遠給 AD 帳號設隨機本機密碼，所以「勾了旗標的 AD 帳號」其實沒有可用的本機密碼——還要另外去「⋯」選單的「變更密碼」對話框設一次，容易漏。

使用者要求把設密碼的動作直接放進編輯表單，跟旗標同一個地方、同一次存檔。

### 修法（沿用既有 plumbing，沒有新機制）

- `tpl/Admin/Users/user-update.tpl`：「本機專屬登入」checkbox 下方加一個 `type="password"` 欄位（`{formname key="PASSWORD"}`），非必填，留空 = 不變更。
- `Presenters/Admin/ManageUsersPresenter.php::UpdateUser()`：在 `ApplyLocalAuthOnly()` / `ApplyWeComUserId()` 之後，`$this->page->GetPassword()` 非空就呼叫既有的 `manageUsersService->UpdatePassword($userId, $pw)`（那個方法本身已經會 re-check `IsAdmin`、負責加密 + `ChangePassword` + `Update`）。`GetPassword()`／`FormKeys::PASSWORD` 早就存在（`AddUser` 跟獨立的「變更密碼」action 都在用），這裡只是讓 `UpdateUser` 也接。
- 欄位不 gate 在 checkbox 上——設本機密碼對一般 AD 帳號無害（AD 登入仍優先），而且允許「先設密碼再勾旗標」或反過來，同一次送出都成立。
- 語言檔：`AdminUserLocalPassword`／`AdminUserLocalPasswordHint`（en_us／zh_tw／zh_cn）。

### 驗證

- 容器內 `php -l` 過；`composer phpcsfixer:lint`／`composer phpstan` 對修改的檔案全過（既有 4 個 phpcsfixer flag 無關）。
- `composer phpunit --filter ManageUsersPresenter`：29 個測試全過，新增 `testUpdateUserSetsLocalPasswordWhenOneIsProvided`（有填 → 呼叫 `UpdatePassword`）、`testUpdateUserLeavesPasswordUnchangedWhenBlank`（留空 → 不呼叫）。
- 開發環境沒有登入帳密，麻煩使用者登入後確認：編輯使用者頁面「本機專屬登入」下方看得到「本機密碼」欄位，填入 + 存檔後，該帳號能用那組密碼在 AD 故障情境下登入。

## 91. 拿掉「本機專屬登入」的管理 UI —— 全部走 AD

使用者決定不維護緊急管理員／本機登入這條路：「AD 不會掛掉，全部走 AD，本機都不要，不然真的很亂」。§90 才剛加的「本機密碼」欄位、§81.5 的「本機專屬登入（緊急管理員）」checkbox 一起移除。

### 做法：只移除 UI，底層留著（不動 auth 邏輯、不加 migration）

上線前夕不動 AD 認證流程、也不跑 schema 變更，風險最低。移除的只有「管理員能設定這個旗標的入口」：

- `tpl/Admin/Users/user-update.tpl`：拿掉 checkbox 區塊 + §90 的「本機密碼」欄位。
- `Presenters/Admin/ManageUsersPresenter.php`：拿掉 `ApplyLocalAuthOnly()` 呼叫與方法本身、§90 的密碼分支。
- `Pages/Admin/ManageUsersPage.php`：拿掉 `GetIsLocalAuthOnly()` 介面宣告與實作。
- `lib/Server/FormKeys.php`：拿掉 `IS_LOCAL_AUTH_ONLY`。
- `lang/en_us`／`zh_tw`／`zh_cn`：拿掉 `AdminUserLocalAuthOnly`／`Hint`／`AdminUserLocalPassword`／`Hint` 四條。
- 測試：`ManageUsersPresenterTest` 拿掉 §90 兩支測試、`FakeManageUsersPage` 的 `_IsLocalAuthOnly`／`GetIsLocalAuthOnly()`。

### 沒有動、刻意保留的

`ActiveDirectory::Validate()` 現在對**所有帳號**都是 fail-closed（AD 連不上或拒絕一律擋）—— 這本來就是「只認 AD」，是使用者要的結果。以下留著當「不影響行為的死碼＋DB 層最後手段」，不清是因為清了要動 auth plugin + 加 4.x migration + 改 `Jobs/ad-reconcile-account-status.php` 的篩選條件，效益/風險不划算，等上線穩定後再另開一輪：

- `users.is_local_auth_only` 欄位（4.5 migration 加的）—— 現在永遠是 `0`，沒有 UI 會寫它。
- `Domain/User::IsLocalAuthOnly()`／`ChangeIsLocalAuthOnly()`、`UserRepository` 的 `Add()`／`Update()` 串接、`ColumnNames`／`ParameterNames`／`Queries`／`Commands` 的對應常數。
- `ActiveDirectory::Validate()` 裡 `if ($localUser->IsLocalAuthOnly())` 這個分支 —— UI 移除後永遠不會命中，但如果真的哪天 AD 全掛，還能直接 `UPDATE users SET is_local_auth_only=1 WHERE username='...'` + 手動設密碼當破窗，不用改程式碼重部署。
- `Jobs/ad-reconcile-account-status.php` 的 `is_local_auth_only=0` 篩選條件（永遠成立，無害）。
- `tests/Plugins/Authentication/ActiveDirectory/ActiveDirectoryTest.php::testLocalAuthOnlyAccountBypassesLdapEntirely`（測的分支還在，測試仍通過）。

### 驗證

- 容器內 `php -l` 過所有修改檔；`composer phpstan` 0 錯；`composer phpcsfixer:lint` 對這 4 個檔案全過。
- `composer phpunit` 全跑：1730 測試，跟改動前一樣只有 1 個既有、無關的失敗（`ManageReservationsPresenterTest::testUsesTwoWeekSpanWhenNoDateFilterProvided`）。
- **行為影響**：這次移除**不改變任何人現在能不能登入** —— `Validate()` 的邏輯沒動，目前沒被 `is_local_auth_only=1` 標記的帳號（＝所有正常帳號）行為完全一樣。只是以後管理員無法再從 UI 設定這個旗標。

## 92. 開放時間管理併進會議室管理(第一步:入口合併,安全版)

使用者:「會議室管理跟開放使用時間管理應該要合併」。查了正式環境的資料 —— **21 個時間表,每個剛好只掛 1 個資源**(而且很多同名,是「複製會議室」時連帶產生的),等於是 1 對 1,「時間表」這層抽象在這個部署裡沒帶來價值,只是把「一間房 + 它的開放時間」硬拆成兩頁。

### 為什麼分兩步

開放時間的儲存不是單一 API:靠一整組隱藏的 `#changeLayoutForm`(7 天分頁 textarea + 時區 + 快速精靈 + `LayoutValidator`),`schedule.js` 的「儲存」只是把值填進那個隱藏表單再送出(見 `saveScheduleHours()`)。要把編輯器完全內嵌進會議室抽屜,得把那整組 template + `ScheduleManagement` JS 也搬過去、還要在會議室 presenter 載入 layout。這條路徑決定「會議室能不能被預約」,而目前沒有開發環境登入帳密可以點測,上線前夕做整組搬移風險過高。

所以第一步只做**入口合併**(不碰儲存路徑,零風險),完整內嵌留待之後能在 dev 點測時再做。

### 這一步做了什麼

- `tpl/globalheader.tpl`:側邊欄拿掉「開放使用時間管理」連結(換成註解說明去向)。`manage_schedules.php` 本身完全沒動,還能用,只是不再從導覽直接進入。
- `tpl/Admin/Resources/manage_resource_drawer_content.tpl`:實體會議室(非線上會議)的編輯抽屜底部新增「每週開放時間」區塊 + 一顆「編輯開放時間」按鈕,連到 `manage_schedules.php?openSchedule={資源的 schedule_id}`。
- `Web/scripts/admin/schedule.js`:`init()` 尾端讀 `?openSchedule=N`,直接開該時間表的抽屜(重用既有的 `openScheduleDrawer()`)。
- `lang/en_us`／`zh_tw`／`zh_cn`:`AdminResourceEditWeeklyHours`。

管理員體感:進「會議室管理」→ 點房間 → 底部「編輯開放時間」→ 該房的開放時間編輯器直接打開。一個入口,側邊欄不再有另一個項目。

### 驗證

- `manage_resource_drawer_content.tpl` / `globalheader.tpl` Smarty 標籤配對正確;`node --check` + `eslint` 過 `schedule.js`;`php -l` 過三個語言檔。
- 沒有動任何 PHP presenter/page 邏輯,不影響單元測試。
- **待使用者登入確認**:①側邊欄沒有「開放使用時間管理」了 ②實體會議室抽屜有「編輯開放時間」按鈕 ③按下去會開到「對的那間房」的時間表抽屜、存開放時間仍正常。

### 第二步(之後,能在 dev 點測時)

把 7 天開放時間編輯器完全內嵌進會議室抽屜,連換頁都沒有;順便清掉那 21 個同名/複製產生的時間表。

## 93. 開放時間編輯器完全內嵌進會議室抽屜(第二步,承 §92)

使用者:「我希望全部整合在一起才對」。把 §92 的「編輯開放時間」按鈕換成**就地的 7 天開放時間編輯器**,不再換頁。

### 重點:後端零新程式碼,重用時間表頁那條已驗證的 `changeLayout` 路徑

沒有在會議室 controller 加任何存 layout 的邏輯。會議室抽屜裡放一個隱藏的 `<form class="resourceHoursForm" action="manage_schedules.php?action=changeLayout">`,欄位就是 `ManageSchedulesPage` 那個 action 讀的那幾個(`scheduleId`、空的 `USING_SINGLE_LAYOUT` → 走 daily、`TIMEZONE`、`reservableSlots_0..6`、`blockedSlots_0..6`、`CSRF_TOKEN`)。`resource.js` 的 `saveResourceHours()` 把 7 天的 toggle/起訖時間組成 slot 字串(**逐字複製 `schedule.js` 的 `saveScheduleHours()`**,含「開放時段以外用 blocked 補滿 24 小時」那條 `LayoutValidator` 規則),填進隱藏表單,async POST。伺服器端看到的請求跟時間表頁按「儲存」完全一樣。失敗時 `LayoutValidator` 會擋在寫入之前、把錯誤 HTML 回來顯示在抽屜裡,不會靜默存壞。

### 改動

- `Presenters/Admin/ManageResourcesPresenter.php`:列表載入時,對每個時間表多跑一次 `scheduleRepository->GetLayout(id, new ScheduleLayoutFactory(tz))`(跟時間表頁同一個呼叫;這個部署每個資源 1:1 對一個時間表),連同各時間表的 timezone 一起 `BindScheduleLayouts()`。
- `Pages/Admin/ManageResourcesPage.php`:`BindScheduleLayouts($layouts, $timezones)` 介面 + 實作,順便 `Set('DayNames', ...)`。
- `tpl/Admin/Resources/manage_resource_drawer_content.tpl`:實體會議室(非線上會議)抽屜的「每週開放時間」區塊,從單一按鈕換成 7 天 [開關 + `type=time` 起 + 訖] + 「更新」按鈕 + 隱藏的 `.resourceHoursForm`。每天的初始值從 `$ScheduleLayouts[$scheduleId]` 帶出來(算法照抄時間表抽屜)。萬一某資源沒有 layout(理論上不會),fallback 成 §92 的連結按鈕。
- `Web/scripts/admin/resource.js`:`.resourceDayOpen` change → enable/disable該列時間輸入;`.saveResourceHours` click → `saveResourceHours()`。

### 驗證

- `php -l` / `composer phpstan`(0 錯)過 presenter + page;`node --check` + `eslint` 過 `resource.js`(既有的 `AdminConfirm is not defined` 那條在乾淨 HEAD 上就有,跟這次無關);Smarty 標籤配對正確。
- `composer phpunit` 全跑:跟改動前一樣(沒有 `ManageResourcesPresenterTest`,介面新增方法也沒有 fake page 要補)。
- **待使用者登入實測**(這是這次改動的關鍵風險點,我沒有 dev 帳密):①實體會議室抽屜的「每週開放時間」是 7 天就地編輯、初始值正確 ②改一天的時段、按「更新」→ 沒有錯誤、關掉重開值有留下 ③去 Overview/預約頁確認那間房的可預約時段真的跟著變 ④線上會議資源的抽屜不受影響。

### 之後

清掉那 21 個同名/複製產生的時間表(每個資源自己編輯後,同名時間表只是雜訊)。`manage_schedules.php` 頁面本身還留著(不在導覽),之後確認完全沒人需要再評估要不要整支移除。

## 94. 會議室列表直接顯示每週開放時間(承 §93)

使用者:「我希望每週開放時間直接在這邊顯示出來,而不是做一個連結,這樣誰會知道」。§93 把編輯器放進抽屜,但列表上看不到,不夠「整合」。

- `tpl/Admin/Resources/manage_resources.tpl`:列表新增「每週開放時間」欄(在「資源類型」與「狀態」之間),用跟時間表頁列表同一套 星期 chip(週日→週六,開放的那天亮色、hover 顯示時段),資料取自 §93 已經綁好的 `$ScheduleLayouts[$resource->GetScheduleId()]`。空的結果列 colspan 5→6。
- `Pages/Admin/ManageResourcesPage.php`:`BindScheduleLayouts()` 多綁一個 `DayAbbr`(chip 用的星期縮寫)。
- chip 的 CSS(`.admin-schedule-day-chip`)在 `Web/css/admin.css`,所有 admin 頁面共用,不需要另外載。

一眼就能在列表看到每間房的開放時間;點「編輯」進抽屜就地改(§93)。`php -l` / Smarty 標籤配對過;沒有動 PHP 邏輯(只多綁一個 view 變數)。待使用者登入確認 chip 顯示正確、跟抽屜裡的值一致。

## 95. 會議室開放時間 UX 調整(承 §93、§94)

使用者給了 6 點細化要求,一次做完:

1. **線上會議資源不顯示開放時間**。列表那欄:資源是 `ResourceType::IsVideoConference()` 就整格留白(`manage_resources.tpl`)。編輯抽屜:整段本來就包在 `{if !$isVideoConferenceResource}` 裡(§93),已經不顯示。
2. **實體會議室的開放時間改成可展開區塊、預設收合**:`manage_resource_drawer_content.tpl` 把 7 天編輯器包進 `<details class="admin-drawer-advanced">`(跟未分類資源的「進階設定」同一套),`<summary>` 旁邊顯示目前的摘要文字。
3. **「全天開放」快捷鈕**:`.setAllDayHours`(`resource.js`)—— 點一下把 7 天全部勾開、每天時間設成 `00:00`–`00:00`(LibreBooking 的「整天」表示法,對應 `LayoutValidator` 要的「00:00 到 00:00」全覆蓋)、enable 時間輸入。**只改前端欄位,不送出**,使用者仍要按「儲存開放時間」。
   > 註:`<input type="time">` 存不了字面的 `24:00`,所以欄位顯示 `00:00`–`00:00`;摘要與行為上等同「24 小時開放」。
4. **列表欄位改成文字摘要**(取代 §94 的星期 chip):
   - `lib/Application/Schedule/WeeklyHoursSummary.php`(新,純函式):吃 7 天的 `[open, start, end]`,回傳 `all_day` / `weekdays` / `closed` / `varies`。
   - `ManageResourcesPresenter::describeWeeklyHours()`:從每個時間表的 layout 抽出 7 天 tuple → `WeeklyHoursSummary::Describe()` → 轉成在地化字串(`全天開放` / `週一至週五 %s–%s` / `每日設定不同` / `未開放`),`BindScheduleLayouts()` 多帶一個 `$ScheduleHoursSummary`。
5. **基本資料「更新」與開放時間「儲存」維持獨立**:兩個各自的 `<form>` / 按鈕,開放時間走的還是 §93 那條 `manage_schedules.php?action=changeLayout` + `LayoutValidator`,沒有動。
6. **測試**:`tests/Application/Schedule/WeeklyHoursSummaryTest.php` 8 個 case —— 全天(§3 的按鈕產物 = 全 `00:00-00:00` → `all_day`)、`24:00` 也算全天、週一至週五同時段 → `weekdays`、其中一天不同 / 多一天 / 7 天都開但非全天 → `varies`、都沒開 → `closed`、空陣列 → `closed`。線上會議隱藏與全天按鈕是樣板/JS 層,靠實測確認。

### 驗證

- `php -l` / `composer phpstan`(0 錯)過新增/修改的 PHP;`node --check` + `eslint` 過 `resource.js`(既有 `AdminConfirm` 那條無關);Smarty 標籤配對正確。
- `composer phpunit --filter WeeklyHoursSummary` 8/8;全跑跟改動前一樣(只有那 1 個既有無關失敗)。
- **待使用者登入實測**:①線上會議資源(Zoom/Meet)列表開放時間欄留白、抽屜沒有開放時間區塊 ②實體會議室抽屜開放時間預設收合,展開後可編輯 ③「全天開放」按一下 7 天全開、時間歸零,按「儲存開放時間」後列表摘要變「全天開放」、Overview 那間房整天可訂 ④設週一到五 09:00–18:00、週末關 → 列表顯示「週一至週五 09:00–18:00」 ⑤基本資料的「更新」跟開放時間的「儲存」互不影響。

## 96. 會議室抽屜:基本資料與開放時間合併成一顆儲存按鈕(承 §95)

使用者實測 §95 後:「兩個 button 應該要同一個吧,這樣很亂」。原本「更新」(基本資料)和「儲存開放時間」是兩顆。

改成**一顆「更新」按鈕存兩件事**,不動後端:

- `manage_resource_drawer_content.tpl`:拿掉 `<details>` 裡的「儲存開放時間」按鈕(和 `.resourceHoursSaved` 提示);只留 `.resourceHoursError` 顯示驗證錯誤。
- `Web/scripts/admin/resource.js`:
  - `saveResourceHours(editor, done)` 多一個 `done(ok)` callback。
  - 新增 `$('form.resourceUpdateForm .save').on('click', …)`,**綁在既有 `$('.save').click()`(第 263 行)之前**,用 `stopImmediatePropagation()` 搶先:先 async POST 存開放時間(還是走 `manage_schedules.php?action=changeLayout` + `LayoutValidator`),成功才 `form.trigger('submit')` 送出基本資料表單(接著整頁 reload)。
  - 開放時間驗證失敗 → 顯示錯誤、**不送出基本資料、不 reload**,使用者修正後再按一次;基本資料的編輯內容不會掉。
  - 拿掉獨立的 `.saveResourceHours` click handler。

兩件事仍是兩個獨立的 POST / endpoint,只是共用同一顆觸發按鈕。`node --check` + `eslint`(既有 `AdminConfirm` 無關)、Smarty 標籤配對過。待實測:改基本資料 + 改開放時間 → 按一次「更新」→ 兩者都存;開放時間填錯 → 只擋開放時間、基本資料表單不送。

## 97. 開放時間儲存改成 manage_resources.php 自己的 action(修 §93 起就存不進去的問題)

使用者實測回報「按更新沒有真的存」。§89 的 logging 修好後,`app.log` 直接指出兩個獨立的問題:

### 問題 A:`resourceForm` 以 GET 送出 → 名稱存成 null

`<form id="resourceForm{id}" class="resourceUpdateForm">`(§66/§83 建的實體會議室基本資料表單)**漏了 `method="post"`**,頁面上其他 form 都有。jquery-form 沒指定 method 就用 form 的 → 預設 GET。`Server::GetRawForm()` 只讀 `$_POST`,所以 `GetResourceName()` 拿到 null → `ResourceRepository::Update()` 炸 `Column 'name' cannot be null`。這個 bug 從表單建立就在,只是沒人按過「更新」;§96 把抽屜的單一按鈕接上這個表單後才浮現。修:補 `method="post"`(commit `2df06360e`)。

### 問題 B:開放時間 POST 去 `manage_schedules.php?action=changeLayout` 這條 cross-page 不可靠

§93 讓抽屜直接 POST 到時間表頁的 action,實測從來沒真的存進去(`app.log` 完全沒有 `changeLayout` 的紀錄)。cross-page 打另一頁的 action 有太多變數(CSRF scope、page 初始化、路由)。改成**在 `manage_resources.php` 自己開一個 action**:

- `ManageResourcesActions::ActionChangeResourceHours`('changeResourceHours')+ `AddAction` 路由。
- `ManageResourcesPresenter::ChangeResourceHours()`:讀 `scheduleId` / `timezone` / `reservableSlots_0..6` / `blockedSlots_0..6`,`ScheduleLayout::ParseDaily()` → `scheduleRepository->AddScheduleLayout()` —— 跟 `ManageSchedulesPresenter::ChangeLayout()` 一模一樣的後端動作,只是同頁服務。
- `LoadValidators()`:對這個 action 註冊同一個 `LayoutValidator`(需求 §5「保留預約可用性驗證」)。
- `ManageResourcesPage`:`GetLayoutTimezone()` / `GetDailyReservableSlots()` / `GetDailyBlockedSlots()` 三個 getter(比照 `ManageSchedulesPage`)。
- `manage_resource_drawer_content.tpl`:`.resourceHoursForm` 的 `action` 改成 `manage_resources.php?action=changeResourceHours`。JS 不用動(還是 POST `form.attr('action')`)。

順帶查清楚:`schedules` 表根本**沒有 `timezone` 欄位**,`Schedule::GetTimezone()` 空值時 fallback 到 `Configuration` 的預設時區,所以 timezone 一定非空,不是 bug 來源。

### 驗證

- `php -l` / `composer phpstan`(0 錯);`composer phpunit` 全跑跟改動前一樣(只有那 1 個既有無關失敗);Smarty 標籤配對正確。
- **待實測**:①改基本資料按「更新」→ 名稱/狀態真的存進去(不再 GET/null)②改開放時間按「更新」→ `app.log` 有 `ChangeResourceHours: scheduleId=…`、列表摘要跟著變、Overview 那間房可預約時段跟著變 ③開放時間填不合法 → `LayoutValidator` 擋下、顯示錯誤、基本資料不送。
