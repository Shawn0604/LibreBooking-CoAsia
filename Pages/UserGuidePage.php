<?php

require_once(ROOT_DIR . 'Pages/SecurePage.php');

class UserGuidePage extends SecurePage
{
    public function __construct()
    {
        parent::__construct('UserGuide');
    }

    public function PageLoad()
    {
        $this->Display('Help/user-guide.tpl');
    }
}
