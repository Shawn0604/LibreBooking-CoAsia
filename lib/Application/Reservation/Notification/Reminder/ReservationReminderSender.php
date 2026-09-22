<?php

require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'Domain/Values/ReservationReminder.php');
require_once(ROOT_DIR . 'Domain/Values/ReservationStatus.php');
require_once(ROOT_DIR . 'Domain/Values/VideoConferenceInfo.php');
require_once(ROOT_DIR . 'lib/Application/Reservation/ReservationEvents.php');
require_once(ROOT_DIR . 'lib/Email/Messages/ReservationReminderEmail.php');
require_once(ROOT_DIR . 'lib/Email/Messages/ReservationEmailTemplateContext.php');
require_once(ROOT_DIR . 'lib/Line/namespace.php');
require_once(ROOT_DIR . 'lib/WeCom/namespace.php');
require_once(ROOT_DIR . 'Pages/Pages.php');

/**
 * Fans a single due reservation reminder out to everyone who should get it, over the channels
 * each recipient has actually enabled. The user never chooses recipients or channels — this
 * class is the whole "誰會收到" policy:
 *
 *   - reservation owner        -> Email  (if their Email notifications are on)
 *                                 + LINE (if bound AND their LINE notifications are on)
 *                                 + 企業微信 (if a WeCom id is set AND their WeCom
 *                                   notifications are on).
 *                                 The shared video-conference host account / password is
 *                                 included for THIS recipient only.
 *   - internal attendees       -> participants + invitees. Email always goes out (see
 *                                 UserWantsEmail()) -- they were put on this specific meeting,
 *                                 so a generic "email notifications" toggle they may have never
 *                                 touched shouldn't hide the fact it's starting. LINE / 企業微信
 *                                 still require their own per-channel opt-in, since those need
 *                                 the attendee to have actively bound an id in the first place.
 *                                 Never the host credentials.
 *   - external guests          -> not notified. The mail server can only deliver to internal
 *                                 addresses, so there is no working channel for them.
 *   - resource / group admins  -> not notified (they are simply never in the recipient set).
 *
 * Each channel is independent and governed by the same rule: no binding / id or an explicit
 * opt-out on that channel means nothing is sent there, so the channels can never disagree
 * about whether a given person is reminded. Someone with more than one channel set up is
 * reminded on each of them.
 *
 * All three channels carry the same fields (reminder type, title, date/time, room or
 * online-meeting name, video link, reservation-detail link) — the Email via the shared
 * reservation-notification-body.tpl, the LINE via the Flex bubble built below, the WeCom
 * gateway (plain-text only) as one readable multi-line message.
 */
class ReservationReminderSender
{
    /** CoAsia primary — kept in sync with reservation-notification-body.tpl / OwnerLineCreatedNotification. */
    private const COASIA_PRIMARY = '#1f3159';

    private IUserRepository $userRepository;
    private IAttributeRepository $attributeRepository;
    private bool $emailEnabled;
    private bool $lineEnabled;
    private bool $wecomEnabled;

    public function __construct(
        IUserRepository $userRepository,
        IAttributeRepository $attributeRepository,
        bool $emailEnabled,
        bool $lineEnabled,
        bool $wecomEnabled = false
    ) {
        $this->userRepository = $userRepository;
        $this->attributeRepository = $attributeRepository;
        $this->emailEnabled = $emailEnabled;
        $this->lineEnabled = $lineEnabled;
        $this->wecomEnabled = $wecomEnabled;
    }

    /**
     * @param ReservationSeries|null $series a fully-loaded series (instances/participants/guests)
     * @param int $reminderType ReservationReminderType::Start | ReservationReminderType::End
     * @param int $minutesPrior how far before start/end this reminder fires (wording only)
     */
    public function Send(?ReservationSeries $series, $reminderType, $minutesPrior)
    {
        if ($series === null) {
            return;
        }

        // Cancelled series never remind. The due-reminder query already filters
        // status_id <> Deleted; re-checked here so a direct Send() (or a series cancelled
        // between the query and this call) can't leak one.
        if ($series->StatusId() == ReservationStatus::Deleted) {
            return;
        }

        if (!$this->emailEnabled && !$this->lineEnabled && !$this->wecomEnabled) {
            return;
        }

        $instance = $series->CurrentInstance();
        if ($instance === null) {
            return;
        }

        $ownerId = $series->UserId();
        $owner = $this->userRepository->LoadById($ownerId);

        $internalUserIds = $this->ResolveInternalAttendees($instance, $ownerId);

        $minutesPrior = (int)$minutesPrior;

        // ---- owner ----
        $this->SendToUser($owner, true, $series, $owner, $reminderType, $minutesPrior);

        // ---- internal attendees (participants + invitees) ----
        foreach ($internalUserIds as $uid) {
            $attendee = $this->userRepository->LoadById($uid);
            if ($attendee === null) {
                continue;
            }
            $this->SendToUser($attendee, false, $series, $owner, $reminderType, $minutesPrior);
        }

        // External guests are deliberately not reminded: the mail server only delivers to
        // internal addresses and guests have no LINE, so there is no channel that would work.
    }

