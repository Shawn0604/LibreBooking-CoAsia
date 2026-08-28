<?php

class ReservationReminder
{
    private $value;
    private $interval;
    private $minutesPrior;

    public function __construct($value, $interval)
    {
        $this->value = is_numeric($value) ? $value : 0;
        $this->interval = $interval;

        if ($interval == ReservationReminderInterval::Days) {
            $this->minutesPrior = $this->value * 60 * 24;
        } elseif ($interval == ReservationReminderInterval::Hours) {
            $this->minutesPrior = $this->value * 60;
        } else {
            $this->interval = ReservationReminderInterval::Minutes;
            $this->minutesPrior = $this->value;
        }
    }

    public static function None()
    {
        return new NullReservationReminder();
    }

    public function Enabled()
    {
        return true;
    }

    public function MinutesPrior()
    {
        return $this->minutesPrior;
    }

    /**
     * Parse a config interval string ("15 minutes" / "1 hours" / "1 days") into
     * {value:int, interval:string}. Returns null when unset/blank/malformed.
     * Unknown unit falls back to minutes.
     *
     * @param string|null $interval
     * @return array{value:int, interval:string}|null
     */
    public static function ConfigIntervalPieces($interval)
    {
        if (empty($interval)) {
            return null;
        }
        $parts = explode(' ', strtolower(trim($interval)));
        if (count($parts) != 2 || !is_numeric($parts[0]) || (int)$parts[0] <= 0) {
            return null;
        }
        $unit = trim($parts[1]);
        if (!in_array($unit, [ReservationReminderInterval::Minutes, ReservationReminderInterval::Hours, ReservationReminderInterval::Days], true)) {
            $unit = ReservationReminderInterval::Minutes;
        }
        return ['value' => (int)$parts[0], 'interval' => $unit];
    }

    /**
     * @param string|null $interval config string, see ConfigIntervalPieces()
     * @return ReservationReminder|null
     */
    public static function FromConfigInterval($interval)
    {
        $pieces = self::ConfigIntervalPieces($interval);
        return $pieces === null ? null : new ReservationReminder($pieces['value'], $pieces['interval']);
    }

    /**
     * @param int $minutes
     * @return ReservationReminder
     */
    public static function FromMinutes($minutes)
    {
        if ($minutes % 1440 == 0) {
            return new ReservationReminder($minutes / 1440, ReservationReminderInterval::Days);
        } elseif ($minutes % 60 == 0) {
            return new ReservationReminder($minutes / 60, ReservationReminderInterval::Hours);
        } else {
            return new ReservationReminder($minutes, ReservationReminderInterval::Minutes);
        }
    }
}

class NullReservationReminder extends ReservationReminder
{
    public function __construct()
    {
        parent::__construct(0, null);
    }

    public function Enabled()
    {
        return false;
    }

    public function MinutesPrior()
    {
        return 0;
    }
}

class ReservationReminderInterval
{
    public const Minutes = 'minutes';
    public const Hours = 'hours';
    public const Days = 'days';
}

class ReservationReminderType
{
    public const Start = 0;
    public const End = 1;
}
