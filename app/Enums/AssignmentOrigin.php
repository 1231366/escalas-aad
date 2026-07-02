<?php

namespace App\Enums;

enum AssignmentOrigin: string
{
    case Generated = 'GENERATED';
    case Swap = 'SWAP';
    case Manual = 'MANUAL';
    case Vacation = 'VACATION';
}
