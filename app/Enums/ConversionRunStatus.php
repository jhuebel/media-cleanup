<?php

namespace App\Enums;

enum ConversionRunStatus: string
{
    case Scanning = 'scanning';
    case Running = 'running';
    case Completed = 'completed';
    case CompletedWithErrors = 'completed_with_errors';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