    /**
     * Deduped participant + invitee user ids, with the owner removed (they are handled
     * separately, with host credentials).
     *
     * @return int[]
     */
    private function ResolveInternalAttendees(Reservation $instance, $ownerId): array
    {
        $internalUserIds = [];
        foreach (array_merge($instance->Participants(), $instance->Invitees()) as $uid) {
            if ($uid == $ownerId) {
                continue;
            }
            if (!in_array($uid, $internalUserIds)) {
                $internalUserIds[] = $uid;
            }
        }

        return $internalUserIds;
    }

    private function SendToUser(User $user, bool $isOwner, ReservationSeries $series, User $owner, $reminderType, $minutesPrior): void
    {
        // Owners can opt out of reminders for their own reservations via the toggle; attendees
        // can't be gated on it (see UserWantsEmail() and the class doc above) since they didn't
        // set their own schedule and the toggle was never meant to hide "this meeting starts soon".
        $emailAllowed = $isOwner ? $this->UserWantsEmail($user) : true;
        if ($this->emailEnabled && $emailAllowed) {
            $address = $user->EmailAddress();
            if (!empty($address)) {
                $this->SendEmail(
                    new EmailAddress($address, $user->FullName()),
                    $isOwner,
                    $series,
                    $owner,
                    $reminderType,
                    $minutesPrior,
                    $user->Language(),
                    $user->Timezone()
                );
            }
        }

        if ($this->lineEnabled) {
            $lineUserId = $user->GetPreferences()->Get(UserPreferences::LINE_USER_ID);
            if (!empty($lineUserId) && $user->WantsLineNotifications()) {
                $this->SendLine($lineUserId, $isOwner, $series, $owner, $user, $reminderType, $minutesPrior);
            }
        }

        if ($this->wecomEnabled) {
            $wecomUserId = $user->GetPreferences()->Get(UserPreferences::WECOM_USER_ID);
            if (!empty($wecomUserId) && $user->WantsWeComNotifications()) {
                $this->SendWeCom($wecomUserId, $isOwner, $series, $owner, $user, $reminderType, $minutesPrior);
            }
        }
    }

    /**
     * The single "接收 Email 通知" toggle on 通知設定 writes all six reservation-lifecycle
     * event preferences together (NotificationPreferencesService::EmailEvents()), so reading
     * one representative event answers "is the Email toggle on". Only consulted for the
     * reservation owner -- see SendToUser().
     */
    private function UserWantsEmail(User $user): bool
    {
        return $user->WantsEventEmail(new ReservationCreatedEvent());
    }

    private function SendEmail(
        EmailAddress $to,
        bool $isOwner,
        ReservationSeries $series,
        User $owner,
        $reminderType,
        $minutesPrior,
        $language = null,
        $timezone = null
    ): void {
        ServiceLocator::GetEmailService()->Send(new ReservationReminderEmail(
            $owner,
            $to,
            $isOwner,
            (int)$reminderType,
            (int)$minutesPrior,
            $series,
            $this->attributeRepository,
            $this->userRepository,
            $language,
            $timezone
        ));
    }

