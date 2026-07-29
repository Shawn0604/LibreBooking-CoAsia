<?php

declare(strict_types=1);

class FakeReservationSaveResultsView implements IReservationSaveResultsView
{
    public $_SaveSuccessful = false;
    public $_Errors = [];
    public $_Warnings = [];
    public $_CanBeRetried = false;
    public $_RetryParameters = [];
    public $_RetryMessages = [];
    public $_CanJoinWaitList = false;

    public function SetSaveSuccessfulMessage($succeeded)
    {
        $this->_SaveSuccessful = $succeeded;
    }

    public function SetErrors($errors)
    {
        $this->_Errors = $errors;
    }

    public function SetWarnings($warnings)
    {
        $this->_Warnings = $warnings;
    }

    public function SetRetryMessages($messages)
    {
        $this->_RetryMessages = $messages;
    }

    public function SetCanBeRetried($canBeRetried)
    {
        $this->_CanBeRetried = $canBeRetried;
    }

    public function SetRetryParameters($retryParameters)
    {
        $this->_RetryParameters = $retryParameters;
    }

    public function GetRetryParameters()
    {
        return $this->_RetryParameters;
    }

    public function SetCanJoinWaitList($canJoinWaitlist)
    {
        $this->_CanJoinWaitList = $canJoinWaitlist;
    }
}
