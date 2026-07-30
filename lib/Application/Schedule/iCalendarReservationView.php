<?php

class iCalendarReservationView
{
    public $Classification;
    public $DateCreated;
    public $DateEnd;
    public $DateStart;
    public $Description;
    public $Organizer;
    public $OrganizerEmail;
    public $RecurRule;
    public $ReferenceNumber;
    public $Summary;
    public $ReservationUrl;
    public $Location;
    public $StartReminder;
    public $EndReminder;
    public $LastModified;
    public $IsPending;
    public $ExtraIcalLines;

    /**
     * The time this particular iCalendar object instance was generated (RFC 5545 DTSTAMP) —
     * distinct from DateCreated (the reservation's original creation time): DTSTAMP must
     * advance on every REQUEST/CANCEL a client receives for the same UID, even when nothing
     * else about the reservation changed, or some calendar apps won't treat it as a fresh
     * update. Always "now", always UTC.
     * @var Date
     */
    public $DtStamp;

    /**
     * iCalendar SEQUENCE — sourced from the reservation series' optimistic-locking version
     * (ReservationItemView::$Version), so it naturally starts at 0 for a new reservation and
     * increases by exactly 1 on every subsequent REQUEST/CANCEL, matching what Outlook/Google
     * Calendar expect in order to apply an update in place instead of adding a duplicate event.
     * @var int
     */
    public $Sequence = 0;

    /**
     * Top-level iCalendar METHOD — 'REQUEST' for create/update, 'CANCEL' when this ICS is
     * attached to a cancellation notification (see ReservationEmailMessage::GetIcsMethod()).
     * @var string
     */
    public $Method;

    /**
     * STATUS for this VEVENT — CANCELLED unconditionally when Method is CANCEL (regardless of
     * whether the reservation happened to require approval), otherwise TENTATIVE/CONFIRMED
     * based on IsPending, same as before.
     * @var string
     */
    public $Status;

    /**
     * @var ExportFactory
     */
    private $ExportFactory;

    /**
     * @var ReservationItemView
     */
    public $ReservationItemView;

    /**
     * @param ReservationItemView $res
     * @param UserSession $currentUser
     * @param IPrivacyFilter $privacyFilter
     * @param string|null $summaryFormat
     * @param string $method 'REQUEST' (create/update, default) or 'CANCEL'
     */
    public function __construct($res, UserSession $currentUser, IPrivacyFilter $privacyFilter, $summaryFormat = null, $method = 'REQUEST')
    {
        if ($summaryFormat == null) {
            $summaryFormat = Configuration::Instance()->GetKey(ConfigKeys::RESERVATION_LABELS_ICS_SUMMARY);
        }
        $factory = new SlotLabelFactory($currentUser);
        $this->ReservationItemView = $res;
        $canViewUser = $privacyFilter->CanViewUser($currentUser, $res, $res->OwnerId);
        $canViewDetails = $privacyFilter->CanViewDetails($currentUser, $res, $res->OwnerId);

        // PrivacyFilter only gates on privacy.hide.reservation.details and privacy.hide.user.details.
        // For anonymous (not logged-in) callers, also enforce privacy.view.reservations, which is the
        // site-wide switch that controls whether unauthenticated visitors may see reservation details at all.
        if (!$currentUser->IsLoggedIn()) {
            $publicViewAllowed = Configuration::Instance()->GetKey(ConfigKeys::PRIVACY_VIEW_RESERVATIONS, new BooleanConverter());
            if (!$publicViewAllowed) {
                $canViewUser = false;
                $canViewDetails = false;
            }
        }

        $this->ExportFactory = PluginManager::Instance()->LoadExport();

        $privateNotice = 'Private';

        $this->Classification = method_exists($this->ExportFactory, 'GetIcalendarClassification') ? $this->ExportFactory->GetIcalendarClassification($res) : 'PUBLIC';
        // ->ToUtc() on every timestamp below is what makes the trailing 'Z' the ical date
        // format (Resources::$systemDateKeys['ical'] = 'Ymd\THis\Z') writes actually true —
        // Date::Format() just prints whatever timezone the Date object currently holds, it
        // doesn't convert anything itself. $res->DateCreated/ModifiedDate are UTC already when
        // sourced from the database (Date::FromDatabase() always tags 'UTC'), so ->ToUtc() is a
        // harmless no-op there; the case this actually fixes is the email attachment path
        // (ReservationEmailMessage::PopulateIcsAttachment()), which never sets DateCreated at
        // all, so it fell through to Date::Now() — the *server's local* timezone — and got
        // labelled 'Z' (UTC) regardless, silently off by the server's UTC offset.
        if ($res->DateCreated) {
            $this->DateCreated = $res->DateCreated->ToUtc();
        } else {
            $this->DateCreated = Date::Now()->ToUtc();
        }

        // Guarded the same way as DateCreated above — some callers (and several existing tests)
        // build a ReservationItemView without dates when they only care about testing unrelated
        // fields (e.g. Organizer), so these can legitimately be null.
        $this->DateEnd = $res->EndDate ? $res->EndDate->ToUtc() : $res->EndDate;
        $this->DateStart = $res->StartDate ? $res->StartDate->ToUtc() : $res->StartDate;
        $this->Summary = $canViewDetails ? self::toRfc5545Text($factory->Format($res, $summaryFormat)) : $privateNotice;
        $this->Description = $canViewDetails ? self::toRfc5545Text($res->Description ?? '') : $privateNotice;
        $fullName = new FullName($res->OwnerFirstName, $res->OwnerLastName);
        $this->Organizer = $canViewUser ? $fullName->__toString() : $privateNotice;
        $this->OrganizerEmail = $canViewUser ? $res->OwnerEmailAddress : $privateNotice;
        $this->RecurRule = $this->CreateRecurRule($res);
        $this->ReferenceNumber = $res->ReferenceNumber;
        $this->ReservationUrl = sprintf(
            '%s/%s?%s=%s',
            Configuration::Instance()->GetScriptUrl(),
            Pages::RESERVATION,
            QueryStringKeys::REFERENCE_NUMBER,
            $res->ReferenceNumber
        );
        $this->Location = $res->ResourceName;

        $this->StartReminder = $res->StartReminder;
        $this->EndReminder = $res->EndReminder;
        $this->LastModified = (empty($res->ModifiedDate) || $res->ModifiedDate->ToString() == '') ? $this->DateCreated : $res->ModifiedDate->ToUtc();
        $this->IsPending = $res->RequiresApproval;

        // DTSTAMP is "when was this particular message generated", never the reservation's
        // original creation time — reusing DateCreated here (as this class used to) means an
        // update email's ICS carries the same DTSTAMP as the original create email, which is
        // exactly the kind of thing that makes Outlook/Google Calendar unsure whether they're
        // looking at a stale re-delivery or a genuine new revision.
        $this->DtStamp = Date::Now()->ToUtc();

        $this->Sequence = isset($res->Version) ? (int)$res->Version : 0;
        $this->Method = $method;
        $this->Status = $method === 'CANCEL' ? 'CANCELLED' : ($this->IsPending ? 'TENTATIVE' : 'CONFIRMED');

        if ($canViewUser && $res->OwnerId == $currentUser->UserId) {
            $this->OrganizerEmail = str_replace('@', '-noreply@', $res->OwnerEmailAddress);
        }

        $this->ExtraIcalLines = method_exists($this->ExportFactory, 'GetIcalendarExtraLines') ? $this->ExportFactory->GetIcalendarExtraLines($res) : null;
    }

