<?php

declare(strict_types=1);

require_once(ROOT_DIR . 'lib/Email/Messages/ReminderEmail.php');
require_once(ROOT_DIR . 'Domain/ReminderNotice.php');

class ReminderEmailTest extends TestBase
{
    public function testStartReminderIncludesVideoConferenceSectionWhenResourceHasVideoUrl()
    {
        $notice = $this->CreateNotice('https://zoom.us/j/94512345678', '945 1234 5678', 'coasia2026');

        $email = new ReminderStartEmail($notice);
        $body = $email->Body();

        $this->assertStringContainsString('https://zoom.us/j/94512345678', $body);
        $this->assertStringContainsString('945 1234 5678', $body);
        $this->assertStringContainsString('coasia2026', $body);
    }

    public function testStartReminderOmitsVideoConferenceSectionForPhysicalRoom()
    {
        $notice = $this->CreateNotice(null, null, null);

        $email = new ReminderStartEmail($notice);
        $body = $email->Body();

        $this->assertStringNotContainsString('視訊連結', $body);
    }

    public function testEndReminderNeverIncludesVideoConferenceSection()
    {
        $notice = $this->CreateNotice('https://zoom.us/j/94512345678', '945 1234 5678', 'coasia2026');

        $email = new ReminderEndEmail($notice);
        $body = $email->Body();

        $this->assertStringNotContainsString('視訊連結', $body);
    }

    private function CreateNotice($videoUrl, $videoMeetingId, $videoPasscode): ReminderNotice
    {
        $row = new ReminderNoticeRow(
            seriesId: 1,
            reservationId: 1,
            referenceNumber: 'refnum1',
            resourceName: 'Zoom TW',
            videoUrl: $videoUrl,
            videoMeetingId: $videoMeetingId,
            videoPasscode: $videoPasscode
        );

        return ReminderNotice::FromRow($row->Rows()[0]);
    }
}
