<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\StaffRole;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\User;
use App\Services\Dashboard\DashboardStats;
use App\Services\Payments\PaymentService;
use App\Support\Digits;
use App\Support\Display;
use App\Support\Jalali;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

function dashUser(StaffRole $role): User
{
    return User::factory()->role($role)->create(['is_active' => true]);
}

/** A clinic-time moment on the given day, at the given hour. */
function dashAt(CarbonImmutable $day, int $hour): CarbonImmutable
{
    return $day->setTime($hour, 0);
}

function dashAppointment(CarbonImmutable $start, AppointmentStatus $status, ?int $clientId = null): Appointment
{
    return Appointment::factory()->create([
        'client_id' => $clientId ?? makeClient()->getKey(),
        'starts_at' => $start,
        'ends_at' => $start->addMinutes(30),
        'status' => $status,
    ]);
}

test('the day counts leave out cancelled appointments and other days', function () {
    $stats = app(DashboardStats::class);
    $today = CarbonImmutable::now('Asia/Tehran')->startOfDay();

    dashAppointment(dashAt($today, 9), AppointmentStatus::Scheduled);
    dashAppointment(dashAt($today, 10), AppointmentStatus::Arrived);
    dashAppointment(dashAt($today, 11), AppointmentStatus::Cancelled);
    dashAppointment(dashAt($today->addDay(), 9), AppointmentStatus::Scheduled);

    expect($stats->appointmentsOn($today))->toBe(2)
        ->and($stats->arrivalsWaiting($today))->toBe(1);
});

test('new clients this week count from the Saturday that starts the Persian week', function () {
    $stats = app(DashboardStats::class);
    $wednesday = CarbonImmutable::parse('2026-11-18', 'Asia/Tehran');
    $saturday = $wednesday->startOfWeek(CarbonInterface::SATURDAY);
    $before = $stats->newClientsThisWeek($wednesday);

    $inside = makeClient();
    $outside = makeClient();

    DB::table('clients')->where('id', $inside->getKey())->update(['created_at' => $saturday->setTime(10, 0)]);
    DB::table('clients')->where('id', $outside->getKey())->update(['created_at' => $saturday->subDay()->setTime(23, 0)]);

    expect($stats->newClientsThisWeek($wednesday))->toBe($before + 1);
});

test('daily clients are distinct, and the no-show rate is no-show over completed plus no-show', function () {
    $stats = app(DashboardStats::class);
    $today = CarbonImmutable::now('Asia/Tehran')->startOfDay();
    $client = makeClient();

    dashAppointment(dashAt($today, 8), AppointmentStatus::Completed, $client->getKey());
    dashAppointment(dashAt($today, 9), AppointmentStatus::Completed, $client->getKey());
    dashAppointment(dashAt($today, 10), AppointmentStatus::Completed);
    dashAppointment(dashAt($today, 11), AppointmentStatus::Completed);
    dashAppointment(dashAt($today, 12), AppointmentStatus::NoShow);

    expect($stats->dailyClients($today))->toBe(3)
        ->and($stats->noShowRate($today))->toBe(0.2);
});

test('the no-show rate is null when no appointment was completed or missed', function () {
    expect(app(DashboardStats::class)->noShowRate(CarbonImmutable::parse('2026-11-18', 'Asia/Tehran')))->toBeNull();
});

test('the day payments are the net per method: a reversal the same day cancels the original', function () {
    $stats = app(DashboardStats::class);
    $admin = dashUser(StaffRole::Admin);
    $secretary = dashUser(StaffRole::Secretary);
    $client = makeClient();
    $service = app(PaymentService::class);

    $service->record([
        'client_id' => $client->getKey(),
        'appointment_id' => null,
        'package_id' => null,
        'amount' => 200000,
        'method' => PaymentMethod::Cash,
        'paid_at' => CarbonImmutable::now('Asia/Tehran'),
        'reference' => null,
        'note' => null,
    ], $secretary);

    $card = $service->record([
        'client_id' => $client->getKey(),
        'appointment_id' => null,
        'package_id' => null,
        'amount' => 100000,
        'method' => PaymentMethod::Card,
        'paid_at' => CarbonImmutable::now('Asia/Tehran'),
        'reference' => null,
        'note' => null,
    ], $secretary);

    $service->reverse($card, 'اشتباه', $admin);

    $today = CarbonImmutable::now('Asia/Tehran');

    $net = $stats->paymentsOn($today);

    expect($net['total'])->toBe(200000)
        ->and($net['byMethod']['cash'])->toBe(200000)
        ->and($net['byMethod']['card'])->toBe(0)
        ->and(array_keys($net['byMethod']))->toBe(array_column(PaymentMethod::cases(), 'value'));
});

