# CoAsia Booking 系統架構

這份文件是給要維護這個系統的人看的技術參考——前端、後端、資料庫三層各自怎麼組織、request 怎麼流過整個系統、資料庫實際有哪些表。跟其他 `COASIA-*.md` 文件的分工：`COASIA-CHANGES.md` 記錄「改了什麼、為什麼」，`COASIA-DEPLOYMENT.md` 記錄「部署架構、怎麼操作」，這份記錄的是「系統本身長什麼樣子」——寫這份文件的直接動機，是有一次除錯花了很多來回才搞懂 `dbversion`／`resource_groups`／`resources.location` 這幾張表的用途跟關聯，早一點有這份文件就能省下不少查證時間。

最後更新：2026-08-19

---

## 目錄

- [系統概觀：沒有 router，每個 `Web/*.php` 就是一條路由](#系統概觀沒有-router每個-webphp-就是一條路由)
- [後端架構](#後端架構)
- [資料庫](#資料庫)
- [前端架構](#前端架構)

---

## 系統概觀：沒有 router，每個 `Web/*.php` 就是一條路由

跟現代框架不同，這個專案**沒有中央路由表**。`Web/` 底下每一個 `.php` 檔案本身就是一條路由，內容幾乎都只有 5-10 行：定義 `ROOT_DIR`、`require_once` 對應的 Page 類別、`new` 一個實例、呼叫 `PageLoad()`。例如完整的 `Web/overview_fullcalendar.php`（全部 8 行）：

```php
define('ROOT_DIR', '../');
require_once(ROOT_DIR . 'Pages/OverviewFullCalendarPage.php');
$page = new OverviewFullCalendarPage();
$page->PageLoad();
```

或 `Web/admin/manage_resources.php`（全部 9 行）：

```php
define('ROOT_DIR', '../../');
require_once(ROOT_DIR . 'Pages/Admin/ManageResourcesPage.php');
require_once(ROOT_DIR . 'Presenters/Admin/ManageResourcesPresenter.php');
$page = new AdminPageDecorator(new ManageResourcesPage());
$page->PageLoad();
```

目錄慣例：

| 位置 | `ROOT_DIR` | 數量 | 慣例 |
|---|---|---|---|
| `Web/*.php` | `'../'` | 42 | 一般使用者頁面，Page 類別繼承 `SecurePage`（建構子內做登入檢查）或 `Page` |
| `Web/admin/*.php` | `'../../'` | 39 | 包一層 `new AdminPageDecorator(...)`——是這個 decorator（不是 Page 本身）在檢查管理員權限 |
| `Web/ajax/*.php` | `'../../'` | 27 | Page 類別放在 `Pages/Ajax/`，用 `Page::SetJson()` 回傳 JSON，不呼叫 `Display()` |
| `Web/reports/`、`Web/export/`、`Web/install/`、`Web/integrate/`、`Web/Services/` | | | 同樣的模式，依功能分資料夾 |

單一頁面內部的「子路由」是靠 querystring，不是 URL path：`ActionPage::PageLoad()`（`Pages/ActionPage.php:23-38`）依 `?action=` 分派到 `ProcessAction()`、`?dr=`（data request）分派到 `ProcessDataRequest()`，都沒有的話才走 `ProcessPageLoad()`。Action 名稱是字串常數，例如 `ManageResourcesActions`（`Presenters/Admin/ManageResourcesPresenter.php:15-49`，光這一頁就定義了約 30 個 action）。

---

## 後端架構

### 三層分工：Page → Presenter → Domain/Repository

- **Page**：視圖轉接層。持有 Smarty 實例，讀取 HTTP 輸入（`GetForm`／`GetQuerystring`／`GetCheckbox`，`Pages/Page.php:329-388`），對外暴露宣告在 `IXxxPage` 介面上的 `GetXxx()`／`SetXxx()`，最後呼叫 `Display('xxx.tpl')`。Page 的建構子會自己 `new` 一個 Presenter，把具體的 Repository 實例注入進去。
- **Presenter**：真正的邏輯層。只依賴 `IXxxPage` 介面跟各種 Repository 介面，完全不碰 `$_POST`、不碰 Smarty。
- **Repository**（`Domain/Access/`）：SQL 層。回傳 Domain 物件。

#### 為什麼要多一層 `IXxxPage` 介面：可測試性

每個 Presenter 的建構子都只型別化到介面（例如 `OverviewFullCalendarPresenter.php:62-72` 只接受 `IOverviewFullCalendarPage, IResourceRepository, IAttributeRepository, IPermissionServiceFactory, IUserRepository`）。測試時就能換上手寫的假物件——`tests/fakes/` 底下有將近 60 個（`FakeResourceRepository.php`、`FakePermissionService.php`、`FakeResourceGroupTree.php`、`FakePageBase.php`、`FakeUserRepository.php`、`FakeConfig.php`、`DBFakes.php`……），加上針對各個 Page 介面的 PHPUnit mock。Presenter 測試放在 `tests/Presenters/`。**這也是為什麼是 Page 去 `new` Presenter，而不是反過來**——Page 是組裝根（composition root），Presenter 對任何具體實作都沒有硬依賴。

### 追蹤範例 A：`Web/overview_fullcalendar.php`（純讀取）

| 步驟 | 檔案:行號 |
|---|---|
| 入口：`new OverviewFullCalendarPage()` → `PageLoad()` | `Web/overview_fullcalendar.php:6-8` |
| Page 建構子：`parent::__construct('Overview')` → `SecurePage` 未登入會直接導向登入頁 | `Pages/OverviewFullCalendarPage.php:23`；`Pages/SecurePage.php:12-16` |
| Page 建構子注入 4 個具體依賴到 Presenter | `Pages/OverviewFullCalendarPage.php:25-31` |
| `PageLoad()` → `$presenter->PageLoad(ServiceLocator::GetServer()->GetUserSession())` | `Pages/OverviewFullCalendarPage.php:35` |
| Presenter：`PermissionServiceFactory->GetPermissionService()` 過濾使用者能看到的資源，再呼叫 `ResourceRepository::GetResourceGroups()` | `Presenters/OverviewFullCalendarPresenter.php:74+`；`Domain/Access/ResourceRepository.php:377` |
| Repository → `GetAllResourceGroupsCommand` + `GetAllResourceGroupAssignmentsCommand` → `ServiceLocator::GetDatabase()->Query()` | `ResourceRepository.php:383-384` |
| Presenter 透過介面回呼：`$this->page->SetResourceGroups(...)` | `IOverviewFullCalendarPage` |
| Page 實作成 Smarty `assign` | `Pages/OverviewFullCalendarPage.php:39-45` |
| `$this->Display('Overview/overview_fullcalendar.tpl')` | `Pages/OverviewFullCalendarPage.php:36`；`Pages/Page.php:430` |

這個頁面有個排序上的小陷阱值得記錄：地區群組的顯示順序**不是資料庫排出來的**——`Queries.php:443` 是照字母排序，實際的「台北→深圳→上海→線上」順序是寫死在 `OverviewFullCalendarPresenter.php:35` 的 `GROUP_DISPLAY_ORDER`。

### 追蹤範例 B：`Web/admin/manage_resources.php`（讀取＋寫入）

| 步驟 | 檔案:行號 |
|---|---|
| 入口：`new AdminPageDecorator(new ManageResourcesPage())` → `PageLoad()` | `Web/admin/manage_resources.php:8-9` |
| 介面家族先於類別宣告：`IUpdateResourcePage`（約 40 個 getter）在 `Pages/Admin/ManageResourcesPage.php:11`，接著 `interface IManageResourcesPage extends IUpdateResourcePage, IActionPage, IPageable` 在第 169 行 |
| `class ManageResourcesPage extends ActionPage implements IManageResourcesPage` | `Pages/Admin/ManageResourcesPage.php:487` |
| `ActionPage::PageLoad()` 依 `?action=` 分派 | `Pages/ActionPage.php:23-38` |
| 沒有 action → `ProcessPageLoad()` → presenter → `Display('Admin/Resources/manage_resources.tpl')` | `Pages/Admin/ManageResourcesPage.php:541-545` |
| 有 action → `ProcessAction()`（第 596 行）把 action 字串對應到 presenter 方法（`Add`、`ChangeLocation`、`BulkUpdate`、`ChangeResourceGroups`……） | `Presenters/Admin/ManageResourcesPresenter.php:254-970` |
| 同一個 Page 也負責 render 各種 drawer/分頁的 partial 樣板 | `manage_resources_duration.tpl`（957）、`_capacity`（966）、`_access`（975）、`_groups`（986）、`_credits`（1070）、`show_resource_qr.tpl`（1078）、`_group_permissions`（1157）、`_user_permissions`（1163）、`_public`（1169） |

`IPageable`（`Pages/IPageable.php`）是第三個介面，提供 `PageableDataStore` 用的分頁/排序契約。

### 目錄地圖

| 目錄 | 用途 |
|---|---|
| `Pages/` | 視圖轉接層＋`IXxxPage` 介面。繼承鏈：`IPage` → `Page`（抽象，持有 Smarty；`Pages/Page.php:15`）→ `SecurePage`（登入檢查）／`ActionPage`（querystring 分派）→ 具體類別。Decorator：`AdminPageDecorator`、`SecureActionPageDecorator`（`Pages/SecurePage.php:25`）。子目錄跟功能對應：`Admin/`、`Ajax/`、`Authentication/`、`Credits/`、`Export/`、`Install/`、`Integrate/`、`Reports/`、`Reservation/`、`Search/` |
| `Presenters/` | 所有頁面邏輯，子目錄結構同上，另外多了 `Calendar/`、`Dashboard/`、`Line/`、`Schedule/`。`ActionPresenter` 是有 action 分派需求的 presenter 基底類別。也放了 `Install/Installer.php` |
| `Domain/`（根目錄） | **實體／聚合根**——有身份、有行為的可變物件：`ReservationSeries.php`、`ExistingReservationSeries.php`、`BookableResource.php`、`User.php`、`Group.php`、`Schedule.php`、`Blackout.php`、`Quota.php`、`ResourceGroup.php`、`ResourceType.php`。也包含 `*View.php` 這種唯讀 DTO（`ReservationItemView`、`ReservationView`）跟 `Domain/Events/` |
| `Domain/Access/` | **Repository**——唯一會直接碰資料庫的地方。每個聚合根一個（`ResourceRepository`、`ReservationRepository`、`UserRepository`、`ScheduleRepository`、`AttributeRepository`、`VideoConferenceHostRepository`……），加上基礎設施輔助類別 `PageableDataStore.php`、`DomainCache.php`、`ReportCommandBuilder.php`、`AttributeFilter.php` |
| `Domain/Values/` | **值物件**——沒有身份、通常不可變。兩種：(a) 型別安全的列舉/常數集合（`ReservationStatus`、`ResourceStatus`、`RoleLevel`、`DayOfWeek`、`SeriesUpdateScope`、`ResourcePermissionType`、**`ResourceLocation`**）；(b) 小型組合物件（`FullName`、`AttributeValue`、`EmailPreferences`、`VideoConferenceInfo`）跟報表用的唯讀模型（`CreditLogView`、`TransactionLogView`）。**跟 `Domain/Access/` 的分工**：`Domain/Access/` 回答「怎麼載入/儲存 X」，`Domain/Values/` 回答「X 合法的值有哪些、怎麼比較/排序/顯示」 |
| `lib/Application/` | 橫跨多個聚合根的**服務層**，位階在 Repository 之上、Presenter 之下。子目錄：`Admin/`（批次操作、CSV 匯入、`ManageReservationsService`）、`Attributes/`（`AttributeService`、表單解析、驗證）、`Authentication/`（`Authentication.php`、`CSRFToken`、`AccountActivation`、`CaptchaService`、訪客帳號）、`Authorization/`（`PermissionService`、`AuthorizationService`、`ResourcePermissionStore` 及其工廠類別）、`Reservation/`（最大的子目錄——驗證規則、通知策略、initializer、`Concurrency/`）、`Schedule/`（行事曆組裝、ICS 訂閱）、`Reporting/`、`Resources/`（`VideoConferenceCredentialEncryption.php`）、`Export/`、`Styling/`、`User/` |
| `lib/Database/` | 命令層（見下方「沒有 ORM」小節）：`Database.php`、`DatabaseFactory.php`、`IDbConnection`、`IReader`、`SqlCommand.php`、`SqlFilter.php`、`Parameter(s).php`、`MySQL/` 驅動、`Commands/`（`Queries.php`、`Commands.php`、`ColumnNames.php`、`ParameterNames.php`、`TableNames.php`） |
| `lib/Common/` | 橫切關注的基礎設施：**`ServiceLocator.php`**（全域存取 DB／Server／EmailService，這個專案的 DI 替代方案）、**`SmartyPage.php`**（Smarty 子類別）、**`PluginManager.php`**、`Resources.php`（多語言字串查找）、`Date.php`／`DateRange.php`／`Time.php`／`TimeInterval.php`（時區感知的日期型別）、`Logging/`、`Security/`、`Validators/`、`Converters/`、`SmartyControls/`、`Helpers/`、`ErrorMessages.php`、`RedirectUrlSanitizer.php` |
| `lib/Email/` | `EmailService.php`（PHPMailer 包裝）、`IEmailService`、`EmailMessage.php`、`SmartyEmail.php`（渲染 `tpl/Email/*.tpl`）、`EmailAddress.php`、`EmailLogger.php`、`Messages/`（每種通知一個類別） |
| `lib/Server/` | HTTP 請求/回應抽象層：`Server.php`（`ServiceLocator::GetServer()` 回傳的 `IServer` 實作）、`UserSession.php`、`Cookie.php`／`CookieKeys.php`、`Url.php`、`UploadedFile.php`，以及鍵名常數類別 `Actions.php`、`FormKeys.php`、`QueryStringKeys.php`、`SessionKeys.php`、`RouteParamsKeys.php` |

另外相關但沒詳細展開：`lib/Config/`（見下方「Config 載入」小節）、`WebServices/`（REST API，跟 `Web/` 是分開的）、`Jobs/`（cron 腳本，含 `import-coasia-employees.php`）、`Controls/`、`plugins/`。

### 認證與外掛系統

**`PluginManager`**（`lib/Common/PluginManager.php:9`）是一個 singleton，有 7 個 `LoadXxx()` 方法，各對應一個擴充點：`LoadAuthentication`、`LoadAuthorization`、`LoadPermission`、`LoadPreReservation`、`LoadPostReservation`、`LoadPostRegistration`、`LoadStyling`。

整體是統一的 decorator pattern。每個 `LoadXxx()`：
1. 先組出預設/基礎實作（例如第 51 行的 `new Authentication(...)`）。
2. 呼叫 `LoadPlugin($configKey, $subDirectory, $baseImplementation)`。
3. 有外掛就回傳外掛，沒有就回傳基礎實作（54-59 行）。

`LoadPlugin()`（`PluginManager.php:218-245`）：
- 從 config 讀外掛**類別名稱**（例如 `ConfigKeys::PLUGIN_AUTHENTICATION`）。
- 用正規表達式 `/^[A-Za-z_][A-Za-z0-9_]*$/` 驗證（224 行）——防止路徑穿越。
- 慣例路徑：`plugins/{子目錄}/{外掛名}/{外掛名}.php`（230 行）。
- `require_once` + `new $plugin($baseImplementation)`——**基礎實作會傳進外掛的建構子**，外掛可以選擇委派給它。
- 依 config key 快取結果（247-258 行）。載入失敗只記 log、不拋例外，null 結果也會被快取。
- 測試時可用 `PluginManager::SetInstance()`（38 行）+ `tests/fakes/FakePluginManager.php` 覆蓋。

**Active Directory 認證的實作**（`plugins/Authentication/ActiveDirectory/`）：
- `ActiveDirectory.php:13`——`class ActiveDirectory extends Authentication implements IAuthentication`，同時繼承基礎類別又拿到基礎實例，可以在需要時 fallback 回本機帳密驗證。
- 關鍵方法：`Validate($username, $password)`（83 行）對 LDAP 做 bind；`Login($username, $loginContext)`（135 行）把 AD 使用者同步進本機 `users` 表。
- 相關檔案：`AdLdapWrapper.php`（包裝內附的 `adLDAP.php`）、`IActiveDirectory.php`、`ActiveDirectoryUser.php`、`ActiveDirectoryOptions.php`、`ActiveDirectoryConfigKeys.php`，還有獨立的外掛設定檔 `ActiveDirectory.config.php`（+`.dist.php`），透過 `lib/Config/PluginConfigKeys.php` 載入。
- 可測試性：`tests/fakes/FakeActiveDirectoryWrapper.php` + `FakeActiveDirectoryOptions.php`，測試放 `tests/Plugins/`。

可用的外掛槽位：`Authentication/`（ActiveDirectory、Apache、CAS、Drupal、Krb5、Ldap、Mellon、Moodle、MoodleAdv、Saml、Shibboleth、WordPress）、`Authorization/`、`Permission/`、`PreReservation/`（含 `AdminCheckOnly`）、`PostReservation/`、`PostRegistration/`、`Export/`、`Styling/`。

### Config 載入機制

檔案：`config/config.dist.php`（產生出來的範本——用 `composer config-dist:generate`／`:check` 重新產生或驗證）複製成 `config/config.php`（gitignore，真實值）。`.env`／`app.env` 提供 `LB_*` 環境變數，由 `vlucas/phpdotenv` 解析。`.env.example` 同樣是產生出來的範本（`composer env-example:generate`）。

**實際優先序**，確認於 `lib/Config/Configuration.php:516-545`（`ConfigFile::GetKey()`）：

```
$envKey = ConfigKeysMeta::envKeyForConfig(config: $configKey);   // line 525
$envValue = $envKey !== null ? env($envKey) : null;              // line 526
if (!empty($envValue))            { $value = $envValue; }        // line 528  ← 環境變數優先
elseif ($section !== '')          { $value = $this->_values[$section][$sectionKey] ?? null; }
else                              { $value = $this->_values[$fullKey] ?? null; }
if ($value === null || $value === '') { $value = $configKey->default; }
return $this->Convert($value, $converter);
```

也就是：**`LB_*` 環境變數 → `config/config.php`（依 section）→ `ConfigKey` 宣告的預設值**。注意判斷式是 `!empty()`，所以環境變數如果被設成空字串或 `"0"` 會直接跳過、繼續往下找。這正是 `COASIA-DEPLOYMENT.md` 記錄過那次「`app.env` 蓋掉 `config.php` 資料庫設定」事故的根本機制。

環境變數名稱怎麼從設定 key 算出來，`lib/Config/ConfigKeysMeta.php:115-123`：
```php
return strtoupper('LB_' . preg_replace('/[.\-]+/', '_', $canonicalKey));
```
例如 `database.hostspec` → `LB_DATABASE_HOSTSPEC`。

`Configuration::Instance()` 是 singleton；`Configuration::GetKey()`（207 行）委派給 `$this->File(self::DEFAULT_CONFIG_ID)->GetKey(...)`，多檔案支援讓外掛可以註冊自己的設定檔。Key 定義在 `ConfigKeys.php`／`AbstractConfigKeys.php`／`ConfigKey.php`（有型別、有 `default`、有 `section`），改名/淘汰的 key 另外用 `DeprecatedConfigKeys.php` 跟 `MigrateConfig.php` 處理。`Configuration::VERSION = '5.2.0'`（94 行）。

---

## 資料庫

### ⚠️ 最重要的認知：`create-schema.sql` 不是完整 schema

`database_schema/create-schema.sql`（464 行）**只是 2.0 年代的基礎 schema，只有 25 張表**。實際上線的 schema 是這份檔案**再疊上 15 個依序套用的 upgrade 資料夾**（`database_schema/upgrades/2.1 … 4.4`），這些資料夾又加了 **34 張表**，還有幾十個欄位。

**沒有任何單一檔案能看到「目前完整的 schema」。** 要知道 `resources` 表到底有哪些欄位，得先讀 `create-schema.sql:184-210`，**再**去 15 個 upgrade 資料夾裡 grep `ALTER TABLE \`resources\``。這正是為什麼 `resources.location` 跟 `resource_groups` 一開始讓人摸不著頭緒——`location` 在最原始的檔案裡就有（`create-schema.sql:187`，`upgrades/2.6/schema.sql:56-57` 把它從短字串加寬成 `VARCHAR(255)`），但 `resource_groups` 是 `upgrades/2.5/schema.sql:8` 才新增的。

`Presenters/Install/Installer.php:62,68` 依序套用這些檔案（`InstallFresh` 先跑 `create-schema.sql`，再跑 `Upgrade()`）。

### 完整資料表清單（59 張）

#### 群組 A — 使用者、認證、Session（11 張）

| 表 | 用途 | 關鍵欄位 | 定義位置 |
|---|---|---|---|
| `users` | 使用者帳號 | PK `user_id`；FK `status_id`→`user_statuses`；`salt`（每個使用者各自的密碼 salt）；`homepageid`（登入後預設導向哪一頁）；`legacyid`／`legacypassword`（2.0 版以前遷移的殘留欄位）；`public_id`（對外用的不透明 ID，唯一）；`ad_object_guid`（**CoAsia 新增**，保留欄位，目前未使用）；`credit_count` | `create-schema.sql:132`；+`upgrades/2.1/schema.sql:10`、`2.6/schema.sql:141`、`4.2/schema.sql:18` |
| `user_statuses` | 查找表：啟用/停用/待審核 | PK `status_id` | `create-schema.sql:121` |
| `groups` | 使用者群組 | PK `group_id`；self-FK `admin_group_id`→`groups`（哪個群組管理這個群組） | `create-schema.sql:74` |
| `user_groups` | 使用者↔群組 M:N | 複合 PK（`group_id`,`user_id`） | `create-schema.sql:164` |
| `roles` | 角色定義查找表 | PK `role_id`；`role_level`（`RoleLevel.php` 用的數字層級） | `create-schema.sql:90` |
| `group_roles` | 群組↔角色 M:N（角色一律透過群組授予，不會直接給使用者） | PK（`group_id`,`role_id`） | `create-schema.sql:102` |
| `user_session` | 伺服器端 session 儲存 | `session_token`；`user_session_value`（序列化的 `UserSession`） | `upgrades/2.4/schema.sql:2` |
| `account_activation` | Email 啟用驗證碼 | `activation_code`（唯一） | `upgrades/2.2/schema.sql:30` |
| `user_preferences` | 通用的每人 key/value 偏好設定 | (`user_id`,`name`,`value`) | `upgrades/2.5/schema.sql:69` |
| `user_email_preferences` | 使用者訂閱哪些通知事件類型 | PK（`user_id`,`event_category`,`event_type`） | `create-schema.sql:396` |
| `announcements`／`announcement_groups`／`announcement_resources` | 儀表板公告＋受眾範圍 | `priority`、`start_date`／`end_date`；join 表限定公告對象的群組/資源 | `create-schema.sql:8`；`upgrades/2.6/schema.sql:173,188` |

#### 群組 B — 會議室與排程（17 張）

| 表 | 用途 | 關鍵欄位 | 定義位置 |
|---|---|---|---|
| `resources` | 可預約的會議室/設備 | PK `resource_id`；FK `schedule_id`；`location`（見下方「兩種地區機制」）；`sort_order`；`status_id`（2.5 版取代舊的 `isactive`）；`resource_type_id`；`admin_group_id`；`autoassign`、`requires_approval`、`buffer_time`、`min/max_duration`、`min/max_notice_time`、`min_notice_time_update/_delete`、`auto_release_minutes`、`enable_check_in`、`color`、`allow_display`、`additional_properties`（序列化 blob）、`public_id` | `create-schema.sql:184` + `2.1`、`2.3:17`、`2.5:3,93-98`、`2.6:105-112`、`2.7:120-123,158`、`2.8:13` |
| `resource_groups` | 階層式會議室分組（見下方對照表） | PK `resource_group_id`；`resource_group_name`；**self-FK `parent_id`→`resource_groups`**，`ON DELETE CASCADE`；`public_id` | `upgrades/2.5/schema.sql:8`；`public_id` 於 `2.6/schema.sql:51` |
| `resource_group_assignment` | 會議室↔分組 M:N | PK（`resource_group_id`,`resource_id`），兩個 FK 都是 CASCADE | `upgrades/2.5/schema.sql:30` |
| `resource_types` | 會議室分類（CoAsia：實體會議室／線上會議） | PK `resource_type_id`；`resource_type_name` | `upgrades/2.5/schema.sql:20` |
| `resource_type_assignment` | 會議室↔類型 M:N——**已是殘留表**，實際使用的路徑是 `resources.resource_type_id` 這個純量 FK | (`resource_id`,`resource_type_id`) | `upgrades/2.6/schema.sql:60` |
| `resource_status_reasons` | 「不可用」狀態附帶的自由文字原因 | `status_id`、`description` | `upgrades/2.5/schema.sql:85` |
| `resource_images` | `resources.image_name` 以外的額外圖片 | `resource_id`、`image_name` | `upgrades/2.7/schema.sql:136` |
| `resource_video_conference_host` | **CoAsia 新增**。視訊會議資源的加密主持人憑證 | PK `resource_id`；`host_account`；`host_password_encrypted`（base64 編碼的 iv+密文） | `upgrades/4.3/schema.sql:16` |
| `schedules` | 一份可預約的行事曆；每個資源恰好屬於一份 | PK `schedule_id`；FK `layout_id`；`isdefault`、`weekdaystart`、`daysvisible`、`admin_group_id`、`published`、`total_concurrent_reservations`、`max_resources_per_reservation`、`additional_properties`、`notes` | `create-schema.sql:53` + `2.3:1`、`2.8:3-10`、`3.0:13,29` |
| `layouts` | 時區＋時段模式的容器，掛在 schedule 底下 | PK `layout_id`；`timezone`；`layout_type` | `create-schema.sql:22`；`layout_type` 於 `2.7/schema.sql:167` |
| `time_blocks` | layout 底下實際的時段切割 | FK `layout_id`；`start_time`／`end_time`；`availability_code`（可預約/封鎖）；`day_of_week`（NULL＝每天都套用） | `create-schema.sql:33`；`day_of_week` 於 `2.4/schema.sql:16` |
| `custom_time_blocks` | 指定日期（非週期性）的時段覆寫 | `start_time`／`end_time` 是 DATETIME，不是 TIME | `upgrades/2.7/schema.sql:169` |
| `peak_times` | 每個 schedule 的尖峰時段（用於計費） | `all_day`、`every_day`、`peak_days`、`begin_month`／`begin_day`／`end_month`／`end_day` | `upgrades/2.6/schema.sql:151` |
| `accessories`／`resource_accessories` | 可租借的附加物品，及每個資源可用的最小/最大數量 | `accessory_quantity`；`minimum_quantity`／`maximum_quantity` | `create-schema.sql:437`；`2.6/schema.sql:29` |
| `blackout_series`／`blackout_instances`／`blackout_series_resources` | 管理員設定的不可用時段（跟預約一樣有 series+instance 兩層） | `repeat_type`／`repeat_options`；`blackout_series.resource_id` 已在 2.5 版**移除**，改用 join 表 | `create-schema.sql:360,377`；`2.5/schema.sql:45,66` |
| `quotas` | 預約額度限制 | `quota_limit`、`unit`、`duration`、`scope`、`enforced_days`、`enforced_time_start/_end`；可為 null 的資源/群組/schedule FK | `create-schema.sql:412`；`2.6/schema.sql:92-99` |

#### 群組 C — 預約（11 張）

| 表 | 用途 | 關鍵欄位 | 定義位置 |
|---|---|---|---|
| `reservation_series` | **聚合根**。一筆預約（含週期性）對應一列 | PK `series_id`；FK `type_id`、`status_id`、`owner_id`；`repeat_type`／`repeat_options`；`last_action_by`；**`version`**（**CoAsia 新增**的樂觀鎖計數器） | `create-schema.sql:277`；`version` 於 `4.1/schema.sql:38` |
| `reservation_instances` | 一次實際發生的預約（週期性預約會展開成多筆） | PK `reservation_instance_id`；FK `series_id`；`start_date`／`end_date`；`reference_number`（給使用者看的預約編號）；`checkin_date`／`checkout_date`；`previous_end_date`；`credit_count` | `create-schema.sql:304`；`2.6/schema.sql:130-138` |
| `reservation_resources` | 一筆預約訂了哪些資源 | PK（`series_id`,`resource_id`）；`resource_level_id`（主資源 vs 附加資源） | `create-schema.sql:341` |
| `reservation_users` | 每個 instance 的參與者/受邀者 | PK（`reservation_instance_id`,`user_id`）；`reservation_user_level`（訂位人/參與者/受邀者） | `create-schema.sql:323` |
| `reservation_guests` | 同上，但給沒有帳號的外部 email | PK（`reservation_instance_id`,`email`） | `upgrades/2.6/schema.sql:125` |
| `reservation_accessories` | 一筆預約附加的物品 | PK（`series_id`,`accessory_id`），`quantity` | `create-schema.sql:450` |
| `reservation_files` | 附件 | `file_name`／`file_type`／`file_size`／`file_extension`；檔案本體存在磁碟上 | `upgrades/2.2/schema.sql:44` |
| `reservation_reminders` | 每筆預約的提醒時間偏移 | `minutes_prior`、`reminder_type`（開始前/結束前） | `upgrades/2.4/schema.sql:34` |
| `reservation_waitlist_requests` | 候補清單 | `user_id`、`resource_id`、`start_date`／`end_date` | `upgrades/2.6/schema.sql:203` |
| `reservation_types`／`reservation_statuses` | 查找表（`label`） | PK `type_id` / `status_id` | `create-schema.sql:256,267` |
| `reservation_requests` | **CoAsia 新增**。冪等性（idempotency）記錄表——前端產生的 UUID 會先佔一列，重複送出/連點時能認出自己已經處理過的請求，避免重複建立預約 | PK（`request_id` VARCHAR(36), `user_id`）；`status`；`reference_number` | `upgrades/4.1/schema.sql:46` |

#### 群組 D — 自訂屬性（4 張）

| 表 | 用途 | 關鍵欄位 | 定義位置 |
|---|---|---|---|
| `custom_attributes` | 管理員自訂欄位的定義 | PK `custom_attribute_id`；`display_label`；`display_type`（1=文字、2=多行文字、4=核取方塊……）；**`attribute_category`**（多型判別欄位：1=預約、2=使用者、4=資源、5=資源類型）；`validation_regex`、`possible_values`、`admin_only`、`is_private`、`secondary_category`、`secondary_entity_ids` | `upgrades/2.2/schema.sql:2`；+`2.6/schema.sql:44-46` |
| `custom_attribute_values` | 實際的值 | `custom_attribute_id`；`entity_id`（多型——實際意義依 `attribute_category` 而定）；`attribute_value` | `upgrades/2.2/schema.sql:17` |
| `custom_attribute_entities` | 把某個屬性限定給特定的 entity ID。取代舊的 `custom_attributes.entity_id` 純量欄位（2.6 版遷移後刪除） | (`custom_attribute_id`,`entity_id`) | `upgrades/2.6/schema.sql:75` |
| `reservation_color_rules` | 依自訂屬性的值決定預約色塊顏色 | `custom_attribute_id`、`comparison_type`、`required_value`、`color` | `upgrades/2.6/schema.sql:12` |

#### 群組 E — 付款/點數（6 張）— 全部是上游原生功能，**CoAsia 部署完全沒用到**

`payment_configuration`、`payment_gateway_settings`（PK（`gateway_type`,`setting_name`)）、`credit_log`、`payment_transaction_log`、`refund_transaction_log`、`terms_of_service`。全部定義在 `upgrades/2.7/schema.sql:10-133`。

#### 群組 F — 權限（2 張）

| 表 | 用途 | 關鍵欄位 |
|---|---|---|
| `user_resource_permissions` | 使用者→會議室的直接授權 | PK（`user_id`,`resource_id`）；`permission_id`；`permission_type`（0=完整、其他=唯讀層級）——`create-schema.sql:217`，`permission_type` 於 `2.7/schema.sql:153` |
| `group_resource_permissions` | 群組→會議室的授權 | PK（`group_id`,`resource_id`）；`permission_type`——`create-schema.sql:237`，`2.7/schema.sql:148` |

`database_schema/coasia-resources.sql:78-82` 記錄過一個陷阱：一個會議室如果這兩張表裡都沒有它的授權紀錄，一般使用者完全訂不了（`PermissionValidationRule` → `NoResourcePermission`），但管理員自己不會發現，因為 `CanBookResource()` 對管理員一律直接放行。

#### 群組 G — 系統/其他（8 張）

`dbversion`（見下方專節）、`saved_reports`（`report_details` 是序列化的報表定義，`upgrades/2.3/schema.sql:5`）、`reminders`（通用的外送佇列：`address`、`message`、`sendtime`、`refnumber`，`upgrades/2.4/schema.sql:19`）。

### `dbversion` 這張表

**結構**（`upgrades/2.1/schema.sql:1-5`）：只有兩欄——`version_number DOUBLE UNSIGNED NOT NULL DEFAULT 0`、`version_date TIMESTAMP`。**沒有主鍵，沒有唯一約束**。是一張只會 append 的紀錄表，「目前版本」的定義是 `MAX(version_number)`。

**誰會讀它：**

| 讀取者 | 查詢 | 行為 |
|---|---|---|
| `Presenters/Install/Installer.php:225`（`GetVersion()`） | `SELECT * FROM dbversion ORDER BY version_number DESC LIMIT 0,1` | 驅動升級精靈 |
| `lib/preflight.php:464` | `SELECT version_number FROM dbversion ORDER BY version_date DESC LIMIT 1` | 純健康檢查：有資料就 PASS 並顯示 `(schema version: N)`；表不存在或**零筆資料**都會回報 `CheckStatus::Warn`「schema not found／請到 /Web/install/」（`preflight.php:475-482`）——**這正是這次部署踩到的那個警告** |
| `WebServices/AuthenticationWebService.php:67` | `GetVersionCommand` → `Queries::GET_VERSION`（`Queries.php:968`） | API 登入回應裡帶上 schema 版本 |

**`GetVersion()` 的判斷順序**（`Installer.php:199-251`）——值得整段記錄下來，因為失敗模式不太直觀：

1. 連不上/選不到資料庫 → `return false`
2. `SELECT * FROM layouts` 失敗 → `return false`（用來判斷「根本沒安裝過」）
3. `dbversion` 表不存在 → `return 2.0`
4. 有找到資料列 → 回傳該版本號；**特例**：如果剛好是 `2.1`，會去檢查 `custom_attributes` 表存不存在，存在就補插入一筆 `2.2` 並回傳 2.2（因應歷史上 2.2 版一個已知的 bug）
5. **表存在但是空的 → 回傳寫死的 `2.8`**（註解寫「目前正在安裝的版本」，現在看已經過時）

第 5 種情況是最容易踩到的陷阱：`dbversion` 是空的**不代表版本是 0**，而是 installer 會直接假設是 2.8，因此**跳過**所有 ≤ 2.8 的升級腳本。

**跟 `upgrades/X.Y/` 的關係：**

- 每個 upgrade 資料夾理論上都該對應一筆新的 `dbversion` 紀錄——但這筆紀錄是 `data.sql` 寫的，不是 `schema.sql`。早期的資料夾直接 `insert into dbversion values('2.2', now())`（`upgrades/2.2/data.sql:1`）；2.9 版之後改用有防呆的寫法：`INSERT ... SELECT ... WHERE NOT EXISTS (SELECT 1 FROM dbversion WHERE version_number = X.Y)`（`upgrades/4.2/data.sql:49-55`）。
- `Installer::Upgrade()`（`Installer.php:88-109`）只呼叫一次 `GetVersion()`，然後對每個資料夾依序跑 `schema.sql` 再跑 `data.sql`，條件是 `floatval(資料夾版本) > floatval(目前版本)`（`Installer.php:124-138`）。
- `phing-tasks/UpgradeDbTask.php:80-81`（`build.xml`／CI 那條路徑）**完全不理會 `dbversion`**，每次都無條件重跑所有資料夾。這也是為什麼 CoAsia 時期的升級腳本都寫成 idempotent（用 `information_schema` 防呆，例如 `upgrades/4.1/schema.sql:28-44`、`4.2/schema.sql:1-3`）。

**兩個現存的實際缺口，值得記住：**

- **`4.3` 跟 `4.4` 都沒有 `data.sql`**，所以這兩版永遠不會寫入 `dbversion` 紀錄——這個 repo 能產生的最大版本號就是 `4.2`。兩套執行機制都是直接 `fopen("$dir/data.sql")`（`Installer.php:136`、`UpgradeDbTask.php:81` → `MySqlScript::GetFullSql()`），檔案不存在只會是警告、不是硬錯誤，所以 4.3/4.4 的 schema 改動**每次跑升級精靈都會重複套用**。
- **`upgrades/4.4/fix-encoding.sql` 兩套機制都不會自動執行**——都只認 `schema.sql`／`data.sql` 這兩個檔名，這個檔案要手動執行。
- 另外，`Configuration::VERSION = '5.2.0'`（`lib/Config/Configuration.php:94`），但 `dbversion` 最大只會是 4.2，所以 `InstallPresenter::CheckForUpgrade()`（`Presenters/Install/InstallPresenter.php:130`）永遠會回報「有可用的升級」。

### `resource_groups` vs `resources.location`：兩種平行存在的地區機制

| | `resource_groups` + `resource_group_assignment` | `resources.location` |
|---|---|---|
| 來源 | LibreBooking 原生，`upgrades/2.5/schema.sql:8,30` | LibreBooking 原生，`create-schema.sql:187`（自由文字欄位，CoAsia 之前完全沒用過） |
| 結構 | 兩張表；`resource_groups(resource_group_id PK, resource_group_name VARCHAR(75), parent_id → self, public_id)`；assignment 是純粹的 M:N join，複合主鍵 | `resources` 上的單一 `VARCHAR(255)` 欄位 |
| 階層 | **有**——`parent_id` self-FK，`ON DELETE CASCADE`，理論上可以任意深度。CoAsia 目前只用到一層（四個組的 `parent_id` 都是 `NULL`，`coasia-resources-regions.sql:125-127`） | 沒有，是平的 |
| 基數 | 多對多——一個資源可以屬於好幾個群組 | 一個資源只有一個值 |
| CoAsia 目前的值 | `台北實體會議室`、`深圳實體會議室`、`上海實體會議室`、`線上會議室`（完整詞面） | `台北`、`深圳`、`上海`、`跨地區`（簡短詞面） |
| 有沒有限制 | 沒有——管理員可以自由建立任意名稱 | **有**——固定四值白名單，定義在 `Domain/Values/ResourceLocation.php:19-22`，寫入時強制檢查（`ManageResourcesPresenter` 的 `ChangeLocation`／`BulkUpdate`／CSV 匯入都會擋） |
| 驅動哪裡 | 只驅動 **Overview FullCalendar 左側會議室選單**。讀取用 `GetAllResourceGroupsCommand` + `GetAllResourceGroupAssignmentsCommand`（`Queries.php:443,445`），消費在 `Domain/Access/ResourceRepository.php:139,377-384`。顯示順序不是資料庫排出來的——查詢本身是字母排序，spec 要求的順序是寫死在 `Presenters/OverviewFullCalendarPresenter.php:35` 的 `GROUP_DISPLAY_ORDER` | **管理中心的地區篩選/欄位**，橫跨會議室管理、預約管理、開放時間、管理總覽、使用分析、CSV 匯入匯出（`coasia-resources-location.sql:1-8`） |
| 排序邏輯 | `Queries.php:443` 依 `parent_id, resource_group_name` 排序群組；assignment 依 `COALESCE(r.sort_order,0), r.name`（`Queries.php:449`） | `ResourceLocation::SortRank()`（`ResourceLocation.php:52`），台北→深圳→上海→跨地區，不認得的值排最後 |

**這兩者彼此完全沒有同步機制**——schema 跟程式碼裡都沒有任何東西會保證一個資源的分組跟它的 `location` 值一致。兩者都是各自跑一次性腳本填進去的。

### CoAsia 專屬的資料庫異動

**CoAsia 新增的 upgrade 資料夾**（用 `git log --diff-filter=A -- database_schema/` 確認）：`4.0`（commit `0b6c8448`）、`4.1`（`c425e9bd` + `d59bf14a`）、`4.2`（`56764a52`）、`4.3`（`961abd9c`）、`4.4`（`30eadf9d` + `a97f5559`）。`2.1`–`3.0` 是上游原生的。

| 異動 | 內容 | 位置 |
|---|---|---|
| `reservation_series.version` | INT UNSIGNED，預設 0，樂觀鎖計數器。`UPDATE … WHERE series_id=? AND version=?`，影響 0 筆代表同時有人在改。選用這個而不是 `last_modified`，是因為後者只有秒級精度 | `upgrades/4.1/schema.sql:38` |
| `reservation_requests`（新表） | 建立預約用的冪等性記錄 | `upgrades/4.1/schema.sql:46` |
| `users.ad_object_guid` + 唯一索引 | 保留給未來比對 AD objectGUID 用；**目前完全沒有填值**——實際比對是用正規化過的帳號/email，見 `Registration::Synchronize()` | `upgrades/4.2/schema.sql:18,34` |
| 5 個預先建好的使用者類自訂屬性 | `Employee ID`、`Chinese Name`、`Korean Name`、`Department Paths`、`AD Managed Account`——全部 `attribute_category=2`、`admin_only=1`；被 `Jobs/import-coasia-employees.php` 使用 | `upgrades/4.2/data.sql:9-47` |
| `resource_video_conference_host`（新表） | 加密儲存的視訊會議主持人憑證，跟明文的 `video_url`／`video_meeting_id`／`video_passcode` 自訂屬性是分開的 | `upgrades/4.3/schema.sql:16` |
| 2 筆 `resource_types` 資料＋回填 | `實體會議室`（資源 1-7）／`線上會議`（8-12）；讓 admin 抽屜可以依類型只顯示相關欄位 | `upgrades/4.4/schema.sql:17-31` |
| 亂碼修正 | 修正上面那兩個 `resource_type_name` 值在正式環境上的亂碼（套用時沒加 `--default-character-set=utf8mb4`） | `upgrades/4.4/fix-encoding.sql:8-9` |

**另外三支獨立、手動套用的種子腳本**（不是 upgrade 資料夾的一部分）。全部**不是** idempotent，套用時都要加 `mysql --default-character-set=utf8mb4`：

| 腳本 | 影響範圍 | 備註 |
|---|---|---|
| `coasia-resources.sql` | `layouts`、`time_blocks`、`schedules`、`resources`（1-12）、`user_resource_permissions`、`resource_groups`（1-2）、`resource_group_assignment`、`custom_attribute_values` | 取代原本的 demo 資料。ID 1-12 是寫死的，跑完會把 AUTO_INCREMENT 往後撥（120-123 行） |
| `coasia-resources-regions.sql` | 把群組 1-2 改名、新增資源 13-21＋群組 3-4、設定 `resources.sort_order` | 新 ID 是用 `MAX(id)` 動態算出來的，**不是**寫死——檔案開頭 9-14 行的註解解釋了原因：實際的 `layouts` ID 因為透過 admin UI 編輯 schedule 會產生孤兒列，早就已經是不連續的 13/14/15/18 這種跳號狀態 |
| `coasia-resources-location.sql` | `UPDATE resources SET location`，涵蓋 ID 1-21 | 總共 4 條 UPDATE，假設前兩支腳本已經跑過 |

### 沒有 ORM，是命令物件模式

這個專案**沒有 ORM**。`composer.json` 裡沒有 Doctrine／Eloquent；`"autoload": {}` 是空的（整個專案用手寫的 `require_once` + 每個目錄一份 `namespace.php` 聚合檔案這種方式載入類別）。實際的模式是手刻的三段式命令層：

1. **`lib/Database/Commands/Queries.php`**（1,376 行）——一堆 `public const` SQL 字串常數，帶 `@name` 這種佔位符。
   `public const GET_ALL_RESOURCE_GROUPS = 'SELECT * FROM \`resource_groups\` ORDER BY \`parent_id\`, \`resource_group_name\`';`（`Queries.php:443`）
2. **`lib/Database/Commands/Commands.php`**（3,007 行）——每個查詢一個薄薄的類別，繼承 `SqlCommand`（`lib/Database/SqlCommand.php:6`），建構子接收領域參數並綁定：
   ```php
   class GetAllResourceGroupAssignmentsCommand extends SqlCommand {
       public function __construct($scheduleId) {
           parent::__construct(Queries::GET_ALL_RESOURCE_GROUP_ASSIGNMENTS);
           $this->AddParameter(new Parameter(ParameterNames::SCHEDULE_ID, $scheduleId));
       }
   }
   ```
   （`Commands.php:1459-1466`）
3. **`Domain/Access/*Repository.php`**——透過 `ServiceLocator::GetDatabase()->Query($command)` 執行，逐列走訪 `IReader`，手動把 `$row[ColumnNames::X]` 映射成領域物件，最後 `$reader->Free()`。最簡短的範例：`Domain/Access/ResourceRepository.php:139-155`。

輔助的常數檔案：`ColumnNames.php`（360 行）、`ParameterNames.php`、`TableNames.php`（24 行）。橫切關注點另外用裝飾器處理——`FilterCommand` 把一個命令包上 `SqlFilter`（`ResourceRepository.php:162`），`PageableDataStore::GetList()` 接收一個命令加一個 `[類別, 'Create']` 工廠 callable 來處理分頁（`ResourceRepository.php:165-166`）。

**這對查文件的意義**：沒有一個類似 `schema.rb`／migration model 的東西可以查。權威答案分散在三個地方——DDL 看 `create-schema.sql` + `upgrades/*/schema.sql`；程式碼實際查了什麼看 `Queries.php`；欄位名稱常數看 `ColumnNames.php`。要找某張表被哪些地方用到，通常直接在 `Queries.php` 裡 grep 表名是最快的方法。

---

## 前端架構

### `tpl/` — Smarty 5

178 個 `.tpl` 檔案。引擎是 `smarty/smarty: ^5.8`（composer.json）。

**版面機制：用 include，不是繼承。** 沒有共用的 base layout，也沒有用 Smarty 的 `{extends}`。每個頁面樣板開頭都是 `{include}` 頁首、結尾 `{include}` 頁尾：

```smarty
{include file='globalheader.tpl' cssFiles='css/overview_fullcalendar.css,css/onboarding-tour.css'}
```
（`tpl/Overview/overview_fullcalendar.tpl:5`）

```smarty
{include file='globalheader.tpl' InlineEdit=true DataTable=true Trumbowyg=true}
```
（`tpl/Admin/Resources/manage_resources.tpl:1`）

`globalheader.tpl` 輸出從 `<!DOCTYPE html>` 一路到 `<div id="main">` 開頭（第 128 行開啟 `<body>`，檔案在標記中段結束）；`globalfooter.tpl` 收尾同樣這幾個 div（`globalfooter.tpl:1-7`）。include 帶的參數是拿來開關可選的 vendor 套件——`DataTable`、`Trumbowyg`、`Validator`、`InlineEdit`、`Select2`，加上 `cssFiles`（逗號分隔，在 `globalheader.tpl:94-99` 拆解）跟 `HideNavBar`。

**目錄結構**（22 個子目錄，跟 `Pages/` 對應）：`Activation/`、`Admin/`、`Ajax/`、`Calendar/`、`Controls/`、`Credits/`、`Dashboard/`、`Email/`、`Export/`、`ExternalAuth/`、`Help/`、`Install/`、`MonitorDisplay/`、`MyAccount/`、`MyMeetings/`、`Overview/`、`Reports/`、`Reservation/`、`ResourceDisplay/`、`Schedule/`、`Search/`、`SearchAvailability/`。根目錄的共用 partial：`globalheader.tpl`、`globalfooter.tpl`、`javascript-includes.tpl`、`json_data.tpl`、`wait-box.tpl`、`error.tpl`、`maintenance.tpl`，另外幾個獨立頁面：`login.tpl`、`dashboard.tpl`、`register.tpl`、`tos.tpl`、`forgot_pwd.tpl`。

**多語言樣板覆寫——有兩套不同機制：**

1. **`SmartyPage::FetchLocalized($templateName, $enforceCustomTemplate, $languageCode)`**——`lib/Common/SmartyPage.php:89-124`。解析順序：`lang/{語言}/{名稱}-custom.tpl` → `lang/{語言}/{名稱}.tpl` → （若 `$enforceCustomTemplate`）`lang/{預設語言}/{名稱}-custom.tpl` → `lang/en_us/{名稱}.tpl`。**只用在信件**——呼叫者是 `lib/Email/EmailMessage.php:45` 跟 `lib/Email/Messages/ReportEmailMessage.php:35`。`-custom.tpl` 這個後綴是給部署端客製化用的掛鉤。
2. **`Page::DisplayLocalized($templateName)`**——`Pages/Page.php:456-469`，頁面端比較單純的版本：如果 `lang/{目前語言}` 底下有這個檔案就加進樣板目錄，沒有就用 `lang/en_us/`。

另外，`SmartyPage::__construct` 永遠都會把 `lang/{目前語言}` 註冊成樣板目錄（`SmartyPage.php:69`），所以理論上任何 `tpl/` 底下的檔案都可以被 `lang/<語言代碼>/` 底下同名的檔案覆蓋。實際上目前存在的約 35 個 `lang/<代碼>/*.tpl` 檔案全部都是信件內容（`ReservationCreated.tpl`、`AccountActivation.tpl`、`ResetPassword.tpl`……）。

**編譯與快取失效**——`SmartyPage.php:49-61`：

| 設定 | 值 |
|---|---|
| `compile_dir` | `tpl_c/`（目前 112 個編譯後的 `.php` 檔） |
| `cache_dir` | `cache/` |
| `caching` | **永遠是 `false`**——輸出快取從來不會開 |
| `compile_check` | `!$cacheTemplates` |
| `force_compile` | `!$cacheTemplates` |

唯一的控制開關是 `ConfigKeys::CACHE_TEMPLATES`（設定 key `cache.templates`，在第 57 行用 `BooleanConverter` 讀取）。設成 **false**（開發環境）時 `force_compile=true`，每個請求都會重新編譯所有樣板。設成 **true**（正式環境）時 `compile_check=false`，代表 Smarty **永遠不會重新檢查來源檔案的時間戳**——所以正式環境上改了 `.tpl` 檔案不會有任何效果，直到手動清空 `tpl_c/`。`Installer::ClearCachedTemplates()`（`Presenters/Install/Installer.php:253-267`）會清空 `tpl_c/` 裡所有檔案，安裝跟升級完成後都會被呼叫（`InstallPresenter.php:106,115`）。

在任何網址後面加 `?debug=1` 可以打開 Smarty 的除錯主控台（`SmartyPage.php:47`）。

### `Web/scripts/` — 沒有打包工具，純手寫 jQuery 時代的檔案

**完全沒有 build 流程。** 檔案是手寫的 ES5／jQuery 風格，透過自訂的 Smarty function `{jsfile src="..."}` 一個一個產生 `<script>` 標籤載入。頂層 37 個檔案，另外兩個子資料夾。

載入的位置是**每個頁面樣板的最下方**，例如 `tpl/Overview/overview_fullcalendar.tpl:564-567`：
```smarty
{jsfile src="onboarding-tour.js"}
{jsfile src="participant-picker.js"}
{jsfile src="reservation-datetime-picker.js"}
{jsfile src="overview_fullcalendar.js"}
```

第三方套件從 `tpl/globalheader.tpl` 跟 `tpl/javascript-includes.tpl` 載入，兩者都有一個 `$UseLocalJquery` 旗標控制的**本機/CDN 雙軌**——本機走 `{vendor_js src="..."}` 讀 `Web/assets/`，否則走公開 CDN（`globalheader.tpl:24-40`、`javascript-includes.tpl:1-62`）。用到的套件：jQuery 3.3.1 + jquery-migrate 3.6.0、jQuery UI 1.14.2、Bootstrap 5.3.3（bundle）、lodash、moment 2.13.0、jquery-form、flatpickr 4.6.13，另外視情況載入 bootstrapValidator、Trumbowyg（+DOMPurify）、DataTables 1.13.7（+responsive/buttons/jszip/pdfmake）。

**組織方式：**

| 種類 | 檔案 |
|---|---|
| 跨頁共用的工具函式 | `phpscheduleit.js`（舊版全域命名空間）、`ajax-helpers.js`、`ajax-form-submit.js`、`date-helper.js`、`force-numeric.js`、`menubar.js`、`autocomplete.js` |
| 多個頁面共用的**元件** | **`participant-picker.js`**、**`reservation-datetime-picker.js`**、`recurrence.js`、`reservationPopup.js`、`resourcePopup.js`、`userPopup.js`、`approval.js`、`onboarding-tour.js`、`search-clear.js` |
| 對應單一頁面（跟某個 `Web/*.php` 路由 1:1） | `overview.js`、`overview_fullcalendar.js`、`schedule.js`、`reservation.js`、`dashboard.js`、`profile.js`、`calendar.js`、`registration.js`、`participation.js`、`availability-search.js`、`reservation-search.js`、`monitor-display.js`、`resourceDisplay.js`、`notification-preferences.js`、`user-credits.js`、`user-guide.js`、`embed-calendar.js`、`reservation-pdf.js`、`reservation-reminder.js` |
| `Web/scripts/admin/`（21 個檔案） | 對應每個 `Web/admin/*.php` 頁面——`resource.js`、`resource-groups.js`、`resource-types.js`、`resource-status.js`、`schedule.js`、`user.js`、`group.js`、`quota.js`、`blackouts.js`、`attributes.js`、`announcement.js`、`accessory.js`、`configuration.js`、`server-settings.js`、`payments.js`、`reservations.js`、`reservation-colors.js`、`email-templates.js`、`reminder.js`，加上共用的 `admin-confirm.js`、`admin-table-paginate.js` |
| `Web/scripts/reports/`（5 個檔案） | `common.js`、`generate-reports.js`、`canned-reports.js`、`saved-reports.js`、`chart.js` |

### `Web/css/`

16 個 CSS 檔案，攤平沒有子資料夾，同樣沒有 build 流程。

**載入順序，出自 `tpl/globalheader.tpl:44-122`：**
1. Vendor CSS（jQuery UI、Bootstrap 5.3.3、Bootstrap Icons、flatpickr，視情況加 Trumbowyg／DataTables／Select2／x-editable）——依 `$UseLocalJquery` 走本機或 CDN
2. 字型——Hind（`assets/fonts/hind/v18/hind.css`）或 Google Fonts；加上 `globalheader.tpl:20-22` 的 Noto Sans TC preconnect+link
3. **`{cssfile src="librebooking.css"}`**（93 行）——永遠會載入的基礎樣式
4. 該頁面自己的 `$cssFiles`，從 `{include}` 參數逗號拆解（94-99 行）
5. `$CssUrl`，接著是 styling 外掛（`styling-plugin.php`，104 行）
6. **`$CssExtensionFile`**（106-108 行）——設定 key `css.extension.file`，**`coasia-theme.css`** 就是靠這個載進來的
7. admin 頁面另外載入 `css/admin.css`（115 行）——原始碼註解說明這是刻意排在 theme 之後
8. `media='print'` 的列印樣式（119-122 行）

**Design Token（設計變數）。** 主要靠兩個檔案撐起整套系統，其他大多是消費端。

| 檔案 | CSS 自訂屬性數量 | 角色 |
|---|---|---|
| `librebooking.css` | 55 個 | 上游原生基礎——定義出可供主題化的介面 |
| **`coasia-theme.css`** | **71 個** | **CoAsia 主題本體。** 檔案開頭註解（1-5 行）：「透過設定 key `css.extension.file` 最後載入。不修改 librebooking.css，只覆蓋那份檔案已經開放出來、可供主題化的 CSS 自訂屬性跟少數幾個選擇器」 |
| `overview_fullcalendar.css` | 18 個 | 頁面專屬 |
| `admin.css` | 10 個 | 所有 admin 頁面都會載入 |
| `reservation-page.css` | 6 個 | 頁面專屬 |
| `notification-preferences.css`、`profile.css` | 各 2 個 | 頁面專屬 |
| `overview.css` | 1 個 | 頁面專屬 |
| `my-meetings.css`、`schedule.css`、`user-guide.css`、`onboarding-tour.css`、`null-style.css` | 0 個 | 純選擇器 |
| `calendar.print.css`、`reservation.print.css`、`schedule.print.css` | 0 個 | 只用於 `media='print'` |

`coasia-theme.css` 的 `:root` 區塊把變數分成四族（每一族在檔案裡都有說明註解）：
- **Bootstrap 5 覆寫**——`--bs-border-radius*`、`--bs-box-shadow*`、`--bs-body-font-family`（`'Noto Sans TC', 'Microsoft JhengHei', 'PingFang TC', Arial, sans-serif`；字重 400/500/600）
- **Google Calendar Material 3 參考變數**——`--gcal-border`、`--gcal-border-strong`、`--gcal-today-bg`、`--gcal-today-accent`、`--gcal-icon-size`、`--gcal-control-height`
- **`schedule.css` 用到的預約色塊顏色**——`--reserved`、`--reservedMine`、`--reservedParticipating`、`--reservedPending`、`--hiliteReservation`、`--label-text-on-dark`
- **側邊欄**——`--sidebar-expanded-width: 285px`／`--sidebar-collapsed-width: 85px`（檔案裡明確註明這是所有寬度計算的唯一真實來源）跟 `--sidebar-*` 色票。註解裡提到深色版跟明暗切換器已經應要求移除，但每條規則仍然讀的是 CSS 自訂屬性而不是寫死的顏色值，所以之後要重新加回第二套主題，可以用一個獨立的覆寫區塊做，不用大改。

另外注意 `Web/css/styling-plugin.php`——一個以 `.php` 產生 CSS 輸出的檔案，是 Styling 外掛槽位（見「認證與外掛系統」小節）的輸出結果。

### 前端沒有建置工具

**`package.json` 純粹只是拿來做 lint，沒有打包工具、沒有轉譯器、沒有壓縮器，也沒有 `build` script。**

```json
"name": "coasia-booking", "private": true,
"description": "Frontend linting tools for CoAsia Booking",
"engines": { "node": ">=20.19.0" }
```

- **Scripts**（全部是 lint／format）：`lint:js`（`eslint --max-warnings 0 Web/scripts/`）、`lint:css`（`stylelint 'Web/css/**/*.css'`）、`lint:format`（`prettier --check`）、`lint:frontend`（三個都跑），對應的 `fix:js`／`fix:css`／`fix:format`／`fix:frontend`。
- **devDependencies**（7 個，完全沒有 runtime 依賴）：`eslint` ^10.7.0、`@eslint/js`、`eslint-config-prettier`、`globals`、`prettier` ^3.9.5、`stylelint` ^17.14.0、`stylelint-config-standard`。
- 根目錄的設定檔：`eslint.config.mjs`（flat config）、`.stylelintrc.json`、`.prettierrc.json`、`.editorconfig`。

所有第三方 JS/CSS 資源要嘛是預先壓縮好、直接放在 `Web/assets/` 底下的檔案，要嘛是執行期從 CDN 拉——沒有任何東西是從原始碼編譯出來的。`node_modules` 純粹是開發工具鏈的需求，執行這個 app 完全用不到。
