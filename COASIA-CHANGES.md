# CoAsia 客製化說明文件

本文件記錄根據 `SPEC_LibreBooking_CoAsia.md` 對 LibreBooking（`develop` branch fork）所做的修改，對應 spec 的 Task 1〜3。目標是讓使用者在預約「Video Conference 資源」（Zoom TW / Zoom CN / Zoom Oversea / Google Meet1 / Google Meet2）時，確認頁、確認信、提醒信都能自動帶出該資源的視訊連結，實體會議室預約則完全不受影響、不顯示任何視訊區塊。

Repo：https://github.com/Shawn0604/LibreBooking-CoAsia（`origin`，私人）
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