test('the dashboard shows the operational cards to secretary and the admin cards to admin only', function () {
    $secretary = dashUser(StaffRole::Secretary);
    $admin = dashUser(StaffRole::Admin);

    $this->actingAs($secretary)
        ->get(route('admin.home'))
        ->assertOk()
        ->assertSee(__('dashboard.today_appointments'))
        ->assertSee(__('dashboard.new_clients_week'))
        ->assertDontSee(__('dashboard.pending_reports'))
        ->assertDontSee(__('dashboard.payments_today'))
        ->assertDontSee(route('admin.reports.daily'));

    $this->actingAs($admin)
        ->get(route('admin.home'))
        ->assertOk()
        ->assertSee(__('dashboard.pending_reports'))
        ->assertSee(__('dashboard.payments_today'))
        ->assertSee(route('admin.reports.daily'));
});

test('the daily report is for admin only; everyone else is refused, and a guest is sent to login', function (StaffRole $role) {
    $this->actingAs(dashUser($role))
        ->get(route('admin.reports.daily'))
        ->assertForbidden();
})->with([
    'secretary' => StaffRole::Secretary,
    'operator' => StaffRole::Operator,
    'specialist' => StaffRole::Specialist,
]);

test('a guest is redirected to login on the dashboard and the daily report', function () {
    $this->get(route('admin.home'))->assertRedirect(route('login'));
    $this->get(route('admin.reports.daily'))->assertRedirect(route('login'));
});

test('the daily report shows the counts for the chosen Jalali day, in Persian digits', function () {
    $admin = dashUser(StaffRole::Admin);
    $day = CarbonImmutable::parse('2026-11-18', 'Asia/Tehran');
    $client = makeClient();

    dashAppointment(dashAt($day, 9), AppointmentStatus::Completed, $client->getKey());
    dashAppointment(dashAt($day, 10), AppointmentStatus::Completed);
    dashAppointment(dashAt($day, 11), AppointmentStatus::Completed);
    dashAppointment(dashAt($day, 12), AppointmentStatus::NoShow);

    $jalali = Jalali::formatGregorianAsJalali('2026-11-18');

    $this->actingAs($admin)
        ->get(route('admin.reports.daily', ['date' => $jalali]))
        ->assertOk()
        ->assertSee('value="'.$jalali.'"', false)
        ->assertSee(Digits::toPersian(25).'٪')
        ->assertSee(__('scheduling.status.no_show'));
});

test('the daily report rejects a bad date', function () {
    $this->actingAs(dashUser(StaffRole::Admin))
        ->get(route('admin.reports.daily', ['date' => '۱۴۰۵/۱۳/۴۰']))
        ->assertSessionHasErrors('date');
});

test('the daily report shows the net payment total in Toman', function () {
    $admin = dashUser(StaffRole::Admin);
    $client = makeClient();

    app(PaymentService::class)->record([
        'client_id' => $client->getKey(),
        'appointment_id' => null,
        'package_id' => null,
        'amount' => 345000,
        'method' => PaymentMethod::Transfer,
        'paid_at' => CarbonImmutable::now('Asia/Tehran'),
        'reference' => null,
        'note' => null,
    ], dashUser(StaffRole::Secretary));

    $this->actingAs($admin)
        ->get(route('admin.reports.daily'))
        ->assertOk()
        ->assertSee(Display::money(345000))
        ->assertSee(PaymentMethod::Transfer->label());
});

test('the report holds no client names', function () {
    $admin = dashUser(StaffRole::Admin);
    $client = Client::factory()->create(['first_name' => 'نامِ', 'last_name' => 'مراجعه‌کننده']);
    dashAppointment(CarbonImmutable::now('Asia/Tehran')->setTime(9, 0), AppointmentStatus::Completed, $client->getKey());

    $this->actingAs($admin)
        ->get(route('admin.reports.daily'))
        ->assertOk()
        ->assertDontSee('نامِ');
});
