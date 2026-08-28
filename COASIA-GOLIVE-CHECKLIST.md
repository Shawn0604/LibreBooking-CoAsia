# CoAsia LibreBooking 正式上線待辦清單

本文件記錄「把系統從目前的開發/測試狀態，變成真正開放給全體員工使用」還需要處理的事項。跟 `COASIA-CHANGES.md`（記錄已經做了什麼、為什麼這樣做）不同，這份是**還沒做、上線前要處理**的清單，會隨進度持續更新。

最後更新：2026-08-27

---

## ✅ 已解決

### 正式環境部署 ＋ HTTPS
`COASIA-DEPLOYMENT.md` 記錄的部署工作已完成：正式主機 `BookingServer`（`10.1.8.32`）建好，`Dockerfile.prod`／`docker-compose.prod.yml`／`Caddyfile` 都已寫好並跑起來（`app`／`db`／`caddy` 三個服務），資料庫已從測試環境搬過去。HTTPS 用公司 AD CS 簽發的正式憑證（`coasia-CSSRV-CA`），瀏覽器打開 `https://booking.coasia.com.tw` 完全不再跳警告，登入頁、AD 帳密登入、管理中心角色切換都已用瀏覽器實際驗證過。LINE webhook 也從臨時的 Cloudflare Quick Tunnel 換成固定網域的正式 Tunnel，已接上 LINE Developers 後台。詳細架構與操作記錄見 `COASIA-DEPLOYMENT.md`。

### LINE 推播已實測成功
原本懷疑是 LINE 官方帳號後台「聊天模式」設定攔截了訊息，實際排查後發現**根本原因不是那個**——是本機測試用的 Cloudflare Quick Tunnel 網址（`cloudflared tunnel --url`）已經失效（原本開著的通道斷了，這種免費通道每次重開都會換一個新網址）。換一個新的 tunnel 網址、更新回 LINE 後台的 Webhook 設定後，webhook 直接收到 LINE 送來的請求並回應成功（HTTP 200），資料庫也確認帳號真的綁定上了（`LineUserId` 有正確寫入）。之後實際建一筆預約，也確認收到 LINE 推播通知。

同時修掉一個順手發現的邊角案例：如果同一支手機的 LINE 身份先後拿去綁不同的系統帳號，原本會讓舊帳號留下一個「看起來還綁著、但其實收不到通知」的殭屍狀態——已經改成後綁的會自動頂掉舊的綁定，並補了單元測試（詳見 `COASIA-CHANGES.md`）。

**正式上線前還是要注意**：這次測試用的 Cloudflare Quick Tunnel 只適合臨時驗證，不是長久方案——正式環境要有一個固定、不會斷的網址，LINE webhook 才會穩定運作（見下方「正式環境部署」）。

**2026-08-27 更新**：LINE 官方帳號從輕量級的 `@883belmh` 換成中量級方案 `CoAisa擎亞電子`（`@516khkux`），因為全公司上線後推播量會超出輕量級免費額度。切換 + 換完後預約通知不推的排查（根因是測試帳號 `LineNotificationsEnabled='0'`）詳見 `COASIA-CHANGES.md` §88。**上線前收尾**：(1) 清掉所有舊 `@883belmh` channel 的 `LineUserId` 死值、請已綁定的人重新綁定新 OA；(2) 因切換過程中憑證曾出現在螢幕分享，把 `@516khkux` 的 channel secret / access token reissue 一次。

### SMTP 寄信帳號已改接公司內部伺服器
已改接公司內部 SMTP Relay（`config/config.php` 的 `phpmailer`／`email` 區塊）：Host `10.1.3.140:25`，內網 open relay，不需要帳密／TLS，寄件人固定為 `coasiaerp@coasia.com`（顯示名稱「擎亞會議室預約系統」），不再掛在任何個人帳號底下。已用兩種方式驗證過：(1) 直接建構一支走 `PHPMailer` 的腳本連到 `10.1.3.140:25` 寄出，成功；(2) 直接呼叫專案自己的 `EmailService` 搭配 `Configuration::Instance()` 讀出的設定值寄出，同樣成功、應用程式的 error log 沒有出現任何寄信失敗紀錄。

