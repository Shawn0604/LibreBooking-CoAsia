<?php

require_once(ROOT_DIR . 'Domain/Values/UserPreferences.php');
require_once(ROOT_DIR . 'Domain/RepeatOptions.php');
require_once(ROOT_DIR . 'Domain/Values/VideoConferenceInfo.php');
require_once(ROOT_DIR . 'lib/Line/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Attributes/Attribute.php');
require_once(ROOT_DIR . 'lib/Email/Messages/ReservationEmailTemplateContext.php');
require_once(ROOT_DIR . 'Pages/Pages.php');

class OwnerLineCreatedNotification implements IReservationNotification
{
    /**
     * @var IUserRepository
     */
    private $userRepository;

    /**
     * @var IAttributeRepository
     */
    private $attributeRepository;

    /**
     * CoAsia primary -- rgb(31, 49, 89). Kept in sync by hand with
     * tpl/Email/reservation-notification-body.tpl's $CoasiaPrimary so the LINE Flex bubble and
     * the email use the exact same brand color, per the notification-redesign spec ("CoAsia
     * 主色使用 rgb(31, 49, 89)").
     */
    private const COASIA_PRIMARY = '#1f3159';

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
        $title = !empty($reservationSeries->Title()) ? $reservationSeries->Title() : Resources::GetInstance()->GetString('NoReservationTitle');

        // This class only ever notifies the reservation's OWNER (that's the whole point of
        // "OwnerLineCreatedNotification"), so requesting host credentials here is safe in the
        // same way ReservationEmailMessage::IsRecipientOwner()==true is safe for owner emails --
        // there is no other recipient this Flex Message could reach.
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

            $dateTimeText .= sprintf(
                '（%s，共 %s 場，至 %s）',
                $repeatLabel,
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

        $bubble = $this->BuildFlexBubble($title, $dateTimeText, $reservationSeries, $primaryVideo, $owner, $reservationUrl);
        $altText = Resources::GetInstance()->GetString('LineFlexAltText');

        $lineService = ServiceLocator::GetLineService();
        $lineService->PushFlex($lineUserId, $altText, $bubble);

        // Flex Message text isn't selectable in LINE (no long-press-to-copy the way a plain
        // text message supports), so the host credentials shown in the bubble above are
        // unusable for actually pasting into Zoom/Meet's host-login screen. Sending them again
        // as a second, plain-text message right after gives the owner something they can
        // actually long-press and copy, while the Flex bubble still carries the full
        // nicely-formatted layout the mockup asked for.
        if ($primaryVideo !== null && (!empty($primaryVideo->HostAccount) || !empty($primaryVideo->HostPassword))) {
            $hostInfoText = $this->BuildHostInfoText($primaryVideo);
            $lineService->Push($lineUserId, $hostInfoText);
        }
    }

    private function BuildHostInfoText($primaryVideo)
    {
        $resources = Resources::GetInstance();
        $lines = [$resources->GetString('LineFlexHostLoginInfo')];

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

        return implode("\n", $lines);
    }