    private function SendLine(
        $lineUserId,
        bool $isOwner,
        ReservationSeries $series,
        User $owner,
        User $recipient,
        $reminderType,
        $minutesPrior
    ): void {
        $isStart = (int)$reminderType !== ReservationReminderType::End;

        $timezone = $recipient->Timezone();
        if (empty($timezone)) {
            $timezone = Configuration::Instance()->GetDefaultTimezone();
        }

        $instance = $series->CurrentInstance();
        $start = $instance->StartDate()->ToTimezone($timezone);
        $end = $instance->EndDate()->ToTimezone($timezone);
        $title = !empty($series->Title())
            ? $series->Title()
            : Resources::GetInstance()->GetString('NoReservationTitle');

        // Host credentials are fetched only when the recipient is the owner AND this is a
        // start reminder — same "never read the secret we won't show" pattern the email path
        // uses (ReservationEmailTemplateContext::VideoConferenceResources()).
        $primaryVideo = $this->ResolvePrimaryVideo($series, $owner, $isOwner && $isStart);

        $reservationUrl = Configuration::Instance()->GetScriptUrl() . '/' . sprintf(
            '%s?%s=%s',
            Pages::RESERVATION,
            QueryStringKeys::REFERENCE_NUMBER,
            $instance->ReferenceNumber()
        );

        $dateTimeText = $start->Format('Y-m-d H:i') . ' - ' . $end->Format('H:i');

        $bubble = $this->BuildFlexBubble($isStart, $title, $dateTimeText, $series, $primaryVideo, $owner, $isOwner, $reservationUrl);
        $altText = Resources::GetInstance()->GetString('LineReminderFlexAltText');

        $lineService = ServiceLocator::GetLineService();
        $lineService->PushFlex($lineUserId, $altText, $bubble);

        // Flex text can't be long-pressed to copy in LINE, so send the owner the host
        // credentials again as plain text they can actually paste. Owner + start only.
        if ($isOwner && $isStart && $primaryVideo !== null
            && (!empty($primaryVideo->HostAccount) || !empty($primaryVideo->HostPassword))) {
            $lineService->Push($lineUserId, $this->BuildHostInfoText($primaryVideo));
        }
    }

