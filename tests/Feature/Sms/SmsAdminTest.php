<?php

declare(strict_types=1);

use App\Enums\AuditAction;
use App\Enums\SmsStatus;
use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Models\SmsLog;
use App\Models\SmsTemplate;
use App\Models\User;
use Database\Seeders\SmsTemplateSeeder;

// SMS admin pages (spec sections 8.2 and 9): templates and resend are admin only; the log is admin and secretary.

function smsUser(StaffRole $role): User
{
    return User::factory()->role($role)->create(['is_active' => true]);
}

beforeEach(function () {
    $this->seed(SmsTemplateSeeder::class);
});

test('the admin sees the template list and the log', function () {
    $this->actingAs(smsUser(StaffRole::Admin))
        ->get(route('admin.sms.templates'))
        ->assertOk()
        ->assertSee('کد ورود');

    $this->actingAs(smsUser(StaffRole::Admin))
        ->get(route('admin.sms.log'))
        ->assertOk();
});

test('a secretary can read the SMS log but not the templates', function () {
    $secretary = smsUser(StaffRole::Secretary);

    $this->actingAs($secretary)->get(route('admin.sms.log'))->assertOk();
    $this->actingAs($secretary)->get(route('admin.sms.templates'))->assertForbidden();
    $this->actingAs($secretary)->get(route('admin.sms.templates.edit', SmsTemplate::query()->firstOrFail()))->assertForbidden();
});

test('a specialist or operator cannot open either page', function (StaffRole $role) {
    $user = smsUser($role);

    $this->actingAs($user)->get(route('admin.sms.log'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.sms.templates'))->assertForbidden();
})->with([StaffRole::Specialist, StaffRole::Operator]);

test('a guest is sent to log in', function () {
    $this->get(route('admin.sms.log'))->assertRedirect(route('login'));
    $this->get(route('admin.sms.templates'))->assertRedirect(route('login'));
});

test('the admin edits a template, the change is audited with old and new text', function () {
    $admin = smsUser(StaffRole::Admin);
    $template = SmsTemplate::query()->where('key', 'welcome')->firstOrFail();
    $original = $template->body;

    $this->actingAs($admin)
        ->put(route('admin.sms.templates.update', $template), [
            'body' => '{name} عزیز، خوش آمدید. {clinic}',
            'is_active' => '1',
        ])
        ->assertRedirect(route('admin.sms.templates'));

    expect($template->fresh()->body)->toBe('{name} عزیز، خوش آمدید. {clinic}');

    $audit = AuditLog::query()->where('action', AuditAction::Update->value)
        ->where('subject_id', $template->getKey())->firstOrFail();
    expect($audit->actor_id)->toBe($admin->getKey())
        ->and($audit->changes['old']['body'])->toBe($original)
        ->and($audit->changes['new']['body'])->toBe('{name} عزیز، خوش آمدید. {clinic}');
});

test('the OTP template stays on even when the form switches it off', function () {
    $admin = smsUser(StaffRole::Admin);
    $template = SmsTemplate::query()->where('key', 'otp')->firstOrFail();

    $this->actingAs($admin)
        ->put(route('admin.sms.templates.update', $template), [
            'body' => 'کد ورود شما: {code}',
            'is_active' => '0',
        ])
        ->assertRedirect(route('admin.sms.templates'));

    expect($template->fresh()->is_active)->toBeTrue();
});

test('a template may only use its own placeholders, and the OTP must keep the code', function (string $key, string $body, bool $valid) {
    $admin = smsUser(StaffRole::Admin);
    $template = SmsTemplate::query()->where('key', $key)->firstOrFail();

    $response = $this->actingAs($admin)->put(route('admin.sms.templates.update', $template), [
        'body' => $body,
        'is_active' => '1',
    ]);

    if ($valid) {
        $response->assertSessionHasNoErrors();
        expect($template->fresh()->body)->toBe($body);
    } else {
        $response->assertSessionHasErrors('body');
        expect($template->fresh()->body)->not->toBe($body);
    }
})->with([
    'unknown placeholder' => ['welcome', '{name} {mobile}', false],
    'code outside OTP' => ['welcome', '{name} {code}', false],
    'OTP keeps code' => ['otp', 'کد شما {code}', true],
    'OTP without code' => ['otp', 'کد شما', false],
    'welcome without code is fine' => ['welcome', '{name} خوش آمدید', true],
]);

test('a secretary cannot edit a template', function () {
    $template = SmsTemplate::query()->where('key', 'welcome')->firstOrFail();

    $this->actingAs(smsUser(StaffRole::Secretary))
        ->put(route('admin.sms.templates.update', $template), ['body' => 'hacked', 'is_active' => '1'])
        ->assertForbidden();

    expect($template->fresh()->body)->not->toBe('hacked');
});

test('the admin resends a failed message as a new row, and a secretary cannot', function () {
    $failed = SmsLog::query()->create([
        'mobile' => '09121234567',
        'template_key' => 'report_ready',
        'body' => 'گزارش آماده است',
        'provider' => 'log',
        'status' => SmsStatus::Failed,
        'attempts' => 4,
    ]);

    $this->actingAs(smsUser(StaffRole::Secretary))
        ->post(route('admin.sms.log.resend', $failed))
        ->assertForbidden();

    expect(SmsLog::query()->count())->toBe(1);

    $this->actingAs(smsUser(StaffRole::Admin))
        ->post(route('admin.sms.log.resend', $failed))
        ->assertRedirect(route('admin.sms.log'));

    expect(SmsLog::query()->count())->toBe(2)
        ->and(SmsLog::query()->latest('id')->firstOrFail()->status)->toBe(SmsStatus::Sent);
});

test('the log shows masked mobiles and no OTP code, and filters by status', function () {
    SmsLog::query()->create([
        'mobile' => '09121234567',
        'template_key' => 'otp',
        'body' => 'کد ورود شما: [کد]',
        'provider' => 'log',
        'status' => SmsStatus::Sent,
    ]);
    SmsLog::query()->create([
        'mobile' => '09129998888',
        'template_key' => 'welcome',
        'body' => 'خوش آمدید',
        'provider' => 'log',
        'status' => SmsStatus::Failed,
        'error' => 'provider_status_400',
    ]);

    $this->actingAs(smsUser(StaffRole::Secretary))
        ->get(route('admin.sms.log'))
        ->assertOk()
        ->assertDontSee('09121234567')
        ->assertSee('۸۸۸۸')
        ->assertSee('provider_status_400');

    $this->actingAs(smsUser(StaffRole::Secretary))
        ->get(route('admin.sms.log', ['status' => 'failed']))
        ->assertOk()
        ->assertSee('خوش آمدید')
        ->assertDontSee('[کد]');
});
