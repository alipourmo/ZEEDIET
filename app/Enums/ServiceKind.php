<?php

declare(strict_types=1);

namespace App\Enums;

/** Kinds of service (spec section 6.2). */
enum ServiceKind: string
{
    case Consultation = 'consultation';
    case BodyAnalysis = 'body_analysis';
    case DeviceSession = 'device_session';
}