    /**
     * The CoAsia WeCom gateway only accepts plain text, so this is the LINE Flex bubble's
     * field set rendered as one readable multi-line message — the same shape as
     * OwnerWeComCreatedNotification. Owner + start reminders also get the video-conference
     * host account / password appended; nobody else ever does.
     */
    private function SendWeCom(
        $wecomUserId,
        bool $isOwner,
        ReservationSeries $series,
        User $owner,
        User $recipient,
        $reminderType,
        $minutesPrior
    ): void {
        $isStart = (int)$reminderType !== ReservationReminderType::End;
        $resources = Resources::GetInstance();

        $timezone = $recipient->Timezone();
        if (empty($timezone)) {
            $timezone = Configuration::Instance()->GetDefaultTimezone();
        }

        $instance = $series->CurrentInstance();
        $start = $instance->StartDate()->ToTimezone($timezone);
        $end = $instance->EndDate()->ToTimezone($timezone);
        $title = !empty($series->Title())
            ? $series->Title()
            : $resources->GetString('NoReservationTitle');

        $primaryVideo = $this->ResolvePrimaryVideo($series, $owner, $isOwner && $isStart);

        $reservationUrl = Configuration::Instance()->GetScriptUrl() . '/' . sprintf(
            '%s?%s=%s',
            Pages::RESERVATION,
            QueryStringKeys::REFERENCE_NUMBER,
            $instance->ReferenceNumber()
        );

        $dateTimeText = $start->Format('Y-m-d H:i') . ' - ' . $end->Format('H:i');

        $lines = [];
        $lines[] = '【CoAsia Booking】' . $resources->GetString($isStart ? 'ReminderEmailStatusStarting' : 'ReminderEmailStatusEnding');
        $lines[] = $resources->GetString('WeComFieldMeetingName') . '：' . $title;
        $lines[] = $resources->GetString('WeComFieldDateTime') . '：' . $dateTimeText;

        if ($primaryVideo !== null) {
            $lines[] = $resources->GetString('WeComFieldVideoResource') . '：' . $primaryVideo->ResourceName;
            if ($isStart && !empty($primaryVideo->Url)) {
                $lines[] = $resources->GetString('WeComJoinVideoConference') . '：' . $primaryVideo->Url;
            }
        } else {
            $lines[] = $resources->GetString('WeComFieldMeetingRoom') . '：' . $series->Resource()->GetName();
        }

        $lines[] = $resources->GetString('WeComFieldOwner') . '：' . $owner->FullName();
        $lines[] = $resources->GetString('WeComViewReservationDetails') . '：' . $reservationUrl;

        if ($isOwner && $isStart && $primaryVideo !== null
            && (!empty($primaryVideo->HostAccount) || !empty($primaryVideo->HostPassword))) {
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

    /**
     * @return VideoConferenceInfo|null the one video resource matching the series' primary resource
     */
    private function ResolvePrimaryVideo(ReservationSeries $series, User $owner, bool $includeHostCredentials)
    {
        $context = new ReservationEmailTemplateContext(
            $series,
            $owner,
            $series->Resource(),
            $this->attributeRepository
        );

        foreach ($context->VideoConferenceResources(includeHostCredentials: $includeHostCredentials) as $video) {
            if ($video->ResourceId == $series->Resource()->GetId()) {
                return $video;
            }
        }

        return null;
    }

    private function BuildHostInfoText(VideoConferenceInfo $primaryVideo): string
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
     * The reminder Flex bubble: status, title, date/time, video-resource-or-room, owner,
     * (start only) join button, (owner + start only) host-info box, view-details link — the
     * same field set the reminder e-mail shows.
     */
    private function BuildFlexBubble(
        bool $isStart,
        $title,
        $dateTimeText,
        ReservationSeries $series,
        $primaryVideo,
        User $owner,
        bool $isOwner,
        $reservationUrl
    ): array {
        $resources = Resources::GetInstance();
        $body = [];

        // 1. 提醒類型
        $body[] = [
            'type' => 'box',
            'layout' => 'baseline',
            'contents' => [[
                'type' => 'text',
                'text' => $resources->GetString($isStart ? 'ReminderEmailStatusStarting' : 'ReminderEmailStatusEnding'),
                'size' => 'xs',
                'weight' => 'bold',
                'color' => self::COASIA_PRIMARY,
            ]],
        ];

        // 2. 會議名稱
        $body[] = [
            'type' => 'text',
            'text' => (string)$title,
            'weight' => 'bold',
            'size' => 'lg',
            'wrap' => true,
            'margin' => 'sm',
        ];

        // 3-5. 日期時間 / 視訊資源或會議室 / 預約人
        $infoRows = [$this->BuildInfoRow($resources->GetString('LineFlexFieldDateTime'), $dateTimeText)];
        if ($primaryVideo !== null) {
            $infoRows[] = $this->BuildInfoRow($resources->GetString('LineFlexFieldVideoResource'), $primaryVideo->ResourceName);
        } else {
            $infoRows[] = $this->BuildInfoRow($resources->GetString('LineFlexFieldMeetingRoom'), $series->Resource()->GetName());
        }
        $infoRows[] = $this->BuildInfoRow($resources->GetString('LineFlexFieldOwner'), $owner->FullName());

        $body[] = [
            'type' => 'box',
            'layout' => 'vertical',
            'margin' => 'lg',
            'spacing' => 'sm',
            'contents' => $infoRows,
        ];

        // 6. 加入視訊會議 — start reminders only
        if ($primaryVideo !== null && $isStart) {
            $body[] = [
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

            // 7. 主持人登入資訊 — owner only, and only if there is anything to show.
            if ($isOwner && (!empty($primaryVideo->HostAccount) || !empty($primaryVideo->HostPassword))) {
                $hostRows = [];
                if (!empty($primaryVideo->MeetingId)) {
                    $hostRows[] = $this->BuildInfoRow($resources->GetString('MeetingId'), $primaryVideo->MeetingId);
                }
                if (!empty($primaryVideo->Passcode)) {
                    $hostRows[] = $this->BuildInfoRow($resources->GetString('Passcode'), $primaryVideo->Passcode);
                }
                if (!empty($primaryVideo->HostAccount)) {
                    $hostRows[] = $this->BuildInfoRow($resources->GetString('VideoConferenceHostAccount'), $primaryVideo->HostAccount);
                }
                if (!empty($primaryVideo->HostPassword)) {
                    $hostRows[] = $this->BuildInfoRow($resources->GetString('VideoConferenceHostPassword'), $primaryVideo->HostPassword);
                }

                $body[] = [
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
                        $hostRows,
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
        $body[] = [
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
                'contents' => $body,
            ],
        ];
    }

    private function BuildInfoRow($label, $value): array
    {
        return [
            'type' => 'box',
            'layout' => 'baseline',
            'contents' => [
                ['type' => 'text', 'text' => (string)$label, 'size' => 'sm', 'color' => '#6c7488', 'flex' => 2],
                ['type' => 'text', 'text' => (string)$value, 'size' => 'sm', 'color' => '#1c2333', 'flex' => 5, 'wrap' => true],
            ],
        ];
    }
}
