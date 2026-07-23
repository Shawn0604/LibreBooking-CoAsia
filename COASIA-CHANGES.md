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
| `Presenters/OverviewPresenter.php` **(新增)** | 查一週範圍內、**所有資源**的預約（`ReservationViewRepository::GetReservations()`，不限定 schedule/resource），依「星期幾＋09:00–18:00 業務時間」換算每筆預約色塊的位置百分比；資源顏色優先用後台設定的 `BookableResource::GetColor()`，沒設定的話用一組固定的備用色盤（跟 Task 4 mockup 的分類色一致） |
| `Pages/OverviewPage.php` **(新增)** | 標準 `SecurePage` 頁面，處理 `?sd=` 週起始日參數、把 Presenter 算好的資料丟給樣板 |
| `Web/overview.php` **(新增)** | 入口檔案 |
| `tpl/Overview/overview.tpl` **(新增)** | 週檢視樣板：週導航（上一週/下一週/今天）、資源篩選標籤、7 天 × 09:00-18:00 格子、色塊 |
| `Web/css/overview.css` **(新增)** | 這個頁面專屬的樣式（不影響其他頁面），沿用 Task 4 Phase 1 已經建立的 `--primary`/`--reservedMine`/`--bs-border-radius` 等變數 |
| `Web/scripts/overview.js` **(新增)** | 資源標籤篩選（純前端顯示/隱藏，不用重新打 API） |
| `Pages/Pages.php` | 新增 `OVERVIEW` 路由常數 |
| `tpl/globalheader.tpl` | 選單「時間表」底下加一個「總覽」連結 |
| `lang/en_us.php` / `lang/zh_tw.php` | 新增 `Overview`/`Weekend`/`Untitled`/`YourReservations` 翻譯字串 |

### 點擊行為——重用既有流程，沒有另外寫訂位邏輯

- 點色塊（既有預約）→ 導去現有的 `reservation.php?rn=...`（跟一直以來測試用的查看/編輯頁完全同一支）
- 沒有另外做「快速新增」表單去呼叫 `ajax/reservation_save.php`——這樣要重新實作 `IReservationSavePage` 介面（將近 30 個方法），風險/工作量都不划算。目前這版**還沒有**「點空白格子直接新增」的功能，這是接下來可以做的加強項目

### 驗證方式

實際在瀏覽器測試：週範圍計算正確（含跨月份邊界）、所有既有測試資料的預約都正確依資源分色顯示、資源篩選標籤能正確篩選（14 筆篩到 2 筆，跟資料庫實際筆數吻合）、點色塊連結正確導到 `reservation.php?rn=...`、上一週/下一週導航網址正確、瀏覽器 console 跟 PHP log 都沒有錯誤。

### 還沒做的（可以再加強）

- 點空白格子直接帶資源/時間新增預約（目前要點進資源篩選或既有預約表單自己選）
- 週檢視以外的「日檢視」「清單檢視」（目前只有週檢視這一種）
- 後台目前沒有幫任何資源設定顏色（`BookableResource::SetColor()`），所以現在看到的顏色都是備用色盤，不是刻意指定的——需要的話可以去後台「管理資源」個別設定

---

## 8. 尚未處理的部分（spec 裡還沒動工的項目）

| 項目 | 狀態 |
|---|---|
| **Section 5.1 — 正式 Docker 部署（`docker-compose.yml`）** | 目前只有本機測試用的 `docker-compose.dev.yml`，spec 要求的正式版（用官方發布的 image、搭配 `.env`）還沒建立 |
| **Section 5.2 — AD/LDAP 設定** | 需要真實的 IT 提供的 AD 服務帳密、Base DN 等，無法在這邊代為設定 |
| **Section 5.3 — HTTPS（Let's Encrypt + Cloudflare DNS-01）** | 需要真實網域、正式主機、Cloudflare API Token |
| **Section 5.4 — 正式 Cron 設定** | 需要正式主機才能設定真的 crontab（見上方 Task 3 備註） |
| **Section 6 — Admin Panel 設定清單** | 大部分是純後台操作（建 Schedule、Custom Attributes、Resource Group、填 video_url 等），需要在正式環境重新做一次（本機開發環境已經手動做過一份測試用的） |
