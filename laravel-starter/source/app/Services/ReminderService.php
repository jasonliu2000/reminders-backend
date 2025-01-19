<?php

namespace App\Services;

use App\Models\Reminder;
use App\Enums\ReminderRecurrenceType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use DateTime;
use Exception;

class ReminderService
{
	/**
     * Returns reminder(s) in given date range
     * 
	 * @param string $start - the start of the date range as a date string
	 * @param string $end - the end of the date range as a date string
     * @return array
	 * @throws Exception
     */
	public static function getRemindersInDateRange(string $start, string $end): array
	{
		try {
			$startDate = new DateTime($start);
			$endDate = new DateTime($end);
		} catch (Exception $e) {
			throw new Exception('Error creating DateTime object: ' . $e->getMessage());
		}

		$eligibleReminders = self::getRemindersStartingBeforeDate($end);
		$results = [];

		foreach ($eligibleReminders as $reminder) {
			$inDateRange = self::isReminderInRange($reminder, $startDate, $endDate);
			if ($inDateRange) {
				$results[] = $reminder;
			}
		}

		return $results;
	}


	/**
     * Returns whether or not the given reminder will occur in the given date range
     * 
	 * @param Reminder $reminder - the Reminder object
	 * @param DateTime $lo - the lower bound of the date range
	 * @param DateTime $hi - the upper bound of the date range
	 * @return bool
     */
	static function isReminderInRange(Reminder $reminder, DateTime $lo, DateTime $hi): bool
	{
		try {
			$reminderStartDate = new DateTime($reminder->start_date);
		} catch (Exception $e) {
			$msg = "Error creating DateTime object for the start date of reminder with id $reminder->id: ";
			Log::error($msg . $e->getMessage());
			return false;
		}

		if ($reminderStartDate >= $lo && $reminderStartDate <= $hi) {
			return true;
		}

		$reminderTime = $reminderStartDate->format('H:i:s\Z');

		switch ($reminder->recurrence_type) {
			case ReminderRecurrenceType::NONE->value:
				return false;

			case ReminderRecurrenceType::DAILY->value:
				return self::isTimeInRange($reminderTime, $lo, $hi);

			case ReminderRecurrenceType::WEEKLY->value:
				return self::isWeekdayInRange($reminderStartDate->format('N'), $lo, $hi, $reminderTime);

			case ReminderRecurrenceType::CUSTOM->value:
				return self::isNthDayInRange($reminder->recurrence_value, $reminderStartDate, $lo, $hi);
				
			case ReminderRecurrenceType::MONTHLY->value:
				return self::isDayInRange($reminderStartDate->format('j'), $lo, $hi, $reminderTime);

			default:
				Log::error('The recurrence type for the reminder was not recognized.');
		}

		return false;
	}


	/**
	 * Returns true if the given time is within the bounds of the range
	 * 
	 * @param string $time - the time to check (ex. '10:15:59Z')
	 * @param DateTime $lo - the lower bound of the date range
	 * @param DateTime $lo - the upper bound of the date range
	 * @return bool
	 */
	static function isTimeInRange(string $time, DateTime $lo, DateTime $hi): bool
	{
		if (self::getFullDaysInDateRange($lo, $hi) >= 1) {
			return true;
		}

		$timeOfDayLo = new DateTime($lo->format('Y-m-d') . 'T' . $time);
		if ($lo->format('j') === $hi->format('j')) {
			return $timeOfDayLo >= $lo && $timeOfDayLo <= $hi;
		}

		$timeOfDayHi = new DateTime($hi->format('Y-m-d') . 'T' . $time);
		return $timeOfDayLo >= $lo || $timeOfDayHi <= $hi;
	}


