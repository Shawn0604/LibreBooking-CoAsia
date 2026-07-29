<?php

declare(strict_types=1);

class ReservationHandlerTest extends TestBase
{
    public function testHandlingReservationCreationDelegatesToServicesForValidationAndPersistenceAndNotification()
    {
        $persistenceService = $this->createMock('IReservationPersistenceService');
        $validationService = $this->createMock('IReservationValidationService');
        $notificationService = $this->createMock('IReservationNotificationService');
        $page = $this->createMock('IReservationSaveResultsView');
        $retryOptions = new FakeReservationRetryOptions();

        $builder = new ExistingReservationSeriesBuilder();
        $series = $builder->Build();
        $instance = new Reservation($series, NullDateRange::Instance());
        $series->WithCurrentInstance($instance);

        $validationResult = new ReservationValidationResult();

        $validationService->expects($this->once())
                          ->method('Validate')
                          ->with($this->equalTo($series))
                          ->willReturn($validationResult);

        $persistenceService->expects($this->once())
                           ->method('Persist')
                           ->with($this->equalTo($series));

        $notificationService->expects($this->once())
                            ->method('Notify')
                            ->with($this->equalTo($series));

        $page->expects($this->once())
             ->method('SetSaveSuccessfulMessage')
             ->with($this->equalTo(true));

        $page->expects($this->once())
             ->method('SetWarnings')
             ->with($this->equalTo($validationResult->GetWarnings()));


        $handler = new ReservationHandler($persistenceService, $validationService, $notificationService, $retryOptions, new FakeResourceLockService());
        $handler->Handle($series, $page);

        $this->assertTrue($retryOptions->_AdjustReservationCalled);
    }

    public function testPreventsPersistenceAndNotificationAndShowsFailedMessageWhenValidationFails()
    {
        $this->fakeConfig->SetKey(ConfigKeys::RESERVATION_ALLOW_WAITLIST, true);
        $persistenceService = $this->createMock('IReservationPersistenceService');
        $validationService = $this->createMock('IReservationValidationService');
        $notificationService = $this->createMock('IReservationNotificationService');
        $page = $this->createMock('IReservationSaveResultsView');

        $errorMessage1 = 'e1';
        $errorMessage2 = 'e2';
        $errors = [$errorMessage1, $errorMessage2];

        $builder = new ExistingReservationSeriesBuilder();
        $series = $builder->Build();

        $validationResult = new ReservationValidationResult(false, $errors, null, false, [], [], true);

        $validationService->expects($this->once())
                          ->method('Validate')
                          ->with($this->equalTo($series))
                          ->willReturn($validationResult);

        $persistenceService->expects($this->never())
                           ->method('Persist');

        $notificationService->expects($this->never())
                            ->method('Notify');

        $page->expects($this->once())
             ->method('SetSaveSuccessfulMessage')
             ->with($this->equalTo(false));

        $page->expects($this->once())
             ->method('SetErrors')
             ->with($this->equalTo($errors));

        $page->expects($this->once())
             ->method('SetCanJoinWaitList')
             ->with($this->equalTo(true));

        $handler = new ReservationHandler($persistenceService, $validationService, $notificationService, new FakeReservationRetryOptions(), new FakeResourceLockService());
        $handler->Handle($series, $page);
    }

    public function testPreventsPersistenceAndNotificationAndShowsRetryMessageIfCanBeRetried()
    {
        $persistenceService = $this->createMock('IReservationPersistenceService');
        $validationService = $this->createMock('IReservationValidationService');
        $notificationService = $this->createMock('IReservationNotificationService');
        $page = new FakeReservationSavePage();
        $page->retryParameters = [];

        $errorMessage1 = 'e1';
        $errorMessage2 = 'e2';
        $errors = [$errorMessage1, $errorMessage2];

        $retryMessages = ['m1', 'm2'];

        $retryParams = [new ReservationRetryParameter('name', 'value')];

        $builder = new ExistingReservationSeriesBuilder();
        $series = $builder->Build();

        $validationResult = new ReservationValidationResult(false, $errors, null, true, $retryParams, $retryMessages);

        $validationService->expects($this->once())
                          ->method('Validate')
                          ->with($this->equalTo($series), $this->equalTo($page->retryParameters))
                          ->willReturn($validationResult);

        $persistenceService->expects($this->never())
                           ->method('Persist');

        $notificationService->expects($this->never())
                            ->method('Notify');

        $handler = new ReservationHandler($persistenceService, $validationService, $notificationService, new FakeReservationRetryOptions(), new FakeResourceLockService());
        $handler->Handle($series, $page);

        $this->assertFalse($page->saveSuccessful);
        $this->assertEquals($errors, $page->errors);
        $this->assertTrue($page->canBeRetried);
        $this->assertEquals($retryParams, $page->retryParameters);
        $this->assertEquals($retryMessages, $page->retryMessages);
    }

