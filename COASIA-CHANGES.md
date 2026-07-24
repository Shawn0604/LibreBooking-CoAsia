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
