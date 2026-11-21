<?php

declare(strict_types=1);

namespace App\Services\Portal;

use App\Enums\ClientStatus;
use App\Enums\SmsTemplateKey;
use App\Models\Client;
use App\Models\OtpCode;
use App\Services\Sms\SmsDispatcher;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * One-time codes for the client portal (spec section 7.5).
 *
 * The request step answers the same way whether or not the mobile belongs to a client: the controller never learns
 * which it was. A code is sent only to a known, active client, and only within the request limits.
 */
final class OtpService
{
    public function __construct(private readonly SmsDispatcher $sms) {}

    /**
     * Asks for a code. Returns nothing: the caller shows the same message in every case.
     */
    public function request(string $mobile, string $ip): void
    {
        $mobileKey = 'portal-otp|mobile|'.$mobile;
        $ipKey = 'portal-otp|ip|'.$ip;

        $limited = RateLimiter::tooManyAttempts($mobileKey, (int) config('clinic.portal.otp_mobile_requests'))
            || RateLimiter::tooManyAttempts($ipKey, (int) config('clinic.portal.otp_ip_requests'));

        // Every request counts, including the refused ones, so a flood stays refused until the window clears.
        RateLimiter::hit($mobileKey, (int) config('clinic.portal.otp_mobile_decay_minutes') * 60);
        RateLimiter::hit($ipKey, (int) config('clinic.portal.otp_ip_decay_minutes') * 60);

        if ($limited) {
            return;
        }

        $code = str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);

        // Hashed for every number, known or not, so the response time does not reveal whether a client owns it.
        $hash = Hash::make($code);

        $client = Client::query()->where('mobile', $mobile)->first();

        if (! $client instanceof Client || ! $this->isActive($client)) {
            return;
        }

        // A newer code replaces any older one that is still valid.
        OtpCode::query()
            ->where('mobile', $mobile)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        OtpCode::query()->create([
            'mobile' => $mobile,
            'code_hash' => $hash,
            'expires_at' => now()->addMinutes((int) config('clinic.portal.otp_ttl_minutes')),
            'attempts' => 0,
            'ip' => $ip,
        ]);

        $this->sms->queue(SmsTemplateKey::Otp, $mobile, [
            'code' => $code,
            'clinic' => (string) config('clinic.name'),
        ]);
    }

    /**
     * Checks a code. Returns the client when the code is right, the code has not expired, and it had fewer than five
     * wrong tries. Every other case returns null, and a wrong try is counted on the newest valid code.
     */
    public function verify(string $mobile, string $code): ?Client
    {
        $otp = OtpCode::query()
            ->where('mobile', $mobile)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $otp instanceof OtpCode) {
            return null;
        }

        $maxAttempts = (int) config('clinic.portal.otp_max_wrong_attempts');

        if ($otp->attempts >= $maxAttempts) {
            $otp->forceFill(['consumed_at' => now()])->save();

            return null;
        }

        if (! Hash::check($code, (string) $otp->code_hash)) {
            $attempts = $otp->attempts + 1;
            $otp->forceFill([
                'attempts' => $attempts,
                'consumed_at' => $attempts >= $maxAttempts ? now() : null,
            ])->save();

            return null;
        }

        $client = Client::query()->where('mobile', $mobile)->first();

        // The code is spent whether or not the client may sign in, so a code is never tried twice.
        $otp->forceFill(['consumed_at' => now()])->save();

        if (! $client instanceof Client || ! $this->isActive($client)) {
            return null;
        }

        return $client;
    }

    /** Larastan reads the cast column as a string, so the status is compared through getAttribute(). */
    private function isActive(Client $client): bool
    {
        return $client->getAttribute('status') === ClientStatus::Active;
    }
}
