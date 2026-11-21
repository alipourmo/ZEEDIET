<?php

declare(strict_types=1);

namespace App\Services\Sms;

/**
 * One message for a gateway. The body is what is sent. The logged body is what may be written to a log: for the OTP
 * it has the code redacted.
 */
final readonly class SmsMessage
{
    public function __construct(
        public string $mobile,
        public string $body,
        public string $loggedBody,
    ) {}
}
