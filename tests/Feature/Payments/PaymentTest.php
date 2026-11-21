<?php

declare(strict_types=1);

use App\Enums\AuditAction;
use App\Enums\PaymentMethod;
use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Payment;
use App\Models\SessionPackage;
use App\Models\User;
use App\Services\Payments\PaymentService;
use App\Support\Display;
use App\Support\Jalali;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

function payUser(StaffRole $role): User
{
    return User::factory()->role($role)->create(['is_active' => true]);
}

/**
 * Records a payment through the service, with defaults that tests can override.
 *
 * @param  array<string, mixed>  $overrides
 */
function payRecord(Client $client, int $amount, array $overrides = [], ?User $by = null): Payment
{
    return app(PaymentService::class)->record(array_merge([
        'client_id' => $client->getKey(),
        'appointment_id' => null,
        'package_id' => null,
        'amount' => $amount,
        'method' => PaymentMethod::Cash,
        'paid_at' => CarbonImmutable::now('Asia/Tehran'),
        'reference' => null,
        'note' => null,
    ], $overrides), $by ?? payUser(StaffRole::Secretary));
}

/** The clinic's today, as a Jalali string typed by a user. */
function payJalaliDaysFromToday(int $days): string
{
    return Jalali::formatGregorianAsJalali(now('Asia/Tehran')->addDays($days)->toDateString());
}

test('recording a payment stores a positive amount and audits it without the reference or the note', function () {
    $client = makeClient();

    $payment = payRecord($client, 500000, [
        'reference' => 'REF-SECRET-123',
        'note' => 'یادداشت محرمانه',
    ]);

    expect($payment->amount)->toBe(500000)
        ->and($payment->isReversal())->toBeFalse()
        ->and($payment->isReversed())->toBeFalse();

    $log = AuditLog::query()
        ->where('action', AuditAction::Create->value)
        ->where('subject_type', Payment::class)
        ->where('subject_id', $payment->getKey())
        ->firstOrFail();

    expect($log->changes['new']['amount'])->toBe(500000)
        ->and(json_encode($log->changes, JSON_UNESCAPED_UNICODE))
        ->not->toContain('REF-SECRET-123')
        ->not->toContain('یادداشت محرمانه');
});

test('a zero or negative payment is refused by the service', function () {
    expect(fn () => payRecord(makeClient(), 0))->toThrow(ValidationException::class)
        ->and(fn () => payRecord(makeClient(), -500))->toThrow(ValidationException::class)
        ->and(Payment::query()->count())->toBe(0);
});

test('a payment for a package must belong to the same client', function () {
    $client = makeClient();
    $other = makeClient();
    $package = SessionPackage::factory()->create(['client_id' => $other->getKey()]);

    expect(fn () => payRecord($client, 100000, ['package_id' => $package->getKey()]))
        ->toThrow(ValidationException::class);

    $own = SessionPackage::factory()->create(['client_id' => $client->getKey()]);

    expect(payRecord($client, 100000, ['package_id' => $own->getKey()])->package_id)->toBe($own->getKey());
});

test('the service refuses a payment from a user without payment.record', function (StaffRole $role) {
    expect(fn () => payRecord(makeClient(), 100000, [], payUser($role)))
        ->toThrow(AuthorizationException::class)
        ->and(Payment::query()->count())->toBe(0);
})->with([
    'specialist' => StaffRole::Specialist,
    'operator' => StaffRole::Operator,
]);

test('payments cannot be updated or deleted', function () {
    $payment = payRecord(makeClient(), 500000);

    expect(fn () => $payment->update(['amount' => 1]))->toThrow(LogicException::class)
        ->and(fn () => $payment->delete())->toThrow(LogicException::class)
        ->and($payment->fresh()?->amount)->toBe(500000);
});

test('the routes for payment update and delete do not exist', function () {
    expect(Route::has('admin.payments.edit'))->toBeFalse()
        ->and(Route::has('admin.payments.update'))->toBeFalse()
        ->and(Route::has('admin.payments.destroy'))->toBeFalse();
});

test('a reversal writes a negative row that cancels the payment, and the original stays as it was', function () {
    $client = makeClient();
    $original = payRecord($client, 300000, ['method' => PaymentMethod::Card]);

    $reversal = app(PaymentService::class)->reverse($original, 'ثبت اشتباه', payUser(StaffRole::Admin));

    expect($reversal->amount)->toBe(-300000)
        ->and($reversal->getAttribute('reverses_id'))->toBe($original->getKey())
        ->and($reversal->method)->toBe(PaymentMethod::Card)
        ->and($reversal->getAttribute('client_id'))->toBe($client->getKey())
        ->and($reversal->isReversal())->toBeTrue()
        ->and($original->fresh()?->amount)->toBe(300000)
        ->and($original->fresh()?->isReversed())->toBeTrue()
        ->and((int) Payment::query()->where('client_id', $client->getKey())->sum('amount'))->toBe(0);

    $log = AuditLog::query()
        ->where('action', AuditAction::Create->value)
        ->where('subject_type', Payment::class)
        ->where('subject_id', $reversal->getKey())
        ->firstOrFail();

    expect($log->changes['new']['reverses_id'])->toBe($original->getKey())
        ->and(json_encode($log->changes, JSON_UNESCAPED_UNICODE))->not->toContain('ثبت اشتباه');
});

