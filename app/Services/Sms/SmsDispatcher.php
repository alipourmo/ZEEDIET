<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Enums\SmsStatus;
use App\Enums\SmsTemplateKey;
use App\Jobs\SendSmsJob;
use App\Models\SmsLog;
use App\Models\SmsTemplate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Renders a template, writes sms_log as queued and queues the send (spec section 9). Duplicates for the same record
 * and template within ten minutes are suppressed. Marketing messages wait for the quiet hours to end.
 */
final class SmsDispatcher
{
    public const DUPLICATE_WINDOW_MINUTES = 10;

    /**
     * Queues a template message. Returns null when the template is switched off, or when the same template already
     * went out for the same record in the duplicate window.
     *
     * @param  array<string, string>  $vars  Placeholder values, for example ['name' => 'سارا'].
     */
    public function queue(SmsTemplateKey $key, string $mobile, array $vars, ?Model $related = null): ?SmsLog
    {
        if ($related !== null && $this->isDuplicate($key->value, $related::class, (int) $related->getKey())) {
            return null;
        }

        $template = SmsTemplate::query()->where('key', $key->value)->first();

        if ($template !== null && ! $template->is_active) {
            return null;
        }

        $source = $template !== null ? $template->body : $key->defaultBody();
        $body = $this->render($source, $vars);
        $logged = $key->redactsCode()
            ? $this->render($source, array_merge($vars, ['code' => '[کد]']))
            : $body;

        return $this->enqueue(
            $mobile,
            $body,
            $logged,
            $key->value,
            $related === null ? null : $related::class,
            $related === null ? null : (int) $related->getKey(),
            $key->isMarketing(),
        );
    }

    /**
     * Sends a failed message again as a new log row. Refused when the message is not failed, or for an OTP, whose code
     * is not stored (a new code is requested instead).
     */
    public function resend(SmsLog $log): SmsLog
    {
        if ($log->currentStatus() !== SmsStatus::Failed) {
            throw ValidationException::withMessages(['status' => __('sms.errors.not_failed')]);
        }

        $key = $log->template_key === null ? null : SmsTemplateKey::tryFrom($log->template_key);

        if ($key?->redactsCode() === true) {
            throw ValidationException::withMessages(['status' => __('sms.errors.otp_not_resendable')]);
        }

        return $this->enqueue(
            $log->mobile,
            $log->body,
            $log->body,
            $log->template_key,
            $log->related_type,
            $log->related_id,
            $key?->isMarketing() ?? false,
        );
    }

    /**
     * @param  array<string, string>  $vars
     */
    public function render(string $body, array $vars): string
    {
        $map = [];

        foreach ($vars as $name => $value) {
            $map['{'.$name.'}'] = $value;
        }

        return strtr($body, $map);
    }

    private function enqueue(
        string $mobile,
        string $body,
        string $logged,
        ?string $templateKey,
        ?string $relatedType,
        ?int $relatedId,
        bool $marketing,
    ): SmsLog {
        $availableAt = $marketing ? QuietHours::nextAllowed(CarbonImmutable::now('Asia/Tehran')) : null;

        /** @var SmsLog $log */
        $log = SmsLog::query()->create([
            'mobile' => $mobile,
            'template_key' => $templateKey,
            'body' => $logged,
            'provider' => (string) config('services.sms.driver'),
            'status' => SmsStatus::Queued,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'attempts' => 0,
            'scheduled_at' => $availableAt,
        ]);

        SendSmsJob::dispatch((int) $log->getKey(), $mobile, $body)->delay($availableAt);

        return $log;
    }

    private function isDuplicate(string $templateKey, string $relatedType, int $relatedId): bool
    {
        return SmsLog::query()
            ->where('template_key', $templateKey)
            ->where('related_type', $relatedType)
            ->where('related_id', $relatedId)
            ->where('created_at', '>=', now()->subMinutes(self::DUPLICATE_WINDOW_MINUTES))
            ->exists();
    }
}
