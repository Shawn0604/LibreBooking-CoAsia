<?php

require_once(ROOT_DIR . 'Domain/Values/UserPreferences.php');
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

        if (empty($lineUserId)) {
            return;
        }

        $currentInstance = $reservationSeries->CurrentInstance();
        $timezone = $owner->Timezone();
        if (empty($timezone)) {
            $timezone = Configuration::Instance()->GetDefaultTimezone();
        }
        $start = $currentInstance->StartDate()->ToTimezone($timezone);
        $end = $currentInstance->EndDate()->ToTimezone($timezone);

        $text = Resources::GetInstance()->GetString('LineReservationCreatedMessage', [
            $reservationSeries->Title(),
            $reservationSeries->Resource()->GetName(),
            $start->Format('Y-m-d H:i'),
            $end->Format('H:i'),
        ]);

        ServiceLocator::GetLineService()->Push($lineUserId, $text);
    }
}
