<?php

declare(strict_types=1);

require_once(ROOT_DIR . 'Presenters/Reservation/ReservationSavePresenter.php');
require_once(ROOT_DIR . 'Pages/Ajax/ReservationSavePage.php');
require_once(ROOT_DIR . 'lib/Application/Reservation/namespace.php');

class ReservationSavePresenterTest extends TestBase
{
    /**
     * @var UserSession
     */
    private $user;

    /**
     * @var int
     */
    private $userId;

    /**
     * @var FakeReservationSavePage
     */
    private $page;

    /**
     * @var ReservationSavePresenter
     */
    private $presenter;

    private IReservationPersistenceService&\PHPUnit\Framework\MockObject\MockObject $persistenceService;

    private IReservationHandler&\PHPUnit\Framework\MockObject\MockObject $handler;

    private IResourceRepository&\PHPUnit\Framework\MockObject\MockObject $resourceRepository;

    private IAttributeRepository&\PHPUnit\Framework\MockObject\MockObject $attributeRepository;

    /**
     * @var FakeScheduleRepository
     */
    private $scheduleRepository;

    private IIdempotencyService&\PHPUnit\Framework\MockObject\MockObject $idempotencyService;

    public function setUp(): void
    {
        parent::setup();

        $this->user = $this->fakeServer->UserSession;
        $this->userId = $this->user->UserId;

        $this->page = new FakeReservationSavePage();

        $this->persistenceService = $this->createMock('IReservationPersistenceService');
        $this->handler = $this->createMock('IReservationHandler');
        $this->resourceRepository = $this->createMock('IResourceRepository');
        $this->attributeRepository = $this->createMock('IAttributeRepository');
        $this->attributeRepository->method('GetByCategory')->willReturn([]);
        $this->scheduleRepository = new FakeScheduleRepository();
        $this->idempotencyService = $this->createMock('IIdempotencyService');

        $this->presenter = new ReservationSavePresenter(
            $this->page,
            $this->persistenceService,
            $this->handler,
            $this->resourceRepository,
            $this->scheduleRepository,
            $this->fakeUser,
            $this->attributeRepository,
            $this->idempotencyService
        );
    }

    public function teardown(): void
    {
        parent::teardown();
    }

