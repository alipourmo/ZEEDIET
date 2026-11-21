<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Enums\ServiceKind;
use App\Enums\StaffRole;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\ClientMedical;
use App\Models\Device;
use App\Models\Service;
use App\Models\User;
use App\Services\Operator\TodayAppointments;
use Carbon\CarbonImmutable;

function todayDeviceService(bool $requiresDevice = true): Service
{
    return Service::factory()->create([
        'kind' => $requiresDevice ? ServiceKind::DeviceSession : ServiceKind::Consultation,
        'requires_device' => $requiresDevice,
        'requires_specialist' => false,
        'is_active' => true,
    ]);
}

/**
 * An appointment for the given client, starting at the given clinic time.
 *
 * @param  array<string, mixed>  $overrides
 */
function todayAppointment(Client $client, Service $service, CarbonImmutable $start, array $overrides = []): Appointment
{
    return Appointment::factory()->create(array_merge([
        'client_id' => $client->getKey(),
        'service_id' => $service->getKey(),
        'device_id' => Device::factory()->create()->getKey(),
        'starts_at' => $start,
        'ends_at' => $start->addMinutes(30),
        'status' => AppointmentStatus::Scheduled,
    ], $overrides));
}

function todayOperator(): User
{
    return User::factory()->role(StaffRole::Operator)->create(['is_active' => true]);
}

function todayAt(string $time): CarbonImmutable
{
    return CarbonImmutable::now('Asia/Tehran')->startOfDay()->setTimeFromTimeString($time);
}

test('the list shows only today\'s device appointments that are not cancelled or no-show', function () {
    $device = todayDeviceService();
    $consultation = todayDeviceService(requiresDevice: false);

    todayAppointment(Client::factory()->create(['first_name' => 'نگار', 'last_name' => 'احمدی']), $device, todayAt('10:00'));
    todayAppointment(Client::factory()->create(['first_name' => 'لیلا', 'last_name' => 'نوری']), $device, todayAt('11:00'), [
        'status' => AppointmentStatus::Cancelled,
        'cancel_reason' => 'لغو شد',
    ]);
    todayAppointment(Client::factory()->create(['first_name' => 'مریم', 'last_name' => 'غفاری']), $device, todayAt('12:00'), [
        'status' => AppointmentStatus::NoShow,
    ]);
    todayAppointment(Client::factory()->create(['first_name' => 'مهدی', 'last_name' => 'کریمی']), $device, todayAt('10:00')->subDay());
    todayAppointment(Client::factory()->create(['first_name' => 'پریسا', 'last_name' => 'صادقی']), $device, todayAt('10:00')->addDay());
    todayAppointment(Client::factory()->create(['first_name' => 'شیما', 'last_name' => 'رستمی']), $consultation, todayAt('13:00'));

    $this->actingAs(todayOperator())->get(route('operator.today'))
        ->assertOk()
        ->assertSee('نگار')
        ->assertDontSee('لیلا')
        ->assertDontSee('مریم')
        ->assertDontSee('مهدی')
        ->assertDontSee('پریسا')
        ->assertDontSee('شیما');
});

test('the list shows the last four digits of a mobile, never the full number', function () {
    $client = Client::factory()->create(['mobile' => '09121234567']);
    todayAppointment($client, todayDeviceService(), todayAt('10:00'));

    $this->actingAs(todayOperator())->get(route('operator.today'))
        ->assertOk()
        ->assertSee('۴۵۶۷')
        ->assertDontSee('09121234567')
        ->assertDontSee('۰۹۱۲۱۲۳۴۵۶۷');
});

test('the list shows the allergy flag but not the medical free text', function () {
    $client = Client::factory()->create();
    ClientMedical::query()->create([
        'client_id' => $client->getKey(),
        'food_allergies' => 'گردو و بادام',
        'device_contraindications' => ['pregnancy'],
    ]);
    todayAppointment($client, todayDeviceService(), todayAt('10:00'));

    $this->actingAs(todayOperator())->get(route('operator.today'))
        ->assertOk()
        ->assertSee(__('alerts.allergy_yes'))
        ->assertSee(__('clients.contraindications.pregnancy'))
        ->assertDontSee('گردو');
});

test('appointments are grouped by the hour they start', function () {
    $device = todayDeviceService();
    todayAppointment(Client::factory()->create(), $device, todayAt('10:00'));
    todayAppointment(Client::factory()->create(), $device, todayAt('10:30'));
    todayAppointment(Client::factory()->create(), $device, todayAt('14:00'));

    $this->actingAs(todayOperator())->get(route('operator.today'))
        ->assertOk()
        ->assertSee('۱۰:۰۰')
        ->assertSee('۱۰:۳۰')
        ->assertSee('۱۴:۰۰');
});

test('an empty day says so', function () {
    $this->actingAs(todayOperator())->get(route('operator.today'))
        ->assertOk()
        ->assertSee(__('operator.none_today'));
});

test('only the operator can open today\'s list; the other roles are refused', function (string $role, int $status) {
    $this->actingAs(User::factory()->role(StaffRole::from($role))->create(['is_active' => true]))
        ->get(route('operator.today'))
        ->assertStatus($status);
})->with([
    'operator' => ['operator', 200],
    'admin' => ['admin', 403],
    'secretary' => ['secretary', 403],
    'specialist' => ['specialist', 403],
]);

test('a guest is sent to log in', function () {
    $this->get(route('operator.today'))->assertRedirect();
});

test('today means the clinic day: an appointment at 00:30 tomorrow is not today', function () {
    $tomorrow = CarbonImmutable::now('Asia/Tehran')->addDay()->startOfDay()->setTime(0, 30);
    $appointment = todayAppointment(Client::factory()->create(), todayDeviceService(), $tomorrow);

    expect(TodayAppointments::isToday($appointment))->toBeFalse();
});
