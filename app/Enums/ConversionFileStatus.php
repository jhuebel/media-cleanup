<?php

namespace App\Enums;

enum ConversionFileStatus: string
{
    case Pending = 'pending';
    case Converting = 'converting';
    case Moving = 'moving';
    case Done = 'done';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case WouldConvert = 'would_convert';
    case Cancelled = 'cancelled';
}
