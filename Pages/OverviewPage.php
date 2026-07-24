<?php

require_once(ROOT_DIR . 'Pages/SecurePage.php');
require_once(ROOT_DIR . 'Presenters/OverviewPresenter.php');
require_once(ROOT_DIR . 'Domain/Access/namespace.php');

class OverviewPage extends SecurePage implements IOverviewPage
{
    /**
     * @var OverviewPresenter
     */
    private $presenter;

    public function __construct()
    {
        parent::__construct('Overview');

        $this->presenter = new OverviewPresenter(
            $this,
            new ResourceRepository(),
            new ReservationViewRepository(),
            new AttributeRepository()
        );
    }

    public function PageLoad()
    {
        $this->presenter->PageLoad(ServiceLocator::GetServer()->GetUserSession());
        $this->Display('Overview/overview.tpl');
    }

    public function GetRequestedWeekStart()
    {
        $timezone = ServiceLocator::GetServer()->GetUserSession()->Timezone;
        $value = $this->GetQuerystring(QueryStringKeys::START_DATE);

        if (empty($value)) {
            return null;
        }

        try {
            return Date::Parse($value, $timezone);
        } catch (Exception $ex) {
            return null;
        }
    }

    public function SetWeekStart(Date $date)
    {
        $this->Set('WeekStart', $date);
    }

    public function SetWeekEnd(Date $date)
    {
        $this->Set('WeekEnd', $date);
    }

    public function SetPreviousWeek(Date $date)
    {
        $this->Set('PreviousWeek', $date->Format('Y-m-d'));
    }

    public function SetNextWeek(Date $date)
    {
        $this->Set('NextWeek', $date->Format('Y-m-d'));
    }

    public function SetIsCurrentWeek($isCurrentWeek)
    {
        $this->Set('IsCurrentWeek', $isCurrentWeek);
    }

    public function SetResources($physicalResources, $videoResources, $resourceColors)
    {
        $this->Set('PhysicalResources', $physicalResources);
        $this->Set('VideoResources', $videoResources);
        $this->Set('ResourceColors', $resourceColors);
    }

    public function SetDays(array $days)
    {
        $this->Set('Days', $days);
    }

    public function SetDayStartHour($hour)
    {
        $this->Set('DayStartHour', $hour);
    }

    public function SetDayEndHour($hour)
    {
        $this->Set('DayEndHour', $hour);
    }

    public function SetHours($hours)
    {
        $this->Set('Hours', $hours);
    }
}
