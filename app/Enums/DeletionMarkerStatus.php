<?php

namespace App\Enums;

enum DeletionMarkerStatus: string
{
    case Ok = 'ok';
    case BadValue = 'bad_value';
}
