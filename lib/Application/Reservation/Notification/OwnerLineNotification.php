<?php

require_once(ROOT_DIR . 'Domain/Values/UserPreferences.php');
require_once(ROOT_DIR . 'Domain/RepeatOptions.php');
require_once(ROOT_DIR . 'lib/Line/namespace.php');

class OwnerLineCreatedNotification implements IReservationNotification
{
    /**
     * @var IUserRepository
     */
    private $userRepository;

    public function __construct(IUserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * @param ReservationSeries $reservationSeries
     */
    public function Notify($reservationSeries)
    {
        $owner = $this->userRepository->LoadById($reservationSeries->UserId());
        $lineUserId = $owner->GetPreferences()->Get(UserPreferences::LINE_USER_ID);

        if (empty($lineUserId) || !$owner->WantsLineNotifications()) {
            return;
        }

        $currentInstance = $reservationSeries->CurrentInstance();
        $timezone = $owner->Timezone();
        if (empty($timezone)) {
            $timezone = Configuration::Instance()->GetDefaultTimezone();
        }
        $start = $currentInstance->StartDate()->ToTimezone($timezone);
        $end = $currentInstance->EndDate()->ToTimezone($timezone);

        // Recurring series creation still fires this Notify() call exactly ONCE (the caller,
        // AddReservationNotificationService, notifies per-series not per-instance) — the
        // per-occurrence pushes are entirely a separate concern, sent later by
        // Jobs/sendreminders.php as each instance's own reminder comes due. What changes here
        // is only the WORDING: a recurring series describes the whole pattern (cadence +
        // occurrence count + last date) instead of silently describing just its first
        // instance and leaving every later occurrence unmentioned.
        if ($reservationSeries->IsRecurring()) {
            // SortedInstances() (not Instances()) — the last element only reliably means
            // "the last occurrence chronologically" if the array is actually date-ordered.
            $instances = $reservationSeries->SortedInstances();
            $occurrenceCount = count($instances);
            $lastInstance = end($instances);
            $lastStart = $lastInstance->StartDate()->ToTimezone($timezone);

            $repeatLabelKey = match ($reservationSeries->RepeatOptions()->RepeatType()) {
                RepeatType::Daily => 'Daily',
                RepeatType::Weekly => 'Weekly',
                RepeatType::Monthly => 'Monthly',
                RepeatType::Yearly => 'Yearly',
                default => 'LineRepeatCustom',
            };
            $repeatLabel = Resources::GetInstance()->GetString($repeatLabelKey);

            $text = Resources::GetInstance()->GetString('LineReservationSeriesCreatedMessage', [
                $reservationSeries->Title(),
                $reservationSeries->Resource()->GetName(),
                $start->Format('Y-m-d H:i'),
                $end->Format('H:i'),
                $repeatLabel,
                $occurrenceCount,
                $lastStart->Format('Y-m-d'),
            ]);
        } else {
            $text = Resources::GetInstance()->GetString('LineReservationCreatedMessage', [
                $reservationSeries->Title(),
                $reservationSeries->Resource()->GetName(),
                $start->Format('Y-m-d H:i'),
                $end->Format('H:i'),
            ]);
        }

        ServiceLocator::GetLineService()->Push($lineUserId, $text);
    }
}
