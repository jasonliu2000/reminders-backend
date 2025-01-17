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
			$firstReminder = new DateTime($reminder->start_date);
		} catch (Exception $e) {
			$msg = "Error creating DateTime object for the start date of reminder with id $reminder->id: ";
			Log::error($msg . $e->getMessage());
			return false;
		}

		if ($firstReminder > $hi) {
			return false;
		}
		if ($firstReminder >= $lo && $firstReminder <= $hi) {
			return true;
		}

		$daysInRange = $lo->diff($hi)->days;

		switch ($reminder->recurrence_type) {
			case ReminderRecurrenceType::NONE->value:
				return false;

			case ReminderRecurrenceType::DAILY->value:
				return true;

			case ReminderRecurrenceType::WEEKLY->value:
				return $this->isWeekdayInRange(
					$reminder->recurrence_value, 
					$lo,
					$daysInRange
				);

			case ReminderRecurrenceType::EVERY_N_DAYS->value:
				return $this->isNthDayInRange(
					$reminder->recurrence_value, 
					$firstReminder,
					$lo,
					$daysInRange
				);
				
			case ReminderRecurrenceType::MONTHLY->value:
				return $this->isDayInRange(
					$firstReminder->format('j'),
					$lo,
					$hi
				);

			default:
				Log::error('The recurrence type for the reminder was not recognized.');
				break;
		}

		return false;
	}


	/**
	 * Returns true if the given weekday will occur in the given date range, otherwise false
	 * 
	 * @param int $weekDay - the weekday to check for (1 - Mon, 7 - Sun)
	 * @param DateTime $loDate - the lower bound of the date range
	 * @param int $daysInRange - the number of days in the date range
	 * @return bool
	 */
	function isWeekdayInRange(int $weekDay, DateTime $loDate, int $daysInRange): bool
	{
		$loDay = (int) $loDate->format('N');
		$offset = ($weekDay - $loDay + 7) % 7;
		return $offset <= $daysInRange;
	}


	/**
	 * Returns true if an occurence of every n days from start will occur in the given date range, otherwise false
	 * 
	 * @param int $n - the cycle of every n days
	 * @param DateTime $start - the starting date of the reminder
	 * @param DateTime $loDate - the lower bound of the date range
	 * @param int $daysInRange - the number of days in the date range
	 * @return bool
	 */
	function isNthDayInRange(int $n, DateTime $start, DateTime $loDate, int $daysInRange): bool
	{
		$diff = $start->diff($loDate)->days;
		$offset = ($diff % $n === 0) ? 0 : $n - ($diff % $n);
		return $offset <= $daysInRange;
	}


	/**
	 * Returns true if the day of the month is in the given date range, otherwise false
	 * 
	 * @param int $day - the day of the month
	 * @param DateTime $lo - the lower bound of the date range
	 * @param DateTime $hi - the upper bound of the date range
	 * @return bool
	 */
	// TODO: Need to handle case where reminder is on the 31th, 30th, 29th of a month
	function isDayInRange(int $day, DateTime $lo, DateTime $hi): bool
	{
		$loDayOfMonth = (int) $lo->format('j');
		$rangeSpansMultipleYears = $lo->format('Y') !== $hi->format('Y');
		$rangeSpansMultipleMonths = $lo->format('n') !== $hi->format('n');

		if ($rangeSpansMultipleYears && ($lo->format('n') !== '12' || $hi->format('n') !== '1')) {
			return true;
		}
		
		if ($rangeSpansMultipleMonths) {
			$daysInFirstMonth = (int) $lo->format('t');
			$daysUntilNextMonth = $daysInFirstMonth - $loDayOfMonth;
			$daysInRange = $lo->diff($hi)->days;
			if ($day >= $loDayOfMonth || $daysInRange - $daysUntilNextMonth >= $day) {
				return true;
			}
		} else {
			$hiDayOfMonth = (int) $hi->format('j');
			if ($day >= $loDayOfMonth && $day <= $hiDayOfMonth) {
				return true;
			}
		}

		return false;
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

}