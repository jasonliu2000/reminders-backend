<?php

namespace App\Enums;

enum ReminderRecurrenceType: string
{
    case DAILY = 'daily';
    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';
    case EVERY_N_DAYS = 'every_n_days';
    case NONE = 'none';
}
