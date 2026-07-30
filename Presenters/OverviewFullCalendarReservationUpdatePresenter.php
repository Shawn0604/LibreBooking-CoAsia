<?php

require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Reservation/namespace.php');

/**
 * Deliberately narrow "basic fields only" update path for the reservation Drawer's current
 * scope (title/description/start/end/primary resource/repeat rule — P0-4 added repeat as a
 * fifth deliberately-exposed field). This is NOT the same code path as
 * Presenters/Reservation/ReservationUpdatePresenter.php, and that's the point: the classic
 * presenter rebuilds the ENTIRE reservation from form fields every time — participants,
 * invitees, guests, accessories, attributes, reminders, allow-participation — are all
 * explicitly re-set from whatever the form submitted (Domain/ReservationSeries.php's Change*()
 * methods diff against the submitted value, so an omitted field reads as "set to empty").
 * Routing the Drawer's basic-field edit through that path would mean every field the Drawer
 * doesn't yet expose editing for would need to round-trip through the browser losslessly just
 * to survive a title change — fragile, and a real data-loss risk if the client payload is
 * incomplete, stale, or tampered with.
 *
 * Instead, this loads the existing series (already fully populated by
 * IReservationRepository::LoadByReferenceNumber(), including all the fields above) and calls
 * ONLY the mutators for what's actually being changed (Update() for title/resource/owner-
 * preserving, UpdateDuration() for the time range, Repeats() for the recurrence rule). Every
 * other Change*()/AllowParticipation()/Add|RemoveReminder() method is deliberately never
 * called, so nothing is diffed against the loaded state and the persistence layer applies zero
 * changes to any of it — see ReservationSeries::ChangeParticipants() etc., which only mutate
 * added/removed/unchanged tracking arrays when actually invoked. Repeats() is safe to call
 * unconditionally regardless of $seriesUpdateScope — ExistingReservationSeries::Repeats()
 * internally consults $this->seriesUpdateStrategy->CanChangeRepeatTo(), the same
 * SeriesUpdateScope-aware guard the classic form's ReservationUpdatePresenter relies on
 * (it also calls Repeats() unconditionally), so a "this instance only" edit correctly no-ops
 * the recurrence change instead of needing a separate check here.
 */
class OverviewFullCalendarReservationUpdatePresenter
{
    /**
     * @var IReservationViewRepository
     */
    private $reservationViewRepository;

    /**
     * @var IReservationAuthorization
     */
    private $reservationAuthorization;

    /**
     * @var IUpdateReservationPersistenceService
     */
    private $persistenceService;

    /**
     * @var IResourceRepository
     */
    private $resourceRepository;

    /**
     * @var IReservationHandler
     */
    private $handler;

    public function __construct(
        IReservationViewRepository $reservationViewRepository,
        IReservationAuthorization $reservationAuthorization,
        IUpdateReservationPersistenceService $persistenceService,
        IResourceRepository $resourceRepository,
        IReservationHandler $handler
    ) {
        $this->reservationViewRepository = $reservationViewRepository;
        $this->reservationAuthorization = $reservationAuthorization;
        $this->persistenceService = $persistenceService;
        $this->resourceRepository = $resourceRepository;
        $this->handler = $handler;
    }

    /**
     * @param string $referenceNumber
     * @param string $seriesUpdateScope SeriesUpdateScope::ThisInstance|FutureInstances|FullSeries
     * @param string $title
     * @param string $description
     * @param string $startDate Y-m-d
     * @param string $startTime H:i:s
     * @param string $endDate Y-m-d
     * @param string $endTime H:i:s
     * @param int $resourceId
     * @param int|null $expectedVersion the version the client last saw (from
     *   OverviewFullCalendarReservationPresenter's 'version' field). When provided, Update()
     *   (Domain/Access/ReservationRepository.php) fails with ReservationVersionConflictException
     *   if the database's current version no longer matches — someone else changed this
     *   reservation since the Drawer loaded it. Null falls back to whatever version gets loaded
     *   fresh in this same request (still race-safe, just not stale-edit-safe).
     * @param UserSession $userSession
     * @param IReservationSaveResultsView $view
     * @return bool false if rejected before ever reaching the handler (not found / not
     *   authorized — $view->SetErrors() has already been called and nothing was persisted);
     *   true once handed off to the handler (which may still fail its own validation, or throw
     *   ReservationVersionConflictException — check $view's error state / catch that for those).
     * @param IRepeatOptionsComposite $repeatOptionsComposite the Drawer's repeat-rule fields
     *   (same FormKeys the classic form uses — RepeatOptionsFactory needs no changes).
     */
    public function UpdateBasicFields(
        $referenceNumber,
        $seriesUpdateScope,
        $title,
        $description,
        $startDate,
        $startTime,
        $endDate,
        $endTime,
        $resourceId,
        $expectedVersion,
        IRepeatOptionsComposite $repeatOptionsComposite,
        UserSession $userSession,
        IReservationSaveResultsView $view
    ) {
        $reservationView = $this->reservationViewRepository->GetReservationForEditing($referenceNumber);
        if (!$reservationView->IsDisplayable()) {
            $view->SetSaveSuccessfulMessage(false);
            $view->SetErrors(['Reservation not found.']);
            return false;
        }

        // Re-checked here independently of whatever the client claims — the Drawer's own
        // canEdit flag (from OverviewFullCalendarReservationPresenter) is a UI affordance,
        // never a trust boundary.
        if (!$this->reservationAuthorization->CanEdit($reservationView, $userSession)) {
            $view->SetSaveSuccessfulMessage(false);
            $view->SetErrors(['Not authorized to edit this reservation.']);
            return false;
        }

        if (!SeriesUpdateScope::IsValid($seriesUpdateScope)) {
            $seriesUpdateScope = SeriesUpdateScope::FullSeries;
        }

        $existingSeries = $this->persistenceService->LoadByReferenceNumber($referenceNumber);
        $existingSeries->ApplyChangesTo($seriesUpdateScope);

        if ($expectedVersion !== null && $expectedVersion !== '') {
            $existingSeries->SetExpectedVersion($expectedVersion);
        }

        $resource = $this->resourceRepository->LoadById($resourceId);

        // $existingSeries->UserId() — the reservation's OWN current owner, never the person
        // performing this edit. Update()'s first argument becomes the series' new UserId()
        // and fires OwnerChangedEvent if it differs from the current one
        // (Domain/ExistingReservationSeries.php), so passing the acting user's id here would
        // silently transfer ownership to whoever clicked save.
        $existingSeries->Update(
            $existingSeries->UserId(),
            $resource,
            htmlspecialchars_decode($title),
            htmlspecialchars_decode($description),
            $userSession
        );

        $duration = DateRange::Create($startDate . ' ' . $startTime, $endDate . ' ' . $endTime, $userSession->Timezone);
        $existingSeries->UpdateDuration($duration);

        $roFactory = new RepeatOptionsFactory();
        $existingSeries->Repeats($roFactory->CreateFromComposite($repeatOptionsComposite, $userSession->Timezone));

        $this->handler->Handle($existingSeries, $view);
        return true;
    }
}
