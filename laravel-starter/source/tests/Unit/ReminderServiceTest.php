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
        // $mockReminder = Mockery::mock('alias:App\Models\Reminder');
        // $mockReminder->shouldReceive('where')
        //     ->with('start_date', '<=', '2025-06-01')
        //     ->once()
        //     ->andReturnSelf();
        // $mockReminder->shouldReceive('get')
        //     ->once()
        //     ->andReturn(collect([
        //         (object) ['id' => 1, 'start_date' => '2025-01-10', 'name' => 'Reminder A'],
        //         (object) ['id' => 2, 'start_date' => '2025-01-12', 'name' => 'Reminder B'],
        //     ]));

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

        // Daily reminders
        $result = $this->service->isReminderInRange($dailyReminder, new DateTime('2025-07-01'), new DateTime('2025-08-01'));
        $this->assertSame($result, true, 'Daily reminders starting before the date range should always return TRUE.');

        // Invalid reminders
        $result = $this->service->isReminderInRange($reminderWithInvalidRecurrence, new DateTime('2025-12-24'), new DateTime('2026-01-24'));
        $this->assertSame($result, false, '');

        $result = $this->service->isReminderInRange($reminderWithInvalidStart, new DateTime('2025-12-24'), new DateTime('2026-01-24'));
        $this->assertSame($result, false, '');
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

        $result = $this->service->isDayInRange(31, new DateTime('2025-02-24'), new DateTime('2025-02-28')); // 28 days in Feb 2025
        $this->assertSame($result, true, 'If the date range includes months that don\t go up to the day, it should return TRUE as long as the date range includes the last day of the month.');

        $result = $this->service->isDayInRange(29, new DateTime('2025-02-24'), new DateTime('2025-02-28')); // 28 days in Feb 2025
        $this->assertSame($result, true, 'If the date range includes months that don\t go up to the day, it should return TRUE as long as the date range includes the last day of the month.');

        $result = $this->service->isDayInRange(31, new DateTime('2025-02-24'), new DateTime('2025-02-28')); // 28 days in Feb 2025
        $this->assertSame($result, true, 'If the date range includes months that don\t go up to the day, it should return TRUE as long as the date range includes the last day of the month.');
    }

    public function testGetDaysInDateRange() 
    {
        $result = $this->service->getDaysInDateRange(new DateTime('2025-01-01'), new DateTime('2025-01-01'));
        $this->assertSame($result, 0);

        $result = $this->service->getDaysInDateRange(new DateTime('2025-01-01'), new DateTime('2025-01-02'));
        $this->assertSame($result, 1);

        $result = $this->service->getDaysInDateRange(new DateTime('2025-01-01'), new DateTime('2025-01-31'));
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