    /**
     * Builds the Flex Message bubble body in the exact 1-8 order from the notification-redesign
     * mockup: status, title, date/time, video-resource-or-room, owner, join button, host-info
     * box, view-details link. Kept as one method (rather than split further) because the
     * ordering itself -- not any individual row -- is the thing the mockup specified precisely.
     *
     * @param string $title
     * @param string $dateTimeText
     * @param ReservationSeries $reservationSeries
     * @param VideoConferenceInfo|null $primaryVideo
     * @param User $owner
     * @param string $reservationUrl
     * @return array
     */
    private function BuildFlexBubble($title, $dateTimeText, $reservationSeries, $primaryVideo, $owner, $reservationUrl)
    {
        $resources = Resources::GetInstance();
        $bodyContents = [];

        // 1. 預約狀態
        $bodyContents[] = [
            'type' => 'box',
            'layout' => 'baseline',
            'contents' => [[
                'type' => 'text',
                'text' => $resources->GetString('LineFlexStatusCreated'),
                'size' => 'xs',
                'weight' => 'bold',
                'color' => '#1e7e34',
            ]],
        ];

        // 2. 會議名稱
        $bodyContents[] = [
            'type' => 'text',
            'text' => $title,
            'weight' => 'bold',
            'size' => 'lg',
            'wrap' => true,
            'margin' => 'sm',
        ];

        $infoRows = [];
        // 3. 日期與時間
        $infoRows[] = $this->BuildInfoRow($resources->GetString('LineFlexFieldDateTime'), $dateTimeText);

        // 4. 視訊會議資源（若為實體會議室則顯示會議室名稱）
        if ($primaryVideo !== null) {
            $infoRows[] = $this->BuildInfoRow($resources->GetString('LineFlexFieldVideoResource'), $primaryVideo->ResourceName);
        } else {
            $infoRows[] = $this->BuildInfoRow($resources->GetString('LineFlexFieldMeetingRoom'), $reservationSeries->Resource()->GetName());
        }

        // 5. 預約人
        $infoRows[] = $this->BuildInfoRow($resources->GetString('LineFlexFieldOwner'), $owner->FullName());

        $bodyContents[] = [
            'type' => 'box',
            'layout' => 'vertical',
            'margin' => 'lg',
            'spacing' => 'sm',
            'contents' => $infoRows,
        ];

        if ($primaryVideo !== null) {
            // 6. 加入視訊會議（全寬按鈕）
            $bodyContents[] = [
                'type' => 'button',
                'style' => 'primary',
                'color' => self::COASIA_PRIMARY,
                'margin' => 'lg',
                'height' => 'sm',
                'action' => [
                    'type' => 'uri',
                    'label' => $resources->GetString('LineFlexJoinVideoConference'),
                    'uri' => $primaryVideo->Url,
                ],
            ];

            // 7. 主持人登入資訊 -- shown directly in the bubble (not a button to a separate
            // page): this Flex Message only ever reaches the reservation owner in the first
            // place (OwnerLineCreatedNotification), so there is no additional recipient to
            // gate against the way the email's $IsRecipientOwner check does.
            if (!empty($primaryVideo->HostAccount) || !empty($primaryVideo->HostPassword)) {
                $hostInfoRows = [];
                if (!empty($primaryVideo->MeetingId)) {
                    $hostInfoRows[] = $this->BuildInfoRow($resources->GetString('MeetingId'), $primaryVideo->MeetingId);
                }
                if (!empty($primaryVideo->Passcode)) {
                    $hostInfoRows[] = $this->BuildInfoRow($resources->GetString('Passcode'), $primaryVideo->Passcode);
                }
                if (!empty($primaryVideo->HostAccount)) {
                    $hostInfoRows[] = $this->BuildInfoRow($resources->GetString('VideoConferenceHostAccount'), $primaryVideo->HostAccount);
                }
                if (!empty($primaryVideo->HostPassword)) {
                    $hostInfoRows[] = $this->BuildInfoRow($resources->GetString('VideoConferenceHostPassword'), $primaryVideo->HostPassword);
                }

                $bodyContents[] = [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'margin' => 'md',
                    'paddingAll' => 'md',
                    'backgroundColor' => '#f6f7fb',
                    'cornerRadius' => 'md',
                    'spacing' => 'sm',
                    'contents' => array_merge(
                        [[
                            'type' => 'text',
                            'text' => '🔒 ' . $resources->GetString('LineFlexHostLoginInfo'),
                            'weight' => 'bold',
                            'size' => 'sm',
                            'color' => '#1c2333',
                        ]],
                        $hostInfoRows,
                        [[
                            'type' => 'text',
                            'text' => $resources->GetString('LineFlexOwnerOnlyNote'),
                            'size' => 'xxs',
                            'color' => '#9aa1b3',
                            'margin' => 'sm',
                        ]]
                    ),
                ];
            }
        }

        // 8. 查看預約詳情
        $bodyContents[] = [
            'type' => 'box',
            'layout' => 'vertical',
            'margin' => 'lg',
            'action' => [
                'type' => 'uri',
                'label' => $resources->GetString('LineFlexViewReservationDetails'),
                'uri' => $reservationUrl,
            ],
            'contents' => [[
                'type' => 'text',
                'text' => $resources->GetString('LineFlexViewReservationDetails'),
                'size' => 'sm',
                'weight' => 'bold',
                'color' => self::COASIA_PRIMARY,
                'align' => 'center',
            ]],
        ];

        return [
            'type' => 'bubble',
            'header' => [
                'type' => 'box',
                'layout' => 'vertical',
                'backgroundColor' => self::COASIA_PRIMARY,
                'paddingAll' => 'lg',
                'contents' => [[
                    'type' => 'text',
                    'text' => 'CoAsia Booking',
                    'color' => '#ffffff',
                    'weight' => 'bold',
                    'size' => 'md',
                ]],
            ],
            'body' => [
                'type' => 'box',
                'layout' => 'vertical',
                'contents' => $bodyContents,
            ],
        ];
    }

    private function BuildInfoRow($label, $value)
    {
        return [
            'type' => 'box',
            'layout' => 'baseline',
            'contents' => [
                ['type' => 'text', 'text' => $label, 'size' => 'sm', 'color' => '#6c7488', 'flex' => 2],
                ['type' => 'text', 'text' => (string) $value, 'size' => 'sm', 'color' => '#1c2333', 'flex' => 5, 'wrap' => true],
            ],
        ];
    }
}
