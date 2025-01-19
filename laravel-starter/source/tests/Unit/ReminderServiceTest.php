<?php

namespace Tests\Unit;

use App\Services\ReminderService;
use App\Models\Reminder;
use Tests\TestCase;
use DateTime;
use Mockery;

class ReminderServiceTest extends TestCase
{
    private ReminderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ReminderService();
    }

    public function testIsReminderInRange()
    {
        $reminder = new Reminder([
            'user' => 'tester',
            'text' => 'Doctor\'s appointment',
            'recurrence_type' => 'none',
            'recurrence_value' => null,
            'start_date' => '2025-06-01',
        ]);

        $dailyReminder = new Reminder([
            'user' => 'tester',
            'text' => 'Take medication',
            'recurrence_type' => 'daily',
            'recurrence_value' => null,
            'start_date' => '2025-02-01',
        ]);

        $reminderWithInvalidRecurrence = new Reminder([
            'user' => 'tester',
            'text' => 'Invalid reminder',
            'recurrence_type' => 'INVALID TYPE',
            'recurrence_value' => null,
            'start_date' => '2025-01-25',
        ]);

        $reminderWithInvalidStart = new Reminder([
            'user' => 'tester',
            'text' => 'Invalid reminder',
            'recurrence_type' => 'none',
            'recurrence_value' => null,
            'start_date' => 'INVALID START',
        ]);

        $result = $this->service->isReminderInRange($reminder, new DateTime('2025-01-01'), new DateTime('2025-01-01'));
        $this->assertSame($result, false, 'Reminder starting after date range ends should return FALSE.');
        
        $result = $this->service->isReminderInRange($reminder, new DateTime('2025-05-01'), new DateTime('2025-07-01'));
        $this->assertSame($result, true, 'Reminder starting in the date range should return TRUE.');

        $result = $this->service->isReminderInRange($reminder, new DateTime('2025-06-01'), new DateTime('2025-07-01'));
        $this->assertSame($result, true, 'Reminder starting at the beginning of the date range should return TRUE.');

        $result = $this->service->isReminderInRange($reminder, new DateTime('2025-05-01'), new DateTime('2025-06-01'));
        $this->assertSame($result, true, 'Reminder starting at the end of the date range should return TRUE.');

        $result = $this->service->isReminderInRange($reminder, new DateTime('2025-07-01'), new DateTime('2025-08-01'));
        $this->assertSame($result, false, 'Reminder with start date before the range and no recurrence should return FALSE.');

        // Invalid reminders
        $result = $this->service->isReminderInRange($reminderWithInvalidRecurrence, new DateTime('2025-12-24'), new DateTime('2026-01-24'));
        $this->assertSame($result, false, '');

        $result = $this->service->isReminderInRange($reminderWithInvalidStart, new DateTime('2025-12-24'), new DateTime('2026-01-24'));
        $this->assertSame($result, false, '');
    }

    public function testIsTimeInRange()
    {
        $result = $this->service->isTimeInRange('06:00:00Z', new DateTime('2025-12-31T12:00:00Z'), new DateTime('2026-01-01T12:00:00Z'));
        $this->assertSame($result, true);

        $result = $this->service->isTimeInRange('18:00:00Z', new DateTime('2025-12-31T12:00:00Z'), new DateTime('2026-01-01T12:00:00Z'));
        $this->assertSame($result, true);

        $result = $this->service->isTimeInRange('00:00:00Z', new DateTime('2025-01-01T00:00:00Z'), new DateTime('2025-01-01T12:00:00Z'));
        $this->assertSame($result, true, 'Should return TRUE if time is same as the lower bound time');

        $result = $this->service->isTimeInRange('12:00:00Z', new DateTime('2025-01-01T00:00:00Z'), new DateTime('2025-01-01T12:00:00Z'));
        $this->assertSame($result, true, 'Should return TRUE if time is same as the upper bound time');

        $result = $this->service->isTimeInRange('12:00:01', new DateTime('2025-01-01T06:00:00Z'), new DateTime('2025-01-01T12:00:00Z'));
        $this->assertSame($result, false);

        $result = $this->service->isTimeInRange('05:59:59Z', new DateTime('2025-01-01T06:00:00Z'), new DateTime('2025-01-01T12:00:00Z'));
        $this->assertSame($result, false);
    }

    public function testIsWeekdayInRange()
    {
        $result = $this->service->isWeekdayInRange(5, new DateTime('2025-07-01'), new DateTime('2025-07-08'));
        $this->assertSame($result, true, 'A weekly reminder should return TRUE for a date range that contains all days of the week.');

        $result = $this->service->isWeekdayInRange(5, new DateTime('2025-02-07') /*Fri*/, new DateTime('2025-02-09'));
        $this->assertSame($result, true, 'A Fri reminder should return TRUE for a date range that starts on a Fri.');

        $result = $this->service->isWeekdayInRange(5, new DateTime('2025-02-06'), new DateTime('2025-02-07') /*Fri*/);
        $this->assertSame($result, true, 'A Fri reminder should return TRUE for a date range that ends on a Fri.');

        $result = $this->service->isWeekdayInRange(5, new DateTime('2025-02-08') /*Sat*/, new DateTime('2025-02-13') /*Thur*/);
        $this->assertSame($result, false, 'A Fri reminder should return FALSE for a date range that doesn\'t contain Fri.');

        // time-specific
        $result = $this->service->isWeekdayInRange(5, new DateTime('2025-02-07T15:00:00Z') /*Fri*/, new DateTime('2025-02-09'), '09:00:00Z');
        $this->assertSame($result, false, 'A Fri morning reminder should return FALSE for a date range that starts on a Friday afternoon.');

        $result = $this->service->isWeekdayInRange(5, new DateTime('2025-02-07T15:00:00Z') /*Fri*/, new DateTime('2025-02-09'), '21:00:00Z');
        $this->assertSame($result, true, 'A Fri night reminder should return TRUE for a date range that starts on a Friday afternoon.');

        $result = $this->service->isWeekdayInRange(7, new DateTime('2025-02-07'), new DateTime('2025-02-09T15:00:00Z') /*Sun*/, '21:00:00Z');
        $this->assertSame($result, false, 'A Sun night reminder should return FALSE for a date range that ends on a Sunday afternoon.');

        $result = $this->service->isWeekdayInRange(7, new DateTime('2025-02-07'), new DateTime('2025-02-09T15:00:00Z') /*Sun*/, '09:00:00Z');
        $this->assertSame($result, true, 'A Sun morning reminder should return TRUE for a date range that ends on a Sunday afternoon.');
    }

    public function testIsNthDayInRange()
    {
        $result = $this->service->isNthDayInRange(4, new DateTime('2025-01-15'), new DateTime('2025-01-16'), new DateTime('2025-01-18'));
        $this->assertSame($result, false, 'The reminder reoccurs on 1/19, so it should return FALSE for 1/16-1/18.');

        $result = $this->service->isNthDayInRange(4, new DateTime('2025-01-15'), new DateTime('2025-01-20'), new DateTime('2025-01-21'));
        $this->assertSame($result, false, 'The reminder reoccurs on 1/19, so it should return FALSE for 1/20-1/21.');

        $result = $this->service->isNthDayInRange(4, new DateTime('2025-01-15'), new DateTime('2025-01-19'), new DateTime('2025-01-21'));
        $this->assertSame($result, true, 'The reminder reoccurs on the same date as the starting date of the range, so this should return TRUE.');

        $result = $this->service->isNthDayInRange(4, new DateTime('2025-01-15'), new DateTime('2025-01-20'), new DateTime('2025-01-23'));
        $this->assertSame($result, true, 'The reminder reoccurs on the same date as the ending date of the range, so this should return TRUE.');

        $result = $this->service->isNthDayInRange(4, new DateTime('2025-01-15'), new DateTime('2025-01-25'), new DateTime('2025-01-30'));
        $this->assertSame($result, true, 'The reminder reoccurs on 1/27, so it should return TRUE for 1/25-1/30.');

        // time-specific
        $result = $this->service->isNthDayInRange(3, new DateTime('2025-01-12T12:00:00'), new DateTime('2025-01-12T18:00:00'), new DateTime('2025-01-15'));
        $this->assertSame($result, false, 'The reminder reoccurs immediately before the start (same day) as well as after the end of the date range (same day), so it should return FALSE.');

        $result = $this->service->isNthDayInRange(3, new DateTime('2025-01-12T23:59:59'), new DateTime('2025-01-16'), new DateTime('2025-01-18T23:59:00'));
        $this->assertSame($result, false, 'The reminder reoccurs immediately before the start as well as immediately after the end of the date range, so it should return FALSE.');

        $result = $this->service->isNthDayInRange(3, new DateTime('2025-01-12T23:59:59'), new DateTime('2025-01-16'), new DateTime('2025-01-19'));
        $this->assertSame($result, true, 'The reminder reoccurs right before the date range ends, so it should return TRUE.');

        $result = $this->service->isNthDayInRange(3, new DateTime('2025-01-12T00:00:01'), new DateTime('2025-01-12'), new DateTime('2025-01-15'));
        $this->assertSame($result, true, 'The reminder starts right after the date range starts, so it should return TRUE');
    }


    public function testIsDayInRange()
    {
        $result = $this->service->isDayInRange(25, new DateTime('2025-03-20'), new DateTime('2025-04-02'));
        $this->assertSame($result, true, 'The reminder occurs on 3/25, so it should return TRUE for 3/20-4/2.');

        $result = $this->service->isDayInRange(25, new DateTime('2025-03-25'), new DateTime('2025-04-02'));
        $this->assertSame($result, true, 'The reminder occurs on 3/25, so it should return TRUE for 3/25-4/2.');

        $result = $this->service->isDayInRange(25, new DateTime('2025-02-26'), new DateTime('2025-03-25'));
        $this->assertSame($result, true, 'The reminder occurs on 3/25, so it should return TRUE for 2/26-3/25.');

        $result = $this->service->isDayInRange(25, new DateTime('2025-03-20'), new DateTime('2025-03-30'));
        $this->assertSame($result, true, 'The reminder occurs on 3/25, so it should return TRUE for 3/20-3/30.');

        $result = $this->service->isDayInRange(25, new DateTime('2025-03-20'), new DateTime('2026-03-30'));
        $this->assertSame($result, true, 'The reminder occurs on 3/25, so it should return TRUE for 3/20/25-3/30/26.');

        $result = $this->service->isDayInRange(25, new DateTime('2025-02-26'), new DateTime('2025-03-24'));
        $this->assertSame($result, false, 'The reminder occurs on 3/25, so it should return FALSE for 2/26-3/24.');

        $result = $this->service->isDayInRange(25, new DateTime('2025-03-26'), new DateTime('2025-04-24'));
        $this->assertSame($result, false, 'The reminder occurs on the 25th of every month, so it should return FALSE for 3/26-4/24.');

        $result = $this->service->isDayInRange(25, new DateTime('2025-03-26'), new DateTime('2026-02-24'));
        $this->assertSame($result, true, 'The reminder occurs on the 25th of every month, so it should return TRUE here.');

        $result = $this->service->isDayInRange(25, new DateTime('2025-12-26'), new DateTime('2026-01-24'));
        $this->assertSame($result, false, 'The reminder occurs on 12/25, so it should return FALSE for 12/26-1/24.');

        $result = $this->service->isDayInRange(25, new DateTime('2025-12-24'), new DateTime('2026-01-24'));
        $this->assertSame($result, true, 'The reminder occurs on 12/25, so it should return TRUE for 12/24-1/24.');

        $result = $this->service->isDayInRange(30, new DateTime('2025-01-31'), new DateTime('2026-02-28'));
        $this->assertSame($result, true, 'The reminder will occur on 2/28 because that is the last possible date in February 2026.');

        $result = $this->service->isDayInRange(31, new DateTime('2025-02-24'), new DateTime('2025-02-28')); // 28 days in Feb 2025
        $this->assertSame($result, true, 'If the date range includes months that don\t go up to the day, it should return TRUE as long as the date range includes the last day of the month.');

        $result = $this->service->isDayInRange(29, new DateTime('2025-02-24'), new DateTime('2025-02-28')); // 28 days in Feb 2025
        $this->assertSame($result, true, 'If the date range includes months that don\t go up to the day, it should return TRUE as long as the date range includes the last day of the month.');

        $result = $this->service->isDayInRange(31, new DateTime('2025-02-24'), new DateTime('2025-02-28')); // 28 days in Feb 2025
        $this->assertSame($result, true, 'If the date range includes months that don\t go up to the day, it should return TRUE as long as the date range includes the last day of the month.');

        // time-specific
        $result = $this->service->isDayInRange(25, new DateTime('2025-12-26'), new DateTime('2025-12-27'), '23:59:59Z');
        $this->assertSame($result, false, 'The reminder occurs a second before the date range with length 1 day starts, so it should return FALSE.');

        $result = $this->service->isDayInRange(25, new DateTime('2025-12-26'), new DateTime('2026-01-26'), '23:59:59Z');
        $this->assertSame($result, true, 'The reminder occurs a second before the date range with length 1 month starts, so it should return TRUE.');

        $result = $this->service->isDayInRange(25, new DateTime('2025-12-26'), new DateTime('2025-12-27'), '23:59:59Z');
        $this->assertSame($result, false, 'The reminder occurs a second before the date range with length 1 day starts, so it should return FALSE.');

        $result = $this->service->isDayInRange(25, new DateTime('2025-12-25'), new DateTime('2025-12-26'), '00:00:01Z');
        $this->assertSame($result, true, 'The reminder occurs a second after the date range starts, so it should return TRUE.');

        $result = $this->service->isDayInRange(25, new DateTime('2025-12-25'), new DateTime('2026-01-26'), '00:00:01Z');
        $this->assertSame($result, true, 'The reminder occurs a second after the date range starts, so it should return TRUE.');

        $result = $this->service->isDayInRange(30, new DateTime('2025-12-25'), new DateTime('2025-12-30T00:00:02Z'), '00:00:01Z');
        $this->assertSame($result, true, 'The reminder occurs a second before the date range ends, so it should return TRUE.');

        $result = $this->service->isDayInRange(4, new DateTime('2025-12-25'), new DateTime('2026-01-04T12:00:01Z'), '12:00:00Z');
        $this->assertSame($result, true, 'The reminder occurs a second before the date range ends, so it should return TRUE.');

        $result = $this->service->isDayInRange(25, new DateTime('2025-12-25T12:00:00Z'), new DateTime('2026-01-01'), '06:00:00Z');
        $this->assertSame($result, false, 'The reminder occurs the same day but earlier time than the start, so it should return FALSE.');
    }

    public function testGetFullDaysInDateRange() 
    {
        $result = $this->service->getFullDaysInDateRange(new DateTime('2025-01-01'), new DateTime('2025-01-01'));
        $this->assertSame($result, 0);

        $result = $this->service->getFullDaysInDateRange(new DateTime('2025-01-01'), new DateTime('2025-01-02'));
        $this->assertSame($result, 1);

        $result = $this->service->getFullDaysInDateRange(new DateTime('2025-01-01'), new DateTime('2025-01-31'));
        $this->assertSame($result, 30);
    }

    public function testIsRangeInSameMonthAndYr() 
    {
        $result = $this->service->isRangeInSameMonthAndYr(new DateTime('2025-01-01'), new DateTime('2025-01-01'));
        $this->assertSame($result, true);

        $result = $this->service->isRangeInSameMonthAndYr(new DateTime('2025-01-01'), new DateTime('2025-01-31'));
        $this->assertSame($result, true);

        $result = $this->service->isRangeInSameMonthAndYr(new DateTime('2025-01-31'), new DateTime('2025-02-01'));
        $this->assertSame($result, false);

        $result = $this->service->isRangeInSameMonthAndYr(new DateTime('2025-01-01'), new DateTime('2026-01-01'));
        $this->assertSame($result, false);
    }

}
