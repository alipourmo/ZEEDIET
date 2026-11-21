<?php

declare(strict_types=1);

use App\Enums\AuditAction;
use App\Enums\ClientStatus;
use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Models\OtpCode;
use App\Models\SmsTemplate;
use App\Models\User;
use App\Services\Sms\SmsGateway;
use App\Services\Sms\SmsMessage;
use App\Services\Sms\SmsResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;

// One-time codes for the client portal (spec 7.5). The gateway records every message, so a test can read the code.

beforeEach(function () {
    $this->gateway = new class implements SmsGateway
    {
        /** @var list<SmsMessage> */
        public array $sent = [];

        public function name(): string
        {
            return 'fake';
        }

        public function send(SmsMessage $message): SmsResult
        {
            $this->sent[] = $message;

            return SmsResult::sent('fake-'.count($this->sent));
        }
    };

    app()->instance(SmsGateway::class, $this->gateway);

    $this->client = makeClient(['mobile' => '09121234567', 'first_name' => 'سارا']);
});

/** The five digits in the newest SMS. */
function portalCodeFrom(object $gateway): string
{
    preg_match('/(\d{5})/', (string) end($gateway->sent)->body, $matches);

    return $matches[1] ?? '';
}

/** A code that is certainly not the one that was sent. */
function portalWrongCode(string $code): string
{
    return $code === '00000' ? '11111' : '00000';
}

/** Asks for a code for the mobile, as the sign-in form does. */
function portalAskForCode($test, string $mobile): TestResponse
{
    return $test->post(route('portal.login.store'), ['mobile' => $mobile]);
}

test('a known mobile gets a 5-digit code by SMS, and the reply is the same as for an unknown mobile', function () {
    $known = portalAskForCode($this, '09121234567');
    $sentAfterKnown = count($this->gateway->sent);

    $unknown = portalAskForCode($this, '09129999999');

    $known->assertRedirect(route('portal.verify'));
    $unknown->assertRedirect(route('portal.verify'));

    expect($known->getSession()->get('success'))->toBe($unknown->getSession()->get('success'))
        ->and($sentAfterKnown)->toBe(1)
        ->and($this->gateway->sent)->toHaveCount(1)
        ->and($this->gateway->sent[0]->mobile)->toBe('09121234567')
        ->and(portalCodeFrom($this->gateway))->toMatch('/^\d{5}$/');
});

test('the code is stored only as a hash, and the SMS log keeps no code', function () {
    portalAskForCode($this, '09121234567');
    $code = portalCodeFrom($this->gateway);

    $otp = OtpCode::query()->firstOrFail();

    expect($otp->code_hash)->not->toContain($code)
        ->and(Hash::check($code, $otp->code_hash))->toBeTrue()
        ->and(DB::table('sms_log')->where('body', 'like', '%'.$code.'%')->count())->toBe(0);
});

test('a correct code signs the client in and writes a login audit row for the client', function () {
    portalAskForCode($this, '09121234567');
    $code = portalCodeFrom($this->gateway);

    $this->post(route('portal.verify.store'), ['code' => $code])->assertRedirect(route('portal.home'));

    $this->assertAuthenticated('client');
    expect(auth('client')->id())->toBe($this->client->getKey());

    $audit = AuditLog::query()->where('action', AuditAction::Login->value)->firstOrFail();
    expect($audit->actor_type)->toBe('client')
        ->and($audit->actor_id)->toBe($this->client->getKey())
        ->and($audit->client_id)->toBe($this->client->getKey());
});

test('a used code cannot be used again, even inside its three minutes', function () {
    portalAskForCode($this, '09121234567');
    $first = portalCodeFrom($this->gateway);

    $this->post(route('portal.verify.store'), ['code' => $first])->assertRedirect(route('portal.home'));
    auth('client')->logout();

    portalAskForCode($this, '09121234567');

    $this->post(route('portal.verify.store'), ['code' => $first])->assertSessionHas('error');
    $this->assertGuest('client');
});

test('asking again replaces the older code', function () {
    portalAskForCode($this, '09121234567');
    $first = portalCodeFrom($this->gateway);

    $this->travel(1)->minutes();
    portalAskForCode($this, '09121234567');
    $second = portalCodeFrom($this->gateway);

    expect(OtpCode::query()->whereNull('consumed_at')->count())->toBe(1);

    if ($first !== $second) {
        $this->post(route('portal.verify.store'), ['code' => $first])->assertSessionHas('error');
        $this->assertGuest('client');
    }

    $this->post(route('portal.verify.store'), ['code' => $second])->assertRedirect(route('portal.home'));
});

