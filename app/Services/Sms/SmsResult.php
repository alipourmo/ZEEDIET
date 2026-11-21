<?php

declare(strict_types=1);

namespace App\Services\Sms;

/**
 * What a gateway reports back for one send attempt.
 */
final readonly class SmsResult
{
    private function __construct(
        public bool $ok,
        public ?string $providerMessageId,
        public ?string $error,
    ) {}

    public static function sent(string $providerMessageId): self
    {
        return new self(true, $providerMessageId, null);
    }

    public static function failed(string $error): self
    {
        return new self(false, null, $error);
    }
}
