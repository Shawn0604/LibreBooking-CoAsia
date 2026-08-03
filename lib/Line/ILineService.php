<?php

interface ILineService
{
    /**
     * Push a message to a specific LINE user (used for reminders/confirmations).
     * @param string $lineUserId
     * @param string $text
     */
    public function Push($lineUserId, $text);

    /**
     * Push a Flex Message bubble to a specific LINE user.
     * @param string $lineUserId
     * @param string $altText notification-list fallback text, shown when the bubble can't render
     * @param array $contents Flex Message "contents" object (bubble/carousel), as a plain array
     */
    public function PushFlex($lineUserId, $altText, $contents);

    /**
     * Reply to an inbound webhook event (used to confirm/reject an account-linking code).
     * @param string $replyToken
     * @param string $text
     */
    public function Reply($replyToken, $text);
}
