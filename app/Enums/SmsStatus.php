<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where an SMS is in its life (spec section 9): queued, then sent, or failed after its retries.
 */
enum SmsStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';

    public function label(): string
    {
        return __('sms.status.'.$this->value);
    }
}
