<?php

require_once(ROOT_DIR . 'lib/Email/namespace.php');
require_once(ROOT_DIR . 'Pages/Pages.php');
require_once(ROOT_DIR . 'Pages/Export/CalendarExportDisplay.php');
require_once(ROOT_DIR . 'lib/Application/Schedule/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Reservation/namespace.php');
require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'lib/Email/Messages/ReservationEmailTemplateContext.php');

abstract class ReservationEmailMessage extends EmailMessage
{
    /**
     * @var User
     */
    protected $reservationOwner;

    /**
     * @var ReservationSeries
     */
    protected $reservationSeries;

    /**
     * @var BookableResource
     */
    protected $primaryResource;

    protected ?string $timezone;

    /**
     * @var IAttributeRepository
     */
    protected $attributeRepository;

    /**
     * @var IUserRepository
     */
    protected $userRepository;

    public function __construct(
        User $reservationOwner,
        ReservationSeries $reservationSeries,
        $language,
        IAttributeRepository $attributeRepository,
        IUserRepository $userRepository
    ) {
        if (empty($language)) {
            $language = $reservationOwner->Language();
        }
        parent::__construct($language);

        $this->reservationOwner = $reservationOwner;
        $this->reservationSeries = $reservationSeries;
        $ownerTimezone = $reservationOwner->Timezone();
        $this->timezone = !empty($ownerTimezone) ? $ownerTimezone : Configuration::Instance()->GetDefaultTimezone();
        $this->attributeRepository = $attributeRepository;
        $this->primaryResource = $reservationSeries->Resource();
        $this->userRepository = $userRepository;
    }

    /**
     * @abstract
     * @return string
     */
    abstract protected function GetTemplateName();

    /**
     * Whether this message's recipient (see To()) is the reservation's own owner, as opposed
     * to a participant/invitee/guest/admin. Gates whether PopulateTemplate() fetches+includes
     * video conference host credentials (VideoConferenceHostRepository) -- defaults to false
     * (secure by default) since most subclasses of this class send to someone OTHER than the
     * owner (participants, invitees, guests, admins); only the base ReservationCreatedEmail/
     * ReservationUpdatedEmail/ReservationApprovedEmail/ReservationDeletedEmail (used by
     * OwnerEmailNotification, which always sends To() the owner) override this to true.
     */
    protected function IsRecipientOwner(): bool
    {
        return false;
    }

    public function To()
    {
        $address = $this->reservationOwner->EmailAddress();
        $name = $this->reservationOwner->FullName();

        return [new EmailAddress($address, $name)];
    }

    public function Body()
    {
        $this->PopulateTemplate();
        return $this->FetchTemplate($this->GetTemplateName());
    }

    public function From()
    {
        $bookedBy = $this->reservationSeries->BookedBy();
        if ($bookedBy != null) {
            $name = new FullName($bookedBy->FirstName, $bookedBy->LastName);
            return new EmailAddress($bookedBy->Email, $name->__toString());
        }
        return new EmailAddress($this->reservationOwner->EmailAddress(), $this->reservationOwner->FullName());
    }

