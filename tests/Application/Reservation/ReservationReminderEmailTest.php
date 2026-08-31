<?php

declare(strict_types=1);

require_once(ROOT_DIR . 'lib/Application/Reservation/namespace.php');
require_once(ROOT_DIR . 'lib/Email/Messages/ReservationReminderEmail.php');

/**
 * The "會議開始前提醒" e-mail — renders through the shared reservation-notification-body.tpl
 * ($Status = 'reminder'), so it carries the same fields as the created/updated e-mails and
 * the LINE reminder.
 */
class ReservationReminderEmailTest extends TestBase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->fakeConfig->SetKey(ConfigKeys::SCRIPT_URL, 'http://localhost/booking');
    }

    public function tearDown(): void
    {
        parent::tearDown();
    }

    public function testStartReminderBodyShowsStatusTitleVideoJoinAndDetailLink()
    {
        $email = $this->reminderEmail(ReservationReminderType::Start, true, withVideo: true);
        $body = $email->Body();

        $this->assertStringContainsString(Resources::GetInstance()->GetString('ReminderEmailStatusStarting'), $body);
        $this->assertStringContainsString('Weekly sync', $body);
        $this->assertStringContainsString('https://zoom.us/j/123', $body); // join button href
        $this->assertStringContainsString('加入視訊會議', $body);
        $this->assertStringContainsString('查看預約詳情', $body);
    }

    public function testEndReminderBodyOmitsTheVideoJoinButton()
    {
        $email = $this->reminderEmail(ReservationReminderType::End, true, withVideo: true);
        $body = $email->Body();

        $this->assertStringContainsString(Resources::GetInstance()->GetString('ReminderEmailStatusEnding'), $body);
        $this->assertStringNotContainsString('加入視訊會議', $body);
    }

    public function testPhysicalRoomReminderShowsRoomNotVideoSection()
    {
        $email = $this->reminderEmail(ReservationReminderType::Start, true, withVideo: false);
        $body = $email->Body();

        $this->assertStringContainsString('Room A', $body);
        $this->assertStringNotContainsString('加入視訊會議', $body);
    }

    public function testRecipientOwnerFlagFollowsTheConstructorArgument()
    {
        $ownerCopy = $this->reminderEmail(ReservationReminderType::Start, true, withVideo: true);
        $attendeeCopy = $this->reminderEmail(ReservationReminderType::Start, false, withVideo: true);

        $this->assertTrue($ownerCopy->RecipientIsOwner());
        $this->assertFalse($attendeeCopy->RecipientIsOwner());
    }

    public function testSubjectUsesStartOrEndKey()
    {
        $this->assertStringContainsString(
            'Room A',
            $this->reminderEmail(ReservationReminderType::Start, true, withVideo: false)->Subject()
        );
    }

    // ---- helper -----------------------------------------------------------

    private function reminderEmail(int $type, bool $isOwner, bool $withVideo): ReservationReminderEmail
    {
        $owner = new FakeUser(10, 'owner@co.com');
        $owner->_WantsEmail = true;

        $resource = new FakeBookableResource(100, $withVideo ? 'Zoom TW' : 'Room A');
        $attributeRepo = new FakeAttributeRepository();
        if ($withVideo) {
            $resource->WithAttribute(new AttributeValue(31, 'https://zoom.us/j/123'));
            $resource->WithAttribute(new AttributeValue(32, '123 456 7890'));
            $resource->WithAttribute(new AttributeValue(33, 'pass123'));
            $attributeRepo->_CustomAttributes = [
                new CustomAttribute(31, 'video_url', CustomAttributeTypes::SINGLE_LINE_TEXTBOX, CustomAttributeCategory::RESOURCE, null, false, null, 1, [], false),
                new CustomAttribute(32, 'video_meeting_id', CustomAttributeTypes::SINGLE_LINE_TEXTBOX, CustomAttributeCategory::RESOURCE, null, false, null, 1, [], false),
                new CustomAttribute(33, 'video_passcode', CustomAttributeTypes::SINGLE_LINE_TEXTBOX, CustomAttributeCategory::RESOURCE, null, false, null, 1, [], false),
            ];
        } else {
            $attributeRepo->_CustomAttributes = [];
        }

        $series = new TestReservationSeries();
        $series->WithOwnerId(10);
        $series->WithResource($resource);
        $series->WithTitle('Weekly sync');
        $series->WithDescription('Recurring team check-in');
        $series->WithCurrentInstance(new TestReservation());

        $recipient = $isOwner
            ? new EmailAddress('owner@co.com', 'Owner')
            : new EmailAddress('attendee@co.com', 'Attendee');

        return new ReservationReminderEmail(
            $owner,
            $recipient,
            $isOwner,
            $type,
            15,
            $series,
            $attributeRepo,
            new FakeUserRepository()
        );
    }
}
