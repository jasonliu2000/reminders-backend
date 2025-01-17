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
	 * @param string $start
	 * @param string $end
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
	 * @param Reminder $reminder
	 * @param DateTime $lo
	 * @param DateTime $hi
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

		$interval = $lo->diff($hi);
		
		// TODO: Need to handle case where reminder is on the 31th, 30th, 29th of a month

		switch ($reminder->recurrence_type) {
			case ReminderRecurrenceType::NONE->value:
				return false;

			case ReminderRecurrenceType::DAILY->value:
				return true;

			case ReminderRecurrenceType::WEEKLY->value:
				$day = $reminder->recurrence_value;
				$loDay = (int) $lo->format('N');
				$daysUntilNextReminder = ($day - $loDay + 7) % 7;

				if ($daysUntilNextReminder <= $interval->days) {
					return true;
				}
				break;

			case ReminderRecurrenceType::EVERY_N_DAYS->value:
				$n = $reminder->recurrence_value;
				$diff = $firstReminder->diff($lo)->days;
				$daysUntilNextReminder = $diff % $n === 0 ? 0 : $n - ($diff % $n);

				if ($daysUntilNextReminder <= $interval->days) {
					return true;
				}
				break;
				
			case ReminderRecurrenceType::MONTHLY->value:
				$dayOfMonth = (int) $firstReminder->format('j');
				// Log::info('reminderDayOfMonth:', [$reminderDayOfMonth]);
				$loDayOfMonth = (int) $lo->format('j');
				// Log::info('beginningDayOfMonth:', [$beginningDayOfMonth]);
				$rangeSpansMultipleYears = $lo->format('Y') !== $hi->format('Y');
				$rangeSpansMultipleMonths = $lo->format('n') !== $hi->format('n');
				// Log::info('diffMonth:', [$beginning->format('n') !== $end->format('n')]);

				if ($rangeSpansMultipleYears && ($lo->format('n') !== '12' || $hi->format('n') !== '1')) {
					return true;
				}
				
				if ($rangeSpansMultipleMonths) {
					$daysInFirstMonth = (int) $lo->format('t');
					$daysUntilNextMonth = $daysInFirstMonth - $loDayOfMonth;
					if ($dayOfMonth >= $loDayOfMonth || $interval->days - $daysUntilNextMonth >= $dayOfMonth) {
						return true;
					}
				} else {
					$hiDayOfMonth = (int) $hi->format('j');
					if ($dayOfMonth >= $loDayOfMonth && $dayOfMonth <= $hiDayOfMonth) {
						return true;
					}
				}

				break;

			default:
				Log::error('The recurrence type for the reminder was not recognized.');
				break;
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