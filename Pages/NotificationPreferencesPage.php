<?php

require_once(ROOT_DIR . 'Pages/SecurePage.php');
require_once(ROOT_DIR . 'Presenters/NotificationPreferencesPresenter.php');

interface INotificationPreferencesPage extends IPage
{
    /**
     * @param bool $enabled
     */
    public function SetEmailEnabled($enabled);

    /**
     * @param bool $enabled
     */
    public function SetEmailNotificationsEnabled($enabled);

    /**
     * @param bool $enabled
     */
    public function SetLineEnabled($enabled);

    /**
     * @param bool $isLinked
     */
    public function SetLineLinked($isLinked);

    /**
     * @param bool $enabled
     */
    public function SetLineNotificationsEnabled($enabled);

    /**
     * @param string $code
     */
    public function SetLineLinkCode($code);

    /**
     * @param string $url
     */
    public function SetLineFriendUrl($url);

    /**
     * @param string $dataUri
     */
    public function SetLineQrDataUri($dataUri);

    /**
     * @param bool $enabled site-wide reservation.reminders.enabled
     */
    public function SetRemindersEnabled($enabled);

    /**
     * @param int $minutes 0 = off
     */
    public function SetReservationStartReminderMinutes($minutes);
}

class NotificationPreferencesPage extends SecurePage implements INotificationPreferencesPage
{
    /**
     * @var NotificationPreferencesPresenter
     */
    private $presenter;

    public function __construct()
    {
        parent::__construct('NotificationPreferences');
        $this->presenter = new NotificationPreferencesPresenter($this, new UserRepository());
    }

    public function PageLoad()
    {
        $this->presenter->PageLoad();
        $this->Display('MyAccount/notification-preferences.tpl');
    }

    public function SetEmailEnabled($enabled)
    {
        $this->Set('EmailEnabled', $enabled);
    }

    public function SetEmailNotificationsEnabled($enabled)
    {
        $this->Set('EmailNotificationsEnabled', $enabled);
    }

    public function SetLineEnabled($enabled)
    {
        $this->Set('LineEnabled', $enabled);
    }

    public function SetLineLinked($isLinked)
    {
        $this->Set('LineLinked', $isLinked);
    }

    public function SetLineNotificationsEnabled($enabled)
    {
        $this->Set('LineNotificationsEnabled', $enabled);
    }

    public function SetLineLinkCode($code)
    {
        $this->Set('LineLinkCode', $code);
    }

    public function SetLineFriendUrl($url)
    {
        $this->Set('LineFriendUrl', $url);
    }

    public function SetLineQrDataUri($dataUri)
    {
        $this->Set('LineQrDataUri', $dataUri);
    }

    public function SetRemindersEnabled($enabled)
    {
        $this->Set('RemindersEnabled', $enabled);
    }

    public function SetReservationStartReminderMinutes($minutes)
    {
        // Raw minute count; the template matches it against the fixed <option> values
        // (5/10/15/30/60/120/1440). 0 = off, and the template pre-selects 15 in that case so
        // enabling the toggle starts from a sensible value.
        $this->Set('ReservationStartReminderMinutes', (int)$minutes);
    }
}