    protected function PopulateTemplate()
    {
        $currentInstance = $this->reservationSeries->CurrentInstance();
        $context = new ReservationEmailTemplateContext(
            reservationSeries: $this->reservationSeries,
            reservationOwner: $this->reservationOwner,
            primaryResource: $this->primaryResource,
            attributeRepository: $this->attributeRepository
        );

        $this->Set('UserName', $this->reservationOwner->FullName());
        $this->Set('StartDate', $currentInstance->StartDate()->ToTimezone($this->timezone));
        $this->Set('EndDate', $currentInstance->EndDate()->ToTimezone($this->timezone));
        $this->Set('ScheduleId', $this->reservationSeries->ScheduleId());
        $this->Set('ResourceName', $this->reservationSeries->Resource()->GetName());
        $resourceImage = ReservationEmailTemplateContext::BuildResourceImageUrl($this->reservationSeries->Resource()->GetImage());
        if ($resourceImage != null) {
            $this->Set('ResourceImage', $resourceImage);
        }

        $this->Set('Title', !empty($this->reservationSeries->Title()) ? $this->reservationSeries->Title() : Resources::GetInstance()->GetString('NoReservationTitle'));
        $this->Set('Description', $this->reservationSeries->Description());

        $repeatVariables = $context->GetRecurrenceInstances($this->timezone);
        $this->Set('RepeatDates', $repeatVariables['RepeatDates']);
        $this->Set('RepeatRanges', $repeatVariables['RepeatRanges']);
        $this->Set('RequiresApproval', $this->reservationSeries->RequiresApproval());

        $this->Set('ReservationUrl', sprintf('%s?%s=%s', Pages::RESERVATION, QueryStringKeys::REFERENCE_NUMBER, $currentInstance->ReferenceNumber()));

        $icalUrl = sprintf('export/%s?%s=%s', Pages::CALENDAR_EXPORT, QueryStringKeys::REFERENCE_NUMBER, $currentInstance->ReferenceNumber());
        $this->Set('ICalUrl', $icalUrl);

        $googleDateFormat = Resources::GetInstance()->GetDateFormat('google');
        $googleCalendarUrl = sprintf(
            'https://www.google.com/calendar/event?action=TEMPLATE&text=%s&dates=%s/%s&ctz=%s&details=%s&location=%s&trp=false&sprop=&sprop=name:',
            urlencode($this->reservationSeries->Title()),
            $currentInstance->StartDate()->ToUtc()->Format($googleDateFormat),
            $currentInstance->EndDate()->ToUtc()->Format($googleDateFormat),
            $currentInstance->StartDate()->Timezone(),
            urlencode($this->reservationSeries->Description()),
            urlencode($this->reservationSeries->Resource()->GetName())
        );
        $this->Set('GoogleCalendarUrl', $googleCalendarUrl);

        $this->Set('ResourceNames', $context->ResourceNames());
        $this->Set('Resources', $context->Resources());
        $this->Set('Accessories', $this->reservationSeries->Accessories());

        $attributeValues = $context->ReservationAttributes();
        $this->Set('Attributes', $attributeValues);

        $videoConferenceResources = $context->VideoConferenceResources(includeHostCredentials: $this->IsRecipientOwner());
        $this->Set('VideoConferenceResources', $videoConferenceResources);
        $this->Set('IsRecipientOwner', $this->IsRecipientOwner());

        // The one video resource, if any, matching this reservation's own primary resource --
        // drives the "視訊會議"/join-button/host-info section as a single value rather than a
        // loop, since the redesigned template (COASIA-CHANGES.md, video conference redesign)
        // shows exactly one video resource's info, not a list. A reservation against a
        // physical resource leaves this null, which is what keeps that whole section off
        // physical-room emails without a separate "$IsVideoResource" flag to keep in sync.
        $primaryVideo = null;
        foreach ($videoConferenceResources as $videoResource) {
            if ($videoResource->ResourceId === $this->primaryResource->GetId()) {
                $primaryVideo = $videoResource;
                break;
            }
        }
        $this->Set('PrimaryVideo', $primaryVideo);

        $createdBy = $context->CreatedBy();
        if ($createdBy != null) {
            $this->Set('CreatedBy', $createdBy);
        }

        $minimumAutoRelease = null;
        foreach ($this->reservationSeries->AllResources() as $resource) {
            if ($resource->IsCheckInEnabled()) {
                $this->Set('CheckInEnabled', true);
            }

            if ($resource->IsAutoReleased()) {
                if ($minimumAutoRelease == null || $resource->GetAutoReleaseMinutes() < $minimumAutoRelease) {
                    $minimumAutoRelease = $resource->GetAutoReleaseMinutes();
                }
            }
        }

        $this->PopulateIcsAttachment($currentInstance, $attributeValues, $videoConferenceResources);

        $this->Set('AutoReleaseMinutes', $minimumAutoRelease);
        $this->Set('ReferenceNumber', $currentInstance->ReferenceNumber());

        $participants = [];
        foreach ($currentInstance->Participants() as $id) {
            $participants[] = $this->userRepository->GetById($id);
        }
        $this->Set('Participants', $participants);
        $this->Set('ParticipatingGuests', $currentInstance->ParticipatingGuests());

        $invitees = [];
        foreach ($currentInstance->Invitees() as $id) {
            $invitees[] = $this->userRepository->GetById($id);
        }
        $this->Set('Invitees', $invitees);
        $this->Set('InvitedGuests', $currentInstance->InvitedGuests());

        $this->Set('CreditsCurrent', $currentInstance->GetCreditsRequired());
        $this->Set('CreditsTotal', $this->reservationSeries->GetCreditsRequired());
    }

