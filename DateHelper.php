<?php

namespace App\Services;

/**
 * Class DateHelper
 * @package App\Services
 *
 * @property \DateTime $now
 * @property \DateTime $currentDay
 * @property \DateTime $firstDayOfMonth
 * @property \DateTimeZone $timezone
 */
class DateHelper
{
    const FORMAT = 'Y-m-d H:i:s';

    private $now;
    private $timezone = null;

    /**
     * DateHelper constructor.
     * @throws
     */
    public function __construct()
    {
        $this->now = new \DateTime();
    }

    public function __get(string $name)
    {
        return $this->strToDateTime($this->{"{$name}Str"}());
    }

    public function strToDateTime(string $str): \DateTime
    {
        $dateTime = \DateTime::createFromFormat(self::FORMAT, $str);
        if ($this->timezone) {
            $dateTime->setTimezone($this->timezone);
        }

        return $dateTime;
    }

    public function currentDayStr(): string
    {
        return $this->now->format('Y-m-d').' 00:00:00';
    }

    public function firstDayOfMonthStr(): string
    {
        return $this->now->format('Y-m').'-01 00:00:00';
    }

    /**
     * @param int $daysCount
     * @param bool $round
     * @return \DateTime
     * @throws
     */
    public function dayAgo(int $daysCount, bool $round = false): \DateTime
    {
        $date = new \DateTime('-'.(string) $daysCount.' day');
        if ($this->timezone) {
            $date->setTimezone($this->timezone);
        }
        if ($round) {
            return $this->strToDateTime($date->format('Y-m-d ').'00:00:00');
        }
        return $date;
    }

    public function diff(\DateTime $dateTimeFrom, \DateTime $dateTimeTo = null): \DateInterval
    {
        return $dateTimeFrom->diff($dateTimeTo ?? $this->now);
    }

    public function setTimezone(\DateTimeZone $zone): self
    {
        $this->timezone = $zone;

        return $this;
    }
}
