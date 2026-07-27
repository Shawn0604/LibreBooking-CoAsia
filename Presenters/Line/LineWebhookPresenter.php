<?php

require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'lib/Line/namespace.php');

class LineWebhookPresenter
{
    /**
     * @var IUserRepository
     */
    private $userRepository;

    /**
     * @var ILineService
     */
    private $lineService;

    public function __construct(IUserRepository $userRepository, ILineService $lineService)
    {
        $this->userRepository = $userRepository;
        $this->lineService = $lineService;
    }

    /**
     * @param array $events Decoded 'events' array from a LINE webhook payload
     */
    public function HandleEvents(array $events)
    {
        foreach ($events as $event) {
            $this->HandleEvent($event);
        }
    }

    private function HandleEvent($event)
    {
        if (($event['type'] ?? null) !== 'message' || ($event['message']['type'] ?? null) !== 'text') {
            return;
        }

        $lineUserId = $event['source']['userId'] ?? null;
        $replyToken = $event['replyToken'] ?? null;
        $code = trim($event['message']['text'] ?? '');

        if (empty($lineUserId) || empty($code)) {
            return;
        }

        $user = $this->userRepository->LoadByPreference(UserPreferences::LINE_LINK_CODE, $code);

        if ($user->Id() === null) {
            Log::Debug('LINE webhook: no user found for link code. LineUserId=%s', $lineUserId);
            if (!empty($replyToken)) {
                $this->lineService->Reply($replyToken, Resources::GetInstance()->GetString('LineLinkCodeNotFound'));
            }
            return;
        }

        // One LINE identity is only meaningfully bound to one system account at a time —
        // testing multiple accounts from the same phone (or genuinely re-linking after
        // switching accounts) would otherwise leave a stale link on whichever account had
        // it before, with no way to tell from that account's own settings page that it
        // silently stopped receiving pushes. Last link wins: unlink the previous owner first.
        $existingOwner = $this->userRepository->LoadByPreference(UserPreferences::LINE_USER_ID, $lineUserId);
        if ($existingOwner->Id() !== null && $existingOwner->Id() !== $user->Id()) {
            $existingOwner->ChangePreference(UserPreferences::LINE_USER_ID, '');
            $this->userRepository->Update($existingOwner);
            Log::Debug('LINE webhook: unlinked user %s from LineUserId=%s before relinking to user %s', $existingOwner->Id(), $lineUserId, $user->Id());
        }

        $user->ChangePreference(UserPreferences::LINE_USER_ID, $lineUserId);
        $user->ChangePreference(UserPreferences::LINE_LINK_CODE, '');
        $this->userRepository->Update($user);

        Log::Debug('LINE webhook: linked user %s to LineUserId=%s', $user->Id(), $lineUserId);

        if (!empty($replyToken)) {
            $this->lineService->Reply($replyToken, Resources::GetInstance()->GetString('LineLinkSuccess'));
        }
    }
}