    /**
     * Escapes a plain-text value for use in an iCalendar TEXT property (RFC 5545 §3.3.11).
     * Backslashes, semicolons, and commas are escaped first; then newline sequences
     * (\r\n, \n, \r) are replaced with the literal two-character escape \n so that
     * calendar clients parse SUMMARY, DESCRIPTION, and any other TEXT property correctly.
     */
    private static function toRfc5545Text(string $value): string
    {
        // addcslashes prefixes each of '\', ',', and ';' with a backslash in a single pass.
        $value = addcslashes($value, '\\,;');
        return str_replace(["\r\n", "\n", "\r"], '\\n', $value);
    }

    /**
     * @param ReservationItemView $res
     * @return null|string
     */
    private function CreateRecurRule($res)
    {
        if (is_a($res, 'ReservationItemView')) {
            // don't populate the recurrence rule when a list of reservation is being exported
            return null;
        }
        ### !!!  THIS DOES NOT WORK BECAUSE EXCEPTIONS TO RECURRENCE RULES ARE NOT PROPERLY HANDLED !!!
        ### see bug report http://php.brickhost.com/forums/index.php?topic=11450.0

        if (empty($res->RepeatType) || $res->RepeatType == RepeatType::None) {
            return null;
        }

        $freqMapping = [RepeatType::Daily => 'DAILY', RepeatType::Weekly => 'WEEKLY', RepeatType::Monthly => 'MONTHLY', RepeatType::Yearly => 'YEARLY'];
        $freq = $freqMapping[$res->RepeatType];
        $interval = $res->RepeatInterval;
        $format = Resources::GetInstance()->GetDateFormat('ical');
        $end = $res->RepeatTerminationDate->SetTime($res->EndDate->GetTime())->Format($format);
        $rrule = sprintf('FREQ=%s;INTERVAL=%s;UNTIL=%s', $freq, $interval, $end);

        if ($res->RepeatType == RepeatType::Monthly) {
            if ($res->RepeatMonthlyType == RepeatMonthlyType::DayOfMonth) {
                $rrule .= ';BYMONTHDAY=' . $res->StartDate->Day();
            }
        }

        if (!empty($res->RepeatWeekdays)) {
            $dayMapping = ['SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA'];
            $days = '';
            foreach ($res->RepeatWeekdays as $weekDay) {
                $days .= ($dayMapping[$weekDay] . ',');
            }
            $days = substr($days, 0, -1);
            $rrule .= (';BYDAY=' . $days);
        }

        return $rrule;
    }
}
