<?php

declare(strict_types=1);

require_once(ROOT_DIR . 'plugins/Authentication/ActiveDirectory/IActiveDirectory.php');

class FakeActiveDirectoryWrapper implements IActiveDirectory
{
    public $_ExpectedConnect = true;
    public $_ConnectCalled = false;

    public $_ExpectedAuthenticate = true;
    public $_AuthenticateCalled = false;
    public $_LastUsername;
    public $_LastPassword;

    public $_GetLdapUserCalled = false;
    public $_ExpectedLdapUser;

    public $_ExpectedAccountStatus = AdAccountStatus::ACTIVE;
    public $_LastAccountStatusUsername;

    public function Connect()
    {
        $this->_ConnectCalled = true;
        return $this->_ExpectedConnect;
    }

    public function Authenticate($username, $password)
    {
        $this->_AuthenticateCalled = true;
        $this->_LastUsername = $username;
        $this->_LastPassword = $password;

        return $this->_ExpectedAuthenticate;
    }

    public function GetLdapUser($username)
    {
        $this->_GetLdapUserCalled = true;

        return $this->_ExpectedLdapUser;
    }

    public function GetAccountStatus($username)
    {
        $this->_LastAccountStatusUsername = $username;

        return $this->_ExpectedAccountStatus;
    }
}
