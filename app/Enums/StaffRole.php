<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Staff roles (spec section 5). The permissions for each role are in config/permissions.php.
 */
enum StaffRole: string
{
    case Admin = 'admin';
    case Secretary = 'secretary';
    case Operator = 'operator';
    case Specialist = 'specialist';
}
