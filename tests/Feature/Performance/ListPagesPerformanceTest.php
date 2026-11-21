<?php

declare(strict_types=1);

use App\Enums\StaffRole;
use App\Models\Client;
use App\Models\Device;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

// Performance pass (spec section 12, S5-T6): 2,000 clients and 20,000 appointments, and the list pages answer in under
// 300 ms. The rows are inserted in bulk. Each page is requested once before it is timed, so view compilation is not
// counted. Lazy loading is refused outside production (AppServiceProvider), so an N+1 query fails these pages too.

const PERF_CLIENTS = 2000;
const PERF_APPOINTMENTS = 20000;
const PERF_MAX_MS = 300;

/**
 * Inserts the dataset and returns the admin who will look at it.
 */
function perfSeedDataset(): User
{
    $admin = User::factory()->role(StaffRole::Admin)->create(['is_active' => true]);
    $service = Service::factory()->create();
    $device = Device::factory()->create();
    $stamp = now()->toDateTimeString();

    for ($from = 0; $from < PERF_CLIENTS; $from += 500) {
        $rows = [];

        for ($i = $from; $i < min($from + 500, PERF_CLIENTS); $i++) {
            $attributes = Client::factory()->make([
                'code' => 'P-'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                'first_name' => 'مراجع '.$i,
                'mobile' => '0913'.str_pad((string) $i, 7, '0', STR_PAD_LEFT),
                'created_by' => $admin->getKey(),
            ])->getAttributes();

            $rows[] = $attributes + ['created_at' => $stamp, 'updated_at' => $stamp];
        }

        DB::table('clients')->insert($rows);
    }

    $clientIds = DB::table('clients')->orderBy('id')->pluck('id')->all();
    $base = CarbonImmutable::parse('2026-06-01 09:00', 'Asia/Tehran');
    $statuses = ['scheduled', 'completed', 'no_show'];

    for ($from = 0; $from < PERF_APPOINTMENTS; $from += 1000) {
        $rows = [];

        for ($i = $from; $i < min($from + 1000, PERF_APPOINTMENTS); $i++) {
            $start = $base->addDays($i % 365)->addMinutes(($i % 20) * 30);

            $rows[] = [
                'client_id' => $clientIds[$i % count($clientIds)],
                'service_id' => $service->getKey(),
                'specialist_id' => null,
                'device_id' => $device->getKey(),
                'room_id' => null,
                'starts_at' => $start->toDateTimeString(),
                'ends_at' => $start->addMinutes(30)->toDateTimeString(),
                'status' => $statuses[$i % 3],
                'source' => 'staff',
                'cancel_reason' => null,
                'note' => null,
                'created_by' => $admin->getKey(),
                'created_at' => $stamp,
                'updated_at' => $stamp,
            ];
        }

        DB::table('appointments')->insert($rows);
    }

    return $admin;
}

test('the list pages answer in under 300 ms with 2,000 clients and 20,000 appointments', function () {
    $admin = perfSeedDataset();

    expect(DB::table('clients')->count())->toBe(PERF_CLIENTS)
        ->and(DB::table('appointments')->count())->toBe(PERF_APPOINTMENTS);

    $this->actingAs($admin);

    foreach (['admin.clients.index', 'admin.calendar.index', 'admin.audit.index', 'admin.sms.log'] as $name) {
        $url = route($name);

        // Warm-up: compiles the view and proves the page renders without lazy-loading errors.
        $this->get($url)->assertOk();

        $start = hrtime(true);
        $this->get($url)->assertOk();
        $milliseconds = (hrtime(true) - $start) / 1_000_000;

        $this->assertLessThan(PERF_MAX_MS, $milliseconds, $name.' took '.round($milliseconds).' ms');
    }
});