### AD 網域登入已打通並實測成功（但目前是暫時的相容設定，見下方 🔴）
`plugins.authentication` 已切到 `ActiveDirectory`，且已經有真人（`shawn.admin`）用自己的 AD 密碼實際登入成功，過程中修掉兩個真正會擋住上線的既有 bug：(1) LDAP 服務帳號連線本身失敗時，vendor plugin 完全不會走資料庫 fallback，導致 AD 一連不上、連本機帳號都登入不了——已修成連線失敗一律檢查 `database.auth.when.ldap.user.not.found` 決定要不要 fallback；(2) `mail` 屬性為空的 AD 帳號登入後，PHP 8.1+ 的 deprecated 警告文字被印進回應本文，卡住登入成功後的 redirect（出現「headers already sent」）——已修成 `mail` 缺值時存空字串。

從資料庫直接比對登入前後的紀錄（全程沒有經手任何密碼）確認四項核心行為都正常：一般帳密登入、既有本機管理員 fallback、AD 使用者首次登入自動建立帳號（且新帳號預設一般員工權限，不是管理員）、`shawn.admin` 用 AD 登入後 `Application Administrators` 群組身分完整保留（`sync.groups=false` 確實保護了既有管理員權限）。詳見 `COASIA-CHANGES.md` §26–27。

### 正式帳號建立：112 位 CoAsia 員工已預先匯入
用 HR/AD 匯出的員工名單（`Jobs/import-coasia-employees.php`）批次建立帳號，讓從未登入過系統的員工也能立即被「新增參與者」搜尋到——第一次真的用 AD 登入時，`Registration::Synchronize()` 會判定 username 已存在而自動接上這筆預建帳號，不會產生第二筆。

`users` 表已從 2 筆變成 114 筆，全部 `status_id=ACTIVE`、只加進非管理員的預設群組「全體員工」，密碼是隨機產生後即丟棄的雜湊值（不是共用預設密碼）。已驗證安全重跑（同一批人跑第二次不會重複建立）、且不會誤動既有帳號（`shawn.admin`／`mark_li` 的密碼雜湊與群組全程沒被動過）。詳見 `COASIA-CHANGES.md` §43。

**還沒做的部分見下方「應該處理」**——這批匯入是一次性動作，HR 名單之後還會更新，且有一筆已知衝突（`shawn_tsai`）當時刻意跳過沒處理。

### 兩個一直沒人查的既有 PHPUnit 失敗（已查出根因並修復）
`ResourceCrossDayRuleTest`（Error）和 `OverviewFullCalendarEventsPresenterTest`（Failure）都不是真的功能缺陷：前者是 `ResourceCrossDayRule` 對 `ScheduleRepository::LoadById()` 回傳 `null`（資源指向不存在的時間表）沒有防呆，直接呼叫 `GetTimezone()` 崩潰，順便修成退回系統預設時區；後者是測試自己斷言錯（`FakeResources::GetString()` 一律回傳原始 key，不會真的翻譯，舊測試卻期待翻譯後的中文字串）。兩個都已修好，全專案 PHPUnit **1725/1725 全數通過**，是這個專案第一次完全乾淨。詳見 `COASIA-CHANGES.md` §75、`COASIA-TEST-REPORT.md`。

### 員工教學／使用說明（已完成）
新增首次登入 7 步驟導覽（自動跳出＋側邊欄「幫助」隨時重播）與完整使用手冊（一般使用者／管理員分區，繁中／簡中／英文三語言）。詳見 `COASIA-CHANGES.md` §71。

