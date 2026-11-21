<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\SmsTemplateKey;
use App\Models\SmsTemplate;
use Illuminate\Database\Seeder;

/**
 * The default SMS templates (spec section 9). Idempotent: an existing template keeps its edited text.
 */
class SmsTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach (SmsTemplateKey::cases() as $key) {
            SmsTemplate::query()->firstOrCreate(
                ['key' => $key->value],
                ['body' => $key->defaultBody(), 'is_active' => true],
            );
        }
    }
}