	/**
	 * Returns true if the given weekday will occur in the given date range, otherwise false
	 * 
	 * @param int $weekDay - the weekday to check for (1 - Mon, 7 - Sun)
	 * @param DateTime $lo - the lower bound of the date range
	 * @param DateTime $lo - the upper bound of the date range
	 * @param string $time - (optional) the time of the weekday to check (ex. '10:15:59Z')
	 * @return bool
	 */
	static function isWeekdayInRange(int $weekDay, DateTime $lo, DateTime $hi, string $time = '00:00:00Z'): bool
	{
		if (self::getFullDaysInDateRange($lo, $hi) >= 7) {
			return true;
		}

		$dt = clone $lo;
		while ($dt <= $hi) {
			if ($dt->format('N') == $weekDay) {
				$reminderDateTime = new DateTime($dt->format('Y-m-d') . 'T' . $time);
				if ($reminderDateTime >= $lo && $reminderDateTime <= $hi) {
					return true;
				}
			}
	
			$dt->modify('+1 day');
		}

		return false;
	}


	/**
	 * Returns true if an occurence of every n days from start will occur in the given date range, otherwise false
	 * 
	 * @param int $n - how many days per cycle (every n days)
	 * @param DateTime $start - the starting date of the reminder
	 * @param DateTime $lo - the lower bound of the date range
	 * @param DateTime $hi - the upper bound of the date range
	 * @return bool
	 */
	static function isNthDayInRange(int $n, DateTime $start, DateTime $lo, DateTime $hi): bool
	{
		if (self::getFullDaysInDateRange($lo, $hi) >= $n) {
			return true;
		}

		$diff = self::getFullDaysInDateRange($start, $lo);
		$daysToIncrement = $diff - ($diff % $n);

		$occurenceDate = clone $start;
		$occurenceDate->modify("+$daysToIncrement day");

		return $occurenceDate == $lo || $occurenceDate->modify("+$n day") <= $hi;
	}


	/**
	 * Returns true if the day of the month is in the given date range, otherwise false
	 * 
	 * @param int $day - the day of the month
	 * @param DateTime $lo - the lower bound of the date range
	 * @param DateTime $hi - the upper bound of the date range
	 * @param string $time - (optional) the time of the day to check (ex. '10:15:59Z')
	 * @return bool
	 */
	static function isDayInRange(int $day, DateTime $lo, DateTime $hi, string $time = '00:00:00Z'): bool
	{
		$currentMonth = $lo->format('n');
		$year = $lo->format('Y');

		$currentMonthDay = min($day, self::getDaysOfMonth($currentMonth, $year));
		$dt = new DateTime($lo->format('Y-m') . '-' . $currentMonthDay . 'T' . $time);

		if (self::isRangeInSameMonthAndYr($lo, $hi)) {
			return $dt >= $lo && $dt <= $hi;
		}

		$nextMonth = $currentMonth % 12 + 1;
		$year += ($nextMonth === 1 ? 1 : 0);
		$nextMonthDay = min($day, self::getDaysOfMonth($nextMonth, $year));
		$nextDt = new DateTime($year . '-' . $nextMonth . '-' . $nextMonthDay . 'T' . $time);
		return $dt >= $lo || $nextDt <= $hi;
	}


	/**
	 * Returns reminder(s) with a start date before or equal to the given date
	 * 
	 * @param string $date
	 * @return Collection
	 */
	static function getRemindersStartingBeforeDate(string $date): Collection
    {
        return Reminder::where('start_date', '<=', $date)->get();
    }


	/**
	 * Returns the number of full days in the given date range
	 * 
	 * @param DateTime $start
	 * @param DateTime $end
	 * @return int
	 */
	static function getFullDaysInDateRange(DateTime $start, DateTime $end): int
	{
		return $start->diff($end)->days;
	}


	/**
	 * Returns the number of days in the given month of the year
	 * 
	 * @param int $month
	 * @param int $year
	 * @return int
	 */
	static function getDaysOfMonth(int $month, int $year): int
	{
		return date('t', mktime(0, 0, 0, $month, 1, $year));
	}


	/**
	 * Returns true if the given date range is contained in the same month and year, otherwise false
	 * 
	 * 
	 * @param DateTime $lo
	 * @param DateTime $hi
	 * @return bool
	 */
	static function isRangeInSameMonthAndYr(DateTime $lo, DateTime $hi): bool
	{
		return $lo->format('Y') === $hi->format('Y') && $lo->format('n') === $hi->format('n');
	}

}