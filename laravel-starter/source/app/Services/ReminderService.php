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
	public function getRemindersInDateRange(string $start, string $end): array
	{
		try {
			$startDate = new DateTime($start);
			$endDate = new DateTime($end);
		} catch (Exception $e) {
			throw new Exception('Error creating DateTime object: ' . $e->getMessage());
		}

		$eligibleReminders = $this->getRemindersStartingBeforeDate($end);
		$results = [];

		foreach ($eligibleReminders as $reminder) {
			$inDateRange = $this->isReminderInRange($reminder, $startDate, $endDate);
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
	function isReminderInRange(Reminder $reminder, DateTime $lo, DateTime $hi): bool
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

		switch ($reminder->recurrence_type) {
			case ReminderRecurrenceType::NONE->value:
				return false;

			case ReminderRecurrenceType::DAILY->value:
				return true;

			case ReminderRecurrenceType::WEEKLY->value:
				return $this->isWeekdayInRange(
					$reminderStartDate->format('N'), 
					$lo,
					$hi
				);

			case ReminderRecurrenceType::CUSTOM->value:
				return $this->isNthDayInRange(
					$reminder->recurrence_value, 
					$reminderStartDate,
					$lo,
					$hi
				);
				
			case ReminderRecurrenceType::MONTHLY->value:
				return $this->isDayInRange(
					$reminderStartDate->format('j'),
					$lo,
					$hi
				);

			default:
				Log::error('The recurrence type for the reminder was not recognized.');
		}

		return false;
	}


	/**
	 * Returns true if the given weekday will occur in the given date range, otherwise false
	 * 
	 * @param int $weekDay - the weekday to check for (1 - Mon, 7 - Sun)
	 * @param DateTime $lo - the lower bound of the date range
	 * @param DateTime $lo - the upper bound of the date range
	 * @return bool
	 */
	function isWeekdayInRange(int $weekDay, DateTime $lo, DateTime $hi): bool
	{
		$loDay = $lo->format('N');
		$offset = ($weekDay - $loDay + 7) % 7;
		return $offset <= $this->getDaysInDateRange($lo, $hi);
	}


	/**
	 * Returns true if an occurence of every n days from start will occur in the given date range, otherwise false
	 * 
	 * @param int $n - the cycle of every n days
	 * @param DateTime $start - the starting date of the reminder
	 * @param DateTime $lo - the lower bound of the date range
	 * @param DateTime $hi - the upper bound of the date range
	 * @return bool
	 */
	function isNthDayInRange(int $n, DateTime $start, DateTime $lo, DateTime $hi): bool
	{
		$diff = $start->diff($lo)->days;
		$offset = ($diff % $n === 0) ? 0 : $n - ($diff % $n);
		return $offset <= $this->getDaysInDateRange($lo, $hi);
	}


	/**
	 * Returns true if the day of the month is in the given date range, otherwise false
	 * 
	 * @param int $day - the day of the month
	 * @param DateTime $lo - the lower bound of the date range
	 * @param DateTime $hi - the upper bound of the date range
	 * @return bool
	 */
	function isDayInRange(int $day, DateTime $lo, DateTime $hi): bool
	{
		$loDayOfMonth = (int) $lo->format('j');
		$hiDayOfMonth = (int) $hi->format('j');

		if ($this->isRangeInSameMonthAndYr($lo, $hi)) {
			$day = min($day, $this->getDaysOfMonth($lo));
			return $day >= $loDayOfMonth && $day <= $hiDayOfMonth;
		}

		$month = $lo->format('m') % 12 + 1;
		$year = $lo->format('Y') + ($month === 1 ? 1 : 0);
		$nextReminderDate = new DateTime($year . '-' . $month . '-' . $day); // https://www.php.net/manual/en/datetime.formats.php
		return $day >= $loDayOfMonth || $nextReminderDate <= $hi;
	}


	/**
	 * Returns reminder(s) with a start date before or equal to the given date
	 * 
	 * @param string $date
	 * @return Collection
	 */
	function getRemindersStartingBeforeDate(string $date): Collection
    {
        return Reminder::where('start_date', '<=', $date)->get();
    }


	/**
	 * Returns the number of days in the given date range
	 * 
	 * @param DateTime $start
	 * @param DateTime $end
	 * @return int
	 */
	function getDaysInDateRange(DateTime $start, DateTime $end): int
	{
		return $start->diff($end)->days;
	}


	/**
	 * Returns the number of days in the month that the datetime is in
	 * 
	 * @param DateTime $start
	 * @return int
	 */
	function getDaysOfMonth(DateTime $dt): int
	{
		return date('t', mktime(0, 0, 0, $dt->format('m'), 1, $dt->format('Y')));
	}


	/**
	 * Returns true if the given date range is contained in the same month and year, otherwise false
	 * 
	 * 
	 * @param DateTime $lo
	 * @param DateTime $hi
	 * @return bool
	 */
	function isRangeInSameMonthAndYr(DateTime $lo, DateTime $hi): bool
	{
		return $lo->format('Y') === $hi->format('Y') && $lo->format('n') === $hi->format('n');
	}

}