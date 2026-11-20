<?php

declare(strict_types=1);

use App\Enums\MeasurementSetStatus;
use App\Enums\StaffRole;
use App\Models\Client;
use App\Models\ClientMedical;
use App\Models\Device;
use App\Models\DeviceSession;
use App\Models\MeasurementSet;
use App\Models\MeasurementType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\MeasurementTypeSeeder;

function entOperator(): User
{
    return User::factory()->role(StaffRole::Operator)->create(['is_active' => true]);
}

/** A device session by this operator, performed at the given clinic time. */
function entSession(User $operator, Client $client, CarbonImmutable $performedAt, int $number = 1): DeviceSession
{
    return DeviceSession::factory()->create([
        'client_id' => $client->getKey(),
        'device_id' => Device::factory(),
        'operator_id' => $operator->getKey(),
        'session_number' => $number,
        'performed_at' => $performedAt,
    ]);
}

test('the page lists the operator\'s own sessions and measurement sets from the last seven days', function () {
    $this->seed(MeasurementTypeSeeder::class);
    $me = entOperator();
    $other = entOperator();
    $today = CarbonImmutable::now('Asia/Tehran');

    entSession($me, Client::factory()->create(['first_name' => 'نگار', 'last_name' => 'احمدی']), $today->setTime(9, 0));
    entSession($me, Client::factory()->create(['first_name' => 'سارا', 'last_name' => 'کریمی']), $today->subDays(6)->setTime(10, 0));
    entSession($other, Client::factory()->create(['first_name' => 'مریم', 'last_name' => 'رضایی']), $today->setTime(11, 0));
    entSession($me, Client::factory()->create(['first_name' => 'لیلا', 'last_name' => 'نوری']), $today->subDays(8)->setTime(10, 0));

    MeasurementSet::query()->create([
        'client_id' => Client::factory()->create(['first_name' => 'پریسا', 'last_name' => 'صادقی'])->getKey(),
        'measured_at' => $today->toDateString(),
        'recorded_by' => $me->getKey(),
        'status' => MeasurementSetStatus::PendingReview,
    ]);

    $this->actingAs($me)
        ->get(route('operator.entries'))
        ->assertOk()
        ->assertSee('نگار')
        ->assertSee('سارا')
        ->assertSee('پریسا')
        ->assertDontSee('مریم')
        ->assertDontSee('لیلا');
});

test('the measurement row shows the values the operator entered', function () {
    $this->seed(MeasurementTypeSeeder::class);
    $me = entOperator();

    $set = MeasurementSet::query()->create([
        'client_id' => Client::factory()->create()->getKey(),
        'measured_at' => CarbonImmutable::now('Asia/Tehran')->toDateString(),
        'recorded_by' => $me->getKey(),
        'status' => MeasurementSetStatus::PendingReview,
    ]);
    $set->measurements()->create(['measurement_type_id' => MeasurementType::query()->where('key', 'weight')->value('id'), 'value' => '72.5']);

    $this->actingAs($me)
        ->get(route('operator.entries'))
        ->assertOk()
        ->assertSee('وزن ۷۲.۵۰ kg', false)
        ->assertSee(__('operator.status_pending'));
});

test('the page shows no phone digits and no medical free text', function () {
    $me = entOperator();
    $client = Client::factory()->create(['mobile' => '09121234567']);
    $client->load('medical');
    ClientMedical::query()->create(['client_id' => $client->getKey(), 'food_allergies' => 'گردو', 'device_contraindications' => []]);

    entSession($me, $client, CarbonImmutable::now('Asia/Tehran')->setTime(9, 0));

    $this->actingAs($me)
        ->get(route('operator.entries'))
        ->assertOk()
        ->assertDontSee('09121234567')
        ->assertDontSee('۰۹۱۲۱۲۳۴۵۶۷')
        ->assertDontSee('گردو');
});

test('an operator with no entries sees the empty message', function () {
    $this->actingAs(entOperator())
        ->get(route('operator.entries'))
        ->assertOk()
        ->assertSee(__('operator.entries_empty'));
});

test('only the operator can open the page; other roles get 403, guests are sent to log in', function (string $role, int $status) {
    $this->actingAs(User::factory()->role(StaffRole::from($role))->create(['is_active' => true]))
        ->get(route('operator.entries'))
        ->assertStatus($status);
})->with([
    'operator' => ['operator', 200],
    'admin' => ['admin', 403],
    'secretary' => ['secretary', 403],
    'specialist' => ['specialist', 403],
]);

test('a guest is sent to log in from my entries', function () {
    $this->get(route('operator.entries'))->assertRedirect();
});
