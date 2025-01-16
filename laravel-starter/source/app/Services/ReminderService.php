<?php

namespace App\Services;

use App\Models\Reminder;
use App\Enums\ReminderRecurrenceType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use DateTime;

class ReminderService
{
	/**
     * Returns reminder(s) in given date range
     * 
     */
	public function getRemindersInDateRange(string $beginning, string $end): array
	{
		$eligibleReminders = $this->getRemindersStartingBefore($end);
		$startDate = new DateTime($beginning);
        $endDate = new DateTime($end);

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
     */
	function isReminderInRange(Reminder $reminder, DateTime $beginning, DateTime $end): bool
	{
		$interval = $beginning->diff($end);
		$reminderStart = new DateTime($reminder->start_date);

		Log::info('reminder:', [$reminder->text]);

		if ($reminderStart->getTimestamp() > $end->getTimestamp()) {
			return false;
		}
		if ($this->isDateInRange($reminderStart, $beginning, $end)) {
			return true;
		}
		
		// TODO: Need to handle case where reminder is on the 31th, 30th, 29th of a month

		switch ($reminder->recurrence_type) {
			case ReminderRecurrenceType::DAILY->value:
				return true;

			case ReminderRecurrenceType::WEEKLY->value:
				$reminderDay = $reminder->recurrence_value;
				$beginningDay = (int) date('N', $beginning->getTimestamp());
				// var_dump($reminderDay);
				// var_dump($beginningDay);
				// var_dump($interval);
				$daysUntilFirstReminder = ($reminderDay - $beginningDay + 7) % 7;

				if ($daysUntilFirstReminder <= $interval->d) {
					return true;
				}
				break;

			case ReminderRecurrenceType::EVERY_N_DAYS->value:
				$n = $reminder->recurrence_value;
				$daysDifference = (int) $reminderStart->diff($beginning)->format('%a');
				Log::info('daysDifference:', [$daysDifference]);

				if ($daysDifference === 0 || $n - $daysDifference <= $interval->d) {
					return true;
				}
				break;
				
			case ReminderRecurrenceType::MONTHLY->value:
				$reminderDayOfMonth = (int) $reminderStart->format('j');
				// Log::info('reminderDayOfMonth:', [$reminderDayOfMonth]);
				$beginningDayOfMonth = (int) $beginning->format('j'); // TODO: change var name, it's confusing
				// Log::info('beginningDayOfMonth:', [$beginningDayOfMonth]);
				$differentMonthOrYr = ($beginning->format('n') !== $end->format('n') || $beginning->format('Y') !== $end->format('Y'));
				// Log::info('diffMonth:', [$beginning->format('n') !== $end->format('n')]);

				if ($differentMonthOrYr) {
					$daysInFirstMonth = (int) $beginning->format('t');
					$daysUntilNextMonth = $daysInFirstMonth - $beginningDayOfMonth;
					if ($reminderDayOfMonth >= $beginningDayOfMonth || $interval->d - $daysUntilNextMonth >= $reminderDayOfMonth) {
						return true;
					}
				} else {
					$endDayOfMonth = (int) $end->format('j');
					// this will include the end date of the date range...
					if ($reminderDayOfMonth >= $beginningDayOfMonth && $reminderDayOfMonth <= $endDayOfMonth) {
						return true;
					}
				}

				break;
			
			case ReminderRecurrenceType::NONE->value:
				// skip, should be handled prior to the switch statement
				// TODO: throw custom exception if we get here?
				break;

			default:
				// TODO: Handle case where recurrence type is not recognized
				break;
		}

		return false;
	}


	/**
	 * Returns reminder(s) with a start date before or equal to the given date
	 * 
	 */
	function getRemindersStartingBefore(string $date): Collection
    {
        return Reminder::where('start_date', '<=', $date)->get();
    }


	/**
	 * Returns true if date is within the lower and upper date ranges, otherwise false
	 * 
	 */
	function isDateInRange(DateTime $date, DateTime $lower, DateTime $upper): bool
    {
        return $date->getTimestamp() >= $lower->getTimestamp() && $date->getTimestamp() <= $upper->getTimestamp();
    }


	/**
	 * Returns true if date2 is after date1, otherwise false
	 * 
	 */
	function compareDates(DateTime $date, DateTime $lower, DateTime $upper): bool
    {
        return $date->getTimestamp() >= $lower->getTimestamp() && $date->getTimestamp() <= $upper->getTimestamp();
    }
}