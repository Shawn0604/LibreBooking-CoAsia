<?php

declare(strict_types=1);

require_once(ROOT_DIR . 'lib/Application/Reservation/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Reservation/Notification/OwnerWeComNotification.php');

class OwnerWeComNotificationTest extends TestBase
{
    /**
     * @var FakeWeComService
     */
    private $wecomService;

    public function setUp(): void
    {
        parent::setup();
        $this->wecomService = new FakeWeComService();
        ServiceLocator::SetWeComService($this->wecomService);
    }

    public function teardown(): void
    {
        ServiceLocator::SetWeComService(null);
        parent::teardown();
    }

    public function testPushesReservationConfirmationWhenOwnerHasWeComIdAndWantsIt()
    {
        $ownerId = 100;
        $resource = new FakeBookableResource(200, 'Taipei Room A');

        $reservation = new TestReservationSeries();
        $reservation->WithOwnerId($ownerId);
        $reservation->WithResource($resource);
        $reservation->WithCurrentInstance(new TestReservation());

        $userRepo = $this->createMock('IUserRepository');
        $attributeRepo = new FakeAttributeRepository();

        $owner = new FakeUser($ownerId);
        $owner->ChangePreference(UserPreferences::WECOM_USER_ID, 'wc-owner-1');
        $userRepo->expects($this->once())->method('LoadById')->with($ownerId)->willReturn($owner);

        $notification = new OwnerWeComCreatedNotification($userRepo, $attributeRepo);
        $notification->Notify($reservation);

        $this->assertCount(1, $this->wecomService->_Pushed);
        $this->assertEquals('wc-owner-1', $this->wecomService->_Pushed[0]['wecomUserId']);
        $this->assertStringContainsString('Taipei Room A', $this->wecomService->_Pushed[0]['text']);
        $this->assertStringContainsString($owner->FullName(), $this->wecomService->_Pushed[0]['text']);
    }

    public function testDoesNotPushWhenOwnerHasNoWeComId()
    {
        $ownerId = 100;
        $resource = new FakeBookableResource(200, 'name');

        $reservation = new TestReservationSeries();
        $reservation->WithOwnerId($ownerId);
        $reservation->WithResource($resource);
        $reservation->WithCurrentInstance(new TestReservation());

        $userRepo = $this->createMock('IUserRepository');
        $owner = new FakeUser($ownerId);
        $userRepo->expects($this->once())->method('LoadById')->with($ownerId)->willReturn($owner);

        $notification = new OwnerWeComCreatedNotification($userRepo, new FakeAttributeRepository());
        $notification->Notify($reservation);

        $this->assertEmpty($this->wecomService->_Pushed);
    }

    public function testDoesNotPushWhenOwnerTurnedWeComNotificationsOff()
    {
        $ownerId = 100;
        $resource = new FakeBookableResource(200, 'name');

        $reservation = new TestReservationSeries();
        $reservation->WithOwnerId($ownerId);
        $reservation->WithResource($resource);
        $reservation->WithCurrentInstance(new TestReservation());

        $userRepo = $this->createMock('IUserRepository');
        $owner = new FakeUser($ownerId);
        $owner->ChangePreference(UserPreferences::WECOM_USER_ID, 'wc-owner-1');
        $owner->ChangeWeComNotificationsPreference(false);
        $userRepo->expects($this->once())->method('LoadById')->with($ownerId)->willReturn($owner);

        $notification = new OwnerWeComCreatedNotification($userRepo, new FakeAttributeRepository());
        $notification->Notify($reservation);

        $this->assertEmpty($this->wecomService->_Pushed);
    }
}
