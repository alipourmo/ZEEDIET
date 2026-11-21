<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Enums\StaffRole;
use App\Models\Appointment;
use App\Models\Device;
use App\Models\Room;
use App\Models\User;
use App\Services\Scheduling\CalendarService;
use App\Support\Digits;
use App\Support\Jalali;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

// 2027-01-02 is a Saturday, Jalali 1405/10/12. Its week runs Saturday to Friday.

/**
 * An appointment that starts at the given Tehran wall-clock time. The factory makes its client and service.
 *
 * @param  array<string, mixed>  $overrides
 */
function calendarBooking(string $start, array $overrides = []): Appointment
{
    $from = CarbonImmutable::parse($start, 'Asia/Tehran');

    return Appointment::factory()->create(array_merge([
        'starts_at' => $from,
        'ends_at' => $from->addMinutes(30),
        'status' => AppointmentStatus::Scheduled,
    ], $overrides));
}

function calendarUrl(string $view, string $jalali = '1405/10/12', array $extra = []): string
{
    return route('admin.calendar.index', array_merge(['view' => $view, 'date' => $jalali], $extra));
}

function calendarSecretary(): User
{
    return User::factory()->role(StaffRole::Secretary)->create(['is_active' => true]);
}

test('admin and secretary may open the calendar, nobody else', function (StaffRole $role, int $status) {
    $user = User::factory()->role($role)->create(['is_active' => true]);

    $this->actingAs($user)
        ->get(calendarUrl('week'))
        ->assertStatus($status);
})->with([
    [StaffRole::Admin, 200],
    [StaffRole::Secretary, 200],
    [StaffRole::Operator, 403],
    [StaffRole::Specialist, 403],
]);

test('a guest is sent to sign in', function () {
    $this->get(calendarUrl('week'))->assertRedirect(route('login'));
});

test('the calendar service refuses a user without appointment.view_all', function () {
    $operator = User::factory()->role(StaffRole::Operator)->create(['is_active' => true]);
    $from = CarbonImmutable::parse('2027-01-01', 'Asia/Tehran');

    expect(fn () => app(CalendarService::class)->blocks($operator, $from, $from->addDays(7), []))
        ->toThrow(AuthorizationException::class);

    expect(fn () => app(CalendarService::class)->counts($operator, $from, $from->addDays(7), []))
        ->toThrow(AuthorizationException::class);
});

test('the week view shows each appointment as a block with its client and status', function () {
    $appointment = calendarBooking('2027-01-02 10:00:00', ['status' => AppointmentStatus::Confirmed]);

    $this->actingAs(calendarSecretary())
        ->get(calendarUrl('week'))
        ->assertOk()
        ->assertSee(Digits::toPersian(Jalali::formatGregorianAsJalali('2027-01-02')))
        ->assertSee($appointment->client->fullName())
        ->assertSee('calendar-block--confirmed', false)
        ->assertSee(__('scheduling.status.confirmed'));
});

test('the day view shows the blocks of that day only', function () {
    $today = calendarBooking('2027-01-02 10:00:00');
    $tomorrow = calendarBooking('2027-01-03 10:00:00');

    $this->actingAs(calendarSecretary())
        ->get(calendarUrl('day', '1405/10/12'))
        ->assertOk()
        ->assertSee($today->client->fullName())
        ->assertDontSee($tomorrow->client->fullName());
});

test('the month view shows a count per day, not the blocks', function () {
    $first = calendarBooking('2027-01-02 10:00:00');
    calendarBooking('2027-01-02 11:00:00');
    calendarBooking('2027-01-02 12:00:00');

    $this->actingAs(calendarSecretary())
        ->get(calendarUrl('month'))
        ->assertOk()
        ->assertSee(__('calendar.count', ['count' => Digits::toPersian(3)]))
        ->assertDontSee($first->client->fullName());
});

test('filters narrow the rows the calendar shows', function () {
    $deviceA = Device::factory()->create();
    $deviceB = Device::factory()->create();
    $inA = calendarBooking('2027-01-02 10:00:00', ['device_id' => $deviceA->getKey()]);
    $inB = calendarBooking('2027-01-02 11:00:00', ['device_id' => $deviceB->getKey()]);

    $this->actingAs(calendarSecretary())
        ->get(calendarUrl('week', '1405/10/12', ['device_id' => $deviceA->getKey()]))
        ->assertOk()
        ->assertSee($inA->client->fullName())
        ->assertDontSee($inB->client->fullName());
});

test('a status filter keeps only that status', function () {
    $cancelled = calendarBooking('2027-01-02 10:00:00', ['status' => AppointmentStatus::Cancelled]);
    $scheduled = calendarBooking('2027-01-02 11:00:00', ['status' => AppointmentStatus::Scheduled]);

    $this->actingAs(calendarSecretary())
        ->get(calendarUrl('week', '1405/10/12', ['status' => 'cancelled']))
        ->assertOk()
        ->assertSee($cancelled->client->fullName())
        ->assertDontSee($scheduled->client->fullName());
});

test('a calendar page makes at most 15 queries, however many appointments it shows', function (string $view) {
    $secretary = calendarSecretary();

    for ($i = 0; $i < 12; $i++) {
        calendarBooking(
            CarbonImmutable::parse('2027-01-02 09:00', 'Asia/Tehran')->addMinutes(30 * $i)->format('Y-m-d H:i:s'),
            [
                'specialist_id' => User::factory()->role(StaffRole::Specialist)->create(['is_active' => true])->getKey(),
                'device_id' => Device::factory()->create()->getKey(),
                'room_id' => Room::factory()->create()->getKey(),
            ],
        );
    }

    DB::enableQueryLog();

    $this->actingAs($secretary)
        ->get(calendarUrl($view))
        ->assertOk();

    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBeLessThanOrEqual(15);
})->with(['day', 'week', 'month']);

test('the title is in Persian and names the Jalali month', function () {
    $title = app(CalendarService::class)->title('month', CarbonImmutable::parse('2027-01-02', 'Asia/Tehran'));

    expect($title)->toContain(Digits::toPersian(1405))
        ->and($title)->toContain((string) __('calendar.months.9'));
});
