<?php

declare(strict_types=1);

require_once(ROOT_DIR . 'Presenters/OverviewFullCalendarReservationUpdatePresenter.php');

class OverviewFullCalendarReservationUpdatePresenterTest extends TestBase
{
    private const OWNER_ID = 100;
    private const ACTING_ADMIN_ID = 999;

    /**
     * @var FakeReservationViewRepository
     */
    private $reservationViewRepository;

    /**
     * @var FakeReservationAuthorization
     */
    private $reservationAuthorization;

    /**
     * @var FakeReservationRepository
     */
    private $reservationRepository;

    /**
     * @var UpdateReservationPersistenceService
     */
    private $persistenceService;

    /**
     * @var FakeResourceRepository
     */
    private $resourceRepository;

    /**
     * @var FakeReservationHandler
     */
    private $handler;

    /**
     * @var FakeReservationSaveResultsView
     */
    private $view;

    /**
     * @var FakeRepeatOptionsComposite
     */
    private $repeatOptions;

    /**
     * @var OverviewFullCalendarReservationUpdatePresenter
     */
    private $presenter;

    public function setUp(): void
    {
        parent::setUp();

        $this->reservationViewRepository = new FakeReservationViewRepository();
        $this->reservationAuthorization = new FakeReservationAuthorization();
        $this->reservationRepository = new FakeReservationRepository();
        $this->persistenceService = new UpdateReservationPersistenceService($this->reservationRepository);
        $this->resourceRepository = new FakeResourceRepository();
        $this->resourceRepository->_Resource = new FakeBookableResource(8, 'Zoom TW');
        $this->handler = new FakeReservationHandler();
        $this->handler->_Success = true;
        $this->view = new FakeReservationSaveResultsView();
        $this->repeatOptions = new FakeRepeatOptionsComposite();

        $this->presenter = new OverviewFullCalendarReservationUpdatePresenter(
            $this->reservationViewRepository,
            $this->reservationAuthorization,
            $this->persistenceService,
            $this->resourceRepository,
            $this->handler
        );
    }

    private function UserSession($userId, $isAdmin = false)
    {
        $session = new UserSession($userId);
        $session->Timezone = 'Asia/Taipei';
        $session->IsAdmin = $isAdmin;
        return $session;
    }

    /**
     * @return ExistingReservationSeries with a real owner, participants, and a start
     *   reminder already loaded — mirroring what LoadByReferenceNumber() returns for a real
     *   reservation with data in those fields (this deployment genuinely has some).
     */
    private function ExistingSeriesWithRealData()
    {
        $builder = new ExistingReservationSeriesBuilder();
        $series = $builder->Build();
        $series->WithOwner(self::OWNER_ID);
        $series->CurrentInstance()->WithParticipants([5, 6]);
        $series->AddStartReminder(new ReservationReminder(15, ReservationReminderInterval::Minutes));
        return $series;
    }

    public function testUpdateBasicFieldsPreservesOriginalOwnerNotActingUser()
    {
        $this->reservationViewRepository->_ReservationView = new ReservationView();
        $this->reservationAuthorization->_CanEdit = true;
        $this->reservationRepository->_Series = $this->ExistingSeriesWithRealData();

        $this->presenter->UpdateBasicFields(
            'rn1',
            SeriesUpdateScope::FullSeries,
            'New Title',
            'New Description',
            '2026-07-29',
            '09:00:00',
            '2026-07-29',
            '10:00:00',
            8,
            null,
            $this->repeatOptions,
            $this->UserSession(self::ACTING_ADMIN_ID, true),
            $this->view
        );

        $this->assertNotNull($this->handler->_LastSeries);
        $this->assertEquals(self::OWNER_ID, $this->handler->_LastSeries->UserId());
        $this->assertNotEquals(self::ACTING_ADMIN_ID, $this->handler->_LastSeries->UserId());
    }

