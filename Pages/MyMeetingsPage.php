<?php

require_once(ROOT_DIR . 'Pages/SecurePage.php');
require_once(ROOT_DIR . 'Presenters/MyMeetingsPresenter.php');
require_once(ROOT_DIR . 'Domain/Access/namespace.php');

class MyMeetingsPage extends SecurePage implements IMyMeetingsPage
{
    /**
     * @var MyMeetingsPresenter
     */
    private $presenter;

    public function __construct()
    {
        parent::__construct('MyMeetings');

        $this->presenter = new MyMeetingsPresenter(
            $this,
            new ReservationViewRepository(),
            new AttributeRepository()
        );
    }

    public function PageLoad()
    {
        $this->presenter->PageLoad(ServiceLocator::GetServer()->GetUserSession());
        $this->Display('MyMeetings/my-meetings.tpl');
    }

    public function SetNextMeeting($nextMeeting)
    {
        $this->Set('NextMeeting', $nextMeeting);
    }

    public function SetUpcoming($days)
    {
        $this->Set('Upcoming', $days);
    }

    public function SetPast($days)
    {
        $this->Set('Past', $days);
    }
}