    public function testCreationBuildsReservationFromPageData()
    {
        $this->fakeConfig->SetKey(ConfigKeys::CREDITS_ENABLED, 'true');
        $timezone = $this->user->Timezone;

        $userId = $this->page->GetUserId();
        $resourceId = $this->page->GetResourceId();
        $title = $this->page->GetTitle();
        $description = $this->page->GetDescription();

        $startDate = $this->page->GetStartDate();
        $endDate = $this->page->GetEndDate();
        $startTime = $this->page->GetStartTime();
        $endTime = $this->page->GetEndTime();
        $additionalResources = $this->page->GetResources();
        $pageAccessories = $this->page->GetAccessories();
        $pageAttributes = $this->page->GetAttributes();
        $participatingGuests = ['p1@email.com'];
        $this->page->participatingGuests = $participatingGuests;
        $invitedGuests = ['i1@email.com'];
        $this->page->invitedGuests = $invitedGuests;

        $roFactory = new RepeatOptionsFactory();
        $repeatOptions = $roFactory->CreateFromComposite($this->page, $timezone);

        $participants = $this->page->GetParticipants();
        $invitees = $this->page->GetInvitees();
        $attachment = new FakeUploadedFile();
        $this->page->attachment = $attachment;

        $resource = new FakeBookableResource($resourceId, 'r1');
        $resource->SetCreditsPerSlot(1);
        $resource->SetPeakCreditsPerSlot(1);
        $additionalResource1 = new FakeBookableResource($additionalResources[0], 'r2');
        $additionalResource2 = new FakeBookableResource($additionalResources[1], 'r3');

        $accessories = [];
        foreach ($pageAccessories as $pa) {
            $accessories[] = new ReservationAccessory($pa->Id, $pa->Quantity, $pa->Name);
        }

        $expectedAttributes = [];
        foreach ($pageAttributes as $attr) {
            $expectedAttributes[] = new AttributeValue($attr->Id, $attr->Value);
        }

        $startReminder = new ReservationReminder($this->page->GetStartReminderValue(), $this->page->GetStartReminderInterval());
        $endReminder = new ReservationReminder($this->page->GetEndReminderValue(), $this->page->GetEndReminderInterval());

        $this->resourceRepository->expects($this->exactly(3))
                                 ->method('LoadById')
                                 ->willReturnMap(
                                     [
                                         [$resourceId, $resource],
                                         [$additionalResources[0], $additionalResource1],
                                         [$additionalResources[1], $additionalResource2]
                                     ]
                                 );

        $fakeScheduleLayout = new FakeScheduleLayout();
        $fakeScheduleLayout->_SlotCount = new SlotCount(1, 2);
        $this->scheduleRepository->_Layout = $fakeScheduleLayout;
        $expectedCredits = 168;

        $duration = DateRange::Create($startDate . ' ' . $startTime, $endDate . ' ' . $endTime, $timezone);

        $actualReservation = $this->presenter->BuildReservation();

        $this->assertEquals($userId, $actualReservation->UserId());
        $this->assertEquals($resourceId, $actualReservation->ResourceId());
        $this->assertEquals($title, $actualReservation->Title());
        $this->assertEquals($description, $actualReservation->Description());
        $this->assertEquals($duration, $actualReservation->CurrentInstance()->Duration());
        $this->assertEquals($repeatOptions, $actualReservation->RepeatOptions());
        $this->assertEquals($participants, $actualReservation->CurrentInstance()->AddedParticipants());
        $this->assertEquals($invitees, $actualReservation->CurrentInstance()->AddedInvitees());
        $this->assertEquals($accessories, $actualReservation->Accessories());
        $this->assertTrue(in_array($expectedAttributes[0], $actualReservation->AttributeValues()));
        $expectedAttachment = ReservationAttachment::Create(
            $attachment->OriginalName(),
            $attachment->MimeType(),
            $attachment->Size(),
            $attachment->Contents(),
            $attachment->Extension(),
            0
        );
        $this->assertEquals([$expectedAttachment], $actualReservation->AddedAttachments());
        $this->assertEquals($startReminder, $actualReservation->GetStartReminder());
        $this->assertEquals($endReminder, $actualReservation->GetEndReminder());
        $this->assertEquals($participatingGuests, $actualReservation->CurrentInstance()->AddedParticipatingGuests());
        $this->assertEquals($invitedGuests, $actualReservation->CurrentInstance()->AddedInvitedGuests());
        $this->assertEquals($expectedCredits, $actualReservation->GetCreditsRequired(), '3 credits per slot, 2 day reservation, recurs 28 times');
    }

    public function testParsesDefaultStartReminderConfigInterval()
    {
        $m = new ReflectionMethod(ReservationSavePresenter::class, 'ParseDefaultReminder');
        $m->setAccessible(true);

        $this->assertEquals(['value' => 15, 'interval' => 'minutes'], $m->invoke($this->presenter, '15 minutes'));
        $this->assertEquals(['value' => 2, 'interval' => 'hours'], $m->invoke($this->presenter, '2 hours'));
        $this->assertEquals(['value' => 1, 'interval' => 'days'], $m->invoke($this->presenter, '1 days'));
        // unknown unit falls back to minutes (mirrors NewReservationInitializer::GetReminderPieces)
        $this->assertEquals(['value' => 30, 'interval' => 'minutes'], $m->invoke($this->presenter, '30 weeks'));

        $this->assertNull($m->invoke($this->presenter, ''));
        $this->assertNull($m->invoke($this->presenter, null));
        $this->assertNull($m->invoke($this->presenter, 'nonsense'));
        $this->assertNull($m->invoke($this->presenter, '0 minutes'));
    }

    public function testAppliesDefaultStartReminderWhenRequestCarriesNone()
    {
        // Calendar quick-create sends no reminder fields -- the configured default still applies.
        $this->page->hasStartReminder = false;
        $this->page->hasEndReminder = false;
        $this->fakeConfig->SetKey(ConfigKeys::RESERVATION_REMINDER_DEFAULT_START, '15 minutes');

        $this->resourceRepository->method('LoadById')->willReturn(new FakeBookableResource(1, 'r1'));

        $reservation = $this->presenter->BuildReservation();

        $this->assertTrue($reservation->GetStartReminder()->Enabled());
        $this->assertEquals(15, $reservation->GetStartReminder()->MinutesPrior());
        $this->assertFalse($reservation->GetEndReminder()->Enabled());
    }

