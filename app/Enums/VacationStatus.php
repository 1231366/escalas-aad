<?php

namespace App\Enums;

enum VacationStatus: string
{
    case Pending = 'PENDING';
    case Approved = 'APPROVED';
    case Declined = 'DECLINED';
    case Cancelled = 'CANCELLED';
}