    public function testUpdateBasicFieldsAppliesTitleDescriptionResourceAndDuration()
    {
        $this->reservationViewRepository->_ReservationView = new ReservationView();
        $this->reservationAuthorization->_CanEdit = true;
        $this->reservationRepository->_Series = $this->ExistingSeriesWithRealData();

        $this->presenter->UpdateBasicFields(
            'rn1',
            SeriesUpdateScope::FullSeries,
            'New Title',
            'New Description',
            '2026-07-29',
            '09:15:00',
            '2026-07-30',
            '10:45:00',
            8,
            null,
            $this->repeatOptions,
            $this->UserSession(self::OWNER_ID),
            $this->view
        );

        $series = $this->handler->_LastSeries;
        $this->assertEquals('New Title', $series->Title());
        $this->assertEquals('New Description', $series->Description());
        $this->assertEquals(8, $series->ResourceId());
        // Cross-day, non-30-minute-aligned range must survive untouched.
        $duration = $series->CurrentInstance()->Duration();
        $this->assertEquals('2026-07-29 09:15:00', $duration->GetBegin()->ToTimezone('Asia/Taipei')->Format('Y-m-d H:i:s'));
        $this->assertEquals('2026-07-30 10:45:00', $duration->GetEnd()->ToTimezone('Asia/Taipei')->Format('Y-m-d H:i:s'));
    }

    public function testUpdateBasicFieldsDoesNotClearParticipants()
    {
        $this->reservationViewRepository->_ReservationView = new ReservationView();
        $this->reservationAuthorization->_CanEdit = true;
        $this->reservationRepository->_Series = $this->ExistingSeriesWithRealData();

        $this->presenter->UpdateBasicFields(
            'rn1',
            SeriesUpdateScope::FullSeries,
            'Only title changed',
            'description',
            '2026-07-29',
            '09:00:00',
            '2026-07-29',
            '10:00:00',
            8,
            null,
            $this->repeatOptions,
            $this->UserSession(self::OWNER_ID),
            $this->view
        );

        $this->assertEquals([5, 6], $this->handler->_LastSeries->CurrentInstance()->Participants());
    }

    public function testUpdateBasicFieldsDoesNotClearReminders()
    {
        $this->reservationViewRepository->_ReservationView = new ReservationView();
        $this->reservationAuthorization->_CanEdit = true;
        $this->reservationRepository->_Series = $this->ExistingSeriesWithRealData();

        $this->presenter->UpdateBasicFields(
            'rn1',
            SeriesUpdateScope::FullSeries,
            'title',
            'description',
            '2026-07-29',
            '09:00:00',
            '2026-07-29',
            '10:00:00',
            8,
            null,
            $this->repeatOptions,
            $this->UserSession(self::OWNER_ID),
            $this->view
        );

        $reminder = $this->handler->_LastSeries->GetStartReminder();
        $this->assertNotNull($reminder);
        $this->assertEquals(15, $reminder->MinutesPrior());
    }

    public function testUpdateBasicFieldsAppliesClientSuppliedExpectedVersion()
    {
        $this->reservationViewRepository->_ReservationView = new ReservationView();
        $this->reservationAuthorization->_CanEdit = true;
        $this->reservationRepository->_Series = $this->ExistingSeriesWithRealData();

        $this->presenter->UpdateBasicFields(
            'rn1',
            SeriesUpdateScope::FullSeries,
            'title',
            'description',
            '2026-07-29',
            '09:00:00',
            '2026-07-29',
            '10:00:00',
            8,
            3,
            $this->repeatOptions,
            $this->UserSession(self::OWNER_ID),
            $this->view
        );

        // Overrides whatever version the series happened to load with — this is what lets
        // ReservationRepository::Update()'s WHERE version=? clause detect a change made by
        // someone else since the Drawer loaded this reservation, not just a change made by a
        // different request racing in between THIS request's own load and persist.
        $this->assertEquals(3, $this->handler->_LastSeries->ExpectedVersion());
    }