    public function testDoesNotApplyDefaultStartReminderWhenNoneConfigured()
    {
        $this->page->hasStartReminder = false;
        $this->page->hasEndReminder = false;
        $this->fakeConfig->SetKey(ConfigKeys::RESERVATION_REMINDER_DEFAULT_START, '');

        $this->resourceRepository->method('LoadById')->willReturn(new FakeBookableResource(1, 'r1'));

        $reservation = $this->presenter->BuildReservation();

        $this->assertFalse($reservation->GetStartReminder()->Enabled());
    }

    public function testHandlingReservationCreationDelegatesToHandler()
    {
        $series = new TestReservationSeries();
        $instance = new Reservation($series, NullDateRange::Instance());
        $series->WithCurrentInstance($instance);

        $this->handler->expects($this->once())
                      ->method('Handle')
                      ->with($this->equalTo($series), $this->isInstanceOf('FakeReservationSavePage'))
                      ->willReturn(true);

        $this->presenter->HandleReservation($series);

        $this->assertEquals($instance->ReferenceNumber(), $this->page->referenceNumber);
        $this->assertEquals($series->RequiresApproval(), $this->page->requiresApproval);
    }

    public function testHandleReservationWithRequestIdClaimsBeforeHandlingAndMarksSucceeded()
    {
        $this->page->requestId = 'req-1';
        $series = new TestReservationSeries();
        $instance = new Reservation($series, NullDateRange::Instance());
        $series->WithCurrentInstance($instance);

        $this->idempotencyService->expects($this->once())
            ->method('TryClaim')
            ->with('req-1', $this->userId)
            ->willReturn(IdempotencyClaim::Claimed());

        $this->handler->expects($this->once())
            ->method('Handle')
            ->willReturn(true);

        $this->idempotencyService->expects($this->once())
            ->method('MarkSucceeded')
            ->with('req-1', $this->userId, $instance->ReferenceNumber());

        $this->presenter->HandleReservation($series);

        $this->assertEquals($instance->ReferenceNumber(), $this->page->referenceNumber);
    }

    public function testHandleReservationWithRequestIdMarksFailedWhenHandlerReturnsFalse()
    {
        $this->page->requestId = 'req-2';
        $series = new TestReservationSeries();
        $instance = new Reservation($series, NullDateRange::Instance());
        $series->WithCurrentInstance($instance);

        $this->idempotencyService->method('TryClaim')->willReturn(IdempotencyClaim::Claimed());
        $this->handler->method('Handle')->willReturn(false);

        $this->idempotencyService->expects($this->once())
            ->method('MarkFailed')
            ->with('req-2', $this->userId);

        $this->presenter->HandleReservation($series);
    }

    public function testHandleReservationAlreadySucceededReplaysStoredReferenceNumberWithoutCallingHandler()
    {
        $this->page->requestId = 'req-3';
        $series = new TestReservationSeries();
        $instance = new Reservation($series, NullDateRange::Instance());
        $series->WithCurrentInstance($instance);

        $this->idempotencyService->method('TryClaim')->willReturn(IdempotencyClaim::AlreadySucceeded('ORIGINAL-REF'));

        $this->handler->expects($this->never())->method('Handle');

        $this->presenter->HandleReservation($series);

        $this->assertEquals('ORIGINAL-REF', $this->page->referenceNumber);
        $this->assertEquals('ORIGINAL-REF', $instance->ReferenceNumber());
        $this->assertTrue($this->page->saveSuccessful);
    }

    public function testHandleReservationInProgressThrowsWithoutCallingHandler()
    {
        $this->page->requestId = 'req-4';
        $series = new TestReservationSeries();
        $instance = new Reservation($series, NullDateRange::Instance());
        $series->WithCurrentInstance($instance);

        $this->idempotencyService->method('TryClaim')->willReturn(IdempotencyClaim::InProgress());

        $this->handler->expects($this->never())->method('Handle');

        $this->expectException(ReservationRequestInProgressException::class);
        $this->presenter->HandleReservation($series);
    }
}
