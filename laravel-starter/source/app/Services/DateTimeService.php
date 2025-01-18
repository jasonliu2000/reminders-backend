<?php

namespace App\Services;

use DateTime;

class DateTimeService
{
    /**
     * Get date format string that should be used to validate all date inputs
     */
    public static function getDateFormat(): string
    {
        // This will enforce API requests to comply with UTC time zone in basic ISO 8601 format (ex. 20250101T000000Z)
        return "Ymd\THis\Z";
    }

    /**
     * Transform date string into RFC3339 format
     * 
     * @param string $datetime - the datetime string in basic ISO 8601 format (ex. 20250101T000000Z)
     */
    public static function transformIntoRFC3339(string $datetime): string
    {
        return DateTime::createFromFormat(self::getDateFormat(), $datetime)->format(DateTime::RFC3339);
    }
}