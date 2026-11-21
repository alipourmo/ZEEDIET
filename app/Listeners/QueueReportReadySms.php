<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\SmsTemplateKey;
use App\Events\ReportPublished;
use App\Services\Sms\SmsDispatcher;

/**
 * The report_ready SMS (spec section 9). The text carries no measurement or medical detail.
 */
final class QueueReportReadySms
{
    public function __construct(private readonly SmsDispatcher $sms) {}

    public function handle(ReportPublished $event): void
    {
        $set = $event->set->loadMissing('client');
        $client = $set->client;

        $this->sms->queue(SmsTemplateKey::ReportReady, $client->mobile, [
            'name' => $client->first_name,
            'clinic' => (string) config('clinic.name'),
        ], $set);
    }
}
