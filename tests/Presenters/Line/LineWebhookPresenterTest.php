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