    public function testUpdateBasicFieldsWithoutExpectedVersionFallsBackToLoadedVersion()
    {
        $this->reservationViewRepository->_ReservationView = new ReservationView();
        $this->reservationAuthorization->_CanEdit = true;
        $series = $this->ExistingSeriesWithRealData();
        $series->WithVersion(5);
        $this->reservationRepository->_Series = $series;

        $this->presenter->UpdateBasicFields(
            'rn1',
            SeriesUpdateScope::FullSeries,
            'title',
            'description',
            '2026-07-29',
            '09:00:00',
            '2026-07-29',
            '10:00:00',
            8,
            null,
            $this->repeatOptions,
            $this->UserSession(self::OWNER_ID),
            $this->view
        );

        $this->assertEquals(5, $this->handler->_LastSeries->ExpectedVersion());
    }

    public function testUpdateBasicFieldsAppliesSubmittedRepeatRule()
    {
        $this->reservationViewRepository->_ReservationView = new ReservationView();
        $this->reservationAuthorization->_CanEdit = true;
        $this->reservationRepository->_Series = $this->ExistingSeriesWithRealData();

        $this->repeatOptions->_RepeatType = RepeatType::Weekly;
        $this->repeatOptions->_RepeatInterval = 2;
        $this->repeatOptions->_RepeatWeekdays = [1, 3];
        $this->repeatOptions->_RepeatTerminationDate = '2026-09-01';

        $this->presenter->UpdateBasicFields(
            'rn1',
            SeriesUpdateScope::FullSeries,
            'title',
            'description',
            '2026-07-29',
            '09:00:00',
            '2026-07-29',
            '10:00:00',
            8,
            null,
            $this->repeatOptions,
            $this->UserSession(self::OWNER_ID),
            $this->view
        );

        $repeatOptions = $this->handler->_LastSeries->RepeatOptions();
        $this->assertEquals(RepeatType::Weekly, $repeatOptions->RepeatType());
    }

    public function testUpdateBasicFieldsWithNoRepeatSubmittedAppliesRepeatNone()
    {
        $this->reservationViewRepository->_ReservationView = new ReservationView();
        $this->reservationAuthorization->_CanEdit = true;
        $this->reservationRepository->_Series = $this->ExistingSeriesWithRealData();

        // FakeRepeatOptionsComposite defaults to RepeatType::None — the Drawer always submits
        // its current repeat state (same principle as title/date/resource), so "not recurring"
        // is a deliberate, explicit submission, not an omitted field.
        $this->presenter->UpdateBasicFields(
            'rn1',
            SeriesUpdateScope::FullSeries,
            'title',
            'description',
            '2026-07-29',
            '09:00:00',
            '2026-07-29',
            '10:00:00',
            8,
            null,
            $this->repeatOptions,
            $this->UserSession(self::OWNER_ID),
            $this->view
        );

        $this->assertEquals(RepeatType::None, $this->handler->_LastSeries->RepeatOptions()->RepeatType());
    }

    public function testUnauthorizedUserCannotUpdate()
    {
        $this->reservationViewRepository->_ReservationView = new ReservationView();
        $this->reservationAuthorization->_CanEdit = false;
        $this->reservationRepository->_Series = $this->ExistingSeriesWithRealData();

        $result = $this->presenter->UpdateBasicFields(
            'rn1',
            SeriesUpdateScope::FullSeries,
            'Hacked Title',
            'description',
            '2026-07-29',
            '09:00:00',
            '2026-07-29',
            '10:00:00',
            8,
            null,
            $this->repeatOptions,
            $this->UserSession(self::ACTING_ADMIN_ID),
            $this->view
        );

        $this->assertFalse($result);
        $this->assertNull($this->handler->_LastSeries);
        $this->assertFalse($this->view->_SaveSuccessful);
    }

    public function testReservationNotFoundIsRejectedBeforeHandler()
    {
        $this->reservationViewRepository->_ReservationView = new NullReservationView();

        $result = $this->presenter->UpdateBasicFields(
            'missing',
            SeriesUpdateScope::FullSeries,
            'title',
            'description',
            '2026-07-29',
            '09:00:00',
            '2026-07-29',
            '10:00:00',
            8,
            null,
            $this->repeatOptions,
            $this->UserSession(self::OWNER_ID),
            $this->view
        );

        $this->assertFalse($result);
        $this->assertNull($this->handler->_LastSeries);
    }
}
