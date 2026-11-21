<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Status of a measurement set (spec sections 6.4 and 7.4). Only published, not superseded sets reach the client
 * portal and the charts.
 */
enum MeasurementSetStatus: string
{
    case PendingReview = 'pending_review';
    case Published = 'published';
}
