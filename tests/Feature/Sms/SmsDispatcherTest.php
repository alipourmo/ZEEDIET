<?php

declare(strict_types=1);

use App\Enums\SmsStatus;
use App\Enums\SmsTemplateKey;
use App\Jobs\SendSmsJob;
use App\Models\Client;
use App\Models\SmsLog;
use App\Models\SmsTemplate;
use App\Services\Sms\KavenegarGateway;
use App\Services\Sms\LogGateway;
use App\Services\Sms\QuietHours;
use App\Services\Sms\SmsDispatcher;
use App\Services\Sms\SmsGateway;
use App\Services\Sms\SmsMessage;
use App\Services\Sms\SmsResult;
use App\Support\Mobile;
use Carbon\CarbonImmutable;
use Database\Seeders\SmsTemplateSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    // A recording gateway. Each send is kept in $sent and can be made to fail with $error.
    $this->gateway = new class implements SmsGateway
    {
        /** @var list<SmsMessage> */
        public array $sent = [];

        public ?string $error = null;

        public function name(): string
        {
            return 'fake';
        }

        public function send(SmsMessage $message): SmsResult
        {
            $this->sent[] = $message;

            return $this->error === null
                ? SmsResult::sent('fake-'.count($this->sent))
                : SmsResult::failed($this->error);
        }
    };

    app()->instance(SmsGateway::class, $this->gateway);
    $this->client = Client::factory()->create(['mobile' => '09121234567']);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

test('a queued SMS is logged as queued, then sent with the provider id and the rendered body', function () {
    $log = app(SmsDispatcher::class)->queue(SmsTemplateKey::Welcome, '09121234567', [
        'name' => 'سارا',
        'clinic' => 'زیدیت',
    ])->fresh();

    expect($log->status)->toBe(SmsStatus::Sent)
        ->and($log->provider)->toBe('fake')
        ->and($log->provider_message_id)->toBe('fake-1')
        ->and($log->attempts)->toBe(1)
        ->and($log->sent_at)->not->toBeNull()
        ->and($log->body)->toBe('سارا عزیز، به زیدیت خوش آمدید. پرونده شما ایجاد شد.');

    expect($this->gateway->sent)->toHaveCount(1)
        ->and($this->gateway->sent[0]->mobile)->toBe('09121234567');
});

test('the same template for the same record is suppressed inside ten minutes, and allowed after', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-18 10:00', 'Asia/Tehran'));
    $dispatcher = app(SmsDispatcher::class);
    $vars = ['name' => 'سارا', 'service' => 'آنالیز بدن', 'date' => '۱ آذر', 'time' => '۱۰:۰۰', 'clinic' => 'زیدیت'];

    expect($dispatcher->queue(SmsTemplateKey::AppointmentConfirmed, '09121234567', $vars, $this->client))->not->toBeNull();

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-18 10:09', 'Asia/Tehran'));
    expect($dispatcher->queue(SmsTemplateKey::AppointmentConfirmed, '09121234567', $vars, $this->client))->toBeNull();

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-18 10:11', 'Asia/Tehran'));
    expect($dispatcher->queue(SmsTemplateKey::AppointmentConfirmed, '09121234567', $vars, $this->client))->not->toBeNull();

    expect(SmsLog::query()->count())->toBe(2);
});

test('a switched-off template sends nothing', function () {
    SmsTemplate::query()->create(['key' => 'welcome', 'body' => 'hi', 'is_active' => false]);

    $log = app(SmsDispatcher::class)->queue(SmsTemplateKey::Welcome, '09121234567', ['name' => 'x', 'clinic' => 'y']);

    expect($log)->toBeNull()
        ->and(SmsLog::query()->count())->toBe(0)
        ->and($this->gateway->sent)->toBe([]);
});

test('an edited active template is used in place of the default text', function () {
    SmsTemplate::query()->create(['key' => 'welcome', 'body' => '{name} خوش آمدی', 'is_active' => true]);

    $log = app(SmsDispatcher::class)->queue(SmsTemplateKey::Welcome, '09121234567', ['name' => 'سارا', 'clinic' => 'x']);

    expect($log->body)->toBe('سارا خوش آمدی');
});

test('an OTP is sent with its code, but the log keeps no code', function () {
    $log = app(SmsDispatcher::class)->queue(SmsTemplateKey::Otp, '09121234567', ['code' => '48213', 'clinic' => 'زیدیت']);

    expect($this->gateway->sent[0]->body)->toContain('48213')
        ->and($this->gateway->sent[0]->loggedBody)->not->toContain('48213')
        ->and($log->fresh()->body)->not->toContain('48213')
        ->and($log->fresh()->body)->toContain('[کد]');
});