test('a payment can be reversed once, and a reversal cannot be reversed', function () {
    $admin = payUser(StaffRole::Admin);
    $service = app(PaymentService::class);
    $original = payRecord(makeClient(), 200000);
    $reversal = $service->reverse($original, 'اولین', $admin);

    expect(fn () => $service->reverse($original->fresh(), 'دومین', $admin))
        ->toThrow(ValidationException::class)
        ->and(fn () => $service->reverse($reversal->fresh(), 'روی برگشت', $admin))
        ->toThrow(ValidationException::class)
        ->and(Payment::query()->count())->toBe(2);
});

test('only admin can reverse: the service refuses the others', function (StaffRole $role) {
    $payment = payRecord(makeClient(), 200000);

    expect(fn () => app(PaymentService::class)->reverse($payment, 'دلیل', payUser($role)))
        ->toThrow(AuthorizationException::class)
        ->and(Payment::query()->count())->toBe(1);
})->with([
    'secretary' => StaffRole::Secretary,
    'operator' => StaffRole::Operator,
    'specialist' => StaffRole::Specialist,
]);

test('the reversal form and its submit are closed to secretary, operator and specialist', function (StaffRole $role) {
    $payment = payRecord(makeClient(), 200000);

    $this->actingAs(payUser($role))
        ->get(route('admin.payments.reverse.form', $payment))
        ->assertForbidden();

    $this->actingAs(payUser($role))
        ->post(route('admin.payments.reverse', $payment), ['note' => 'دلیل'])
        ->assertForbidden();

    expect(Payment::query()->count())->toBe(1);
})->with([
    'secretary' => StaffRole::Secretary,
    'operator' => StaffRole::Operator,
    'specialist' => StaffRole::Specialist,
]);

test('admin reverses through the form, and the reason is required', function () {
    $admin = payUser(StaffRole::Admin);
    $payment = payRecord(makeClient(), 200000);

    $this->actingAs($admin)
        ->post(route('admin.payments.reverse', $payment), ['note' => '   '])
        ->assertSessionHasErrors('note');

    expect(Payment::query()->count())->toBe(1);

    $this->actingAs($admin)
        ->post(route('admin.payments.reverse', $payment), ['note' => 'ورود به حساب اشتباه'])
        ->assertRedirect(route('admin.payments.index', ['client' => $payment->getAttribute('client_id')]))
        ->assertSessionHas('success');

    expect(Payment::query()->where('reverses_id', $payment->getKey())->value('note'))->toBe('ورود به حساب اشتباه');
});

test('a reversed payment cannot be reversed again from the form', function () {
    $admin = payUser(StaffRole::Admin);
    $payment = payRecord(makeClient(), 200000);
    app(PaymentService::class)->reverse($payment, 'اول', $admin);

    $this->actingAs($admin)
        ->get(route('admin.payments.reverse.form', $payment))
        ->assertRedirect(route('admin.payments.index'))
        ->assertSessionHasErrors('payment');
});

test('secretary records a payment through the form, with Persian digits and separators', function () {
    $client = makeClient();
    $secretary = payUser(StaffRole::Secretary);

    $this->actingAs($secretary)
        ->post(route('admin.payments.store'), [
            'client_id' => $client->getKey(),
            'amount' => '۲۵۰٬۰۰۰',
            'method' => PaymentMethod::Pos->value,
            'paid_at' => Jalali::formatGregorianAsJalali(now('Asia/Tehran')->toDateString()),
            'package_id' => '',
            'reference' => '۱۲۳۴',
            'note' => '',
        ])
        ->assertRedirect(route('admin.payments.index', ['client' => $client->getKey()]))
        ->assertSessionHas('success');

    $payment = Payment::query()->where('client_id', $client->getKey())->firstOrFail();

    expect($payment->amount)->toBe(250000)
        ->and($payment->method)->toBe(PaymentMethod::Pos)
        ->and($payment->reference)->toBe('1234')
        ->and($payment->note)->toBeNull()
        ->and($payment->package_id)->toBeNull();
});

test('a payment dated in the past is stored on that day', function () {
    $client = makeClient();

    $this->actingAs(payUser(StaffRole::Secretary))
        ->post(route('admin.payments.store'), [
            'client_id' => $client->getKey(),
            'amount' => '100000',
            'method' => PaymentMethod::Cash->value,
            'paid_at' => payJalaliDaysFromToday(-10),
        ])
        ->assertSessionHasNoErrors();

    expect(Payment::query()->where('client_id', $client->getKey())->firstOrFail()->paid_at->toDateString())
        ->toBe(now('Asia/Tehran')->subDays(10)->toDateString());
});

