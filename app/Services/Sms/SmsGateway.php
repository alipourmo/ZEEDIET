<?php

declare(strict_types=1);

namespace App\Services\Sms;

/**
 * A provider that sends SMS (spec section 9). A gateway makes one attempt. Retries belong to SendSmsJob.
 */
interface SmsGateway
{
    public function name(): string;

    public function send(SmsMessage $message): SmsResult;
}