test('a failed attempt is kept as failed after the job gives up, and the job does not send twice', function () {
    $this->gateway->error = 'provider_status_400';
    $log = SmsLog::query()->create([
        'mobile' => '09121234567',
        'template_key' => 'welcome',
        'body' => 'hi',
        'provider' => 'fake',
        'status' => SmsStatus::Queued,
        'attempts' => 0,
    ]);

    $job = new SendSmsJob($log->getKey(), '09121234567', 'hi');

    expect(fn () => $job->handle($this->gateway))->toThrow(RuntimeException::class);

    $job->failed(new RuntimeException('SMS not sent: provider_status_400'));

    $log->refresh();
    expect($log->status)->toBe(SmsStatus::Failed)
        ->and($log->attempts)->toBe(1)
        ->and($log->error)->toContain('provider_status_400');

    // A sent row is not sent again when its job runs a second time.
    $this->gateway->error = null;
    $sent = SmsLog::query()->create(['mobile' => '09121234567', 'body' => 'hi', 'provider' => 'fake', 'status' => SmsStatus::Sent]);
    (new SendSmsJob($sent->getKey(), '09121234567', 'hi'))->handle($this->gateway);

    expect($this->gateway->sent)->toHaveCount(1);
});

test('resend queues a new row for a failed message, and refuses a sent one or an OTP', function () {
    $dispatcher = app(SmsDispatcher::class);

    $failed = SmsLog::query()->create([
        'mobile' => '09121234567',
        'template_key' => 'report_ready',
        'body' => 'گزارش آماده است',
        'provider' => 'fake',
        'status' => SmsStatus::Failed,
        'attempts' => 4,
    ]);
    $again = $dispatcher->resend($failed);

    expect($again->getKey())->not->toBe($failed->getKey())
        ->and($again->status)->toBe(SmsStatus::Sent)
        ->and($again->body)->toBe('گزارش آماده است')
        ->and($failed->fresh()->status)->toBe(SmsStatus::Failed);

    $sent = SmsLog::query()->create(['mobile' => '09121234567', 'body' => 'x', 'provider' => 'fake', 'status' => SmsStatus::Sent]);
    expect(fn () => $dispatcher->resend($sent))->toThrow(ValidationException::class);

    $otp = SmsLog::query()->create([
        'mobile' => '09121234567',
        'template_key' => 'otp',
        'body' => '[کد]',
        'provider' => 'fake',
        'status' => SmsStatus::Failed,
    ]);
    expect(fn () => $dispatcher->resend($otp))->toThrow(ValidationException::class);
});

test('a marketing-type message in quiet hours is held until 08:00 Tehran time', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-18 23:30', 'Asia/Tehran'));

    $log = app(SmsDispatcher::class)->queue(SmsTemplateKey::Welcome, '09121234567', ['name' => 'x', 'clinic' => 'y']);

    expect($log->scheduled_at->format('Y-m-d H:i'))->toBe('2026-11-19 08:00');
});

test('quiet hours cover 21:00 to 08:00 and nothing else', function (string $at, ?string $expected) {
    $now = CarbonImmutable::parse("2026-11-18 $at", 'Asia/Tehran');
    $next = QuietHours::nextAllowed($now);

    expect($next?->format('Y-m-d H:i'))->toBe($expected);
})->with([
    ['12:00', null],
    ['20:59', null],
    ['21:00', '2026-11-19 08:00'],
    ['07:59', '2026-11-18 08:00'],
    ['08:00', null],
]);

test('the mobile is masked to its last four digits', function () {
    expect(Mobile::mask('09121234567'))->toBe('•••••••4567');
});

test('the log gateway refuses to send in production, so no message is written there', function () {
    $original = app()['env'];
    app()['env'] = 'production';

    try {
        $result = (new LogGateway)->send(new SmsMessage('09121234567', 'x', 'x'));
    } finally {
        app()['env'] = $original;
    }

    expect($result->ok)->toBeFalse()
        ->and($result->error)->toBe('log_gateway_in_production');
});

test('kavenegar sends one HTTP request with the receptor, sender and text, and reports the provider id', function () {
    Http::fake([
        'api.kavenegar.com/*' => Http::response([
            'return' => ['status' => 200, 'message' => 'OK'],
            'entries' => [['messageid' => 991]],
        ], 200),
    ]);

    $result = (new KavenegarGateway('test-key', '10008663'))->send(new SmsMessage('09121234567', 'سلام', 'سلام'));

    expect($result->ok)->toBeTrue()
        ->and($result->providerMessageId)->toBe('991');

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request['receptor'] === '09121234567'
        && $request['sender'] === '10008663'
        && $request['message'] === 'سلام');
});

test('kavenegar reports a provider error without the response body, and refuses to send without a key', function () {
    Http::fake(['api.kavenegar.com/*' => Http::response(['return' => ['status' => 411]], 200)]);

    $failed = (new KavenegarGateway('test-key', '10008663'))->send(new SmsMessage('09121234567', 'x', 'x'));
    expect($failed->ok)->toBeFalse()->and($failed->error)->toBe('provider_status_411');

    $unset = (new KavenegarGateway('', '10008663'))->send(new SmsMessage('09121234567', 'x', 'x'));
    expect($unset->error)->toBe('kavenegar_not_configured');

    Http::assertSentCount(1);
});

test('the template seeder creates the seven templates once and keeps edited text', function () {
    $this->seed(SmsTemplateSeeder::class);
    SmsTemplate::query()->where('key', 'welcome')->update(['body' => 'edited']);

    $this->seed(SmsTemplateSeeder::class);

    expect(SmsTemplate::query()->count())->toBe(7)
        ->and(SmsTemplate::query()->where('key', 'welcome')->value('body'))->toBe('edited');
});
