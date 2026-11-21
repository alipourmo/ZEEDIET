<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Audit actions (spec section 6.5). The first eight are from the spec table. The two password actions are
 * added for spec section 7.8 ("any password reset").
 */
enum AuditAction: string
{
    case Login = 'login';
    case Logout = 'logout';
    case View = 'view';
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
    case Publish = 'publish';
    case Export = 'export';
    case PasswordChange = 'password_change';
    case PasswordReset = 'password_reset';
    // A device session refused because the client has a contraindication for the device (spec 7.3).
    case SessionRefused = 'session_refused';
}
