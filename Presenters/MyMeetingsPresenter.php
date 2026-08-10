<?php

require_once(ROOT_DIR . 'Domain/Access/namespace.php');

class MyMeetingsPresenter
{
    /** How far back/forward to look. Bounded so a long-running recurring series can't blow this up. */
    private const PAST_DAYS = 180;
    private const FUTURE_DAYS = 365;

    /**
     * @var IMyMeetingsPage
     */
    private $page;

    /**
     * @var IReservationViewRepository
     */
    private $reservationViewRepository;

    /**
     * @var IAttributeRepository
     */
    private $attributeRepository;

    public function __construct(
        IMyMeetingsPage $page,
        IReservationViewRepository $reservationViewRepository,
        IAttributeRepository $attributeRepository
    ) {
        $this->page = $page;
        $this->reservationViewRepository = $reservationViewRepository;
        $this->attributeRepository = $attributeRepository;
    }

    public function PageLoad(UserSession $userSession)
    {
        $timezone = $userSession->Timezone;
        $now = Date::Now()->ToTimezone($timezone);

        $reservations = $this->reservationViewRepository->GetReservations(
            $now->AddDays(-self::PAST_DAYS),
            $now->AddDays(self::FUTURE_DAYS),
            $userSession->UserId,
            ReservationUserLevel::ALL,
            null,
            null,
            true
        );

        $upcoming = [];
        $past = [];

        /** @var ReservationItemView $reservation */
        foreach ($reservations as $reservation) {
            $start = $reservation->StartDate->ToTimezone($timezone);
            if ($start->GreaterThanOrEqual($now)) {
                $upcoming[] = $reservation;
            } else {
                $past[] = $reservation;
            }
        }

        usort($upcoming, fn ($a, $b) => $a->StartDate->Compare($b->StartDate));
        usort($past, fn ($a, $b) => $b->StartDate->Compare($a->StartDate));

        // The "next meeting" card just highlights the top of $upcoming -- it still appears as
        // the first row of the grouped list below too, same as any other calendar's "up next"
        // summary sitting above its own full agenda.
        $next = count($upcoming) > 0 ? $upcoming[0] : null;
        $this->page->SetNextMeeting($next == null ? null : $this->BuildNextMeeting($next, $timezone));

        $this->page->SetUpcoming($this->GroupByDay($upcoming, $timezone));
        $this->page->SetPast($this->GroupByDay($past, $timezone));
    }

    /**
     * @param ReservationItemView $reservation
     * @param string $timezone
     * @return array
     */
    private function BuildNextMeeting(ReservationItemView $reservation, $timezone)
    {
        $start = $reservation->StartDate->ToTimezone($timezone);
        $end = $reservation->EndDate->ToTimezone($timezone);

        return [
            'referenceNumber' => $reservation->ReferenceNumber,
            'title' => $reservation->Title,
            'resourceName' => $reservation->ResourceName,
            'date' => $start->GetDate(),
            'startLabel' => $start->Format('H:i'),
            'endLabel' => $end->Format('H:i'),
            // JS Date wants milliseconds -- multiplied in the template, kept as whole seconds
            // here so this stays the same unit every other Timestamp() call in the codebase uses.
            'startTimestamp' => $start->Timestamp(),
            'joinUrl' => $this->GetVideoUrl($reservation->ResourceId),
        ];
    }

    /**
     * Resource-level video_url custom attribute (the same one the reservation confirmation
     * email's "join meeting" section reads -- see ReservationEmailTemplateContext::
     * VideoConferenceResources()), looked up just for the one resource the next meeting is on.
     *
     * @param int $resourceId
     * @return string|null
     */
    private function GetVideoUrl($resourceId)
    {
        if (empty($resourceId)) {
            return null;
        }

        $videoUrlAttributeId = null;
        foreach ($this->attributeRepository->GetByCategory(CustomAttributeCategory::RESOURCE) as $attribute) {
            if ($attribute->Label() == 'video_url') {
                $videoUrlAttributeId = $attribute->Id();
                break;
            }
        }

        if ($videoUrlAttributeId === null) {
            return null;
        }

        foreach ($this->attributeRepository->GetEntityValues(CustomAttributeCategory::RESOURCE, [$resourceId]) as $entityValue) {
            if ($entityValue->AttributeId == $videoUrlAttributeId && !empty($entityValue->Value)) {
                return $entityValue->Value;
            }
        }

        return null;
    }

    /**
     * @param ReservationItemView[] $reservations
     * @param string $timezone
     * @return array
     */
    private function GroupByDay($reservations, $timezone)
    {
        $groups = [];
        $order = [];

        foreach ($reservations as $reservation) {
            $start = $reservation->StartDate->ToTimezone($timezone);
            $key = $start->Format('Y-m-d');

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'date' => $start->GetDate(),
                    'items' => [],
                ];
                $order[] = $key;
            }

            $groups[$key]['items'][] = [
                'referenceNumber' => $reservation->ReferenceNumber,
                'title' => $reservation->Title,
                'resourceName' => $reservation->ResourceName,
                'startLabel' => $start->Format('H:i'),
                'endLabel' => $reservation->EndDate->ToTimezone($timezone)->Format('H:i'),
                'bg' => $reservation->GetColor(),
                'fg' => $reservation->GetTextColor(),
                'requiresApproval' => !empty($reservation->RequiresApproval),
            ];
        }

        $ordered = [];
        foreach ($order as $key) {
            $ordered[] = $groups[$key];
        }

        return $ordered;
    }
}

interface IMyMeetingsPage
{
    /**
     * @param array|null $nextMeeting
     */
    public function SetNextMeeting($nextMeeting);

    /**
     * @param array $days
     */
    public function SetUpcoming($days);

    /**
     * @param array $days
     */
    public function SetPast($days);
}
