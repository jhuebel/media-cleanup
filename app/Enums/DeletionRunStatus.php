<?php

namespace App\Enums;

enum DeletionRunStatus: string
{
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
}
