<?php

class UserPreferences
{
    public const RESERVATION_COLOR = 'ReservationColor';
    public const REPORT_COLUMNS = 'ReportColumns';
    public const CALENDAR_FILTER = 'CalendarFilter';
    public const LINE_USER_ID = 'LineUserId';
    public const LINE_LINK_CODE = 'LineLinkCode';
    // WeCom (企業微信) recipient id. Unlike LINE (bound via a QR + webhook handshake), this
    // is entered by an administrator on the user-management page — see the WeCom API deck,
    // slide 6 ("增加企業微信ID欄位: 帳號角色管理").
    public const WECOM_USER_ID = 'WecomUserId';
    // The user's explicitly-chosen "設為預設" room on the Overview FullCalendar resource
    // picker — highest-priority tier in its default-resource resolution, above both the
    // last-selected room (localStorage, see overview-fc-selected-resource in
    // overview_fullcalendar.js) and the classic app's own default-schedule mechanism (see
    // OverviewFullCalendarPresenter::PageLoad()'s $systemDefaultResourceId).
    public const OVERVIEW_DEFAULT_RESOURCE_ID = 'OverviewDefaultResourceId';
    // Unset/missing means "enabled" — a bound user who has never touched this toggle should
    // keep receiving pushes by default, same as how binding itself already implies wanting
    // notifications. Only an explicit '0' (see Get() callers) turns them off.
    public const LINE_NOTIFICATIONS_ENABLED = 'LineNotificationsEnabled';
    // Same "unset means enabled" semantics as LINE_NOTIFICATIONS_ENABLED — only an explicit
    // '0' turns WeCom pushes off for a user who otherwise has a WecomUserId set.
    public const WECOM_NOTIFICATIONS_ENABLED = 'WecomNotificationsEnabled';
    // "開始前提醒" -- minutes before a reservation starts to fire a reminder, set by the user
    // on 通知設定 and applied to every reservation they create (ReservationSavePresenter).
    // '0'/unset = off. Stored as a plain minute count.
    public const RESERVATION_START_REMINDER_MINUTES = 'ReservationStartReminderMinutes';

    private $preferences = [];
    private $changed = [];
    private $added = [];

    /**
     * @param string $allPreferences
     * @return UserPreferences
     */
    public static function Parse($allPreferences)
    {
        $preferences = new UserPreferences();

        if (empty($allPreferences)) {
            return $preferences;
        }

        $pairs = explode('!sep!', $allPreferences);

        if (empty($pairs)) {
            return $preferences;
        }

        foreach ($pairs as $pair) {
            $nv = explode('=', $pair);
            if (count($nv) != 2) {
                continue;
            }
            $preferences->Add($nv[0], $nv[1]);
        }

        return $preferences;
    }

    /**
     * @param $name string
     * @param $value string
     */
    public function Add($name, $value)
    {
        $this->preferences[$name] = $value;
    }

    /**
     * @param $name string
     * @return null|string
     */
    public function Get($name)
    {
        if ($name == UserPreferences::RESERVATION_COLOR &&
            Configuration::Instance()->GetKey(ConfigKeys::SCHEDULE_USE_PER_USER_COLORS, new BooleanConverter()) == false) {
            return null;
        }

        if (array_key_exists($name, $this->preferences)) {
            return $this->preferences[$name];
        }

        return null;
    }

    /**
     * @param $name string
     * @param $value string
     */
    public function Update($name, $value)
    {
        $currentValue = null;

        if (array_key_exists($name, $this->preferences)) {
            $currentValue = $this->preferences[$name];
            if ($value != $currentValue) {
                Log::Debug('changed ' . $name);

                $this->changed[] = $name;
            }
        } else {
            Log::Debug('added ' . $name);
            $this->added[] = $name;
        }

        $this->preferences[$name] = $value;
    }

    /**
     * @return array|string[]
     */
    public function All()
    {
        return $this->preferences;
    }

    /**
     * @return array|string[]
     */
    public function ChangedPreferences()
    {
        return $this->changed;
    }

    /**
     * @return array|string[]
     */
    public function AddedPreferences()
    {
        return $this->added;
    }
}
