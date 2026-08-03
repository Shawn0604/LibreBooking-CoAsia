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
    public $_PushedFlex = [];

    /**
     * @var array
     */
    public $_Replied = [];

    public function Push($lineUserId, $text)
    {
        $this->_Pushed[] = ['lineUserId' => $lineUserId, 'text' => $text];
    }

    public function PushFlex($lineUserId, $altText, $contents)
    {
        $this->_PushedFlex[] = ['lineUserId' => $lineUserId, 'altText' => $altText, 'contents' => $contents];
    }

    public function Reply($replyToken, $text)
    {
        $this->_Replied[] = ['replyToken' => $replyToken, 'text' => $text];
    }
}
