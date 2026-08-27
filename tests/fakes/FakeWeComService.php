<?php

declare(strict_types=1);

require_once(ROOT_DIR . 'lib/WeCom/namespace.php');

class FakeWeComService implements IWeComService
{
    /**
     * @var array
     */
    public $_Pushed = [];

    public function Push($wecomUserId, $text)
    {
        $this->_Pushed[] = ['wecomUserId' => $wecomUserId, 'text' => $text];
    }
}
