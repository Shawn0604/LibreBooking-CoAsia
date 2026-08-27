<?php

require_once(ROOT_DIR . 'Domain/Values/UserPreferences.php');
require_once(ROOT_DIR . 'Domain/RepeatOptions.php');
require_once(ROOT_DIR . 'Domain/Values/VideoConferenceInfo.php');
require_once(ROOT_DIR . 'lib/WeCom/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Attributes/Attribute.php');
require_once(ROOT_DIR . 'lib/Email/Messages/ReservationEmailTemplateContext.php');
require_once(ROOT_DIR . 'Pages/Pages.php');

/**
 * WeCom (企業微信) equivalent of OwnerLineCreatedNotification. The CoAsia WeCom gateway only
 * accepts a plain-text body, so this builds one readable multi-line message rather than a
 * Flex bubble, from the same fields the LINE notification and confirmation email use.
 *
 * Independent of the LINE channel: a user with both a LineUserId and a WecomUserId set
 * receives the confirmation on both, per the WeCom API deck slide 7 ("當帳號有微信/Line ID時
 * (都發)").
 */
class OwnerWeComCreatedNotification implements IReservationNotification
{
    /**
     * @var IUserRepository
     */
    private $userRepository;

    /**
     * @var IAttributeRepository
     */
    private $attributeRepository;

    public function __construct(IUserRepository $userRepository, IAttributeRepository $attributeRepository)
    {
        $this->userRepository = $userRepository;
        $this->attributeRepository = $attributeRepository;
    }

    /**
     * @param ReservationSeries $reservationSeries
     */
    public function Notify($reservationSeries)
    {
        $owner = $this->userRepository->LoadById($reservationSeries->UserId());
        $wecomUserId = $owner->GetPreferences()->Get(UserPreferences::WECOM_USER_ID);

        if (empty($wecomUserId) || !$owner->WantsWeComNotifications()) {
            return;
        }

        $resources = Resources::GetInstance();
        $currentInstance = $reservationSeries->CurrentInstance();
        $timezone = $owner->Timezone();
        if (empty($timezone)) {
            $timezone = Configuration::Instance()->GetDefaultTimezone();
        }
        $start = $currentInstance->StartDate()->ToTimezone($timezone);
        $end = $currentInstance->EndDate()->ToTimezone($timezone);
        $title = !empty($reservationSeries->Title()) ? $reservationSeries->Title() : $resources->GetString('NoReservationTitle');

        // Owner-only recipient, same as OwnerLineCreatedNotification -- safe to request host
        // credentials here because this message can reach no one but the reservation owner.
        $context = new ReservationEmailTemplateContext(
            $reservationSeries,
            $owner,
            $reservationSeries->Resource(),
            $this->attributeRepository
        );
        $primaryVideo = null;
        foreach ($context->VideoConferenceResources(includeHostCredentials: true) as $videoResource) {
            if ($videoResource->ResourceId == $reservationSeries->Resource()->GetId()) {
                $primaryVideo = $videoResource;
                break;
            }
        }

        $dateTimeText = $start->Format('Y-m-d H:i') . ' - ' . $end->Format('H:i');

        if ($reservationSeries->IsRecurring()) {
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

            $dateTimeText .= sprintf(
                $resources->GetString('WeComRecurringSuffix'),
                $resources->GetString($repeatLabelKey),
                $occurrenceCount,
                $lastStart->Format('Y-m-d')
            );
        }

        $reservationUrl = Configuration::Instance()->GetScriptUrl() . '/' . sprintf(
            '%s?%s=%s',
            Pages::RESERVATION,
            QueryStringKeys::REFERENCE_NUMBER,
            $currentInstance->ReferenceNumber()
        );

        $lines = [];
        $lines[] = '【CoAsia Booking】' . $resources->GetString('WeComReservationCreatedTitle');
        $lines[] = $resources->GetString('WeComFieldMeetingName') . '：' . $title;
        $lines[] = $resources->GetString('WeComFieldDateTime') . '：' . $dateTimeText;

        if ($primaryVideo !== null) {
            $lines[] = $resources->GetString('WeComFieldVideoResource') . '：' . $primaryVideo->ResourceName;
            if (!empty($primaryVideo->Url)) {
                $lines[] = $resources->GetString('WeComJoinVideoConference') . '：' . $primaryVideo->Url;
            }
        } else {
            $lines[] = $resources->GetString('WeComFieldMeetingRoom') . '：' . $reservationSeries->Resource()->GetName();
        }

        $lines[] = $resources->GetString('WeComFieldOwner') . '：' . $owner->FullName();
        $lines[] = $resources->GetString('WeComViewReservationDetails') . '：' . $reservationUrl;

        if ($primaryVideo !== null && (!empty($primaryVideo->HostAccount) || !empty($primaryVideo->HostPassword))) {
            $lines[] = '';
            $lines[] = '🔒 ' . $resources->GetString('WeComHostLoginInfo');
            if (!empty($primaryVideo->MeetingId)) {
                $lines[] = $resources->GetString('MeetingId') . '：' . $primaryVideo->MeetingId;
            }
            if (!empty($primaryVideo->Passcode)) {
                $lines[] = $resources->GetString('Passcode') . '：' . $primaryVideo->Passcode;
            }
            if (!empty($primaryVideo->HostAccount)) {
                $lines[] = $resources->GetString('VideoConferenceHostAccount') . '：' . $primaryVideo->HostAccount;
            }
            if (!empty($primaryVideo->HostPassword)) {
                $lines[] = $resources->GetString('VideoConferenceHostPassword') . '：' . $primaryVideo->HostPassword;
            }
        }

        ServiceLocator::GetWeComService()->Push($wecomUserId, implode("\n", $lines));
    }
}
