<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Support\Mobile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Writes messages to storage/logs/sms.log, for local development and tests (spec 7.5). The mobile is masked and the
 * OTP code is redacted. In production this gateway refuses to send, so no message file appears there.
 */
final class LogGateway implements SmsGateway
{
    public function name(): string
    {
        return 'log';
    }

    public function send(SmsMessage $message): SmsResult
    {
        if (app()->isProduction()) {
            return SmsResult::failed('log_gateway_in_production');
        }

        Log::channel('sms')->info('sms', [
            'to' => Mobile::mask($message->mobile),
            'body' => $message->loggedBody,
        ]);

        return SmsResult::sent('log-'.Str::uuid()->toString());
    }
}
