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
