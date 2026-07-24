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
     * Reply to an inbound webhook event (used to confirm/reject an account-linking code).
     * @param string $replyToken
     * @param string $text
     */
    public function Reply($replyToken, $text);
}
