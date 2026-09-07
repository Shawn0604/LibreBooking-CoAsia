<?php

/**
*  Cron Example:
*  This script must be executed every minute for to enable Reservation Reminders functionality
*  * * * * * /usr/bin/env php -f ${WWW_DIR}/librebooking/Jobs/sendreminders.php
*
*  "會議開始前提醒": for every reminder that comes due this minute, ReservationReminderSender
*  fans out to the reservation owner + internal attendees (each over their own enabled
*  Email / LINE / 企業微信) and to external guests (Email only). See that class for the full policy.
*/

define('ROOT_DIR', __DIR__ . '/../');
require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'Domain/Access/ReminderRepository.php');
require_once(ROOT_DIR . 'lib/Application/Reservation/Notification/Reminder/ReservationReminderDispatcher.php');
require_once(ROOT_DIR . 'Jobs/JobCop.php');

Log::Debug('Running sendreminders.php');

JobCop::EnsureCommandLine();

try {
    $emailEnabled = Configuration::Instance()->GetKey(ConfigKeys::EMAIL_ENABLED, new BooleanConverter());
    $lineEnabled = Configuration::Instance()->GetKey(ConfigKeys::LINE_ENABLED, new BooleanConverter());
    $wecomEnabled = Configuration::Instance()->GetKey(ConfigKeys::WECOM_ENABLED, new BooleanConverter());

    if (!$emailEnabled && !$lineEnabled && !$wecomEnabled) {
        Log::Debug('sendreminders.php: none of email / LINE / WeCom is enabled, nothing to do');
        return;
    }

    $sender = new ReservationReminderSender(
        new UserRepository(),
        new AttributeRepository(),
        $emailEnabled,
        $lineEnabled,
        $wecomEnabled
    );

    $dispatcher = new ReservationReminderDispatcher(
        new ReminderRepository(),
        new ReservationRepository(),
        $sender
    );

    $dispatcher->DispatchDue(Date::Now());
} catch (Throwable $ex) {
    Log::Error('Error running sendreminders.php: %s', $ex);
}

Log::Debug('Finished running sendreminders.php');
