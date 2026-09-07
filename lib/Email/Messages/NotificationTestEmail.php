<?php

require_once(ROOT_DIR . 'lib/Email/namespace.php');
require_once(ROOT_DIR . 'lib/Email/EmailMessage.php');

/**
 * One-off "did an email actually reach me" check, fired by the 測試通知 button on 通知設定.
 * Deliberately not template-backed -- a plain body is enough; it only needs to arrive.
 * Bypasses the per-event Email preference for the same reason the LINE / WeCom test pushes
 * do: it answers "does delivery work", not "will I be notified".
 */
class NotificationTestEmail extends EmailMessage
{
    private EmailAddress $to;

    public function __construct(User $user)
    {
        parent::__construct($user->Language());
        $this->to = new EmailAddress($user->EmailAddress(), $user->FullName());
    }

    public function To()
    {
        return [$this->to];
    }

    public function Subject()
    {
        return $this->Translate('EmailTestNotificationSubject');
    }

    public function Body()
    {
        return $this->Translate('EmailTestNotificationMessage');
    }
}
