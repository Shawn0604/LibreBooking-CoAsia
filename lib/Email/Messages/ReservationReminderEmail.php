<?php

require_once(ROOT_DIR . 'Domain/Values/ReservationReminder.php');
require_once(ROOT_DIR . 'lib/Email/Messages/ReservationEmailMessage.php');

/**
 * "會議開始前提醒" e-mail — one instance per recipient.
 *
 * Reuses the shared reservation-notification-body.tpl (the same visual system as the
 * created / updated / cancelled e-mails) through its {@see $Status} == 'reminder' branch, so
 * the reminder e-mail and the reminder LINE push always show the same fields: reminder type,
 * title, date/time, room or online-meeting name, video link, and the reservation-detail
 * link.
 *
 * {@see IsRecipientOwner()} gates the "host login info" box exactly the way it does for the
 * owner-vs-participant created e-mails — participants, invitees and external guests never see
 * the shared host account / password.
 */
class ReservationReminderEmail extends ReservationEmailMessage
{
    private EmailAddress $recipient;
    private bool $recipientIsOwner;
    private int $reminderType;
    private int $minutesPrior;

    /**
     * @param User $reservationOwner the series owner (used for "From", timezone/language default, ICS organiser)
     * @param EmailAddress $recipient who this copy is actually sent to
     * @param bool $recipientIsOwner true only when $recipient is the owner themselves
     * @param int $reminderType ReservationReminderType::Start | ReservationReminderType::End
     * @param int $minutesPrior how far before start/end the reminder fires (wording only)
     * @param ReservationSeries $reservationSeries fully loaded series
     * @param string|null $language recipient language override, falls back to the owner's
     * @param string|null $timezone recipient timezone override, falls back to the owner's
     */
    public function __construct(
        User $reservationOwner,
        EmailAddress $recipient,
        bool $recipientIsOwner,
        int $reminderType,
        int $minutesPrior,
        ReservationSeries $reservationSeries,
        IAttributeRepository $attributeRepository,
        IUserRepository $userRepository,
        ?string $language = null,
        ?string $timezone = null
    ) {
        parent::__construct(
            $reservationOwner,
            $reservationSeries,
            !empty($language) ? $language : $reservationOwner->Language(),
            $attributeRepository,
            $userRepository
        );

        if (!empty($timezone)) {
            $this->timezone = $timezone;
        }

        $this->recipient = $recipient;
        $this->recipientIsOwner = $recipientIsOwner;
        $this->reminderType = $reminderType;
        $this->minutesPrior = $minutesPrior;
    }

    public function To()
    {
        return [$this->recipient];
    }

    protected function IsRecipientOwner(): bool
    {
        return $this->recipientIsOwner;
    }

    /**
     * Public mirror of {@see IsRecipientOwner()} — the host-login-info box in
     * reservation-notification-body.tpl is gated on this, so it is also the thing tests
     * assert to prove the shared host account / password never reaches a non-owner copy.
     */
    public function RecipientIsOwner(): bool
    {
        return $this->recipientIsOwner;
    }

    /**
     * @return int ReservationReminderType::Start | ReservationReminderType::End
     */
    public function ReminderType(): int
    {
        return $this->reminderType;
    }

    public function Subject()
    {
        $key = $this->reminderType === ReservationReminderType::End
            ? 'ReservationEndingSoonSubject'
            : 'ReservationStartingSoonSubject';

        return $this->Translate($key, [$this->reservationSeries->Resource()->GetName()]);
    }

    protected function GetTemplateName()
    {
        return 'ReservationReminder.tpl';
    }

    protected function PopulateTemplate()
    {
        parent::PopulateTemplate();

        $isEnd = $this->reminderType === ReservationReminderType::End;
        $this->Set('Status', 'reminder');
        $this->Set('ReminderType', $isEnd ? 'end' : 'start');
        $this->Set('ReminderMinutesPrior', $this->minutesPrior);
        $this->Set(
            'ReminderStatusLabel',
            Resources::GetInstance()->GetString($isEnd ? 'ReminderEmailStatusEnding' : 'ReminderEmailStatusStarting')
        );
    }
}
