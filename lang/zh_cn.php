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
* Tested on Booked Scheduler 2.3
*
*
*
*/

require_once('Language.php');
require_once('en_us.php');

class zh_cn extends en_us
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
        $strings['Timezone'] = '时区';
        $strings['Edit'] = '编辑';
        $strings['Change'] = '更改';
        $strings['Rename'] = '重新命名';
        $strings['Remove'] = '移除';
        $strings['Delete'] = '删除';
        $strings['Update'] = '更新';
        $strings['Cancel'] = '退出';
        $strings['Add'] = '增加';
        $strings['Name'] = '名称';
        $strings['Yes'] = '是';
        $strings['No'] = '否';
        $strings['FirstNameRequired'] = '名字是必需的.';
        $strings['LastNameRequired'] = '姓氏是必需的.';
        $strings['PwMustMatch'] = '密码必须跟前面输入的密码一致.';
        $strings['PwComplexity'] = '密码必须最少6个字符，由字符、数字、符号组成.';
        $strings['ValidEmailRequired'] = '一个有效的电子邮件地址是必需的.';
        $strings['UniqueEmailRequired'] = '这个电子邮件地址已经被注册过了.';
        $strings['UniqueUsernameRequired'] = '这个用户名已经被注册过了.';
        $strings['UserNameRequired'] = '用户名是必需的.';
        $strings['CaptchaMustMatch'] = '请您完全按照密码图片上显示的内容输入字符.';
        $strings['Today'] = '今天';
        $strings['Week'] = '周';
        $strings['Month'] = '月';
        $strings['BackToCalendar'] = '回到日历';
        $strings['BeginDate'] = '开始';
        $strings['EndDate'] = '结束';
        $strings['Username'] = '用户名';
        $strings['Password'] = '密码';
        $strings['PasswordConfirmation'] = '确认密码';
        $strings['DefaultPage'] = '默认主页';
        $strings['MyCalendar'] = '我的日历';
        $strings['ScheduleCalendar'] = '计划日历';
        $strings['Registration'] = '注册';
        $strings['NoAnnouncements'] = '没有通告';
        $strings['Announcements'] = '通告';
        $strings['NoUpcomingReservations'] = '您没有即将到来的预约';
        $strings['UpcomingReservations'] = '即将到来的预约';
        $strings['MyMeetings'] = '我的会议';
        $strings['NextMeeting'] = '下一场会议';
        $strings['NoUpcomingMeetingsSoon'] = '近期没有安排会议';
        $strings['JoinMeeting'] = '加入会议';
        $strings['CountdownMinutesSuffix'] = '分钟后';
        $strings['CountdownHoursSuffix'] = '小时后';
        $strings['CountdownDaysSuffix'] = '天后';
        $strings['ShowHide'] = '显示/隐藏';
        $strings['Copy'] = '复制';
        $strings['Export'] = '导出';
        $strings['Info'] = '第 _PAGE_ 页，共 _PAGES_ 页（总计 _MAX_ 笔）';
        $strings['LengthMenu'] = '每页显示 _MENU_ 笔';
        $strings['DataTablesFirst'] = '第一页';
        $strings['DataTablesPrevious'] = '上一页';
        $strings['DataTablesNext'] = '下一页';
        $strings['DataTablesLast'] = '最后一页';
        $strings['DataTablesProcessing'] = '处理中…';
        $strings['Error'] = '错误';
        $strings['ReturnToPreviousPage'] = '回到您刚才所在的最后一个页面';
        $strings['UnknownError'] = '未知错误';
        $strings['InsufficientPermissionsError'] = '您没有权限进入这个资源';
        $strings['MissingReservationResourceError'] = '没有选择资源';
        $strings['MissingReservationScheduleError'] = '没有选择时间表';
        $strings['DoesNotRepeat'] = '不重复';
        $strings['Daily'] = '每天';
        $strings['Weekly'] = '每周';
        $strings['Monthly'] = '每月';
        $strings['Yearly'] = '每年';
        $strings['RepeatPrompt'] = '重复';
        $strings['hours'] = '小时';
        $strings['days'] = '天';
        $strings['weeks'] = '周';
        $strings['months'] = '月';
        $strings['years'] = '年';
        $strings['day'] = '天';
        $strings['week'] = '周';
        $strings['month'] = '月';
        $strings['year'] = '年';
        $strings['repeatDayOfMonth'] = '天/月';
        $strings['repeatDayOfWeek'] = '天/周';
        $strings['RepeatUntilPrompt'] = '直到';
        $strings['RepeatEveryPrompt'] = '每';
        $strings['RepeatDaysPrompt'] = '在';
        $strings['CreateReservationHeading'] = '建立一个预约';
        $strings['EditReservationHeading'] = '编辑预约 %s';
        $strings['ViewReservationHeading'] = '浏览预约 %s';
        $strings['ReservationErrors'] = '更改预约';
        $strings['Create'] = '建立';
        $strings['ThisInstance'] = '只此一次';
        $strings['AllInstances'] = '所有情形';
        $strings['FutureInstances'] = '将来情形';
        $strings['Print'] = '打印';
        $strings['ShowHideNavigation'] = '显示/隐藏 导航';
        $strings['ReferenceNumber'] = '参考数字';
        $strings['Tomorrow'] = '明天';
        $strings['LaterThisWeek'] = '本周后段时间';
        $strings['NextWeek'] = '下一周';
        $strings['SignOut'] = '注销';
        $strings['LayoutDescription'] = '自 %s 起，一次显示 %s 天';
        $strings['AllResources'] = '所有资源';
        $strings['TakeOffline'] = 'Take 脱机';
        $strings['BringOnline'] = 'Bring 在线';
        $strings['AddImage'] = '添加图片';
        $strings['NoImage'] = '没有分派图片';
        $strings['Move'] = '更改';
        $strings['AppearsOn'] = '根据 %s 显示';
        $strings['Location'] = '地区';
        $strings['NoLocationLabel'] = '(没有指定地区)';
        $strings['Contact'] = '联系';
        $strings['NoContactLabel'] = '(没有联系方式)';
        $strings['Description'] = '说明';
        $strings['NoDescriptionLabel'] = '(没有说明)';
        $strings['Notes'] = '笔记';
        $strings['NoNotesLabel'] = '(没有笔记)';
        $strings['NoTitleLabel'] = '(没有标题)';
        $strings['UsageConfiguration'] = '使用配置';
        $strings['ChangeConfiguration'] = '改变配置';
        $strings['ResourceMinLength'] = '预约必须持续至少 %s';
        $strings['ResourceMinLengthNone'] = '这里没有最低预约时间';
        $strings['ResourceMaxLength'] = '预约不能延长超过 %s';
        $strings['ResourceMaxLengthNone'] = '这里没有最多预约时间';
        $strings['ResourceRequiresApproval'] = '预约必须得到批准';
        $strings['ResourceRequiresApprovalNone'] = '预约不需要审批';
        $strings['ResourcePermissionAutoGranted'] = '自动授予权限';
        $strings['ResourcePermissionNotAutoGranted'] = '没有自动获得许可';
        $strings['ResourceMinNotice'] = '必须在开始时间前至少 %s 完成预约';
        $strings['ResourceMinNoticeNone'] = '到当前时间均可以预约';
        $strings['ResourceMaxNotice'] = '预约不能在当前时间 %s 后前结束';
        $strings['ResourceMaxNoticeNone'] = '预约可以在将来任意时间点结束';
        $strings['ResourceAllowMultiDay'] = '可以跨日期预约';
        $strings['ResourceNotAllowMultiDay'] = '不能跨日期预约';
        $strings['ResourceCapacity'] = '这个资源可以容纳 %s 人';
        $strings['ResourceCapacityNone'] = '这个资源有无限容纳能力';
        $strings['AddNewResource'] = '添加新的资源';
        $strings['AddNewUser'] = '添加新的用户';
        $strings['AddUser'] = '添加用户';
        $strings['Schedule'] = '时间表';
        $strings['AddResource'] = '增加资源';
        $strings['Capacity'] = '容量';
        $strings['Access'] = '进入';
        $strings['Duration'] = '持续时间';
        $strings['Active'] = '启动';
        $strings['Inactive'] = '未启动';
        $strings['ResetPassword'] = '重设密码';
        $strings['LastLogin'] = '上次登入';
        $strings['Search'] = '搜寻';
        $strings['ResourcePermissions'] = '资源许可';
        $strings['Reservations'] = '预约';
        $strings['Groups'] = '群组';
        $strings['Color'] = '颜色';
        $strings['CreditHistory'] = '积分记录';
        $strings['Activate'] = '启用';
        $strings['Deactivate'] = '停用';
        $strings['ResetPassword'] = '重设密码';
        $strings['AllUsers'] = '所有用户';
        $strings['AllGroups'] = '所有群组';
        $strings['AllSchedules'] = '所有时间表';
        $strings['UsernameOrEmail'] = '用户名或电子邮件';
        $strings['Members'] = '成员';
        $strings['QuickSlotCreation'] = '每 %s 分钟建立时间间隔 (在 %s 和 %s 之间)';
        $strings['ApplyUpdatesTo'] = '申请更新';
        $strings['CancelParticipation'] = '取消参与';
        $strings['Attending'] = '参与';
        $strings['QuotaConfiguration'] = '在 %s 只有 %s 用户来自 %s 可在去 %s %s 每 %s';
        $strings['reservations'] = '预约';
        $strings['ChangeCalendar'] = '更改日历';
        $strings['AddQuota'] = '增加配额';
        $strings['FindUser'] = '搜寻用户';
        $strings['Created'] = '已建立';
        $strings['LastModified'] = '最后更新的';
        $strings['GroupName'] = '组名';
        $strings['GroupMembers'] = '群组成员';
        $strings['GroupRoles'] = '群组权限';
        $strings['GroupAdmin'] = '群组管理员';
        $strings['Actions'] = '活动';
        $strings['CurrentPassword'] = '当前密码';
        $strings['NewPassword'] = '新密码';
        $strings['InvalidPassword'] = '当前密码不正确';
        $strings['PasswordChangedSuccessfully'] = '您的密码已成功更新';
        $strings['SignedInAs'] = '登陆为';
        $strings['NotSignedIn'] = '您还没有登陆';
        $strings['ReservationTitle'] = '预约名称';
        $strings['ReservationDescription'] = '预约说明';
        $strings['ResourceList'] = '待预约资源';
        $strings['Accessories'] = '设备/附件';
        $strings['Add'] = '增加';
        $strings['ParticipantList'] = '参与者';
        $strings['InvitationList'] = '受邀请者';
        $strings['AccessoryName'] = '附件名称';
        $strings['QuantityAvailable'] = '可用数量';
        $strings['Resources'] = '资源';
        $strings['Participants'] = '参与者';
        $strings['User'] = '用户';
        $strings['Resource'] = '资源';
        $strings['Status'] = '状态';
        $strings['Approve'] = '审批';
        $strings['Page'] = '页';
        $strings['Rows'] = '行';
        $strings['Unlimited'] = '无限的';
        $strings['Email'] = '电子邮件';
        $strings['EmailAddress'] = '电子邮件地址';
        $strings['Phone'] = '电话';
        $strings['Organization'] = '组织';
        $strings['Position'] = '位置';
        $strings['Language'] = '语言';
        $strings['Permissions'] = '许可';
        $strings['Reset'] = '重置';
        $strings['FindGroup'] = '搜寻群组';
        $strings['Manage'] = '管理';
        $strings['None'] = '无';
        $strings['AddToOutlook'] = '添加到日历';
        $strings['Done'] = '完成';
        $strings['RememberMe'] = '记住我';
        $strings['FirstTimeUser?'] = '您是首次来访用户?';
        $strings['CreateAnAccount'] = '建立一个账号';
        $strings['ViewSchedule'] = '查看时间表';
        $strings['ForgotMyPassword'] = '忘记密码';
        $strings['YouWillBeEmailedANewPassword'] = '您将会收到一份系统自动生成的密码';
        $strings['Close'] = '关闭';
        $strings['ExportToCSV'] = '汇出为 CSV 文件';
        $strings['OK'] = '同意';
        $strings['Working'] = '工作中';
        $strings['Login'] = '登陆';
        $strings['AdditionalInformation'] = '附加信息';
        $strings['AllFieldsAreRequired'] = '所有字段都需要填写';
        $strings['Optional'] = '选填内容';
        $strings['YourProfileWasUpdated'] = '您的个人资料已经更新';
        $strings['YourSettingsWereUpdated'] = '您的设置已经上传';
        $strings['Register'] = '注册';
        $strings['SecurityCode'] = '安全码';
        $strings['ReservationCreatedPreference'] = '当我建立一个预约 或 一个预约已经为我建立';
        $strings['ReservationUpdatedPreference'] = '当我建立一个预约 或 一个预约已经为我更新';
        $strings['ReservationDeletedPreference'] = '当我删除一个预约 或 一个预约已经为我删除';
        $strings['ReservationApprovalPreference'] = '当我的待审批预约获得批准';
        $strings['PreferenceSendEmail'] = '电邮给我';
        $strings['PreferenceNoEmail'] = '不用提醒我';
        $strings['ReservationCreated'] = '您的预约已成功建立！';
        $strings['ReservationUpdated'] = '您的预约已成功更新！';
        $strings['ReservationRemoved'] = '您的预约已删除';
        $strings['YourReferenceNumber'] = '您的参考数字是 %s';
        $strings['UpdatingReservation'] = '更新预约';
        $strings['ChangeUser'] = '更改用户';
        $strings['MoreResources'] = '更多的资源';
        $strings['ReservationLength'] = '预约长度';
        $strings['ParticipantList'] = '参与者名单';
        $strings['AddParticipants'] = '添加';
        $strings['InviteOthers'] = '邀请他人';
        $strings['AddResources'] = '添加资源';
        $strings['AddAccessories'] = '添加设备/附件';
        $strings['Accessory'] = '附件';
        $strings['QuantityRequested'] = '请求数';
        $strings['CreatingReservation'] = '建立预约';
        $strings['UpdatingReservation'] = '更新预约';
        $strings['DeleteWarning'] = '这一行动是永久性的，不可更改！ ';
        $strings['DeleteAccessoryWarning'] = '删除这个附件将会导致它在所有预约中被删除.';
        $strings['AddAccessory'] = '添加附件';
        $strings['AddBlackout'] = '添加管制';
        $strings['AllResourcesOn'] = '全部资源在';
        $strings['Reason'] = '理由';
        $strings['BlackoutShowMe'] = '显示有冲突的预约';
        $strings['BlackoutDeleteConflicts'] = '删除有冲突的预约';
        $strings['Filter'] = '筛选';
        $strings['Between'] = '在此期间';
        $strings['CreatedBy'] = '建立者';
        $strings['BlackoutCreated'] = '管制时间已经建立！';
        $strings['BlackoutNotCreated'] = '不能建立管制时间';
        $strings['BlackoutConflicts'] = '存在有冲突的管制时间';
        $strings['ReservationConflicts'] = '存在有冲突的预约时间';
        $strings['UsersInGroup'] = '这个群组里的成员';
        $strings['Browse'] = '浏览';
        $strings['DeleteGroupWarning'] = '删除这个群组会移除所有关联的资源许可，组内成员都将拥有相关资源的权限.';
        $strings['WhatRolesApplyToThisGroup'] = '哪些角色适用于本群组?';
        $strings['WhoCanManageThisGroup'] = '谁能管理这个群组?';
        $strings['WhoCanManageThisSchedule'] = '谁能管理这时间表?';
        $strings['AddGroup'] = '添加群组';
        $strings['AllQuotas'] = '全部配额';
        $strings['QuotaReminder'] = '请记住：配额是强制性地基于已计划的时间分区的.';
        $strings['AllReservations'] = '全部预约';
        $strings['PendingReservations'] = '待审核预约';
        $strings['Approving'] = '审核中';
        $strings['MoveToSchedule'] = '移动到时间表';
        $strings['DeleteResourceWarning'] = '删除这个资源会删除所有关联的数据，包括';
        $strings['DeleteResourceWarningReservations'] = '所有过去的、现在的和将来的预约所关联的.';
        $strings['DeleteResourceWarningPermissions'] = '全部权限分配';
        $strings['DeleteResourceWarningReassign'] = '请您在继续之前把您不想删除的任何内容再一次分配.';
        $strings['ScheduleLayout'] = '规划(全时段 %s)';
        $strings['ReservableTimeSlots'] = '预约的时间间隔';
        $strings['BlockedTimeSlots'] = '管制的时间间隔';
        $strings['ThisIsTheDefaultSchedule'] = '这是默认的时间表';
        $strings['DefaultScheduleCannotBeDeleted'] = '默认时间表不能被删除';
        $strings['MakeDefault'] = '设为默认的';
        $strings['BringDown'] = '下调';
        $strings['ChangeLayout'] = '改变规划';
        $strings['AddSchedule'] = '增加时间表';
        $strings['StartsOn'] = '开始于';
        $strings['NumberOfDaysVisible'] = '显示天数';
        $strings['UseSameLayoutAs'] = '使用相同规划为';
        $strings['Format'] = '格式';
        $strings['OptionalLabel'] = '可选符号';
        $strings['LayoutInstructions'] = '每行输入一个时间间隔.时间间隔必须能提供全部的24小时而且开始和结束于上午12：00.';
        $strings['AddUser'] = '添加用户';
        $strings['UserPermissionInfo'] = '实际进入资源可能会因角色、群组许可或其他许可设定而有所不同.';
        $strings['DeleteUserWarning'] = '删除用户将会删除与他们有关的所有过去的、现在的、将来的预约.';
        $strings['AddAnnouncement'] = '添加通告';
        $strings['Announcement'] = '通告';
        $strings['Priority'] = '优先';
        $strings['Reservable'] = '可预约的';
        $strings['Unreservable'] = '不可预约的';
        $strings['Reserved'] = '已预约';
        $strings['MyReservation'] = '我的预约';
        $strings['Pending'] = '待审批';
        $strings['Past'] = '以前的';
        $strings['Restricted'] = '受限的';
        $strings['ViewAll'] = '查看全部';
        $strings['MoveResourcesAndReservations'] = '移动资源和预约到';
        $strings['TurnOffSubscription'] = '关闭日历订阅';
        $strings['TurnOnSubscription'] = '允许订阅此日历';
        $strings['SubscribeToCalendar'] = '订阅此日历';
        $strings['SubscriptionsAreDisabled'] = '管理员已经禁止订阅此日历';
        $strings['NoResourceAdministratorLabel'] = '(没有资源管理员)';
        $strings['WhoCanManageThisResource'] = '谁能管理这项资源?';
        $strings['ResourceAdministrator'] = '资源管理员';
        $strings['Private'] = '私有的';
        $strings['Accept'] = '接受';
        $strings['Decline'] = '拒绝';
        $strings['ShowFullWeek'] = '显示全部一周';
        $strings['CustomAttributes'] = '自定义属性';
        $strings['AddAttribute'] = '添加一个属性';
        $strings['EditAttribute'] = '更新一个属性';
        $strings['DisplayLabel'] = '显示符号';
        $strings['Type'] = '类型';
        $strings['Required'] = '需要';
        $strings['ValidationExpression'] = '验证表达式';
        $strings['PossibleValues'] = '可能的值';
        $strings['SingleLineTextbox'] = '单行文本框';
        $strings['MultiLineTextbox'] = '多行文本框';
        $strings['Checkbox'] = '检查框';
        $strings['SelectList'] = '选择列表';
        $strings['CommaSeparated'] = '逗号分隔';
        $strings['Category'] = '类别';
        $strings['CategoryReservation'] = '预约';
        $strings['CategoryGroup'] = '群组';
        $strings['SortOrder'] = '排序方式';
        $strings['Title'] = '标题';
        $strings['AdditionalAttributes'] = '附加属性';
        $strings['True'] = '是';
        $strings['False'] = '否';
        $strings['ForgotPasswordEmailSent'] = '一封包含重设密码提示的电子邮件已经发往您提供的的电子邮件地址';
        $strings['ActivationEmailSent'] = '您会立即收到一封有关启动的电子邮件.';
        $strings['AccountActivationError'] = '对不起，我们不能启动您的账号.';
        $strings['Attachments'] = '附件';
        $strings['AttachFile'] = '附件文件';
        $strings['Maximum'] = '最大值';
        $strings['NoScheduleAdministratorLabel'] = '没有时间表管理员';
        $strings['ScheduleAdministrator'] = '时间表管理员';
        $strings['Total'] = '总数';
        $strings['QuantityReserved'] = '已预约数量';
        $strings['AllAccessories'] = '所有设备/附件';
        $strings['GetReport'] = '取得报告';
        $strings['NoResultsFound'] = '没有符合的结果';
        $strings['SaveThisReport'] = '储存这报告';
        $strings['ReportSaved'] = '报告已储存!';
        $strings['EmailReport'] = '以电邮寄出报告';
        $strings['ReportSent'] = '报告已寄出!';
        $strings['RunReport'] = '导出报告';
        $strings['NoSavedReports'] = '你没有已储存的报告.';
        $strings['CurrentWeek'] = '本周';
        $strings['CurrentMonth'] = '本月';
        $strings['AllTime'] = '全部时间';
        $strings['FilterBy'] = '以..过滤';
        $strings['Select'] = '选择';
        $strings['List'] = '以 列';
        $strings['TotalTime'] = '以 总时间';
        $strings['Count'] = '以 数目';
        $strings['Usage'] = '使用情况';
        $strings['AggregateBy'] = '合计以';
        $strings['Range'] = '范围';
        $strings['Choose'] = '选择';
        $strings['All'] = '所有';
        $strings['ViewAsChart'] = '以图表观看';
        $strings['ReservedResources'] = '已预约的资源';
        $strings['ReservedAccessories'] = '已预约的设备/附件';
        $strings['ResourceUsageTimeBooked'] = '资源使用情况 - 预订时间';
        $strings['ResourceUsageReservationCount'] = '资源使用情况 - 预约次数';
        $strings['Top20UsersTimeBooked'] = '二十高使用量使用者 - 预订时间';
        $strings['Top20UsersReservationCount'] = '二十高使用量使用者 - 预约次数';
        // End Strings

        // Errors
        $strings['LoginError'] = '用户名和密码不符合';
        $strings['ReservationFailed'] = '没办法建立您的预约';
        $strings['MinNoticeError'] = '这份预约需要高级提示.最早能提供的日期和时间是 %s.';
        $strings['MaxNoticeError'] = '这份预约不能在以后进行.最迟能被预约的日期和时间是 %s.';
        $strings['MinDurationError'] = '这份预约必须延长到 %s.';
        $strings['MaxDurationError'] = '这份预约不能延长到 %s.';
        $strings['ConflictingAccessoryDates'] = '没有足够的附件：';
        $strings['NoResourcePermission'] = '您没有权限访问一个或一个以上的请求.';
        $strings['ConflictingReservationDates'] = '在接下来的日子里存在有冲突的预约:';
        $strings['StartDateBeforeEndDateRule'] = '开始时间和日期必须早于结束时间和日期';
        $strings['RecurringWithoutTerminationRule'] = '重复黑名单需要设定终止日期。';
        $strings['StartIsInPast'] = '开始时间和日期必须比当前时间晚';
        $strings['EmailDisabled'] = '管理员已经禁止了邮件提醒';
        $strings['ValidLayoutRequired'] = '时间间隔必须提供全天24小时而且必须从上午12时开始并结束于上午12时.';
        $strings['CustomAttributeErrors'] = '您提供的附加属性出了问题:';
        $strings['CustomAttributeRequired'] = '%s 是必填项目';
        $strings['CustomAttributeInvalid'] = '提供给 %s 的内容是有效的.';
        $strings['AttachmentLoadingError'] = '对不起，在加载档时发现了一个问题.';
        $strings['InvalidAttachmentExtension'] = '您只能上传这些类型的档: %s';
        // End Errors

        // Page Titles
        $strings['CreateReservation'] = '建立预约';
        $strings['EditReservation'] = '编辑预约';
        $strings['LogIn'] = '登入';
        $strings['ManageReservations'] = '预约';
        $strings['AwaitingActivation'] = '等待启动';
        $strings['PendingApproval'] = '待审批中';
        $strings['ManageSchedules'] = '时间表';
        $strings['ManageResources'] = '资源';
        $strings['ManageAccessories'] = '设备/附件';
        $strings['ManageUsers'] = '用户';
        $strings['ManageGroups'] = '群组';
        $strings['ManageQuotas'] = '配额';
        $strings['ManageBlackouts'] = '管制时间';
        $strings['MyDashboard'] = '我的控制板';
        $strings['ServerSettings'] = '服务器设置';
        $strings['Dashboard'] = '控制台';
        $strings['Help'] = '帮助';
        $strings['Administration'] = '管理';
        $strings['About'] = '关于';
        $strings['Bookings'] = '预订';
        $strings['Schedule'] = '时间表';
        $strings['Reservations'] = '预约';
        $strings['Account'] = '账号';
        $strings['PersonalInformation'] = '个人资料';
        $strings['Preferences'] = '偏好设置';
        $strings['ChangeLanguage'] = '切换语言';
        $strings['EditProfile'] = '编辑个人编纂';
        $strings['FindAnOpening'] = '找到一个开放的';
        $strings['OpenInvitations'] = '开放的邀请';
        $strings['MyCalendar'] = '我的日历';
        $strings['ResourceCalendar'] = '资源日历';
        $strings['Reservation'] = '新预约';
        $strings['Install'] = '安装';
        $strings['ChangePassword'] = '改变密码';
        $strings['MyAccount'] = '我的账号';
        $strings['Profile'] = '个人信息';
        $strings['ApplicationManagement'] = '程序管理';
        $strings['AdminOverview'] = '管理总览';
        $strings['AdminSectionReservations'] = '预约管理';
        $strings['AdminSectionUsers'] = '使用者管理';
        $strings['ManageEmailTemplates'] = '通知与信件';
        $strings['ImportICS'] = '导入行事历（ICS）';
        $strings['DataCleanup'] = '数据清理';
        $strings['ManageConfiguration'] = '系统设定';
        $strings['ServerSettings'] = '系统诊断';
        $strings['CustomAttributes'] = '自定义属性';
        $strings['AdminNavMore'] = '更多';
        $strings['AdminNavResources'] = '会议室管理';
        $strings['AdminNavSchedules'] = '开放时间设定';
        $strings['AdminNavEmailTemplates'] = '通知信件';
        $strings['AdminSystemSettings'] = '系统设定';
        $strings['AdminNotificationLog'] = '通知记录';
        $strings['AdminAdSyncStatus'] = 'AD 同步';
        $strings['AdminActivityLog'] = 'Admin 操作记录';
        $strings['AdminOverviewInUse'] = '使用中';
        $strings['AdminOverviewAvailableRooms'] = '可用会议室';
        $strings['AdminOverviewNotificationFailures'] = '通知失败';
        $strings['AdminOverviewNeedsAttention'] = '需要处理';
        $strings['AdminOverviewNoIssues'] = '目前没有需要处理的异常';
        $strings['AdminOverviewSystemStatusSummary'] = 'AD 同步正常 · Email 正常 · LINE 正常';
        $strings['AdminOverviewViewAllReservations'] = '查看全部预约';
        $strings['AdminOverviewManageRooms'] = '管理会议室';
        $strings['AdminOverviewUnassignedLocation'] = '未指定地区';
        $strings['AdminWarningMissingEmail'] = '有 %s 个账号缺少 Email，将无法收到通知。';
        $strings['AdminOverviewSubtitle'] = '掌握今天的预约与系统状态';
        $strings['AdminOverviewStatusPending'] = '待确认';
        $strings['AdminOverviewStatusInProgress'] = '进行中';
        $strings['AdminOverviewStatusUpcoming'] = '即将开始';
        $strings['AdminOverviewStatusEnded'] = '已结束';
        $strings['AdminOverviewNotConfigured'] = '尚未设定';
        $strings['AdminBackToOverview'] = '返回管理总览';
        $strings['AdminReservationsDescription'] = '检视、筛选并管理所有预约记录。';
        $strings['AdminResourcesDescription'] = '管理会议室与可预约资源的状态、群组与设定。';
        $strings['AdminUsersDescription'] = '管理使用者账号与角色权限。';
        $strings['AdminSchedulesDescription'] = '设定各会议室的开放时间与规则。';
        $strings['AdminSystemSettingsDescription'] = '管理通知信件、AD 同步、自定义属性、系统诊断与数据清理。';
        $strings['AdminUserSearchPlaceholder'] = '搜索姓名、账号或 Email';
        $strings['AdminUserColumnNameAvatar'] = '姓名／头像';
        $strings['AdminUserColumnAccount'] = '账号与 Email';
        $strings['AdminUserColumnDepartment'] = '部门';
        $strings['AdminUserColumnEmailNotify'] = 'Email 通知';
        $strings['AdminUserColumnLineNotify'] = 'LINE 通知';
        $strings['AdminUserColumnLastLogin'] = '最后登录';
        $strings['AdminUserNotifyOn'] = '开启';
        $strings['AdminUserNotifyOff'] = '关闭';
        $strings['AdminUserLineNotBound'] = '未绑定';
        $strings['AdminUserRole'] = '角色';
        $strings['AdminUserRoleUser'] = '一般使用者';
        $strings['AdminUserRoleAdmin'] = '系统管理员';
        $strings['AdminUserCurrentGroups'] = '所属群组';
        $strings['AdminUserSortBy'] = '排序方式';
        $strings['AdminUserSortNameAsc'] = '姓名 A-Z';
        $strings['AdminUserSortNameDesc'] = '姓名 Z-A';
        $strings['AdminUserSortLastLoginDesc'] = '最后登录（新到旧）';
        $strings['AdminUserSortLastLoginAsc'] = '最后登录（旧到新）';
        $strings['AdminReservationColumnTime'] = '时间';
        $strings['AdminReservationStatusPending'] = '待审核';
        $strings['AdminReservationStatusConfirmed'] = '已确认';
        $strings['AdminReservationNoResults'] = '没有符合条件的预约';
        $strings['AdminReservationCancel'] = '取消预约';
        $strings['AdminReservationConfirmCancelTitle'] = '确认取消此预约？';
        $strings['AdminReservationConfirmCancelMessage'] = '取消后将无法复原。';
        $strings['AdminUserViewEdit'] = '查看／编辑';
        $strings['AdminUserCannotDeleteAD'] = 'AD 管理账号无法直接删除';
        $strings['AdminUserConfirmDeactivateTitle'] = '确认停用此使用者？';
        $strings['AdminUserNoResults'] = '没有符合条件的使用者';
        $strings['AdminResourceAdvancedSettings'] = '进阶设定';
        $strings['AdminResourceNoResults'] = '没有符合条件的会议室';
        $strings['AdminResourceConfirmDeleteMessage'] = '此操作将永久删除此会议室及其所有预约记录与权限设定，且无法复原。';
        $strings['AdminScheduleWeeklyHours'] = '每周开放时间';
        $strings['AdminScheduleClosed'] = '休息';
        $strings['AdminScheduleInUse'] = '使用中';
        $strings['AdminScheduleUnused'] = '未使用';
        $strings['AdminScheduleNoResults'] = '没有符合条件的开放时间规则';
        $strings['Default'] = '预设';
        $strings['AdminUsageAnalytics'] = '使用分析';
        $strings['AdminUsageAnalyticsDescription'] = '预约总数、热门时段，以及各会议室与城市的使用情况。';
        $strings['AdminUsageAnalyticsExportExcel'] = '汇出 Excel';
        $strings['AdminUsageAnalyticsAllLocations'] = '所有城市';
        $strings['AdminUsageAnalyticsTotalReservations'] = '预约总数';
        $strings['AdminUsageAnalyticsTotalHours'] = '总时数';
        $strings['AdminUsageAnalyticsAverageDuration'] = '平均会议时长（分钟）';
        $strings['AdminUsageAnalyticsPopularHours'] = '热门预约时段';
        $strings['AdminUsageAnalyticsByResource'] = '各会议室使用排行';
        $strings['AdminUsageAnalyticsByLocation'] = '各城市使用情况';
        $strings['AdminUsageAnalyticsNoResults'] = '此日期范围内没有预约记录';
        $strings['AdminUsageAnalyticsUnassignedLocation'] = '未指定';
        $strings['AdminUsageAnalyticsHour'] = '时段';
        $strings['AdminUsageAnalyticsDateRange'] = '日期范围';
        $strings['AdminUsageAnalyticsSummary'] = '总览';
        $strings['AdminNotificationLogEmpty'] = '尚无寄送记录，此功能将于串接实际寄送记录后启用。';
        $strings['AdminAdSyncEmpty'] = '尚无同步记录，此功能将于串接排程 AD 同步后启用。';
        $strings['AdminActivityLogEmpty'] = '尚无操作记录，此功能将于串接管理员操作稽核后启用。';
        $strings['ForgotPassword'] = '忘了密码';
        $strings['NotificationPreferences'] = '通知设定';
        $strings['ManageAnnouncements'] = '通告';
        $strings['Responsibilities'] = '主要职责';
        $strings['GroupReservations'] = '群组预约';
        $strings['ResourceReservations'] = '预约资源';
        $strings['Customization'] = '自定义';
        $strings['Attributes'] = '属性';
        $strings['AccountActivation'] = '账号激活';
        $strings['ScheduleReservations'] = '计划预约';
        $strings['Reports'] = '报告';
        $strings['GenerateReport'] = '建立一个新报告';
        $strings['MySavedReports'] = '我已储存的报告';
        $strings['CommonReports'] = '共享的报告';
        $strings['ViewDay'] = '观看日期';
        $strings['Group'] = '群组';
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
        $strings['DayMondayAbbr'] = '周一';
        $strings['DayTuesdayAbbr'] = '周二';
        $strings['DayWednesdayAbbr'] = '周三';
        $strings['DayThursdayAbbr'] = '周四';
        $strings['DayFridayAbbr'] = '周五';
        $strings['DaySaturdayAbbr'] = '周六';
        // End Day representations

        // Email Subjects
        $strings['ReservationApprovedSubject'] = '您的账号已经启动';
        $strings['ReservationCreatedSubject'] = '您的预约已经建立';
        $strings['ReservationUpdatedSubject'] = '您的预约已经更新';
        $strings['ReservationDeletedSubject'] = '您的预约已经移除';
        $strings['ReservationCreatedAdminSubject'] = '提示：一个预约已经建立';
        $strings['ReservationUpdatedAdminSubject'] = '提示：一个预约已经更新';
        $strings['ReservationDeleteAdminSubject'] = '提示：一个预约已经移除';
        $strings['ParticipantAddedSubject'] = '预约参与通知';
        $strings['ParticipantDeletedSubject'] = '已移除的预约';
        $strings['InviteeAddedSubject'] = '预约邀请';
        $strings['ResetPassword'] = '重置密码申请';
        $strings['ActivateYourAccount'] = '请启动您的账号';
        // End Email Subjects

        //NEEDS CHECKING
        //Past Reservations
        $strings['NoPastReservations'] = '您没有以前的预订';
        $strings['PastReservations'] = '以前的预订';
        $strings['AllNoPastReservations'] = '在过去的%s天内没有以前的预订';
        $strings['AllPastReservations'] = '所有以前的预订';
        $strings['Yesterday'] = '昨天';
        $strings['EarlierThisWeek'] = '本周早些时候';
        $strings['PreviousWeek'] = '上周';
        //End Past Reservations

        //Group Upcoming Reservations
        $strings['NoGroupUpcomingReservations'] = '您的团体没有即将到来的预订';
        $strings['GroupUpcomingReservations'] = '我的团体即将到来的预订';
        //End Group Upcoming Reservations

        //Facebook Login SDK Error
        $strings['FacebookLoginErrorMessage'] = '使用Facebook登录时发生错误。请重试。';
        //End Facebook Login SDK Error

        //Pending Approval Reservations in Dashboard
        $strings['NoPendingApprovalReservations'] = '您没有等待批准的预订';
        $strings['PendingApprovalReservations'] = '等待批准的预订';
        $strings['LaterThisMonth'] = '本月晚些时候';
        $strings['LaterThisYear'] = '本年晚些时候';
        $strings['Remaining'] = '剩余';
        //End Pending Approval Reservations in Dashboard

        //Missing Check In/Out Reservations in Dashboard
        $strings['NoMissingCheckOutReservations'] = '没有缺失的退房预订';
        $strings['MissingCheckOutReservations'] = '缺失的退房预订';
        //End Missing Check In/Out Reservations in Dashboard

        //Schedule Resource Permissions
        $strings['NoResourcePermissions'] = '由于您在此预订中的任何资源都没有权限，因此无法查看预订详细信息';
        //End Schedule Resource Permissions
        //END NEEDS CHECKING

        // Onboarding tour
        $strings['TourWelcomeTitle'] = '欢迎使用 CoAsia 会议室预约系统';
        $strings['TourWelcomeBody'] = '花 30 秒带你认识怎么找空档、怎么订会议室。之后随时可以在左侧"帮助"→"操作教程"重新看这段导览。';
        $strings['TourSidebarTitle'] = '认识左侧导航栏';
        $strings['TourSidebarBody'] = '<ul><li><strong>+新增预约</strong>：快速打开创建预约窗口，效果跟在日历上拖拽一样。</li><li><strong>日历首页</strong>：回到会议室日历。</li><li><strong>我的会议</strong>：查看你自己即将到来与过去的预约。</li><li><strong>通知设定</strong>：调整要接收哪些通知。</li><li><strong>我的账号</strong>：查看与编辑个人资料、修改密码。</li><li><strong>帮助</strong>：重新看这段导览，或打开完整使用手册。</li><li>若账号有管理权限，还会看到<strong>管理中心</strong>，用来管理会议室、用户与系统设置。</li></ul>';
        $strings['TourResourceTitle'] = '先选你要的会议室';
        $strings['TourResourceBody'] = '点这里可以搜索、切换会议室，列表会分成"实体会议室"和"视频会议"两区。';
        $strings['TourAvailabilityTitle'] = '一眼看出哪些时段还空着';
        $strings['TourAvailabilityBody'] = '白色代表可预约，彩色色块是已被订走的时段。可以切换日／周／月视图，右上角还能切换"只看我的"。';
        $strings['TourDragCreateTitle'] = '直接在日历上拖拽就能订';
        $strings['TourDragCreateBody'] = '在空白时段按住鼠标拖拽，放开后会弹出快速创建窗口，填标题、参与者就能提交。拖拽只能限在同一天内哦。侧边栏的"+新增预约"按钮也会打开同一个窗口，只是不用先选时段。';
        $strings['TourEditCancelTitle'] = '修改或取消已订的会议';
        $strings['TourEditCancelBody'] = '点一下已订的时段会弹出小卡片，可以查看详情、编辑或取消；如果是重复性会议，取消时可以选择只取消这一次还是全部。';
        $strings['TourMyMeetingsTitle'] = '这里看你自己的所有会议';
        $strings['TourMyMeetingsBody'] = '即将开始的会议、历史记录都在这里，视频会议还会直接显示"加入会议"按钮。';
        $strings['TourStartButton'] = '开始导览';
        $strings['TourSkipButton'] = '跳过';
        $strings['TourNextButton'] = '下一步';
        $strings['TourBackButton'] = '上一步';
        $strings['TourDoneButton'] = '完成，开始使用';
        $strings['TourStepOf'] = '第 {n} / {total} 步';
        $strings['TourReplayMenuItem'] = '操作教程';

        // User guide page
        $strings['UserGuide'] = '使用手册';
        $strings['UserGuideIntro'] = '从找会议室、订会议室，到管理你的会议，这里都有说明。';
        $strings['UserGuideGeneralSection'] = '普通用户';
        $strings['UserGuideAdminSection'] = '管理员';

        $strings['UserGuideLoginTitle'] = '登录与首页介绍';
        $strings['UserGuideLoginBody'] = '<p>使用你的公司账号（账号或 Email）与密码登录，登录后会直接进入日历首页。</p><p>左侧导航栏由上到下依次是：</p><ul><li><strong>+新增预约</strong>——快速打开创建预约窗口，效果跟在日历上拖拽一样。</li><li><strong>日历首页</strong>——回到会议室日历。</li><li><strong>我的会议</strong>——查看你自己即将到来与过去的预约。</li><li><strong>通知设定</strong>——调整要接收哪些通知。</li><li><strong>我的账号</strong>——查看与编辑个人资料、修改密码。</li><li><strong>管理中心</strong>——只有具备管理权限的账号才会看到。</li><li><strong>帮助</strong>——重新播放操作教程，或打开这份使用手册。</li><li>最下方是退出登录。</li></ul><p>侧边栏最上方有一个折叠按钮，可以折叠成只显示图标，让画面更宽敞。</p>';

        $strings['UserGuideResourcePickerTitle'] = '选择与筛选会议室';
        $strings['UserGuideResourcePickerBody'] = '<p>日历左上角的会议室选择器，点开后可以用搜索框快速找会议室，列表会分成"实体会议室"与"视频会议"两区。</p><p>系统一次只会显示一间会议室的日历，选好之后画面会切换成该会议室的时段。</p>';

        $strings['UserGuideAvailabilityTitle'] = '查看空闲时段';
        $strings['UserGuideAvailabilityBody'] = '<p>日历上白色空白代表可预约，彩色色块是已经被订走的时段，每间会议室有固定的颜色。右上角可以切换"日""周""月"三种视图，也可以用"只看我的"筛选只显示自己的预约。</p>';

        $strings['UserGuideDragCreateTitle'] = '创建新预约：日历拖拽';
        $strings['UserGuideDragCreateBody'] = '<p>在日历空白处按住鼠标拖拽出想要的时段，放开后会弹出"快速创建"窗口，填写标题、确认会议室、加入参与者，点击"创建"即可完成。</p><p>拖拽只能限定在同一天内，无法跨到第二天。</p>';

        $strings['UserGuideFullFormTitle'] = '创建新预约：完整表单与周期性预约';
        $strings['UserGuideFullFormBody'] = '<p>如果需要设定重复性会议（例如每周固定会议），请在"快速创建"窗口点选"更多选项"，会进入完整的预约表单，可以设定重复规则（每天/每周/自定义日期）、提醒时间、附件等高级选项，这些是"快速创建"窗口没有的。</p>';

        $strings['UserGuideEditCancelTitle'] = '编辑与取消预约';
        $strings['UserGuideEditCancelBody'] = '<p>点击日历上已存在的预约，会弹出小卡片显示标题、时间、地点与创建者，卡片上有"编辑"与"删除"按钮（只有你自己或有权限的人才看得到）。编辑会进入完整的预约表单页面。</p><p>删除时如果是重复性会议，可以选择只取消这一次、取消未来所有场次，或整个系列都取消。</p>';

        $strings['UserGuideMyMeetingsTitle'] = '查看"我的会议"';
        $strings['UserGuideMyMeetingsBody'] = '<p>侧边栏"我的会议"会列出你即将到来与过去的所有预约：最上方是"下一场会议"卡片并附倒计时，接着是按日期分组的即将到来列表，最下方可以展开查看历史记录。如果是视频会议，符合条件时会直接显示"加入会议"按钮。</p>';

        $strings['UserGuideRoomTypesTitle'] = '实体会议室与视频会议室的差异';
        $strings['UserGuideRoomTypesBody'] = '<p>会议室分成"实体会议室"与"视频会议"两种。设定了在线会议链接的会议室会被归类为视频会议，预约后系统会在"我的会议"与日历事件卡片上显示"加入会议"按钮，点击即可直接进入在线会议；实体会议室则单纯只是空间预约，没有这个按钮。</p>';

        $strings['UserGuideNotificationsAccountTitle'] = '通知设定与我的账号';
        $strings['UserGuideNotificationsAccountBody'] = '<p>"通知设定"可以调整你要通过 Email 或 LINE 收到哪些种类的通知（例如预约成功、即将开始提醒、被取消等）。"我的账号"可以查看与编辑个人资料、修改密码。</p>';

        $strings['UserGuideFaqTitle'] = '常见问题及错误提示';
        $strings['UserGuideFaqBody'] = '<ul><li><strong>"此时段与其他预约冲突"</strong>——代表这段时间已经被订走了，请换一个时段。</li><li><strong>"开始时间在过去"或日期时间错误</strong>——请检查你选的日期与时间是否正确。</li><li><strong>"预约不能跨天"</strong>——单笔预约的开始与结束必须在同一天，跨天的会议请拆成两笔分开预约，或改用完整表单设定重复规则。</li><li><strong>"标题为必填"</strong>——请填写会议标题再提交。</li><li><strong>"超过此会议室允许的最长预约时间"</strong>——每间会议室都有各自的最长可预约时长，请缩短时间，或联系管理员调整设定。</li><li><strong>"已达配额上限"</strong>——代表你或你的部门在某个时间范围内的预约数量或时长已达上限，请等额度释放或联系管理员。</li><li><strong>"此时段不在开放时间内"</strong>——该会议室在这个时间并未开放预约（例如非上班时段），请选择开放时间内的时段。</li></ul>';

        // Admin section
        $strings['UserGuideAdminOverviewTitle'] = '管理中心总览';
        $strings['UserGuideAdminOverviewBody'] = '<p>只有具备管理权限的账号，侧边栏才会出现"管理中心"。系统分成两种管理层级：完整管理员可以看到全部 7 个管理项目；被指派为"群组管理员""会议室管理员"或"日程管理员"的用户，则会在"主要职责"看到对应范围的管理功能。普通用户完全不会看到这两个菜单。</p>';

        $strings['UserGuideAdminReservationsTitle'] = '预约管理';
        $strings['UserGuideAdminReservationsBody'] = '<p>可以查看、筛选、审核（如果会议室需要审核）或代为取消所有用户的预约，适合处理纠纷或协助用户调整预约。</p>';

        $strings['UserGuideAdminResourcesTitle'] = '会议室管理';
        $strings['UserGuideAdminResourcesBody'] = '<p>新增、编辑、停用会议室，设定容量、所在位置、是否需要审核、在线会议链接（决定它会不会被归类到前台的"视频会议"分组并显示加入会议按钮）等属性。</p><p>这里另外还有一个独立的"是否为视频会议室"分类标记，只用在管理后台自己的筛选与分组，跟前面提到、真正决定前台显示与加入会议按钮的在线会议链接设定是两回事。设定视频会议室时建议两个都检查，只设定其中一个是常见的疏漏。</p>';

        $strings['UserGuideAdminUsersTitle'] = '用户与权限管理';
        $strings['UserGuideAdminUsersBody'] = '<p>管理账号的启用或停用、指派角色权限（完整管理员，或群组／会议室／日程管理员等委任角色）、群组成员设定。</p>';

        $strings['UserGuideAdminSchedulesTitle'] = '日程管理';
        $strings['UserGuideAdminSchedulesBody'] = '<p>设定每个日程表的开放时段、公休日、特殊开放或关闭日，决定用户实际能在日历上选到哪些时段。</p>';

        $strings['UserGuideAdminAnalyticsTitle'] = '使用量分析';
        $strings['UserGuideAdminAnalyticsBody'] = '<p>查看会议室使用率、预约量趋势等统计信息，协助评估会议室数量是否足够或需要调整。</p>';

        $strings['UserGuideAdminSettingsTitle'] = '系统设置';
        $strings['UserGuideAdminSettingsBody'] = '<p>系统层级设定与诊断，包含 Email／SMTP、LINE 通知、AD 同步、系统健康检查等。</p>';

        $strings['UserGuideAdminTroubleshootingTitle'] = '管理端常见疑难排解';
        $strings['UserGuideAdminTroubleshootingBody'] = '<p>说明配额规则、停用时段（blackout）、额度（credits）等高级规则设定之后，用户端会看到的错误提示与对应的排查方向，方便快速判断是规则设定造成的还是真正的系统问题。</p>';

        // Simplified timezone picker region labels (lib/Common/SimplifiedTimezones.php)
        $strings['TzDateLineWest'] = '国际换日线西侧';
        $strings['TzSamoa'] = '萨摩亚、中途岛';
        $strings['TzHawaii'] = '夏威夷';
        $strings['TzAlaska'] = '阿拉斯加';
        $strings['TzPacific'] = '太平洋时间（美加）';
        $strings['TzMountain'] = '山地时间（美加）';
        $strings['TzCentral'] = '中部时间（美加）';
        $strings['TzEastern'] = '东部时间（美加）';
        $strings['TzAtlanticCanada'] = '大西洋时间（加拿大）';
        $strings['TzBrasilia'] = '巴西利亚';
        $strings['TzAzores'] = '亚速尔群岛';
        $strings['TzLondon'] = '格林尼治标准时间、伦敦';
        $strings['TzCentralEurope'] = '巴黎、柏林、阿姆斯特丹';
        $strings['TzEasternEurope'] = '雅典、开罗';
        $strings['TzMoscow'] = '莫斯科、巴格达';
        $strings['TzTehran'] = '德黑兰';
        $strings['TzDubai'] = '迪拜、巴库';
        $strings['TzKabul'] = '喀布尔';
        $strings['TzKarachi'] = '卡拉奇';
        $strings['TzIndia'] = '新德里、孟买';
        $strings['TzKathmandu'] = '加德满都';
        $strings['TzDhaka'] = '阿斯塔纳、达卡';
        $strings['TzYangon'] = '仰光';
        $strings['TzBangkok'] = '曼谷、雅加达';
        $strings['TzTaipei'] = '北京、新加坡、台北';
        $strings['TzSeoul'] = '首尔、东京';
        $strings['TzAdelaide'] = '达尔文、阿德莱德';
        $strings['TzSydney'] = '关岛、堪培拉、悉尼';
        $strings['TzNewCaledonia'] = '新喀里多尼亚';
        $strings['TzAuckland'] = '奥克兰、斐济';
        $strings['TzTonga'] = '汤加';
        $strings['TzKiritimati'] = '基里巴斯';

        $strings['WeComUserId'] = '企业微信 ID';
        $strings['WeComUserIdHint'] = '用户的企业微信账号 ID。填入后，预约确认与提醒也会通过企业微信推送。';
        $strings['WeComReservationCreatedTitle'] = '预约成功';
        $strings['WeComFieldMeetingName'] = '会议名称';
        $strings['WeComFieldDateTime'] = '日期与时间';
        $strings['WeComFieldMeetingRoom'] = '会议室';
        $strings['WeComFieldVideoResource'] = '视频会议资源';
        $strings['WeComJoinVideoConference'] = '加入视频会议';
        $strings['WeComFieldOwner'] = '预约人';
        $strings['WeComViewReservationDetails'] = '查看预约详情';
        $strings['WeComHostLoginInfo'] = '主持人登录信息（仅预约人可查看）';
        $strings['WeComRecurringSuffix'] = '（%s，共 %s 场，至 %s）';
        $strings['WeComReminderStartMessage'] = "提醒：“%s”即将开始\n地点：%s";
        $strings['WeComReminderEndMessage'] = "提醒：“%s”即将结束\n地点：%s";

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
        return 'zh_cn';
    }
}
