<?php

namespace Tests\Unit;

use App\Services\DateTimeService;
use Tests\TestCase;
use DateTime;
use Mockery;

class DateTimeServiceTest extends TestCase
{
    public function testGetDateFormat()
    {
		$this->assertEquals("Ymd\THis\Z", DateTimeService::getDateFormat());
    }

	public function testTransformIntoRFC3339()
	{
		$datetime = "20251205T105839Z";
		$rfc3339 = DateTimeService::transformIntoRFC3339($datetime);
		$this->assertEquals("2025-12-05T10:58:39+00:00", $rfc3339);
	}
}