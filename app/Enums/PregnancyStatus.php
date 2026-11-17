<?php

declare(strict_types=1);

namespace App\Enums;

enum PregnancyStatus: string
{
    case None = 'none';
    case Pregnant = 'pregnant';
    case Breastfeeding = 'breastfeeding';
}
