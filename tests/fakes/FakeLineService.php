<?php

declare(strict_types=1);

require_once(ROOT_DIR . 'lib/Line/namespace.php');

class FakeLineService implements ILineService
{
    /**
     * @var array
     */
    public $_Pushed = [];

    /**
     * @var array
     */
    public $_Replied = [];

    public function Push($lineUserId, $text)
    {
        $this->_Pushed[] = ['lineUserId' => $lineUserId, 'text' => $text];
    }

    public function Reply($replyToken, $text)
    {
        $this->_Replied[] = ['replyToken' => $replyToken, 'text' => $text];
    }
}