### 資料庫每日備份（已完成）
**2026-08-28**：[`scripts/db-backup.sh`](scripts/db-backup.sh)（`mysqldump` 全庫 + uploads volume + 不在版控的機密設定，30 天 rolling）已設進 `shawn_admin` 的 crontab（每晚 02:30），備份寫到 Synology `CETP-SVR-NAS03`（`10.1.3.151`）的 `NetBackup/Booking_backup_from_10.1.8.32/`，走 NFS（fstab 開機自動掛）。首次手動執行成功，[`scripts/db-restore-drill.sh`](scripts/db-restore-drill.sh) 還原演練通過（`users`／`reservation_series`／`reservation_instances`／`resources`／`schedules`／`groups` 六張表筆數一致）。備份與還原完整說明見 [`COASIA-BACKUP-RESTORE.md`](COASIA-BACKUP-RESTORE.md)。

**仍待確認**：Synology Active Backup for Business 有沒有把 `BookingServer` 這台 VM 也納入 agentless VMware 備份（整機層級的第二道保險，非必要但有更好）。

### 正式環境 logging 修好（fix 已 commit，待部署）
**2026-08-28**：`Dockerfile.prod` 建好 `/var/log/librebooking/log`（chown www-data），`docker-compose.prod.yml` 加 `lb_prod_logs` 具名 volume。原本 Monolog 因為這個 root 擁有的目錄建不出來，`app.log` 從來沒被寫過（§88 的 LINE 問題就是因此完全沒 log 可看）。詳見 `COASIA-CHANGES.md` §89。

**待部署**：VM 上 `git pull` → `docker compose -f docker-compose.prod.yml build app` → `up -d --force-recreate app`，然後照 §89 的驗證步驟確認 `app.log` 真的會寫。

---

## 🔴 必須解決（會直接卡住上線）

*目前無。* 資料庫備份、logging 兩個原本的硬阻擋都已處理（logging 的 fix 待部署）；AD 明文 LDAP 已評估為可接受風險（見下方 🟡）。

---

## 🟡 應該處理（重要，但不會卡上線）

### 資源權限政策要跟業務單位確認
目前「全體員工」群組是**全部 12 個資源都開放給所有人預約**（含高爾夫球室等特殊資源）——這是用來解決權限漏洞的技術方案（讓新帳號、AD 帳號自動繼承權限），但「哪些資源該限定特定部門」是業務決策。建議上線前跟相關主管確認一輪；要限制的話後台「ManageGroups」就能直接調整，不需要改程式碼。

### 測試資料清理
目前資料庫裡有 57 筆預約，一大半是測試留下的（26 筆空白標題、4 筆「test」、4 筆「tt」，其餘是各種「Task1/2/3 ... Test」字樣的功能驗證紀錄）——正式上線前要整批清掉，避免員工第一眼看到的行事曆一團亂。

**注意**：後台「資料清理」（`Pages/Admin/DataCleanupPage.php`）雖然是真的能動的功能，但只能按「日期」篩選要刪除的預約，這 57 筆測試資料是靠**標題內容**辨識（空白標題、"test"、"tt" 這些字樣），不是集中在某個日期區間。除非這些測試資料剛好都是最舊的、日期篩選能一次篩掉，不然沒辦法只靠這個工具清乾淨，還是得手動下 SQL 或寫一支一次性腳本按標題篩選。

### 使用者管理有並發編輯衝突會靜默覆蓋的問題
`COASIA-DEPLOYMENT.md` 有記一筆：兩個管理員同時編輯同一個使用者，其中一人的修改可能會被默默覆蓋掉、沒有任何警告或衝突提示。使用者不多的時候機率低，但全面推廣、管理員變多之後這個機率會上升，值得評估要不要加個簡單的樂觀鎖或提示。

### 員工帳號匯入的收尾事項
- `shawn_tsai`（Excel 算出來的 username）跟現有 `shawn.admin`（email 相同）帳號衝突，匯入時刻意跳過沒處理——需要人工決定要不要合併／忽略。
- 這次是一次性匯入（吃 `D:\Downloads` 底下的 `.xls` 匯出檔）；HR 名單之後還會定期更新（目前已經有 06-08、07-15、07-31 三份），需要決定要不要排程定期重跑 `Jobs/import-coasia-employees.php`，還是每次都手動觸發。
- `ad_object_guid` 欄位已經建好但目前沒有任何流程真的去寫值（匯入來源沒有這個欄位、AD 登入路徑也還沒接上即時讀取）——如果之後想用它做更精準的帳號比對，需要額外一輪開發。

