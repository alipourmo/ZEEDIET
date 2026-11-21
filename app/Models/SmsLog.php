<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SmsStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * One SMS send, from queued to sent or failed (spec section 9). The body is stored as it was sent, except that an
 * OTP's code is redacted. Rows are updated as the job runs, so this table is not append-only.
 */
class SmsLog extends Model
{
    protected $table = 'sms_log';

    /** @var list<string> */
    protected $fillable = [
        'mobile',
        'template_key',
        'body',
        'provider',
        'provider_message_id',
        'status',
        'error',
        'related_type',
        'related_id',
        'attempts',
        'scheduled_at',
        'sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SmsStatus::class,
            'attempts' => 'integer',
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }
}
