<?php

declare(strict_types=1);

namespace App\Services\Sms;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Kavenegar over HTTP. One attempt with a timeout, and no retry inside the gateway (spec section 9). The API key is
 * part of the URL, so this class never logs the URL or the response body.
 */
final class KavenegarGateway implements SmsGateway
{
    private const ENDPOINT = 'https://api.kavenegar.com/v1/%s/sms/send.json';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $sender,
    ) {}

    public function name(): string
    {
        return 'kavenegar';
    }

    public function send(SmsMessage $message): SmsResult
    {
        if ($this->apiKey === '') {
            return SmsResult::failed('kavenegar_not_configured');
        }

        try {
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->asForm()
                ->post(sprintf(self::ENDPOINT, $this->apiKey), [
                    'receptor' => $message->mobile,
                    'sender' => $this->sender,
                    'message' => $message->body,
                ]);
        } catch (ConnectionException) {
            return SmsResult::failed('connection');
        }

        $status = (int) data_get($response->json(), 'return.status', 0);

        if ($response->successful() && $status === 200) {
            return SmsResult::sent((string) data_get($response->json(), 'entries.0.messageid', ''));
        }

        return SmsResult::failed('provider_status_'.$status);
    }
}