### 正式帳號建立（剩餘部分）
AD 打通後，一般員工可以直接用 AD 帳密登入自動建立/接上帳號；上表 112 筆匯入的員工也已經能被搜尋。剩下需要人工確認的只有：AD 裡除了 `shawn.admin` 以外，其他需要管理員權限的人選要不要／怎麼授予（目前只有 `shawn.admin` 在 `Application Administrators` 群組裡）。

### 真實使用者驗收測試（UAT）
目前只有內部測試過（含開發過程中用 `mark_li` 帳號實測建立預約＋新增參與者的完整流程），還沒有真正跨部門的員工在自己的帳號上實際用過。建議找幾位不同部門的同事試用一輪——尤其是預約流程、LINE 通知、資源權限範圍——抓出自己想不到的問題。

這不是假設性的擔心：使用者管理「最後登入」時間顯示錯誤（差 7 小時，見 `COASIA-CHANGES.md` §67）這個 bug 一直存在到最近才被發現回報，代表這種「一般使用不會馬上發現、但確實是 bug」的問題現在應該還有沒被抓出來的，繼續自己測不如找真實使用者試用來得有效率。

### 近期新功能還沒經過廣泛測試
「會議室管理依類型簡化」「系統診斷儀表板」（`COASIA-CHANGES.md` §66）都是最近才做的，目前只有開發者自己在 Docker 開發環境裡點過幾輪，還沒有其他人實際用過。如果打算近期就全面推廣，這兩塊值得再多留意一下，或是先讓熟悉系統的人試用確認沒問題再推。

更晚近的一批（§71–§75：首次登入導覽、使用手冊、語言/時區選單簡化、行事曆色塊顯示預約者名字、Quick Create 防重複送出、自動更新）自動化測試（PHPUnit、併發測試框架）都已跑過且全數通過，但一樣還沒有其他人用真實帳號在瀏覽器裡完整走過一輪——尤其是簡體中文／英文介面實際操作（`COASIA-TEST-REPORT.md` T26／T27）跟多帳號同時使用（T33–T35），這幾項需要真人跟多個帳號才能驗證，見 `COASIA-TEST-REPORT.md` 的完整清單。

### AD bind 走明文 LDAP（389）— 已評估為可接受，延後處理
原本列在 🔴。現有三台 DC 的 LDAPS（636）只支援 TLS 1.0、憑證已過期，636 交握會被斷線；要 IT 更新 DC 憑證＋開啟 TLS 1.2 才能改回，短期做不到。**2026-08-27 使用者確認：公司所有內部系統的 AD bind 目前都走 389**，這套系統走 389 跟現有架構一致、不是新增的曝險面，因此決定接受現況、不擋內部上線。服務帳號（`robot_co`）密碼在 app↔DC 的內網路徑上明碼傳輸，威脅模型是「能側錄內網流量者」，對整個組織早已成立。

**延後處理（等 IT 修好 DC 憑證後）**：把 `plugins/Authentication/ActiveDirectory/ActiveDirectory.config.php` 的 `port` 改回 `636`、`use.ssl` 改回 `'true'`（設定檔已留還原註解，見 `COASIA-CHANGES.md` §27）；同時把測試用的 `robot_co` 換成 IT 建立的專用唯讀服務帳號（例如 `svc_librebooking`）。

---

## 🟢 可以之後再處理

- **舊版總覽頁 `overview.php` 何去何從**：目前完整保留、可獨立訪問，還沒決定要不要整支移除，可以先觀察新版穩定一陣子再決定
- **上線溝通計畫**：要不要有新舊並行的過渡期、什麼時候正式宣布、上線後問題找誰，這幾件事建議先想清楚
- **側邊欄收合/展開的過渡動畫**：目前收合時文字/圖示是瞬間切換，視覺上有點跳，不影響功能，之後有空可以再打磨
