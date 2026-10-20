<?php

namespace App\Enums;

enum RoleName: string
{
    case SUPERADMIN = 'Superadmin';
    case MANAGER    = 'Manager';
    case PARTNER    = 'Partner';
}
