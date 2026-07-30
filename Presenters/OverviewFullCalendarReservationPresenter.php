<?php

require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Reservation/namespace.php');

/**
 * Backing service for the FullCalendar-based overview page's reservation Drawer
 * (Web/scripts/overview_fullcalendar.js) — loads a single reservation's FULL editable data
 * by reference number, so the Drawer never has to guess at fields (participants, reminders,
 * custom attributes, recurrence) from the calendar's own lightweight event objects. Reuses
 * the same services the classic edit form (Pages/Reservation/ExistingReservationPage.php)
 * relies on rather than re-deriving permission/visibility rules: IReservationViewRepository
 * for the actual ReservationView, IPrivacyFilter for whether details may be seen at all, and
 * IReservationAuthorization for whether they may be edited/deleted.
 */
class OverviewFullCalendarReservationPresenter
{
    /**
     * @var IReservationViewRepository
     */
    private $reservationViewRepository;

    /**
     * @var IPrivacyFilter
     */
    private $privacyFilter;

    /**
     * @var IReservationAuthorization
     */
    private $reservationAuthorization;

    public function __construct(
        IReservationViewRepository $reservationViewRepository,
        IPrivacyFilter $privacyFilter,
        IReservationAuthorization $reservationAuthorization
    ) {
        $this->reservationViewRepository = $reservationViewRepository;
        $this->privacyFilter = $privacyFilter;
        $this->reservationAuthorization = $reservationAuthorization;
    }

    /**
     * @param string $referenceNumber
     * @param UserSession $userSession
     * @return array JSON-serializable payload. Only ever contains 'found' when the
     *   reservation doesn't exist, or 'found'+'canView' (both false) when it exists but this
     *   user isn't allowed to see it — no other field is present in either case, so there's
     *   nothing for an unauthorized caller to scrape off the response.
     */
    public function GetReservationData($referenceNumber, UserSession $userSession)
    {
        $reservationView = $this->reservationViewRepository->GetReservationForEditing($referenceNumber);

        if (!$reservationView->IsDisplayable()) {
            return ['found' => false];
        }

        $canView = $this->privacyFilter->CanViewDetails($userSession, $reservationView, $reservationView->OwnerId);
        if (!$canView) {
            return ['found' => true, 'canView' => false];
        }

        $canEdit = $this->reservationAuthorization->CanEdit($reservationView, $userSession);
        $timezone = $userSession->Timezone;

        return [
            'found' => true,
            'canView' => true,
            'canEdit' => $canEdit,
            'canDelete' => $canEdit,
            'referenceNumber' => $reservationView->ReferenceNumber,
            'version' => $reservationView->Version,
            'title' => $reservationView->Title,
            'description' => $reservationView->Description,
            'resourceId' => (string)$reservationView->ResourceId,
            'additionalResourceIds' => array_map('strval', $reservationView->AdditionalResourceIds),
            'startDate' => $reservationView->StartDate->ToTimezone($timezone)->Format('Y-m-d'),
            'startTime' => $reservationView->StartDate->ToTimezone($timezone)->Format('H:i:s'),
            'endDate' => $reservationView->EndDate->ToTimezone($timezone)->Format('Y-m-d'),
            'endTime' => $reservationView->EndDate->ToTimezone($timezone)->Format('H:i:s'),
            'isRecurring' => $reservationView->IsRecurring(),
            'repeat' => [
                'type' => $reservationView->RepeatType,
                'interval' => $reservationView->RepeatInterval,
                'weekdays' => array_map('intval', $reservationView->RepeatWeekdays ?? []),
                'monthlyType' => $reservationView->RepeatMonthlyType,
                'terminationDate' => $this->FormatNullableDate($reservationView->RepeatTerminationDate, $timezone),
                'customDates' => array_map(
                    fn ($date) => $date->ToTimezone($timezone)->Format('Y-m-d'),
                    $reservationView->CustomRepeatDates ?? []
                ),
            ],
            'participants' => $this->MapUsers($reservationView->Participants),
            'invitees' => $this->MapUsers($reservationView->Invitees),
            'participatingGuests' => array_values($reservationView->ParticipatingGuests ?? []),
            'invitedGuests' => array_values($reservationView->InvitedGuests ?? []),
            'reminders' => [
                'start' => $this->MapReminder($reservationView->StartReminder),
                'end' => $this->MapReminder($reservationView->EndReminder),
            ],
            'allowParticipation' => (bool)$reservationView->AllowParticipation,
            'attributes' => $this->MapAttributes($reservationView->Attributes),
            'accessories' => $this->MapAccessories($reservationView->Accessories),
            'attachments' => $this->MapAttachments($reservationView->Attachments),
            'ownerId' => $reservationView->OwnerId,
            'ownerName' => trim($reservationView->OwnerFirstName . ' ' . $reservationView->OwnerLastName),
        ];
    }

    /**
     * @param ReservationUserView[] $users
     * @return array
     */
    private function MapUsers($users)
    {
        $mapped = [];
        foreach ($users as $user) {
            $mapped[] = [
                'userId' => $user->UserId,
                'fullName' => $user->FullName,
            ];
        }
        return $mapped;
    }

    /**
     * @param ReservationReminderView|null $reminder
     * @return array
     */
    private function MapReminder($reminder)
    {
        if ($reminder === null) {
            return ['enabled' => false, 'value' => null, 'interval' => null];
        }

        return [
            'enabled' => true,
            'value' => $reminder->GetValue(),
            'interval' => $reminder->GetInterval(),
        ];
    }

    /**
     * @param AttributeValue[] $attributes
     * @return array<string, array{label: string, value: mixed}>
     */
    private function MapAttributes($attributes)
    {
        $mapped = [];
        foreach ($attributes as $attribute) {
            $mapped[(string)$attribute->AttributeId] = [
                'label' => $attribute->AttributeLabel,
                'value' => $attribute->Value,
            ];
        }
        return $mapped;
    }

    /**
     * @param ReservationAccessoryView[] $accessories
     * @return array
     */
    private function MapAccessories($accessories)
    {
        $mapped = [];
        foreach ($accessories as $accessory) {
            $mapped[] = [
                'accessoryId' => $accessory->AccessoryId,
                'name' => $accessory->Name,
                'quantityReserved' => $accessory->QuantityReserved,
                'quantityAvailable' => $accessory->QuantityAvailable,
            ];
        }
        return $mapped;
    }

    /**
     * @param ReservationAttachmentView[] $attachments
     * @return array
     */
    private function MapAttachments($attachments)
    {
        $mapped = [];
        foreach ($attachments as $attachment) {
            $mapped[] = [
                'fileId' => $attachment->FileId(),
                'fileName' => $attachment->FileName(),
            ];
        }
        return $mapped;
    }

    private function FormatNullableDate($date, $timezone)
    {
        if (empty($date) || $date instanceof NullDate) {
            return null;
        }
        return $date->ToTimezone($timezone)->Format('Y-m-d');
    }
}