    public function testCanJoinWaitListIfTurnedOnAndNotRecurringSeriesAndOnlyErrorIsAvailability()
    {
        $persistenceService = $this->createMock('IReservationPersistenceService');
        $validationService = $this->createMock('IReservationValidationService');
        $notificationService = $this->createMock('IReservationNotificationService');
        $page = $this->createMock('IReservationSaveResultsView');

        $errorMessage1 = 'e1';
        $errorMessage2 = 'e2';
        $errors = [$errorMessage1, $errorMessage2];

        $builder = new ExistingReservationSeriesBuilder();
        $series = $builder->Build();

        $validationResult = new ReservationValidationResult(false, $errors, null, false, [], [], true);

        $validationService->expects($this->once())
            ->method('Validate')
            ->with($this->equalTo($series))
            ->willReturn($validationResult);

        $persistenceService->expects($this->never())
            ->method('Persist');

        $notificationService->expects($this->never())
            ->method('Notify');

        $page->expects($this->once())
            ->method('SetSaveSuccessfulMessage')
            ->with($this->equalTo(false));

        $page->expects($this->once())
            ->method('SetErrors')
            ->with($this->equalTo($errors));

        $handler = new ReservationHandler($persistenceService, $validationService, $notificationService, new FakeReservationRetryOptions(), new FakeResourceLockService());
        $handler->Handle($series, $page);
    }

    // ---- P0 concurrency protection: lock -> re-validate -> persist -> commit -> notify.
    // See lib/Application/Reservation/Concurrency/ResourceLockService.php's doc comment for
    // why the lock has to wrap re-validation, not just persistence. ----

    public function testLocksAllResourcesBeforeValidating()
    {
        $persistenceService = $this->createMock('IReservationPersistenceService');
        $validationService = $this->createMock('IReservationValidationService');
        $notificationService = $this->createMock('IReservationNotificationService');
        $page = $this->createMock('IReservationSaveResultsView');
        $lockService = new FakeResourceLockService();

        $builder = new ExistingReservationSeriesBuilder();
        $series = $builder->WithPrimaryResource(new FakeBookableResource(5))->Build();

        $validationService->method('Validate')->willReturn(new ReservationValidationResult());

        $handler = new ReservationHandler($persistenceService, $validationService, $notificationService, new FakeReservationRetryOptions(), $lockService);
        $handler->Handle($series, $page);

        // ExistingReservationSeriesBuilder's constructor unconditionally adds resource id 3
        // as an additional resource (WithResource(new FakeBookableResource(3))) — so the
        // primary (5) plus that fixed additional resource (3) are both expected, sorted.
        $this->assertEquals([3, 5], $lockService->_LockedResourceIds);
    }

    public function testLockedResourceIdsIncludeResourcesRemovedByAnUpdate()
    {
        $persistenceService = $this->createMock('IReservationPersistenceService');
        $validationService = $this->createMock('IReservationValidationService');
        $notificationService = $this->createMock('IReservationNotificationService');
        $page = $this->createMock('IReservationSaveResultsView');
        $lockService = new FakeResourceLockService();

        $builder = new ExistingReservationSeriesBuilder();
        $series = $builder->WithPrimaryResource(new FakeBookableResource(5))->Build();
        // Simulate what ExistingReservationSeries::Update() does when the resource changes —
        // it fires a ResourceRemovedEvent for the resource being moved away from.
        $series->AddEvent(new ResourceRemovedEvent(new FakeBookableResource(9), $series));

        $validationService->method('Validate')->willReturn(new ReservationValidationResult());

        $handler = new ReservationHandler($persistenceService, $validationService, $notificationService, new FakeReservationRetryOptions(), $lockService);
        $handler->Handle($series, $page);

        // Both the new (5, plus the builder's fixed additional resource 3) and the vacated
        // (9) resource must be locked — freeing 9 can unblock someone else's conflicting
        // request just as much as taking 5 does.
        $this->assertEquals([3, 5, 9], $lockService->_LockedResourceIds);
    }

