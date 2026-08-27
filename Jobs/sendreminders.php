<?php

/**
*  Cron Example:
*  This script must be executed every minute for to enable Reservation Reminders functionality
*  * * * * * /usr/bin/env php -f ${WWW_DIR}/librebooking/Jobs/sendreminders.php
*/

define('ROOT_DIR', __DIR__ . '/../');
require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'Domain/Reminder.php');
require_once(ROOT_DIR . 'lib/Email/Messages/ReminderEmail.php');
require_once(ROOT_DIR . 'lib/Line/namespace.php');
require_once(ROOT_DIR . 'lib/WeCom/namespace.php');
require_once(ROOT_DIR . 'Jobs/JobCop.php');

Log::Debug('Running sendreminders.php');

JobCop::EnsureCommandLine();

try {
    $emailEnabled = Configuration::Instance()->GetKey(ConfigKeys::EMAIL_ENABLED, new BooleanConverter());
    $lineEnabled = Configuration::Instance()->GetKey(ConfigKeys::LINE_ENABLED, new BooleanConverter());
    $wecomEnabled = Configuration::Instance()->GetKey(ConfigKeys::WECOM_ENABLED, new BooleanConverter());
    if (!$emailEnabled && !$lineEnabled && !$wecomEnabled) {
        return;
    }

    $repository = new ReminderRepository();
    $userRepository = new UserRepository();
    $now = Date::Now();

    $startNotices = $repository->GetReminderNotices($now, ReservationReminderType::Start);
    Log::Debug('Found %s start reminders', count($startNotices));
    foreach ($startNotices as $notice) {
        if ($emailEnabled) {
            ServiceLocator::GetEmailService()->Send(new ReminderStartEmail($notice));
        }
        if ($lineEnabled || $wecomEnabled) {
            $noticeOwner = $userRepository->LoadById($notice->UserId());
            if ($lineEnabled) {
                $lineUserId = $noticeOwner->GetPreferences()->Get(UserPreferences::LINE_USER_ID);
                if (!empty($lineUserId) && $noticeOwner->WantsLineNotifications()) {
                    $text = Resources::GetInstance()->GetString('LineReminderStartMessage', [$notice->Title(), $notice->ResourceNames()]);
                    ServiceLocator::GetLineService()->Push($lineUserId, $text);
                }
            }
            if ($wecomEnabled) {
                $wecomUserId = $noticeOwner->GetPreferences()->Get(UserPreferences::WECOM_USER_ID);
                if (!empty($wecomUserId) && $noticeOwner->WantsWeComNotifications()) {
                    $text = Resources::GetInstance()->GetString('WeComReminderStartMessage', [$notice->Title(), $notice->ResourceNames()]);
                    ServiceLocator::GetWeComService()->Push($wecomUserId, $text);
                }
            }
        }
    }

    $endNotices = $repository->GetReminderNotices(Date::Now(), ReservationReminderType::End);
    Log::Debug('Found %s end reminders', count($endNotices));
    foreach ($endNotices as $notice) {
        if ($emailEnabled) {
            ServiceLocator::GetEmailService()->Send(new ReminderEndEmail($notice));
        }
        if ($lineEnabled || $wecomEnabled) {
            $noticeOwner = $userRepository->LoadById($notice->UserId());
            if ($lineEnabled) {
                $lineUserId = $noticeOwner->GetPreferences()->Get(UserPreferences::LINE_USER_ID);
                if (!empty($lineUserId) && $noticeOwner->WantsLineNotifications()) {
                    $text = Resources::GetInstance()->GetString('LineReminderEndMessage', [$notice->Title(), $notice->ResourceNames()]);
                    ServiceLocator::GetLineService()->Push($lineUserId, $text);
                }
            }
            if ($wecomEnabled) {
                $wecomUserId = $noticeOwner->GetPreferences()->Get(UserPreferences::WECOM_USER_ID);
                if (!empty($wecomUserId) && $noticeOwner->WantsWeComNotifications()) {
                    $text = Resources::GetInstance()->GetString('WeComReminderEndMessage', [$notice->Title(), $notice->ResourceNames()]);
                    ServiceLocator::GetWeComService()->Push($wecomUserId, $text);
                }
            }
        }
    }
} catch (Exception $ex) {
    Log::Error('Error running sendreminders.php: %s', $ex);
}

Log::Debug('Finished running sendreminders.php');
