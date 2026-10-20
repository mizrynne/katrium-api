<?php

declare(strict_types=1);

namespace App\Enums;

enum GuestTag: string
{
    case Regular = 'regular';
    case Vip = 'vip';
    case Blacklisted = 'blacklisted';
}