    public function testCommitsLockOnSuccessfulSave()
    {
        $persistenceService = $this->createMock('IReservationPersistenceService');
        $validationService = $this->createMock('IReservationValidationService');
        $notificationService = $this->createMock('IReservationNotificationService');
        $page = $this->createMock('IReservationSaveResultsView');
        $lockService = new FakeResourceLockService();

        $builder = new ExistingReservationSeriesBuilder();
        $series = $builder->Build();

        $validationService->method('Validate')->willReturn(new ReservationValidationResult());

        $handler = new ReservationHandler($persistenceService, $validationService, $notificationService, new FakeReservationRetryOptions(), $lockService);
        $handler->Handle($series, $page);

        $this->assertTrue($lockService->_CommitCalled);
        $this->assertFalse($lockService->_RollbackCalled);
    }

    public function testRollsBackLockWhenValidationFails()
    {
        $persistenceService = $this->createMock('IReservationPersistenceService');
        $validationService = $this->createMock('IReservationValidationService');
        $notificationService = $this->createMock('IReservationNotificationService');
        $page = $this->createMock('IReservationSaveResultsView');
        $lockService = new FakeResourceLockService();

        $builder = new ExistingReservationSeriesBuilder();
        $series = $builder->Build();

        $validationService->method('Validate')->willReturn(new ReservationValidationResult(false, ['conflict'], null, false, [], [], false));
        $persistenceService->expects($this->never())->method('Persist');
        $notificationService->expects($this->never())->method('Notify');

        $handler = new ReservationHandler($persistenceService, $validationService, $notificationService, new FakeReservationRetryOptions(), $lockService);
        $handler->Handle($series, $page);

        // A rejected reservation still needs its transaction ended (lock released) — just
        // via rollback, not commit, since nothing was written.
        $this->assertTrue($lockService->_RollbackCalled);
        $this->assertFalse($lockService->_CommitCalled);
    }

    public function testRollsBackLockAndRethrowsWhenPersistThrows()
    {
        $persistenceService = $this->createMock('IReservationPersistenceService');
        $validationService = $this->createMock('IReservationValidationService');
        $notificationService = $this->createMock('IReservationNotificationService');
        $page = $this->createMock('IReservationSaveResultsView');
        $lockService = new FakeResourceLockService();

        $builder = new ExistingReservationSeriesBuilder();
        $series = $builder->Build();

        $validationService->method('Validate')->willReturn(new ReservationValidationResult());
        $persistenceService->method('Persist')->willThrowException(new Exception('db exploded'));
        $notificationService->expects($this->never())->method('Notify');

        $handler = new ReservationHandler($persistenceService, $validationService, $notificationService, new FakeReservationRetryOptions(), $lockService);

        $threw = false;
        try {
            $handler->Handle($series, $page);
        } catch (Exception $ex) {
            $threw = true;
        }

        $this->assertTrue($threw, 'Persist() failures must still propagate to the caller');
        $this->assertTrue($lockService->_RollbackCalled);
        $this->assertFalse($lockService->_CommitCalled);
    }

    public function testNotifiesOnlyAfterCommit()
    {
        $persistenceService = $this->createMock('IReservationPersistenceService');
        $validationService = $this->createMock('IReservationValidationService');
        $page = $this->createMock('IReservationSaveResultsView');

        $callOrder = [];
        $lockService = new class ($callOrder) extends FakeResourceLockService {
            private $log;
            public function __construct(&$log)
            {
                $this->log = &$log;
            }
            public function Commit()
            {
                parent::Commit();
                $this->log[] = 'commit';
            }
        };

        $notificationService = $this->getMockBuilder('IReservationNotificationService')
            ->onlyMethods(['Notify'])
            ->getMock();
        $notificationService->method('Notify')->willReturnCallback(function () use (&$callOrder) {
            $callOrder[] = 'notify';
        });

        $builder = new ExistingReservationSeriesBuilder();
        $series = $builder->Build();
        $validationService->method('Validate')->willReturn(new ReservationValidationResult());

        $handler = new ReservationHandler($persistenceService, $validationService, $notificationService, new FakeReservationRetryOptions(), $lockService);
        $handler->Handle($series, $page);

        $this->assertEquals(['commit', 'notify'], $callOrder);
    }
}

class FakeReservationRetryOptions implements IReservationRetryOptions
{
    /**
     * @var bool
     */
    public $_AdjustReservationCalled = false;

    public function AdjustReservation(ReservationSeries $series, $retryParameters)
    {
        $this->_AdjustReservationCalled = true;
    }
}
