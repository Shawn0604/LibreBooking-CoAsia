<?php

/**
*
*
* Translated by lujisheng in CSDN (Simplified Chinese)
*
* http://blog.csdn.net/lujisheng/article/details/7821538
*
* Converted and edited by mingminghome (Traditional Chinese & Simplified Chinese))
* E-mail: mingminghomework@gmail.com
*
*
* Tested on Booked Scheduler 2.4.2
*
*
*
*/
require_once('Language.php');
require_once('en_us.php');

class zh_tw extends en_us
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @return array
     */
    protected function _LoadDates()
    {
        $dates = parent::_LoadDates();

        $dates['general_date'] = 'Y/m/d';
        $dates['general_datetime'] = 'Y/m/d H:i:s';
        $dates['schedule_daily'] = 'l, Y/m/d';
        $dates['reservation_email'] = 'Y/m/d @ H:i (e)';
        $dates['res_popup'] = 'Y/m/d H:i';
        $dates['res_popup_time'] = 'H:i';
        $dates['short_datetime'] = 'Y/m/d H:i';
        $dates['short_reservation_date'] = 'Y/m/d H:i';
        $dates['dashboard'] = 'l, Y/m/d H:i';
        $dates['period_time'] = 'H:i';
        $dates['mobile_reservation_date'] = 'n/j H:i';
        $dates['general_date_js'] = 'yy/mm/dd';
        $dates['general_time_js'] = 'HH:mm';
        $dates['momentjs_datetime'] = 'YYYY/MM/DD HH:mm';
        $dates['calendar_time'] = 'HH:mm';
        $dates['calendar_dates'] = 'M/d';
        $dates['embedded_time'] = 'H:i';
        $dates['embedded_datetime'] = 'n/j H:i';

        $this->Dates = $dates;

        return $this->Dates;
    }

    /**
     * @return array
     */
    protected function _LoadStrings()
    {
        $strings = parent::_LoadStrings();

        $strings['FirstName'] = '名';
        $strings['LastName'] = '姓';
        $strings['Timezone'] = '時區';
        $strings['Edit'] = '編輯';
        $strings['Change'] = '更改';
        $strings['Rename'] = '重新命名';
        $strings['Remove'] = '移除';
        $strings['Delete'] = '刪除';
        $strings['Update'] = '更新';
        $strings['Cancel'] = '退出';
        $strings['Add'] = '增加';
        $strings['Name'] = '名稱';
        $strings['Yes'] = '是';
        $strings['No'] = '否';
        $strings['FirstNameRequired'] = '名字是必需的.';
        $strings['LastNameRequired'] = '姓氏是必需的.';
        $strings['PwMustMatch'] = '密碼必須跟前面輸入的密碼一致.';
        $strings['PwComplexity'] = '密碼必須最少6個字元，由字元、數位、符號組成.';
        $strings['ValidEmailRequired'] = '一個有效的電子郵寄地址是必需的.';
        $strings['UniqueEmailRequired'] = '這個電子郵寄地址已經被註冊過了.';
        $strings['UniqueUsernameRequired'] = '這個用戶名已經被註冊過了.';
        $strings['UserNameRequired'] = '用戶名是必需的.';
        $strings['CaptchaMustMatch'] = '請您完全按照密碼圖片上顯示的內容輸入字元.';
        $strings['Today'] = '今天';
        $strings['BackToToday'] = '回到今天';
        $strings['Day'] = '日';
        $strings['Week'] = '週';
        $strings['Month'] = '月';
        $strings['BackToCalendar'] = '回到日曆';
        $strings['BeginDate'] = '開始';
        $strings['EndDate'] = '結束';
        $strings['Username'] = '用戶名';
        $strings['Password'] = '密碼';
        $strings['PasswordConfirmation'] = '確認密碼';
        $strings['AddMeetingTitle'] = '新增會議名稱';
        $strings['OtherSettings'] = '其他設定';
        $strings['PermissionsAndCustomFields'] = '權限與自訂欄位';
        $strings['EditFullReservationSubtitle'] = '編輯完整的會議室預約';
        $strings['CreateFullReservationSubtitle'] = '新增完整的會議室預約';
        $strings['CompanyAccount'] = '公司帳號';
        $strings['CompanyAccountOrEmailPlaceholder'] = '輸入帳號或 Email';
        $strings['RoomBookingSystem'] = 'CoAsia 會議室預約';
        $strings['SignInWithCompanyAccount'] = '使用公司帳號登入';
        $strings['DefaultPage'] = '默認主頁';
        $strings['MyCalendar'] = '我的日曆';
        $strings['ScheduleCalendar'] = '計劃日曆';
        $strings['Registration'] = '註冊';
        $strings['NoAnnouncements'] = '沒有通告';
        $strings['Announcements'] = '通告';
        $strings['NoUpcomingReservations'] = '您沒有即將到來的預約';
        $strings['UpcomingReservations'] = '即將到來的預約';
        $strings['ShowHide'] = '顯示/隱藏';
        $strings['Copy'] = '複製';
        $strings['Export'] = '匯出';
        $strings['Info'] = '第 _PAGE_ 頁，共 _PAGES_ 頁（總計 _MAX_ 筆）';
        $strings['LengthMenu'] = '每頁顯示 _MENU_ 筆';
        $strings['DataTablesFirst'] = '第一頁';
        $strings['DataTablesPrevious'] = '上一頁';
        $strings['DataTablesNext'] = '下一頁';
        $strings['DataTablesLast'] = '最後一頁';
        $strings['DataTablesProcessing'] = '處理中…';
        $strings['Error'] = '錯誤';
        $strings['ReturnToPreviousPage'] = '回到您剛才所在的最後一個頁面';
        $strings['UnknownError'] = '未知錯誤';
        $strings['InsufficientPermissionsError'] = '您沒有權限進入這個資源';
        $strings['MissingReservationResourceError'] = '沒有選擇資源';
        $strings['MissingReservationScheduleError'] = '沒有選擇時間表';
        $strings['DoesNotRepeat'] = '不重複';
        $strings['Daily'] = '每天';
        $strings['Weekly'] = '每週';
        $strings['Monthly'] = '每月';
        $strings['Yearly'] = '每年';
        $strings['RepeatPrompt'] = '重複';
        $strings['hours'] = '小時';
        $strings['days'] = '天';
        $strings['weeks'] = '週';
        $strings['months'] = '月';
        $strings['years'] = '年';
        $strings['day'] = '天';
        $strings['week'] = '週';
        $strings['month'] = '月';
        $strings['year'] = '年';
        $strings['repeatDayOfMonth'] = '天/月';
        $strings['repeatDayOfWeek'] = '天/週';
        $strings['RepeatUntilPrompt'] = '直到';
        $strings['RepeatEveryPrompt'] = '每';
        $strings['RepeatDaysPrompt'] = '在';
        $strings['CreateReservationHeading'] = '新增預約';
        $strings['EditReservationHeading'] = '編輯預約 %s';
        $strings['ViewReservationHeading'] = '瀏覽預約 %s';
        $strings['ReservationErrors'] = '更改預約';
        $strings['Create'] = '建立';
        $strings['ThisInstance'] = '只此一次';
        $strings['AllInstances'] = '所有情形';
        $strings['FutureInstances'] = '將來情形';
        $strings['Print'] = '列印';
        $strings['ShowHideNavigation'] = '顯示/隱藏 導航';
        $strings['CollapseSidebar'] = '收合側邊欄';
        $strings['ExpandSidebar'] = '展開側邊欄';
        $strings['ReferenceNumber'] = '參考數字';
        $strings['Tomorrow'] = '明天';
        $strings['LaterThisWeek'] = '本周後段時間';
        $strings['NextWeek'] = '下一周';
        $strings['SignOut'] = '登出';
        $strings['LayoutDescription'] = '自 %s 起，一次顯示 %s 天';
        $strings['AllResources'] = '所有資源';
        $strings['SearchResources'] = '搜尋資源';
        $strings['NoMatchesFound'] = '找不到符合的資源';
        $strings['SearchParticipants'] = '輸入姓名、帳號或 Email';
        $strings['RequiredParticipants'] = '必要參與者';
        $strings['OptionalParticipants'] = '選擇性參與者';
        $strings['ExternalParticipantBadge'] = '外部';
        $strings['InviteExternalEmail'] = '按 Enter 邀請此外部 Email：{{email}}';
        $strings['TakeOffline'] = 'Take 離線';
        $strings['BringOnline'] = 'Bring 線上';
        $strings['AddImage'] = '添加圖片';
        $strings['NoImage'] = '沒有分派圖片';
        $strings['Move'] = '更改';
        $strings['AppearsOn'] = '根據 %s 顯示';
        $strings['Location'] = '地區';
        $strings['NoLocationLabel'] = '(沒有指定地區)';
        $strings['AllLocations'] = '所有地區';
        $strings['ResourceLocationTaipei'] = '台北實體會議室';
        $strings['ResourceLocationShenzhen'] = '深圳實體會議室';
        $strings['ResourceLocationShanghai'] = '上海實體會議室';
        $strings['ResourceLocationCrossRegion'] = '線上會議室（跨地區）';
        $strings['Contact'] = '聯繫';
        $strings['NoContactLabel'] = '(沒有聯繫方式)';
        $strings['Description'] = '說明';
        $strings['NoDescriptionLabel'] = '(沒有說明)';
        $strings['Notes'] = '筆記';
        $strings['NoNotesLabel'] = '(沒有筆記)';
        $strings['NoTitleLabel'] = '(沒有標題)';
        $strings['UsageConfiguration'] = '使用配置';
        $strings['ChangeConfiguration'] = '改變配置';
        $strings['ResourceMinLength'] = '預約必須持續至少 %s';
        $strings['ResourceMinLengthNone'] = '這裡沒有最低預約時間';
        $strings['ResourceMaxLength'] = '預約不能延長超過 %s';
        $strings['ResourceMaxLengthNone'] = '這裡沒有最多預約時間';
        $strings['ResourceRequiresApproval'] = '預約必須得到批准';
        $strings['ResourceRequiresApprovalNone'] = '預約不需要審批';
        $strings['ResourcePermissionAutoGranted'] = '自動授予權限';
        $strings['ResourcePermissionNotAutoGranted'] = '沒有自動獲得許可';
        $strings['ResourceMinNotice'] = '必須在開始時間前至少 %s 完成預約';
        $strings['ResourceMinNoticeNone'] = '到當前時間均可以預約';
        $strings['ResourceMaxNotice'] = '預約不能在當前時間 %s 後前結束';
        $strings['ResourceMaxNoticeNone'] = '預約可以在將來任意時間點結束';
        $strings['ResourceAllowMultiDay'] = '可以跨日期預約';
        $strings['ResourceNotAllowMultiDay'] = '不能跨日期預約';
        $strings['ResourceCapacity'] = '這個資源可以容納 %s 人';
        $strings['ResourceCapacityNone'] = '這個資源有無限容納能力';
        $strings['AddNewResource'] = '添加新的資源';
        $strings['AddNewUser'] = '添加新的用戶';
        $strings['AddUser'] = '添加用戶';
        $strings['Schedule'] = '時間表';
        $strings['AddResource'] = '增加資源';
        $strings['Capacity'] = '容量';
        $strings['Access'] = '進入';
        $strings['Duration'] = '持續時間';
        $strings['Active'] = '啟動';
        $strings['Inactive'] = '未啟動';
        $strings['ResetPassword'] = '重設密碼';
        $strings['LastLogin'] = '上次登入';
        $strings['Search'] = '搜尋';
        $strings['ResourcePermissions'] = '資源許可';
        $strings['Reservations'] = '預約';
        $strings['Groups'] = '群組';
        $strings['Color'] = '顏色';
        $strings['CreditHistory'] = '點數紀錄';
        $strings['Activate'] = '啟用';
        $strings['Deactivate'] = '停用';
        $strings['ResetPassword'] = '重設密碼';
        $strings['AllUsers'] = '所有用戶';
        $strings['AllGroups'] = '所有群組';
        $strings['AllSchedules'] = '所有時間表';
        $strings['UsernameOrEmail'] = '用戶名或電子郵件';
        $strings['Members'] = '成員';
        $strings['QuickSlotCreation'] = '每 %s 分鐘建立時間間隔 (在 %s 和 %s 之間)';
        $strings['ApplyUpdatesTo'] = '申請更新';
        $strings['CancelParticipation'] = '取消參與';
        $strings['Attending'] = '參與';
        $strings['QuotaConfiguration'] = '在 %s 只有 %s 用戶來自 %s 可在去 %s %s 每 %s';
        $strings['reservations'] = '預約';
        $strings['ChangeCalendar'] = '更改日曆';
        $strings['AddQuota'] = '增加配額';
        $strings['FindUser'] = '搜尋用戶';
        $strings['Created'] = '已建立';
        $strings['LastModified'] = '最後更新的';
        $strings['GroupName'] = '群組名稱';
        $strings['GroupMembers'] = '群組成員';
        $strings['GroupRoles'] = '群組權限';
        $strings['GroupAdmin'] = '群組管理員';
        $strings['Actions'] = '活動';
        $strings['CurrentPassword'] = '當前密碼';
        $strings['NewPassword'] = '新密碼';
        $strings['InvalidPassword'] = '當前密碼不正確';
        $strings['PasswordChangedSuccessfully'] = '您的密碼已成功更新';
        $strings['SignedInAs'] = '登入為';
        $strings['NotSignedIn'] = '您還未登入';
        $strings['ReservationTitle'] = '預約名稱';
        $strings['ReservationDescription'] = '預約說明';
        $strings['ResourceList'] = '待預約資源';
        $strings['Accessories'] = '設備/附件';
        $strings['ParticipantList'] = '參與者';
        $strings['InvitationList'] = '受邀請者';
        $strings['AccessoryName'] = '附件名稱';
        $strings['QuantityAvailable'] = '可用數量';
        $strings['Resources'] = '資源';
        $strings['Participants'] = '參與者';
        $strings['User'] = '用戶';
        $strings['Resource'] = '資源';
        $strings['Status'] = '狀態';
        $strings['Approve'] = '審批';
        $strings['Page'] = '頁';
        $strings['Rows'] = '行';
        $strings['Unlimited'] = '無限的';
        $strings['Email'] = '電子郵件';
        $strings['EmailAddress'] = '電子郵寄地址';
        $strings['Phone'] = '電話';
        $strings['Organization'] = '組織';
        $strings['Position'] = '位置';
        $strings['Language'] = '語言';
        $strings['Permissions'] = '許可';
        $strings['Reset'] = '重置';
        $strings['FindGroup'] = '搜尋群組';
        $strings['Manage'] = '管理';
        $strings['None'] = '無';
        $strings['AddToOutlook'] = '添加到日曆';
        $strings['Done'] = '完成';
        $strings['RememberMe'] = '記住我';
        $strings['FirstTimeUser?'] = '您是首次來訪用戶?';
        $strings['CreateAnAccount'] = '建立一個帳號';
        $strings['ViewSchedule'] = '查看時間表';
        $strings['ViewDetails'] = '查看詳情';
        $strings['LineNotifications'] = 'LINE 通知';
        $strings['LineLinkInstructions'] = '加官方帳號好友後，傳送這組代碼完成綁定：';
        $strings['LineAddFriend'] = '加官方帳號好友';
        $strings['LineLinked'] = 'LINE 通知已綁定';
        $strings['LineUnlink'] = '解除綁定';
        $strings['LineLinkCodeNotFound'] = '找不到這組代碼，請確認代碼是否正確，或回設定頁重新產生。';
        $strings['LineLinkSuccess'] = '綁定成功！之後會在這裡收到會議室預約提醒。';
        $strings['LineReservationCreatedMessage'] = "預約成功：%s\n地點：%s\n時間：%s - %s";
        $strings['LineReservationSeriesCreatedMessage'] = "重複預約成功：%s\n地點：%s\n首場：%s - %s\n重複頻率：%s，共 %s 場，至 %s";
        $strings['LineFlexAltText'] = '您的會議室預約已建立';
        $strings['LineFlexStatusCreated'] = '預約成功';
        $strings['LineFlexFieldDateTime'] = '日期與時間';
        $strings['LineFlexFieldVideoResource'] = '視訊會議資源';
        $strings['LineFlexFieldMeetingRoom'] = '會議室';
        $strings['LineFlexFieldOwner'] = '預約人';
        $strings['LineFlexJoinVideoConference'] = '加入視訊會議';
        $strings['LineFlexHostLoginInfo'] = '主持人登入資訊';
        $strings['LineFlexOwnerOnlyNote'] = '僅預約人可查看';
        $strings['LineFlexViewReservationDetails'] = '查看預約詳情';
        $strings['LineReminderStartMessage'] = "提醒：「%s」即將開始\n地點：%s";
        $strings['LineReminderEndMessage'] = "提醒：「%s」即將結束\n地點：%s";
        $strings['LineRepeatCustom'] = '自訂';
        $strings['LineNotificationsToggleLabel'] = '接收 LINE 通知';
        $strings['LineTestNotification'] = '測試通知';
        $strings['LineTestNotificationSent'] = '測試通知已送出，請至 LINE 確認是否收到。';
        $strings['LineTestNotificationMessage'] = '這是一則來自預約系統的測試通知，收到即代表 LINE 通知功能正常運作。';
        $strings['LineNotEnabled'] = 'LINE 通知功能尚未啟用。';
        $strings['LineNotLinked'] = '您的帳號尚未綁定 LINE。';
        $strings['LineStartBinding'] = '開始綁定';
        $strings['ForgotMyPassword'] = '忘記密碼';
        $strings['YouWillBeEmailedANewPassword'] = '您將會收到一份系統自動生成的密碼';
        $strings['Close'] = '關閉';
        $strings['ExportToCSV'] = '匯出為 CSV 文件';
        $strings['OK'] = '同意';
        $strings['Working'] = '工作中';
        $strings['Login'] = '登入';
        $strings['AdditionalInformation'] = '附加資訊';
        $strings['AllFieldsAreRequired'] = '所有欄位都需要填寫';
        $strings['Optional'] = '選填內容';
        $strings['YourProfileWasUpdated'] = '您的個人資料已經更新';
        $strings['YourSettingsWereUpdated'] = '您的設置已經上傳';
        $strings['Register'] = '註冊';
        $strings['SecurityCode'] = '安全碼';
        $strings['ReservationCreatedPreference'] = '有新的預約建立（包含由他人代我建立）';
        $strings['ReservationUpdatedPreference'] = '我的預約內容有異動，例如時間或地點被更改';
        $strings['ReservationDeletedPreference'] = '我的預約被取消或刪除';
        $strings['ReservationApprovalPreference'] = '我送出的預約申請通過審核';
        $strings['ReservationParticipationActivityPreference'] = '有人加入或退出我的預約';
        $strings['ReservationSeriesEndingPreference'] = '我的定期預約系列即將到期結束';
        $strings['PreferenceSendEmail'] = '傳送電子郵件通知';
        $strings['PreferenceNoEmail'] = '不用提醒我';
        $strings['EmailNotifications'] = '電子郵件通知';
        $strings['NotificationPreferencesIntro'] = '選擇哪些預約異動要用電子郵件通知你。';
        $strings['EmailNotificationsToggleLabel'] = '接收 Email 通知';
        $strings['EmailNotificationsToggleDescription'] = '接收完整預約紀錄與行事曆附件';
        $strings['ReservationCreated'] = '您的預約已成功建立！';
        $strings['ReservationUpdated'] = '您的預約已成功更新！';
        $strings['ReservationRemoved'] = '您的預約已刪除';
        $strings['YourReferenceNumber'] = '您的參考數字是 %s';
        $strings['VideoConferenceInfo'] = '視訊連結';
        $strings['VideoLink'] = '連結';
        $strings['MeetingId'] = '會議 ID';
        $strings['Passcode'] = '密碼';
        $strings['VideoConferenceHostAccount'] = '共用主持人帳號';
        $strings['VideoConferenceHostPassword'] = '主持人登入密碼';
        $strings['UpdatingReservation'] = '更新預約';
        $strings['ChangeUser'] = '更改用戶';
        $strings['MoreResources'] = '更多的資源';
        $strings['ReservationLength'] = '預約長度';
        $strings['MoreOptions'] = '更多選項';
        $strings['ParticipantList'] = '參與者名單';
        $strings['AddParticipants'] = '添加';
        $strings['InviteOthers'] = '邀請他人';
        $strings['AddResources'] = '添加資源';
        $strings['AddAccessories'] = '添加設備/附件';
        $strings['Accessory'] = '附件';
        $strings['QuantityRequested'] = '請求數';
        $strings['CreatingReservation'] = '建立預約';
        $strings['UpdatingReservation'] = '更新預約';
        $strings['DeleteWarning'] = '這一行動是永久性的，不可更改！ ';
        $strings['DeleteAccessoryWarning'] = '刪除這個附件將會導致它在所有預約中被刪除.';
        $strings['AddAccessory'] = '添加附件';
        $strings['AddBlackout'] = '添加管制';
        $strings['AllResourcesOn'] = '全部資源在';
        $strings['Reason'] = '理由';
        $strings['BlackoutShowMe'] = '顯示有衝突的預約';
        $strings['BlackoutDeleteConflicts'] = '刪除有衝突的預約';
        $strings['Filter'] = '篩選';
        $strings['Between'] = '在此期間';
        $strings['CreatedBy'] = '建立者';
        $strings['BlackoutCreated'] = '管制時間已經建立！';
        $strings['BlackoutNotCreated'] = '不能建立管制時間';
        $strings['BlackoutConflicts'] = '存在有衝突的管制時間';
        $strings['ReservationConflicts'] = '存在有衝突的預約時間';
        $strings['UsersInGroup'] = '這個群組裡的成員';
        $strings['Browse'] = '瀏覽';
        $strings['DeleteGroupWarning'] = '刪除這個群組會移除所有關聯的資源許可，組內成員都將擁有相關資源的權限.';
        $strings['WhatRolesApplyToThisGroup'] = '哪些角色適用於本群組?';
        $strings['WhoCanManageThisGroup'] = '誰能管理這個群組?';
        $strings['WhoCanManageThisSchedule'] = '誰能管理這時間表?';
        $strings['AddGroup'] = '添加群組';
        $strings['AllQuotas'] = '全部配額';
        $strings['QuotaReminder'] = '請記住：配額是強制性地基於已計劃的時間分區的.';
        $strings['AllReservations'] = '全部預約';
        $strings['PendingReservations'] = '待審核預約';
        $strings['Approving'] = '審核中';
        $strings['MoveToSchedule'] = '移動到時間表';
        $strings['DeleteResourceWarning'] = '刪除這個資源會刪除所有關聯的資料，包括';
        $strings['DeleteResourceWarningReservations'] = '所有過去的、現在的和將來的預約所關聯的.';
        $strings['DeleteResourceWarningPermissions'] = '全部權限分配';
        $strings['DeleteResourceWarningReassign'] = '請您在繼續之前把您不想刪除的任何內容再一次分配.';
        $strings['ScheduleLayout'] = '規劃(全時段 %s)';
        $strings['ReservableTimeSlots'] = '預約的時間間隔';
        $strings['BlockedTimeSlots'] = '管制的時間間隔';
        $strings['ThisIsTheDefaultSchedule'] = '這是默認的時間表';
        $strings['DefaultScheduleCannotBeDeleted'] = '默認時間表不能被刪除';
        $strings['MakeDefault'] = '設為默認的';
        $strings['BringDown'] = '下調';
        $strings['ChangeLayout'] = '改變規劃';
        $strings['AddSchedule'] = '增加時間表';
        $strings['StartsOn'] = '開始於';
        $strings['NumberOfDaysVisible'] = '顯示天數';
        $strings['UseSameLayoutAs'] = '使用相同規劃為';
        $strings['Format'] = '格式';
        $strings['OptionalLabel'] = '可選符號';
        $strings['LayoutInstructions'] = '每行輸入一個時間間隔.時間間隔必須能提供全部的24小時而且開始和結束於上午12：00.';
        $strings['AddUser'] = '添加用戶';
        $strings['UserPermissionInfo'] = '實際進入資源可能會因角色、群組許可或其他許可設定而有所不同.';
        $strings['DeleteUserWarning'] = '刪除用戶將會刪除與他們有關的所有過去的、現在的、將來的預約.';
        $strings['AddAnnouncement'] = '添加通告';
        $strings['Announcement'] = '通告';
        $strings['Priority'] = '優先';
        $strings['Reservable'] = '可預約的';
        $strings['Unreservable'] = '不可預約的';
        $strings['Reserved'] = '已預約';
        $strings['MyReservation'] = '我的預約';
        $strings['Pending'] = '待審批';
        $strings['Past'] = '以前的';
        $strings['Restricted'] = '受限的';
        $strings['ViewAll'] = '查看全部';
        $strings['MoveResourcesAndReservations'] = '移動資源和預約到';
        $strings['TurnOffSubscription'] = '關閉日曆訂閱';
        $strings['TurnOnSubscription'] = '允許訂閱此日曆';
        $strings['SubscribeToCalendar'] = '訂閱此日曆';
        $strings['SubscriptionsAreDisabled'] = '管理員已經禁止訂閱此日曆';
        $strings['NoResourceAdministratorLabel'] = '(沒有資源管理員)';
        $strings['WhoCanManageThisResource'] = '誰能管理這項資源?';
        $strings['ResourceAdministrator'] = '資源管理員';
        $strings['Private'] = '私有的';
        $strings['Accept'] = '接受';
        $strings['Decline'] = '拒絕';
        $strings['ShowFullWeek'] = '顯示全部一週';
        $strings['CustomAttributes'] = '自訂屬性';
        $strings['AddAttribute'] = '添加一個屬性';
        $strings['EditAttribute'] = '更新一個屬性';
        $strings['DisplayLabel'] = '顯示符號';
        $strings['Type'] = '類型';
        $strings['Required'] = '需要';
        $strings['ValidationExpression'] = '驗證運算式';
        $strings['PossibleValues'] = '可能的值';
        $strings['SingleLineTextbox'] = '單行文字方塊';
        $strings['MultiLineTextbox'] = '多行文字方塊';
        $strings['Checkbox'] = '檢查框';
        $strings['SelectList'] = '選擇列表';
        $strings['CommaSeparated'] = '逗號分隔';
        $strings['Category'] = '類別';
        $strings['CategoryReservation'] = '預約';
        $strings['CategoryGroup'] = '群組';
        $strings['SortOrder'] = '排序方式';
        $strings['Title'] = '標題';
        $strings['AdditionalAttributes'] = '附加屬性';
        $strings['True'] = '是';
        $strings['False'] = '否';
        $strings['ForgotPasswordEmailSent'] = '一封包含重設密碼提示的電子郵件已經發往您提供的的電子郵寄地址';
        $strings['ActivationEmailSent'] = '您會立即收到一封有關啟動的電子郵件.';
        $strings['AccountActivationError'] = '對不起，我們不能啟動您的帳號.';
        $strings['Attachments'] = '附件';
        $strings['AttachFile'] = '附件檔';
        $strings['Maximum'] = '最大值';
        $strings['NoScheduleAdministratorLabel'] = '沒有時間表管理員';
        $strings['ScheduleAdministrator'] = '時間表管理員';
        $strings['Total'] = '總數';
        $strings['QuantityReserved'] = '已預約數量';
        $strings['AllAccessories'] = '所有設備/附件';
        $strings['GetReport'] = '取得報告';
        $strings['NoResultsFound'] = '沒有符合的結果';
        $strings['SaveThisReport'] = '儲存這報告';
        $strings['ReportSaved'] = '報告已儲存!';
        $strings['EmailReport'] = '以電郵寄出報告';
        $strings['ReportSent'] = '報告已寄出!';
        $strings['RunReport'] = '導出報告';
        $strings['NoSavedReports'] = '你沒有已儲存的報告.';
        $strings['CurrentWeek'] = '本週';
        $strings['CurrentMonth'] = '本月';
        $strings['AllTime'] = '全部時間';
        $strings['FilterBy'] = '以..過濾';
        $strings['Select'] = '選擇';
        $strings['List'] = '以 列';
        $strings['TotalTime'] = '以 總時間';
        $strings['Count'] = '以 數目';
        $strings['Usage'] = '使用情況';
        $strings['AggregateBy'] = '合計以';
        $strings['Range'] = '範圍';
        $strings['Choose'] = '選擇';
        $strings['All'] = '所有';
        $strings['ViewAsChart'] = '以圖表觀看';
        $strings['ReservedResources'] = '已預約的資源';
        $strings['ReservedAccessories'] = '已預約的設備/附件';
        $strings['ResourceUsageTimeBooked'] = '資源使用情況 - 預訂時間';
        $strings['ResourceUsageReservationCount'] = '資源使用情況 - 預約次數';
        $strings['Top20UsersTimeBooked'] = '二十高使用量使用者 - 預訂時間';
        $strings['Top20UsersReservationCount'] = '二十高使用量使用者 - 預約次數';
        $strings['ConfigurationUpdated'] = '設定檔已更新';
        $strings['ConfigurationUiNotEnabled'] = '這個頁面不能訪問，因為$conf[\'settings\'][\'pages\'][\'enable.configuration\'] 設置為「否」或「欠缺」。';
        $strings['ConfigurationFileNotWritable'] = '設定檔不能寫入。請檢查設定檔的權限後再嘗試。';
        $strings['ConfigurationUpdateHelp'] = '有關配置請參閱配置部份 <a target=_blank href=%s>說明</a>';
        $strings['GeneralConfigSettings'] = '配置';
        $strings['UseSameLayoutForAllDays'] = '每日使用同樣的設定';
        $strings['LayoutVariesByDay'] = '自訂每日的設定';
        $strings['ManageReminders'] = '提醒';
        $strings['ReminderUser'] = '用戶名';
        $strings['ReminderMessage'] = '訊息';
        $strings['ReminderAddress'] = '地址';
        $strings['ReminderSendtime'] = '寄出時間';
        $strings['ReminderRefNumber'] = '預約參考編號';
        $strings['ReminderSendtimeDate'] = '提醒日期';
        $strings['ReminderSendtimeTime'] = '提醒時間 (HH:MM)';
        $strings['ReminderSendtimeAMPM'] = '早上 / 下午';
        $strings['AddReminder'] = '新增提醒';
        $strings['DeleteReminderWarning'] = '確定？';
        $strings['NoReminders'] = '你有沒有即將到來的提醒';
        $strings['Reminders'] = '提醒';
        $strings['SendReminder'] = '發出提醒';
        $strings['minutes'] = '分';
        $strings['hours'] = '小時';
        $strings['days'] = '日';
        $strings['ReminderBeforeStart'] = '開始時間之前';
        $strings['ReminderBeforeEnd'] = '結束時間之前';
        $strings['Logo'] = '圖示';
        $strings['CssFile'] = 'CSS 檔案';
        $strings['ThemeUploadSuccess'] = '您的變更已保存。刷新頁面變更才會生效。';
        $strings['MakeDefaultSchedule'] = '設定為預設的時間表';
        $strings['DefaultScheduleSet'] = '這是你預設的時間表';
        $strings['FlipSchedule'] = '翻轉的時間表排版';
        $strings['Next'] = '下一個';
        $strings['Success'] = '成功';
        $strings['Participant'] = '參與者';
        // End Strings

        // Install
        $strings['InstallApplication'] = '安裝 phpScheduleIt (只限 MySQL)';
        $strings['IncorrectInstallPassword'] = '對不起！密碼不正確。';
        $strings['SetInstallPassword'] = '在執行安裝前，您必須設置安裝密碼。';
        $strings['InstallPasswordInstructions'] = '在 %s 請將 %s 的密碼設定為隨機的及難以猜測，然後返回到這個頁面。<BR/>您可以用 %s 的';
        $strings['NoUpgradeNeeded'] = '有沒有升級的需要。執行安裝過程中會刪除所有現有數據，並安裝一個全新的phpScheduleIt！';
        $strings['ProvideInstallPassword'] = '請提供安裝密碼';
        $strings['InstallPasswordLocation'] = '安裝密碼可以在 %s 內 %s 找到';
        $strings['VerifyInstallSettings'] = '進入下一步前，請驗證以下預設設置。或者，你可以在 %s 改變他們。';
        $strings['DatabaseName'] = '數據庫名稱';
        $strings['DatabaseUser'] = '數據庫用戶';
        $strings['DatabaseHost'] = '數據庫主機';
        $strings['DatabaseCredentials'] = '你必須提供一個有創建數據庫權限的MySQL用戶。如果你不知道，請聯繫您的數據庫管理員。在一般情況下，可使用 Root 用戶';
        $strings['MySQLUser'] = 'MySQL 用戶';
        $strings['InstallOptionsWarning'] = '下列選項可能不適合用於託管環境 (hosted environment) 。如果您要安裝在託管環境 (hosted environment) 中，請使用MySQL的嚮導工具來完成這些步驟。';
        $strings['CreateDatabase'] = '創建數據庫';
        $strings['CreateDatabaseUser'] = '創建數據庫用戶';
        $strings['PopulateExampleData'] = '導入示例數據。創建管理員帳號：admin/password 和 用戶帳號：user/password';
        $strings['DataWipeWarning'] = '警告：這將刪除任何現有的數據';
        $strings['RunInstallation'] = '執行安裝';
        $strings['UpgradeNotice'] = '你從 <b>%s</b> 版本升級到 <b>%s</b> 版本';
        $strings['RunUpgrade'] = '進行升級';
        $strings['Executing'] = '執行中';
        $strings['StatementFailed'] = '失敗。詳細信息：';
        $strings['SQLStatement'] = 'SQL 語句：';
        $strings['ErrorCode'] = '錯誤代碼:';
        $strings['ErrorText'] = '錯誤訊息:';
        $strings['InstallationSuccess'] = '安裝完成';
        $strings['RegisterAdminUser'] = '註冊您的管理員用戶。這是必需的，如果你沒有導入示例數據。請確保 $conf[\'settings\'][\'allow.self.registration\'] = \'true\' 在 %s 檔案';
        $strings['LoginWithSampleAccounts'] = '如果有導入示例數據，您可以登錄 admin/password 為管理員用戶或者 user/password 為普通用戶';
        $strings['InstalledVersion'] = '您現在正在運行 %s 版本的phpScheduleIt';
        $strings['InstallUpgradeConfig'] = '建議升級您的配置文件';
        $strings['InstallationFailure'] = '安裝過程出現問題。請予以更正，並重新安裝。';
        $strings['ConfigureApplication'] = '配置 phpScheduleIt';
        $strings['ConfigUpdateSuccess'] = '你的設定檔目前是最新的';
        $strings['ConfigUpdateFailure'] = '我們不能自動更新你的設定檔。請使用下列的內容覆蓋config.php：';
        // End Install

        // Errors
        $strings['LoginError'] = '用戶名和密碼不符合';
        $strings['ReservationFailed'] = '沒辦法建立您的預約';
        $strings['MinNoticeError'] = '這份預約需要高級提示.最早能提供的日期和時間是 %s.';
        $strings['MaxNoticeError'] = '這份預約不能在以後進行.最遲能被預約的日期和時間是 %s.';
        $strings['MinDurationError'] = '這份預約必須延長到 %s.';
        $strings['MaxDurationError'] = '這份預約不能延長到 %s.';
        $strings['ConflictingAccessoryDates'] = '沒有足夠的附件：';
        $strings['NoResourcePermission'] = '您沒有權限訪問一個或一個以上的請求.';
        $strings['ConflictingReservationDates'] = '在接下來的日子裡存在有衝突的預約:';
        $strings['StartDateBeforeEndDateRule'] = '開始時間和日期必須早於結束時間和日期';
        $strings['MultiDayRule'] = '%s 不允許跨日預約，開始與結束時間必須在同一天。';
        $strings['RecurringWithoutTerminationRule'] = '重複封鎖需要設定終止日期。';
        $strings['StartIsInPast'] = '開始時間和日期必須比當前時間晚';
        $strings['EmailDisabled'] = '管理員已經禁止了郵件提醒';
        $strings['ValidLayoutRequired'] = '時間間隔必須提供全天24小時而且必須從上午12時開始並結束於上午12時.';
        $strings['CustomAttributeErrors'] = '您提供的附加屬性出了問題:';
        $strings['CustomAttributeRequired'] = '%s 是必填項目';
        $strings['CustomAttributeInvalid'] = '提供給 %s 的內容是有效的.';
        $strings['AttachmentLoadingError'] = '對不起，在載入檔時發現了一個問題.';
        $strings['InvalidAttachmentExtension'] = '您只能上傳這些類型的檔: %s';
        $strings['InvalidStartSlot'] = '開始時間和日期不正確';
        $strings['InvalidEndSlot'] = '結束時間和日期不正確';
        $strings['MaxParticipantsError'] = '%s 只可以支援 %s 參與者';
        $strings['ReservationCriticalError'] = '有一個嚴重的錯誤影響您的預訂。如果這種情況持續下去，您的系統管理員聯繫。';
        $strings['InvalidStartReminderTime'] = '開始提醒時間不正確';
        $strings['InvalidEndReminderTime'] = '結束提醒時間不正確';
        // End Errors

        // Page Titles
        $strings['CreateReservation'] = '建立預約';
        $strings['EditReservation'] = '編輯預約';
        $strings['LogIn'] = '登入';
        $strings['ManageReservations'] = '預約';
        $strings['AwaitingActivation'] = '等待啟動';
        $strings['PendingApproval'] = '待審批中';
        $strings['ManageSchedules'] = '時間表';
        $strings['ManageResources'] = '資源';
        $strings['ManageAccessories'] = '設備/附件';
        $strings['ManageUsers'] = '用戶';
        $strings['ManageGroups'] = '群組';
        $strings['ManageQuotas'] = '配額';
        $strings['ManageBlackouts'] = '管制時間';
        $strings['MyDashboard'] = '我的控制板';
        $strings['Overview'] = '總覽';
        $strings['Weekend'] = '週末';
        $strings['Untitled'] = '未命名預約';
        $strings['NoReservationTitle'] = '（無標題）';
        $strings['AddTitle'] = '新增會議名稱';
        $strings['QuickCreateSubheading'] = '快速建立會議室預約';
        $strings['AddParticipantsAction'] = '新增參與者';
        $strings['AddDescription'] = '新增說明';
        $strings['ResourceAvailable'] = '此時段可用';
        $strings['ResourceUnavailable'] = '此時段不可用';
        $strings['CancelAction'] = '取消';
        $strings['YourReservations'] = '我的預約';
        $strings['PhysicalRooms'] = '實體會議室';
        $strings['VideoConferenceResources'] = '視訊會議';
        $strings['SelectResourcePlaceholder'] = '請選擇會議室';
        $strings['SetAsDefaultResource'] = '設為預設';
        $strings['RemoveDefaultResource'] = '取消預設';
        $strings['MyMeetings'] = '我的會議';
        $strings['NextMeeting'] = '下一場會議';
        $strings['NoUpcomingMeetingsSoon'] = '近期沒有安排會議';
        $strings['JoinMeeting'] = '加入會議';
        $strings['CountdownMinutesSuffix'] = '分鐘後';
        $strings['CountdownHoursSuffix'] = '小時後';
        $strings['CountdownDaysSuffix'] = '天後';
        $strings['ReservationScopeAll'] = '全體';
        $strings['ReservationScopeMine'] = '只看我的';
        $strings['ConfirmDeleteReservation'] = '確定要刪除這筆預約嗎？';
        $strings['DeleteRecurringScopePrompt'] = '要刪除哪些預約？';
        $strings['DeleteNotifiesParticipants'] = '刪除後，參與者會收到取消通知。';
        $strings['NewReservation'] = '新增預約';
        $strings['EditReservation'] = '編輯預約';
        $strings['ParticipantsSummary'] = '%s 位參與者（更多選項可管理）';
        $strings['RemindersSummary'] = '已設定提醒（更多選項可調整）';
        $strings['RecurringSummary'] = '重複預約（更多選項可調整規則）';
        $strings['LoadReservationFailed'] = '無法載入這筆預約。';
        $strings['NetworkErrorRetry'] = '網路錯誤，請再試一次。';
        $strings['UnsavedChangesPrompt'] = '尚未儲存的變更會遺失，確定要離開嗎？';
        $strings['ApplyUpdateTo'] = '要把這次修改套用到哪些預約？';
        $strings['EndDateBeforeStartDate'] = '結束時間必須晚於開始時間。';
        $strings['ReservationChanged'] = '這筆預約已被其他人修改，請重新載入後再試。';
        $strings['ReloadLatest'] = '重新載入最新內容';
        $strings['RepeatEndDateRequired'] = '此重複規則需要設定結束日期。';
        $strings['RepeatWeekdayRequired'] = '請至少選擇一個星期幾。';
        $strings['RepeatCustomDateRequired'] = '請至少新增一個日期。';
        $strings['ServerSettings'] = '系統診斷';
        $strings['Dashboard'] = '控制台';
        $strings['Help'] = '幫助';
        $strings['Administration'] = '管理';
        $strings['About'] = '關於';
        $strings['Bookings'] = '預訂';
        $strings['Schedule'] = '時間表';
        $strings['Reservations'] = '預約';
        $strings['Account'] = '帳號';
        $strings['PersonalInformation'] = '個人資料';
        $strings['Preferences'] = '偏好設定';
        $strings['ChangeLanguage'] = '切換語言';
        $strings['EditProfile'] = '編輯個人編纂';
        $strings['FindAnOpening'] = '找到一個開放的';
        $strings['OpenInvitations'] = '開放的邀請';
        $strings['MyCalendar'] = '我的日曆';
        $strings['ResourceCalendar'] = '資源日曆';
        $strings['Reservation'] = '新預約';
        $strings['Install'] = '安裝';
        $strings['ChangePassword'] = '改變密碼';
        $strings['MyAccount'] = '我的帳號';
        $strings['Profile'] = '個人資訊';
        $strings['ApplicationManagement'] = '管理中心';
        $strings['AdminOverview'] = '管理總覽';
        $strings['AdminSectionReservations'] = '預約管理';
        $strings['AdminSectionResources'] = '會議室與資源';
        $strings['AdminSectionUsers'] = '使用者管理';
        $strings['AdminSectionSchedules'] = '開放時間與規則';
        $strings['AdminSectionNotifications'] = '通知與信件';
        $strings['AdminSectionMaintenance'] = '系統維護';
        $strings['AdminOverviewTodaysReservations'] = '今日預約';
        $strings['AdminOverviewResourceStatus'] = '會議室狀態';
        $strings['AdminOverviewRecentUsers'] = '近期使用者';
        $strings['AdminOverviewWarnings'] = '系統警告';
        $strings['AdminOverviewNoReservationsToday'] = '今天沒有預約。';
        $strings['AdminOverviewAvailable'] = '可使用';
        $strings['AdminOverviewUnavailable'] = '不可使用';
        $strings['AdminOverviewHidden'] = '已隱藏';
        $strings['AdminOverviewNoWarnings'] = '目前沒有需要注意的系統警告。';
        $strings['AdminOverviewJoinedOn'] = '加入時間';
        $strings['AdminWarningEmailDisabled'] = 'Email 通知目前是關閉的，預約人將不會收到任何通知信。';
        $strings['AdminWarningLineMisconfigured'] = 'LINE 通知已啟用，但尚未設定 Channel Access Token，LINE 推播無法送出。';
        $strings['AdminWarningPendingApproval'] = '目前有 %s 筆預約正在等待審核。';
        $strings['AdminWarningMissingEmail'] = '有 %s 個帳號缺少 Email，將無法收到通知。';
        $strings['ManageEmailTemplates'] = '通知與信件';
        $strings['ImportICS'] = '匯入行事曆（ICS）';
        $strings['DataCleanup'] = '資料清理';
        $strings['AdminNavMore'] = '更多';
        $strings['AdminNavResources'] = '會議室管理';
        $strings['AdminNavSchedules'] = '開放時間設定';
        $strings['AdminNavEmailTemplates'] = '通知信件';
        $strings['AdminSystemSettings'] = '系統設定';
        $strings['AdminNotificationLog'] = '通知紀錄';
        $strings['AdminAdSyncStatus'] = 'AD 同步';
        $strings['AdminActivityLog'] = 'Admin 操作紀錄';
        $strings['AdminOverviewInUse'] = '使用中';
        $strings['AdminOverviewAvailableRooms'] = '可用會議室';
        $strings['AdminOverviewNotificationFailures'] = '通知失敗';
        $strings['AdminOverviewNeedsAttention'] = '需要處理';
        $strings['AdminOverviewNoIssues'] = '目前沒有需要處理的異常';
        $strings['AdminOverviewViewAllReservations'] = '查看全部預約';
        $strings['AdminOverviewManageRooms'] = '管理會議室';
        $strings['AdminOverviewUnassignedLocation'] = '未指定地區';
        $strings['AdminOverviewSubtitle'] = '掌握今天的預約與系統狀態';
        $strings['AdminOverviewStatusPending'] = '待確認';
        $strings['AdminOverviewStatusInProgress'] = '進行中';
        $strings['AdminOverviewStatusUpcoming'] = '即將開始';
        $strings['AdminOverviewStatusEnded'] = '已結束';
        $strings['AdminOverviewNotConfigured'] = '尚未設定';
        $strings['AdminOverviewInProgress'] = '進行中';
        $strings['AdminOverviewStartingSoon'] = '一小時內即將開始';
        $strings['AdminOverviewUnavailableRooms'] = '停用／維修中會議室';
        $strings['AdminOverviewLiveRoomStatus'] = '即時會議室狀態';
        $strings['AdminOverviewMaintenance'] = '維修中';
        $strings['AdminOverviewUntil'] = '至';
        $strings['AdminOverviewFree'] = '空閒';
        $strings['AdminOverviewNextAt'] = '下一場';
        $strings['AdminOverviewNothingScheduled'] = '無排程';
        $strings['AdminOverviewWeeklyTrend'] = '近七日預約趨勢';
        $strings['AdminOverviewTopResources'] = '熱門會議室排行';
        $strings['AdminOverviewReservationCount'] = '%s 筆預約';
        $strings['AdminOverviewNoReservationsThisWeek'] = '本週尚無預約';
        $strings['AdminBackToOverview'] = '返回管理總覽';
        $strings['AdminReservationsDescription'] = '檢視、篩選並管理所有預約紀錄。';
        $strings['AdminResourcesDescription'] = '管理會議室與可預約資源的狀態、群組與設定。';
        $strings['AdminUsersDescription'] = '管理使用者帳號與角色權限。';
        $strings['AdminSchedulesDescription'] = '設定各會議室的開放時間與規則。';
        $strings['AdminSystemSettingsDescription'] = '管理通知信件、AD 同步、自訂屬性、系統診斷與資料清理。';
        $strings['AdminUserSearchPlaceholder'] = '搜尋姓名、帳號或 Email';
        $strings['AdminUserColumnNameAvatar'] = '姓名／頭像';
        $strings['AdminUserColumnAccount'] = '帳號與 Email';
        $strings['AdminUserColumnDepartment'] = '部門';
        $strings['AdminUserColumnEmailNotify'] = 'Email 通知';
        $strings['AdminUserColumnLineNotify'] = 'LINE 通知';
        $strings['AdminUserColumnLastLogin'] = '最後登入';
        $strings['AdminUserNotifyOn'] = '開啟';
        $strings['AdminUserNotifyOff'] = '關閉';
        $strings['AdminUserLineNotBound'] = '未綁定';
        $strings['AdminUserRole'] = '角色';
        $strings['AdminUserRoleUser'] = '一般使用者';
        $strings['AdminUserRoleAdmin'] = '系統管理員';
        $strings['AdminUserCurrentGroups'] = '所屬群組';
        $strings['AdminUserSortBy'] = '排序方式';
        $strings['AdminUserSortNameAsc'] = '姓名 A-Z';
        $strings['AdminUserSortNameDesc'] = '姓名 Z-A';
        $strings['AdminUserSortLastLoginDesc'] = '最後登入（新到舊）';
        $strings['AdminUserSortLastLoginAsc'] = '最後登入（舊到新）';
        $strings['AdminReservationColumnTime'] = '時間';
        $strings['AdminReservationStatusPending'] = '待審核';
        $strings['AdminReservationStatusConfirmed'] = '已確認';
        $strings['AdminReservationNoResults'] = '沒有符合條件的預約';
        $strings['AdminReservationCancel'] = '取消預約';
        $strings['AdminReservationConfirmCancelTitle'] = '確認取消此預約？';
        $strings['AdminReservationConfirmCancelMessage'] = '取消後將無法復原。';
        $strings['AdminUserViewEdit'] = '查看／編輯';
        $strings['AdminUserCannotDeleteAD'] = 'AD 管理帳號無法直接刪除';
        $strings['AdminScheduleDefaultCannotDelete'] = '預設排程無法刪除';
        $strings['AdminUserConfirmDeactivateTitle'] = '確認停用此使用者？';
        $strings['AdminUserNoResults'] = '沒有符合條件的使用者';
        $strings['AdminResourceAdvancedSettings'] = '進階設定';
        $strings['AdminResourceNoResults'] = '沒有符合條件的會議室';
        $strings['AdminResourceConfirmDeleteMessage'] = '此操作將永久刪除此會議室及其所有預約記錄與權限設定，且無法復原。';
        $strings['AdminScheduleWeeklyHours'] = '每週開放時間';
        $strings['AdminScheduleClosed'] = '休息';
        $strings['AdminScheduleInUse'] = '使用中';
        $strings['AdminScheduleUnused'] = '未使用';
        $strings['AdminScheduleNoResults'] = '沒有符合條件的開放時間規則';
        $strings['Default'] = '預設';
        $strings['AdminUsageAnalytics'] = '使用分析';
        $strings['AdminUsageAnalyticsDescription'] = '預約總數、熱門時段，以及各會議室與城市的使用情況。';
        $strings['AdminUsageAnalyticsExportExcel'] = '匯出 Excel';
        $strings['AdminUsageAnalyticsAllLocations'] = '所有城市';
        $strings['AdminUsageAnalyticsTotalReservations'] = '預約總數';
        $strings['AdminUsageAnalyticsTotalHours'] = '總時數';
        $strings['AdminUsageAnalyticsAverageDuration'] = '平均會議時長（分鐘）';
        $strings['AdminUsageAnalyticsPopularHours'] = '熱門預約時段';
        $strings['AdminUsageAnalyticsByResource'] = '各會議室使用排行';
        $strings['AdminUsageAnalyticsByLocation'] = '各城市使用情況';
        $strings['AdminUsageAnalyticsNoResults'] = '此日期範圍內沒有預約記錄';
        $strings['AdminUsageAnalyticsUnassignedLocation'] = '未指定';
        $strings['AdminUsageAnalyticsHour'] = '時段';
        $strings['AdminUsageAnalyticsDateRange'] = '日期範圍';
        $strings['AdminUsageAnalyticsSummary'] = '總覽';
        $strings['AdminNotificationLogEmpty'] = '尚無寄送紀錄，此功能將於串接實際寄送記錄後啟用。';
        $strings['AdminAdSyncEmpty'] = '尚無同步紀錄，此功能將於串接排程 AD 同步後啟用。';
        $strings['AdminActivityLogEmpty'] = '尚無操作紀錄，此功能將於串接管理員操作稽核後啟用。';
        $strings['ForgotPassword'] = '忘了密碼';
        $strings['NotificationPreferences'] = '通知設定';
        $strings['CalendarHome'] = '行事曆首頁';
        $strings['ManageAnnouncements'] = '通告';
        $strings['Responsibilities'] = '主要職責';
        $strings['GroupReservations'] = '群組預約';
        $strings['ResourceReservations'] = '預約資源';
        $strings['Customization'] = '自訂';
        $strings['Attributes'] = '屬性';
        $strings['AccountActivation'] = '帳號啟動';
        $strings['ScheduleReservations'] = '計劃預約';
        $strings['Reports'] = '報告';
        $strings['GenerateReport'] = '建立一個新報告';
        $strings['MySavedReports'] = '我已儲存的報告';
        $strings['CommonReports'] = '共享的報告';
        $strings['ViewDay'] = '觀看日期';
        $strings['Group'] = '群組';
        $strings['ManageConfiguration'] = '系統設定';
        $strings['LookAndFeel'] = '佈景設定';
        // End Page Titles

        // Day representations
        $strings['DaySundaySingle'] = 'S';
        $strings['DayMondaySingle'] = 'M';
        $strings['DayTuesdaySingle'] = 'T';
        $strings['DayWednesdaySingle'] = 'W';
        $strings['DayThursdaySingle'] = 'T';
        $strings['DayFridaySingle'] = 'F';
        $strings['DaySaturdaySingle'] = 'S';

        $strings['DaySundayAbbr'] = '周日';
        $strings['DayMondayAbbr'] = '週一';
        $strings['DayTuesdayAbbr'] = '週二';
        $strings['DayWednesdayAbbr'] = '週三';
        $strings['DayThursdayAbbr'] = '週四';
        $strings['DayFridayAbbr'] = '週五';
        $strings['DaySaturdayAbbr'] = '週六';
        // End Day representations

        // Email Subjects
        $strings['ReservationApprovedSubject'] = '您的帳號已經啟動';
        $strings['ReservationCreatedSubject'] = '您的預約已經建立';
        $strings['ReservationUpdatedSubject'] = '您的預約已經更新';
        $strings['ReservationDeletedSubject'] = '您的預約已經移除';
        $strings['ReservationCreatedAdminSubject'] = '提示：一個預約已經建立';
        $strings['ReservationUpdatedAdminSubject'] = '提示：一個預約已經更新';
        $strings['ReservationDeleteAdminSubject'] = '提示：一個預約已經移除';
        $strings['ParticipantAddedSubject'] = '預約參與通知';
        $strings['ParticipantDeletedSubject'] = '已移除的預約';
        $strings['InviteeAddedSubject'] = '預約邀請';
        $strings['ResetPassword'] = '重置密碼申請';
        $strings['ActivateYourAccount'] = '請啟動您的帳號';
        $strings['ReportSubject'] = '您要求的報告 (%s)';
        $strings['ReservationStartingSoonSubject'] = '%s 的預約即將開始';
        $strings['ReservationEndingSoonSubject'] = '%s 的預約即將結束';
        // End Email Subjects

        //NEEDS CHECKING
        //Past Reservations
        $strings['NoPastReservations'] = '您沒有以前的預訂';
        $strings['PastReservations'] = '以前的預訂';
        $strings['AllNoPastReservations'] = '在過去的%s天內沒有以前的預訂';
        $strings['AllPastReservations'] = '所有以前的預訂';
        $strings['Yesterday'] = '昨天';
        $strings['EarlierThisWeek'] = '本週早些時候';
        $strings['PreviousWeek'] = '上週';
        //End Past Reservations

        //Group Upcoming Reservations
        $strings['NoGroupUpcomingReservations'] = '您的團體沒有即將到來的預訂';
        $strings['GroupUpcomingReservations'] = '我的團體即將到來的預訂';
        //End Group Upcoming Reservations

        //Facebook Login SDK Error
        $strings['FacebookLoginErrorMessage'] = '使用Facebook登入時發生錯誤。請重試。';
        //End Facebook Login SDK Error

        //Pending Approval Reservations in Dashboard
        $strings['NoPendingApprovalReservations'] = '您沒有等待審批的預訂';
        $strings['PendingApprovalReservations'] = '等待審批的預訂';
        $strings['LaterThisMonth'] = '本月晚些時候';
        $strings['LaterThisYear'] = '本年晚些時候';
        $strings['Remaining'] = '剩餘';
        //End Pending Approval Reservations in Dashboard

        //Missing Check In/Out Reservations in Dashboard
        $strings['NoMissingCheckOutReservations'] = '沒有遺失的退房預訂';
        $strings['MissingCheckOutReservations'] = '遺失的退房預訂';
        //End Missing Check In/Out Reservations in Dashboard

        //Schedule Resource Permissions
        $strings['NoResourcePermissions'] = '由於您在此預訂中的任何資源都沒有權限，因此無法查看預訂詳細信息';
        //End Schedule Resource Permissions
        //END NEEDS CHECKING

        $strings['AdminOtherTools'] = '其他工具';
        $strings['DiagnosticDocsHeading'] = '文件';
        $strings['DiagnosticLastChecked'] = '最後檢查';
        $strings['DiagnosticRecheckAll'] = '重新檢查全部';
        $strings['DiagnosticStatusOk'] = '正常';
        $strings['DiagnosticStatusError'] = '異常';
        $strings['DiagnosticStatusUnconfigured'] = '尚未設定';
        $strings['DiagnosticStatusChecking'] = '檢查中...';
        $strings['DiagnosticLastWebhookReceived'] = '最後收到 webhook';
        $strings['DiagnosticNeverReceived'] = '尚未收到過';
        $strings['DiagnosticSmtpTitle'] = 'SMTP 郵件';
        $strings['DiagnosticLineTitle'] = 'LINE 通知';
        $strings['DiagnosticFilesTitle'] = '檔案權限';
        $strings['DiagnosticAdNotConfigured'] = '目前的登入驗證方式不是 Active Directory。';
        $strings['DiagnosticAdConfigMissing'] = '已選擇 Active Directory 外掛，但找不到對應的設定檔。';
        $strings['DiagnosticAdConnectionFailed'] = '無法連線到 AD/LDAP 伺服器，請確認網域控制站位址與服務帳號設定。';
        $strings['DiagnosticSmtpNotConfigured'] = '尚未啟用郵件或 SMTP 功能。';
        $strings['DiagnosticSmtpConnectionFailed'] = '無法連線到郵件伺服器，請確認 SMTP 主機、連接埠與網路連線。';
        $strings['DiagnosticLineNotConfigured'] = '尚未啟用 LINE 通知功能。';
        $strings['DiagnosticLineTokenInvalid'] = 'LINE 頻道權杖驗證失敗，可能已過期或被撤銷。';
        $strings['DiagnosticLineConnectionFailed'] = '無法連線到 LINE API，請確認能連到 api.line.me。';
        $strings['DiagnosticFilesNotWritable'] = '有必要目錄（tpl_c 或圖片上傳目錄）不存在或無法寫入，請確認目錄權限設定。';
        $strings['DiagnosticArchitecture'] = '系統架構圖';
        $strings['DiagnosticArchitectureDescription'] = '前後端與資料庫串接說明';
        $strings['DiagnosticOpsDoc'] = '維運文件';
        $strings['DiagnosticOpsDocDescription'] = '部署架構、基礎設施與疑難排解紀錄';
        $strings['AdminVideoConferenceInfo'] = '線上會議資訊';
        $strings['VideoConferenceHostLogin'] = '主持人登入資訊';
        $strings['VideoConferenceHostLoginHint'] = '點擊編輯以查看或修改啟動此會議所需的主持人登入帳號密碼。';

        // Onboarding tour
        $strings['TourWelcomeTitle'] = '歡迎使用 CoAsia 會議室預約系統';
        $strings['TourWelcomeBody'] = '花 30 秒帶你認識怎麼找空檔、怎麼訂會議室。之後隨時可以在左側「幫助」→「操作教學」重新看這段導覽。';
        $strings['TourSidebarTitle'] = '認識左側導覽列';
        $strings['TourSidebarBody'] = '<ul><li><strong>+新增預約</strong>：快速開啟建立預約視窗，效果跟在行事曆上拖曳一樣。</li><li><strong>行事曆首頁</strong>：回到會議室行事曆。</li><li><strong>我的會議</strong>：查看你自己即將到來與過去的預約。</li><li><strong>通知設定</strong>：調整要接收哪些通知。</li><li><strong>我的帳號</strong>：查看與編輯個人資料、變更密碼。</li><li><strong>幫助</strong>：重新看這段導覽，或打開完整使用手冊。</li><li>若帳號有管理權限，還會看到<strong>管理中心</strong>，用來管理會議室、使用者與系統設定。</li></ul>';
        $strings['TourResourceTitle'] = '先選你要的會議室';
        $strings['TourResourceBody'] = '點這裡可以搜尋、切換會議室，清單會分成「實體會議室」和「視訊會議」兩區。';
        $strings['TourAvailabilityTitle'] = '一眼看出哪些時段還空著';
        $strings['TourAvailabilityBody'] = '白色代表可預約，彩色色塊是已被訂走的時段。可以切換日／週／月檢視，右上角還能切換「只看我的」。';
        $strings['TourDragCreateTitle'] = '直接在行事曆上拖曳就能訂';
        $strings['TourDragCreateBody'] = '在空白時段按住滑鼠拖曳，放開後會跳出快速建立視窗，填標題、參與者就能送出。拖曳只能限在同一天內喔。側邊欄的「+新增預約」按鈕也會開啟同一個視窗，只是不用先選時段。';
        $strings['TourEditCancelTitle'] = '修改或取消已訂的會議';
        $strings['TourEditCancelBody'] = '點一下已訂的時段會跳出小卡片，可以查看詳情、編輯或取消；如果是重複性會議，取消時可以選擇只取消這一次還是全部。';
        $strings['TourMyMeetingsTitle'] = '這裡看你自己的所有會議';
        $strings['TourMyMeetingsBody'] = '即將開始的會議、歷史記錄都在這裡，視訊會議還會直接顯示「加入會議」按鈕。';
        $strings['TourStartButton'] = '開始導覽';
        $strings['TourSkipButton'] = '略過';
        $strings['TourNextButton'] = '下一步';
        $strings['TourBackButton'] = '上一步';
        $strings['TourDoneButton'] = '完成，開始使用';
        $strings['TourStepOf'] = '第 {n} / {total} 步';
        $strings['TourReplayMenuItem'] = '操作教學';

        // User guide page
        $strings['UserGuide'] = '使用手冊';
        $strings['UserGuideIntro'] = '從找會議室、訂會議室，到管理你的會議，這裡都有說明。';
        $strings['UserGuideGeneralSection'] = '一般使用者';
        $strings['UserGuideAdminSection'] = '管理員';

        $strings['UserGuideLoginTitle'] = '登入與首頁介紹';
        $strings['UserGuideLoginBody'] = '<p>使用你的公司帳號（帳號或 Email）與密碼登入，登入後會直接進入行事曆首頁。</p><p>左側導覽列由上到下依序是：</p><ul><li><strong>+新增預約</strong>——快速開啟建立預約視窗，效果跟在行事曆上拖曳一樣。</li><li><strong>行事曆首頁</strong>——回到會議室行事曆。</li><li><strong>我的會議</strong>——查看你自己即將到來與過去的預約。</li><li><strong>通知設定</strong>——調整要接收哪些通知。</li><li><strong>我的帳號</strong>——查看與編輯個人資料、變更密碼。</li><li><strong>管理中心</strong>——只有具備管理權限的帳號才會看到。</li><li><strong>幫助</strong>——重新播放操作教學，或打開這份使用手冊。</li><li>最下方是登出。</li></ul><p>側邊欄最上方有一個收合按鈕，可以收合成只顯示圖示，讓畫面更寬敞。</p>';

        $strings['UserGuideResourcePickerTitle'] = '選擇與篩選會議室';
        $strings['UserGuideResourcePickerBody'] = '<p>行事曆左上角的會議室選擇器，點開後可以用搜尋框快速找會議室，清單會分成「實體會議室」與「視訊會議」兩區。</p><p>系統一次只會顯示一間會議室的行事曆，選好之後畫面會切換成該會議室的時段。</p>';

        $strings['UserGuideAvailabilityTitle'] = '查看空閒時段';
        $strings['UserGuideAvailabilityBody'] = '<p>行事曆上白色空白代表可預約，彩色色塊是已經被訂走的時段，每間會議室有固定的顏色。右上角可以切換「日」「週」「月」三種檢視，也可以用「只看我的」篩選只顯示自己的預約。</p>';

        $strings['UserGuideDragCreateTitle'] = '建立新預約：行事曆拖曳';
        $strings['UserGuideDragCreateBody'] = '<p>在行事曆空白處按住滑鼠拖曳出想要的時段，放開後會跳出「快速建立」視窗，填寫標題、確認會議室、加入參與者，按下「建立」即可完成。</p><p>拖曳只能限定在同一天內，無法跨到隔天。</p>';

        $strings['UserGuideFullFormTitle'] = '建立新預約：完整表單與週期性預約';
        $strings['UserGuideFullFormBody'] = '<p>如果需要設定重複性會議（例如每週固定會議），請在「快速建立」視窗點選「更多選項」，會進入完整的預約表單，可以設定重複規則（每天/每週/自訂日期）、提醒時間、附件等進階選項，這些是「快速建立」視窗沒有的。</p>';

        $strings['UserGuideEditCancelTitle'] = '編輯與取消預約';
        $strings['UserGuideEditCancelBody'] = '<p>點擊行事曆上已存在的預約，會跳出小卡片顯示標題、時間、地點與建立者，卡片上有「編輯」與「刪除」按鈕（只有你自己或有權限的人才看得到）。編輯會進入完整的預約表單頁面。</p><p>刪除時如果是重複性會議，可以選擇只取消這一次、取消未來所有場次，或整個系列都取消。</p>';

        $strings['UserGuideMyMeetingsTitle'] = '查看「我的會議」';
        $strings['UserGuideMyMeetingsBody'] = '<p>側邊欄「我的會議」會列出你即將到來與過去的所有預約：最上方是「下一場會議」卡片並附倒數計時，接著是依日期分組的即將到來清單，最下方可以展開查看歷史紀錄。如果是視訊會議，符合條件時會直接顯示「加入會議」按鈕。</p>';

        $strings['UserGuideRoomTypesTitle'] = '實體會議室與視訊會議室的差異';
        $strings['UserGuideRoomTypesBody'] = '<p>會議室分成「實體會議室」與「視訊會議」兩種。有設定線上會議連結的會議室會被歸類為視訊會議，預約後系統會在「我的會議」與行事曆事件卡片上顯示「加入會議」按鈕，點擊即可直接進入線上會議；實體會議室則單純只是空間預約，沒有這個按鈕。</p>';

        $strings['UserGuideNotificationsAccountTitle'] = '通知設定與我的帳號';
        $strings['UserGuideNotificationsAccountBody'] = '<p>「通知設定」可以調整你要透過 Email 或 LINE 收到哪些種類的通知（例如預約成功、即將開始提醒、被取消等）。「我的帳號」可以查看與編輯個人資料、變更密碼。</p>';

        $strings['UserGuideFaqTitle'] = '常見問題及錯誤訊息';
        $strings['UserGuideFaqBody'] = '<ul><li><strong>「此時段與其他預約衝突」</strong>——代表這段時間已經被訂走了，請換一個時段。</li><li><strong>「開始時間在過去」或日期時間錯誤</strong>——請檢查你選的日期與時間是否正確。</li><li><strong>「預約不能跨日」</strong>——單筆預約的開始與結束必須在同一天，跨日的會議請拆成兩筆分開預約，或改用完整表單設定重複規則。</li><li><strong>「標題為必填」</strong>——請填寫會議標題再送出。</li><li><strong>「超過此會議室允許的最長預約時間」</strong>——每間會議室都有各自的最長可預約時數，請縮短時間，或聯絡管理員調整設定。</li><li><strong>「已達配額上限」</strong>——代表你或你的部門在某個時間範圍內的預約數量或時數已達上限，請等額度釋放或聯絡管理員。</li><li><strong>「此時段不在開放時間內」</strong>——該會議室在這個時間並未開放預約（例如非上班時段），請選擇開放時間內的時段。</li></ul>';

        // Admin section
        $strings['UserGuideAdminOverviewTitle'] = '管理中心總覽';
        $strings['UserGuideAdminOverviewBody'] = '<p>只有具備管理權限的帳號，側邊欄才會出現「管理中心」。系統分成兩種管理層級：完整管理員可以看到全部 7 個管理項目；被指派為「群組管理員」「會議室管理員」或「排程管理員」的使用者，則會在「主要職責」看到對應範圍的管理功能。一般使用者完全不會看到這兩個選單。</p>';

        $strings['UserGuideAdminReservationsTitle'] = '預約管理';
        $strings['UserGuideAdminReservationsBody'] = '<p>可以查看、篩選、審核（如果會議室需要審核）或代為取消所有使用者的預約，適合處理糾紛或協助使用者調整預約。</p>';

        $strings['UserGuideAdminResourcesTitle'] = '會議室管理';
        $strings['UserGuideAdminResourcesBody'] = '<p>新增、編輯、停用會議室，設定容量、所在位置、是否需要審核、線上會議連結（決定它會不會被歸類到前台的「視訊會議」分組並顯示加入會議按鈕）等屬性。</p><p>這裡另外還有一個獨立的「是否為視訊會議室」分類標記，只用在管理後台自己的篩選與分組，跟前面提到、真正決定前台顯示與加入會議按鈕的線上會議連結設定是兩回事。設定視訊會議室時建議兩個都檢查，只設定其中一個是常見的疏漏。</p>';

        $strings['UserGuideAdminUsersTitle'] = '使用者與權限管理';
        $strings['UserGuideAdminUsersBody'] = '<p>管理帳號的啟用或停用、指派角色權限（完整管理員，或群組／會議室／排程管理員等委任角色）、群組成員設定。</p>';

        $strings['UserGuideAdminSchedulesTitle'] = '排程管理';
        $strings['UserGuideAdminSchedulesBody'] = '<p>設定每個時間表的開放時段、公休日、特殊開放或關閉日，決定使用者實際能在行事曆上選到哪些時段。</p>';

        $strings['UserGuideAdminAnalyticsTitle'] = '使用量分析';
        $strings['UserGuideAdminAnalyticsBody'] = '<p>查看會議室使用率、預約量趨勢等統計資訊，協助評估會議室數量是否足夠或需要調整。</p>';

        $strings['UserGuideAdminSettingsTitle'] = '系統設定';
        $strings['UserGuideAdminSettingsBody'] = '<p>系統層級設定與診斷，包含 Email／SMTP、LINE 通知、AD 同步、系統健康檢查等。</p>';

        $strings['UserGuideAdminTroubleshootingTitle'] = '管理端常見疑難排解';
        $strings['UserGuideAdminTroubleshootingBody'] = '<p>說明配額規則、停用時段（blackout）、額度（credits）等進階規則設定之後，使用者端會看到的錯誤訊息與對應的排查方向，方便快速判斷是規則設定造成的還是真正的系統問題。</p>';

        // Simplified timezone picker region labels (lib/Common/SimplifiedTimezones.php)
        $strings['TzDateLineWest'] = '國際換日線西側';
        $strings['TzSamoa'] = '薩摩亞、中途島';
        $strings['TzHawaii'] = '夏威夷';
        $strings['TzAlaska'] = '阿拉斯加';
        $strings['TzPacific'] = '太平洋時間（美加）';
        $strings['TzMountain'] = '山地時間（美加）';
        $strings['TzCentral'] = '中部時間（美加）';
        $strings['TzEastern'] = '東部時間（美加）';
        $strings['TzAtlanticCanada'] = '大西洋時間（加拿大）';
        $strings['TzBrasilia'] = '巴西利亞';
        $strings['TzAzores'] = '亞速爾群島';
        $strings['TzLondon'] = '格林威治標準時間、倫敦';
        $strings['TzCentralEurope'] = '巴黎、柏林、阿姆斯特丹';
        $strings['TzEasternEurope'] = '雅典、開羅';
        $strings['TzMoscow'] = '莫斯科、巴格達';
        $strings['TzTehran'] = '德黑蘭';
        $strings['TzDubai'] = '杜拜、巴庫';
        $strings['TzKabul'] = '喀布爾';
        $strings['TzKarachi'] = '喀拉蚩';
        $strings['TzIndia'] = '新德里、孟買';
        $strings['TzKathmandu'] = '加德滿都';
        $strings['TzDhaka'] = '阿斯塔納、達卡';
        $strings['TzYangon'] = '仰光';
        $strings['TzBangkok'] = '曼谷、雅加達';
        $strings['TzTaipei'] = '北京、新加坡、台北';
        $strings['TzSeoul'] = '首爾、東京';
        $strings['TzAdelaide'] = '達爾文、阿得雷德';
        $strings['TzSydney'] = '關島、坎培拉、雪梨';
        $strings['TzNewCaledonia'] = '新喀里多尼亞';
        $strings['TzAuckland'] = '奧克蘭、斐濟';
        $strings['TzTonga'] = '東加';
        $strings['TzKiritimati'] = '吉里巴斯';

        $this->Strings = $strings;

        return $this->Strings;
    }

    /**
     * @return array
     */
    protected function _LoadDays()
    {
        $days = parent::_LoadDays();

        /***
        DAY NAMES
        All of these arrays MUST start with Sunday as the first element
        and go through the seven day week, ending on Saturday
         ***/
        // The full day name
        $days['full'] = ['星期日', '星期一', '星期二', '星期三', '星期四', '星期五', '星期六'];
        // The three letter abbreviation
        $days['abbr'] = ['日', '一', '二', '三', '四', '五', '六'];
        // The two letter abbreviation
        $days['two'] = ['日', '一', '二', '三', '四', '五', '六'];
        // The one letter abbreviation
        $days['letter'] = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];

        $this->Days = $days;

        return $this->Days;
    }

    /**
     * @return array
     */
    protected function _LoadMonths()
    {
        $months = parent::_LoadMonths();

        /***
        MONTH NAMES
        All of these arrays MUST start with January as the first element
        and go through the twelve months of the year, ending on December
         ***/
        // The full month name
        $months['full'] = ['一月', '二月', '三月', '四月', '五月', '六月', '七月', '八月', '九月', '十月', '十一月', '十二月'];
        // The three letter month name
        $months['abbr'] = ['一', '二', '三', '四', '五', '六', '七', '八', '九', '十', '十一', '十二'];

        $this->Months = $months;

        return $this->Months;
    }

    /**
     * @return array
     */
    protected function _LoadLetters()
    {
        $this->Letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];

        return $this->Letters;
    }

    protected function _GetHtmlLangCode()
    {
        return 'zh_tw';
    }
}
