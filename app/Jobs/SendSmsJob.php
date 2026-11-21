<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\SmsStatus;
use App\Models\SmsLog;
use App\Services\Sms\SmsGateway;
use App\Services\Sms\SmsMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Sends one queued SMS (spec section 9). Three retries after the first attempt, with backoff of 1, 5 and 15 minutes.
 * A send that is already sent is skipped, so a repeated job does nothing.
 */
final class SendSmsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 4;

    /** @var list<int> Seconds to wait before each retry: 1, 5 and 15 minutes. */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly int $logId,
        public readonly string $mobile,
        public readonly string $body,
    ) {}

    public function handle(SmsGateway $gateway): void
    {
        $log = SmsLog::query()->find($this->logId);

        if ($log === null || $log->currentStatus() === SmsStatus::Sent) {
            return;
        }

        $log->forceFill(['attempts' => $log->attempts + 1])->save();

        $result = $gateway->send(new SmsMessage($this->mobile, $this->body, $log->body));

        if (! $result->ok) {
            throw new RuntimeException('SMS not sent: '.$result->error);
        }

        $log->forceFill([
            'status' => SmsStatus::Sent,
            'provider' => $gateway->name(),
            'provider_message_id' => $result->providerMessageId,
            'sent_at' => now(),
            'error' => null,
        ])->save();
    }

    /**
     * After the last attempt, the row is marked failed. The provider's error is kept; the text is not.
     */
    public function failed(Throwable $exception): void
    {
        SmsLog::query()->whereKey($this->logId)->update([
            'status' => SmsStatus::Failed->value,
            'error' => Str::limit($exception->getMessage(), 480),
        ]);
    }
}
