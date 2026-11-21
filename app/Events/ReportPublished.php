<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\MeasurementSet;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A measurement set was published (spec 7.4). S5 attaches the report_ready SMS to this event. Nothing listens yet.
 */
final class ReportPublished
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly MeasurementSet $set) {}
}