    /**
     * @param Reservation $currentInstance
     * @param Attribute[] $attributeValues
     * @param VideoConferenceInfo[] $videoConferenceResources
     */
    protected function PopulateIcsAttachment($currentInstance, $attributeValues, $videoConferenceResources = [])
    {
        $locationName = $this->reservationSeries->Resource()->GetName();
        foreach ($videoConferenceResources as $videoResource) {
            if ($videoResource->ResourceName === $locationName) {
                $locationName .= ' - ' . $videoResource->Url;
                break;
            }
        }

        $rv = new ReservationItemView(
            $currentInstance->ReferenceNumber(),
            $currentInstance->StartDate()->ToUTC(),
            $currentInstance->EndDate()->ToUTC(),
            $locationName,
            $this->reservationSeries->Resource()->GetResourceId(),
            $currentInstance->ReservationId(),
            null,
            !empty($this->reservationSeries->Title()) ? $this->reservationSeries->Title() : Resources::GetInstance()->GetString('NoReservationTitle'),
            $this->reservationSeries->Description(),
            $this->reservationSeries->ScheduleId(),
            $this->reservationOwner->FirstName(),
            $this->reservationOwner->LastName(),
            $this->reservationOwner->Id(),
            $this->reservationOwner->GetAttribute(UserAttribute::Phone),
            $this->reservationOwner->GetAttribute(UserAttribute::Organization),
            $this->reservationOwner->GetAttribute(UserAttribute::Position)
        );

        $ca = new CustomAttributes();
        /** @var LBAttribute $attribute */
        foreach ($attributeValues as $attribute) {
            $ca->Add($attribute->Id(), $attribute->Value());
        }
        $rv->Attributes = $ca;
        $rv->UserPreferences = $this->reservationOwner->GetPreferences();
        $rv->OwnerEmailAddress = $this->reservationOwner->EmailAddress();
        // SEQUENCE — see Domain/ReservationSeries::Version()/WithVersion(). Starts at 0 for a
        // brand-new series and is bumped by ReservationRepository::Update()/DeleteSeriesEventCommand
        // right after each successful write, so a create/update/cancel notification's ICS always
        // carries a SEQUENCE that's at least as high as the last one a recipient's calendar app saw.
        $rv->Version = $this->reservationSeries->Version();

        $icsView = new iCalendarReservationView(
            $rv,
            $this->IcsCurrentUser(),
            new NullPrivacyFilter(),
            null,
            $this->GetIcsMethod()
        );

        $display = new CalendarExportDisplay();
        $icsContents = $display->Render([$icsView]);
        $this->AddStringAttachment($icsContents, $this->BuildIcsFileName($currentInstance->ReferenceNumber()));
    }

    /**
     * The "current user" iCalendarReservationView renders the ICS attachment's summary/labels
     * and privacy checks against. Normally the {@see UserSession} that made the booking, but
     * reminder e-mails are built by a cron job (Jobs/sendreminders.php) where the series is
     * loaded fresh from the database and BookedBy() is therefore null — and
     * iCalendarReservationView type-hints UserSession, so a null there is a fatal TypeError.
     * Fall back to a session synthesised from the reservation owner, mirroring the same
     * owner-fallback {@see From()} already uses.
     *
     * @return UserSession
     */
    protected function IcsCurrentUser(): UserSession
    {
        $bookedBy = $this->reservationSeries->BookedBy();
        if ($bookedBy instanceof UserSession) {
            return $bookedBy;
        }

        $session = new UserSession($this->reservationOwner->Id());
        $session->FirstName = $this->reservationOwner->FirstName();
        $session->LastName = $this->reservationOwner->LastName();
        $session->Email = $this->reservationOwner->EmailAddress();
        $ownerTimezone = $this->reservationOwner->Timezone();
        $session->Timezone = !empty($ownerTimezone) ? $ownerTimezone : Configuration::Instance()->GetDefaultTimezone();
        $session->LanguageCode = $this->reservationOwner->Language();

        return $session;
    }

    /**
     * Top-level iCalendar METHOD for this notification's ICS attachment. 'REQUEST' (create/
     * update) by default; ReservationDeletedEmail overrides this to 'CANCEL' — the two need
     * genuinely different ICS bodies (STATUS:CANCELLED etc, see iCalendarReservationView),
     * not just a different subject line.
     * @return string
     */
    protected function GetIcsMethod()
    {
        return 'REQUEST';
    }

    /**
     * A unique, single-extension filename per reservation — deliberately not the fixed literal
     * 'reservation.ics' every notification used to attach, which some mail gateways/clients
     * (already primed to append their own sniffed extension to a generic-looking attachment
     * name) have been observed re-suffixing into 'reservation.ics.ics'. Stripping any existing
     * .ics suffix from the reference number before appending our own makes that impossible from
     * this end regardless of the root cause.
     * @param string $referenceNumber
     * @return string
     */
    private function BuildIcsFileName($referenceNumber)
    {
        $base = preg_replace('/\.ics$/i', '', (string)$referenceNumber);
        return 'reservation-' . $base . '.ics';
    }
}