test('a code expires after three minutes', function () {
    portalAskForCode($this, '09121234567');
    $code = portalCodeFrom($this->gateway);

    $this->travel(4)->minutes();

    $this->post(route('portal.verify.store'), ['code' => $code])->assertSessionHas('error');
    $this->assertGuest('client');
});

test('a code takes five wrong tries, then it is spent even when the right code comes next', function () {
    portalAskForCode($this, '09121234567');
    $code = portalCodeFrom($this->gateway);
    $wrong = portalWrongCode($code);

    for ($i = 0; $i < 5; $i++) {
        $this->post(route('portal.verify.store'), ['code' => $wrong])->assertSessionHas('error');
    }

    $this->post(route('portal.verify.store'), ['code' => $code])->assertSessionHas('error');
    $this->assertGuest('client');

    expect(OtpCode::query()->firstOrFail()->attempts)->toBe(5);
});

test('a mobile gets at most three code requests in ten minutes; the fourth sends nothing', function () {
    for ($i = 0; $i < 4; $i++) {
        portalAskForCode($this, '09121234567');
    }

    expect($this->gateway->sent)->toHaveCount(3)
        ->and(OtpCode::query()->count())->toBe(3);

    $this->travel(11)->minutes();
    portalAskForCode($this, '09121234567');

    expect($this->gateway->sent)->toHaveCount(4);
});

test('an IP gets at most ten code requests in an hour, across mobiles', function () {
    $mobiles = [];

    for ($i = 0; $i < 11; $i++) {
        $mobiles[] = '0912111'.str_pad((string) $i, 4, '0', STR_PAD_LEFT);
        makeClient(['mobile' => $mobiles[$i]]);
    }

    foreach ($mobiles as $mobile) {
        portalAskForCode($this, $mobile);
    }

    expect($this->gateway->sent)->toHaveCount(10);
});

test('an inactive client gets no code, and nor does a soft-deleted one', function () {
    $this->client->forceFill(['status' => ClientStatus::Inactive])->save();
    portalAskForCode($this, '09121234567');

    $deleted = makeClient(['mobile' => '09128887777']);
    $deleted->delete();
    portalAskForCode($this, '09128887777');

    expect($this->gateway->sent)->toBe([]);
});

test('a badly formed mobile is refused by validation, and nothing is sent', function () {
    $this->post(route('portal.login.store'), ['mobile' => '12345'])->assertSessionHasErrors('mobile');

    expect($this->gateway->sent)->toBe([]);
});

test('the sign-in page sends a signed-in client to the portal', function () {
    portalAskForCode($this, '09121234567');
    $this->post(route('portal.verify.store'), ['code' => portalCodeFrom($this->gateway)]);

    $this->get(route('portal.login'))->assertRedirect(route('portal.home'));
});

test('logout signs the client out and writes a logout audit row', function () {
    portalAskForCode($this, '09121234567');
    $this->post(route('portal.verify.store'), ['code' => portalCodeFrom($this->gateway)]);

    $this->post(route('portal.logout'))->assertRedirect(route('portal.login'));

    $this->assertGuest('client');
    expect(AuditLog::query()->where('action', AuditAction::Logout->value)->where('actor_id', $this->client->getKey())->exists())->toBeTrue();
});

test('the OTP template stays on even when an admin has switched it off', function () {
    SmsTemplate::query()->create(['key' => 'otp', 'body' => 'کد: {code}', 'is_active' => false]);

    portalAskForCode($this, '09121234567');

    expect($this->gateway->sent)->toHaveCount(1);
});

test('a staff session does not open the portal', function () {
    $this->actingAs(User::factory()->role(StaffRole::Admin)->create(['is_active' => true]))
        ->get(route('portal.home'))
        ->assertRedirect(route('portal.login'));
});

test('a client session does not open the staff panels', function () {
    // The staff routes check the default (staff) guard, so a client is treated as a guest and sent to staff sign-in.
    $this->actingAs($this->client, 'client');
    auth()->shouldUse('web');

    $this->get(route('admin.home'))->assertRedirect(route('login'));
});

test('the code check is limited per IP, so a new code cannot reset the guessing budget', function () {
    portalAskForCode($this, '09121234567');
    $code = portalCodeFrom($this->gateway);

    // Twenty checks from one IP use up the budget (spec 7.5 leaves the verify step open, so the limit is ours).
    for ($i = 0; $i < 20; $i++) {
        $this->post(route('portal.verify.store'), ['code' => portalWrongCode($code)]);
    }

    // A fresh code for the same mobile does not reopen the IP budget, so the right code is refused too.
    portalAskForCode($this, '09121234567');
    $fresh = portalCodeFrom($this->gateway);

    $this->post(route('portal.verify.store'), ['code' => $fresh])
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->assertGuest('client');
});