test('a payment dated in the future, an invalid date, or a bad amount is refused on the form', function () {
    $client = makeClient();
    $secretary = payUser(StaffRole::Secretary);
    $base = [
        'client_id' => $client->getKey(),
        'amount' => '100000',
        'method' => PaymentMethod::Cash->value,
    ];

    $this->actingAs($secretary)
        ->post(route('admin.payments.store'), $base + ['paid_at' => payJalaliDaysFromToday(3)])
        ->assertSessionHasErrors('paid_at');

    $this->actingAs($secretary)
        ->post(route('admin.payments.store'), $base + ['paid_at' => '۱۴۰۵/۱۳/۴۰'])
        ->assertSessionHasErrors('paid_at');

    $this->actingAs($secretary)
        ->post(route('admin.payments.store'), ['amount' => '0', 'method' => 'bitcoin', 'client_id' => $client->getKey(), 'paid_at' => payJalaliDaysFromToday(0)])
        ->assertSessionHasErrors(['amount', 'method']);

    expect(Payment::query()->count())->toBe(0);
});

test('the list filters by client and by a Jalali date range, and shows the net total', function () {
    $secretary = payUser(StaffRole::Secretary);
    $client = makeClient();
    $other = makeClient();

    payRecord($client, 123000, ['paid_at' => now('Asia/Tehran')->subDays(20)], $secretary);
    payRecord($client, 456000, ['paid_at' => now('Asia/Tehran')], $secretary);
    payRecord($other, 789000, ['paid_at' => now('Asia/Tehran')], $secretary);

    // The client and date filters leave one row: 456,000 on today's date. The net total is that row alone.
    $this->actingAs($secretary)
        ->get(route('admin.payments.index', [
            'client' => $client->getKey(),
            'from' => payJalaliDaysFromToday(-5),
        ]))
        ->assertOk()
        ->assertSee(Display::money(456000))
        ->assertDontSee(Display::money(123000))
        ->assertDontSee(Display::money(789000));

    // Without a filter, the net total is all three rows.
    $this->actingAs($secretary)
        ->get(route('admin.payments.index'))
        ->assertOk()
        ->assertSee(Display::money(1368000));
});

test('without a client filter the list shows every client\'s payments', function () {
    $secretary = payUser(StaffRole::Secretary);
    payRecord(makeClient(), 111000, [], $secretary);
    payRecord(makeClient(), 222000, [], $secretary);

    $this->actingAs($secretary)
        ->get(route('admin.payments.index'))
        ->assertOk()
        ->assertSee(Display::money(111000))
        ->assertSee(Display::money(222000))
        ->assertSee(Display::money(333000));
});

test('opening a client ledger is audited with the payments section only', function () {
    $client = makeClient();
    payRecord($client, 100000, ['note' => 'متن ممیزی'], payUser(StaffRole::Secretary));

    $this->actingAs(payUser(StaffRole::Secretary))
        ->get(route('admin.payments.index', ['client' => $client->getKey()]))
        ->assertOk();

    $log = AuditLog::query()
        ->where('action', AuditAction::View->value)
        ->where('subject_type', Client::class)
        ->where('subject_id', $client->getKey())
        ->firstOrFail();

    expect($log->changes['sections'] ?? null)->toBe(['payments'])
        ->and(json_encode($log->changes, JSON_UNESCAPED_UNICODE))->not->toContain('متن ممیزی');
});

test('the admin client file links to the client ledger', function () {
    $client = makeClient();

    $this->actingAs(payUser(StaffRole::Admin))
        ->get(route('admin.clients.show', $client))
        ->assertOk()
        ->assertSee(route('admin.payments.index', ['client' => $client->getKey()]));
});

test('the payments pages are closed to specialist and operator, and the secretary cannot reverse', function () {
    $client = makeClient();
    $payment = payRecord($client, 100000);

    $secretary = payUser(StaffRole::Secretary);
    $operator = payUser(StaffRole::Operator);
    $specialist = payUser(StaffRole::Specialist);

    foreach ([$operator, $specialist] as $user) {
        $this->actingAs($user)->get(route('admin.payments.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.payments.create'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.payments.store'), [])->assertForbidden();
    }

    $this->actingAs($secretary)->get(route('admin.payments.index'))->assertOk();
    $this->actingAs($secretary)->get(route('admin.payments.create'))->assertOk();
    $this->actingAs($secretary)->get(route('admin.payments.reverse.form', $payment))->assertForbidden();
});

test('a guest is redirected to login on every payment route', function () {
    $payment = payRecord(makeClient(), 100000);

    $this->get(route('admin.payments.index'))->assertRedirect(route('login'));
    $this->get(route('admin.payments.create'))->assertRedirect(route('login'));
    $this->get(route('admin.payments.reverse.form', $payment))->assertRedirect(route('login'));
});
