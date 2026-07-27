<?php

declare(strict_types=1);

require_once(ROOT_DIR . 'Presenters/Line/LineWebhookPresenter.php');

class LineWebhookPresenterTest extends TestBase
{
    /**
     * @var FakeUserRepository
     */
    private $userRepository;

    /**
     * @var FakeLineService
     */
    private $lineService;

    /**
     * @var LineWebhookPresenter
     */
    private $presenter;

    public function setUp(): void
    {
        parent::setUp();

        $this->userRepository = new FakeUserRepository();
        $this->userRepository->_UserByPreference = null;
        $this->lineService = new FakeLineService();
        $this->presenter = new LineWebhookPresenter($this->userRepository, $this->lineService);
    }

    private function TextMessageEvent($text, $lineUserId = 'U1234', $replyToken = 'reply-token-1')
    {
        return [
            'type' => 'message',
            'replyToken' => $replyToken,
            'source' => ['userId' => $lineUserId],
            'message' => ['type' => 'text', 'text' => $text],
        ];
    }

    public function testLinksUserWhenCodeMatchesAndReplies()
    {
        $user = new FakeUser(42);
        $this->userRepository->_UserByPreference = $user;
        $this->userRepository->_UserById[42] = $user;

        $this->presenter->HandleEvents([$this->TextMessageEvent('123456', 'Uabc')]);

        $this->assertEquals('Uabc', $user->GetPreferences()->Get(UserPreferences::LINE_USER_ID));
        $this->assertSame($user, $this->userRepository->_UpdatedUser);

        $this->assertCount(1, $this->lineService->_Replied);
        $this->assertEquals('reply-token-1', $this->lineService->_Replied[0]['replyToken']);
        $this->assertEquals('LineLinkSuccess', $this->lineService->_Replied[0]['text']);
    }

    public function testRelinkingSameLineIdentityUnlinksThePreviousAccount()
    {
        // Same physical LINE identity ('Uabc') was already bound to user 7 (e.g. testing a
        // different account earlier); now user 42's link code arrives from that same
        // identity. User 7 should be unlinked so it doesn't keep a stale, silently-broken
        // binding, and user 42 should end up linked.
        $newOwner = new FakeUser(42);
        $previousOwner = new FakeUser(7);
        $previousOwner->ChangePreference(UserPreferences::LINE_USER_ID, 'Uabc');

        $this->userRepository->_UserByPreferenceMap['LineLinkCode:123456'] = $newOwner;
        $this->userRepository->_UserByPreferenceMap['LineUserId:Uabc'] = $previousOwner;
        $this->userRepository->_UserById[42] = $newOwner;

        $this->presenter->HandleEvents([$this->TextMessageEvent('123456', 'Uabc')]);

        $this->assertEquals('', $previousOwner->GetPreferences()->Get(UserPreferences::LINE_USER_ID));
        $this->assertEquals('Uabc', $newOwner->GetPreferences()->Get(UserPreferences::LINE_USER_ID));
        $this->assertContains($previousOwner, $this->userRepository->_UpdatedUsers);
        $this->assertContains($newOwner, $this->userRepository->_UpdatedUsers);

        $this->assertCount(1, $this->lineService->_Replied);
        $this->assertEquals('LineLinkSuccess', $this->lineService->_Replied[0]['text']);
    }

    public function testRelinkingSameAccountDoesNotTriggerUnlink()
    {
        // If the "previous owner" found for this LineUserId turns out to be the SAME user
        // that's linking now (re-sending their own code), nothing extra should happen.
        $user = new FakeUser(42);
        $this->userRepository->_UserByPreferenceMap['LineLinkCode:123456'] = $user;
        $this->userRepository->_UserByPreferenceMap['LineUserId:Uabc'] = $user;
        $this->userRepository->_UserById[42] = $user;

        $this->presenter->HandleEvents([$this->TextMessageEvent('123456', 'Uabc')]);

        $this->assertEquals('Uabc', $user->GetPreferences()->Get(UserPreferences::LINE_USER_ID));
        $this->assertCount(1, $this->userRepository->_UpdatedUsers);
    }

    public function testCodeNotFoundRepliesWithError()
    {
        $this->userRepository->_UserByPreference = null;

        $this->presenter->HandleEvents([$this->TextMessageEvent('000000')]);

        $this->assertNull($this->userRepository->_UpdatedUser);
        $this->assertCount(1, $this->lineService->_Replied);
        $this->assertEquals('LineLinkCodeNotFound', $this->lineService->_Replied[0]['text']);
    }

    public function testIgnoresNonMessageEvents()
    {
        $this->presenter->HandleEvents([['type' => 'follow', 'replyToken' => 'x', 'source' => ['userId' => 'U1']]]);

        $this->assertEmpty($this->lineService->_Replied);
        $this->assertNull($this->userRepository->_UpdatedUser);
    }

    public function testIgnoresNonTextMessages()
    {
        $event = [
            'type' => 'message',
            'replyToken' => 'x',
            'source' => ['userId' => 'U1'],
            'message' => ['type' => 'sticker'],
        ];

        $this->presenter->HandleEvents([$event]);

        $this->assertEmpty($this->lineService->_Replied);
    }

    public function testIgnoresEmptyText()
    {
        $this->presenter->HandleEvents([$this->TextMessageEvent('   ')]);

        $this->assertEmpty($this->lineService->_Replied);
    }

    public function testHandlesEmptyEventsArray()
    {
        $this->presenter->HandleEvents([]);

        $this->assertEmpty($this->lineService->_Replied);
        $this->assertEmpty($this->lineService->_Pushed);
    }
}
