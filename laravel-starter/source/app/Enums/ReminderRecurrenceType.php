<?php

namespace App\Enums;

enum ReminderRecurrenceType: string
{
    case DAILY = 'daily';
    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';
    case CUSTOM = 'custom';
    case NONE = 'none';
}
