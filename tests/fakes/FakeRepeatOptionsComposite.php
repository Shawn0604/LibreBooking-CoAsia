<?php

declare(strict_types=1);

class FakeRepeatOptionsComposite implements IRepeatOptionsComposite
{
    public $_RepeatType = RepeatType::None;
    public $_RepeatInterval = 1;
    public $_RepeatWeekdays = [];
    public $_RepeatMonthlyType = RepeatMonthlyType::DayOfMonth;
    public $_RepeatTerminationDate = '2030-01-01';
    public $_RepeatCustomDates = [];

    public function GetRepeatType()
    {
        return $this->_RepeatType;
    }

    public function GetRepeatInterval()
    {
        return $this->_RepeatInterval;
    }

    public function GetRepeatWeekdays()
    {
        return $this->_RepeatWeekdays;
    }

    public function GetRepeatMonthlyType()
    {
        return $this->_RepeatMonthlyType;
    }

    public function GetRepeatTerminationDate()
    {
        return $this->_RepeatTerminationDate;
    }

    public function GetRepeatCustomDates()
    {
        return $this->_RepeatCustomDates;
    }
}
