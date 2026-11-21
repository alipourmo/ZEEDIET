<?php

declare(strict_types=1);

use App\Enums\StaffRole;
use App\Models\Client;
use App\Models\MeasurementSet;
use App\Models\User;
use App\Services\Measurements\MeasurementRecorder;
use App\Services\Progress\ProgressService;
use App\Services\Reports\ReportWorkflow;
use App\Support\Display;
use Carbon\CarbonImmutable;
use Database\Seeders\MeasurementTypeSeeder;

beforeEach(function () {
    $this->seed(MeasurementTypeSeeder::class);
});

function progUser(StaffRole $role): User
{
    return User::factory()->role($role)->create(['is_active' => true]);
}

/** The clinic's day, offset by a number of days. */
function progDay(int $days): CarbonImmutable
{
    return CarbonImmutable::now('Asia/Tehran')->startOfDay()->addDays($days);
}

/**
 * @param  array<string, string>  $values
 */
function progPending(array $values, Client $client, CarbonImmutable $at): MeasurementSet
{
    return app(MeasurementRecorder::class)->record($client, progUser(StaffRole::Operator), $at, $values);
}

/**
 * A recorded set, published by a specialist.
 *
 * @param  array<string, string>  $values
 */
function progPublish(array $values, Client $client, CarbonImmutable $at): MeasurementSet
{
    return app(ReportWorkflow::class)->publish(progPending($values, $client, $at), progUser(StaffRole::Specialist));
}

/**
 * @param  list<array{key: string, label: string, unit: string, first: ?string, latest: ?string, delta: ?string}>  $rows
 * @return array{key: string, label: string, unit: string, first: ?string, latest: ?string, delta: ?string}
 */
function progRow(array $rows, string $key): array
{
    foreach ($rows as $row) {
        if ($row['key'] === $key) {
            return $row;
        }
    }

    throw new RuntimeException('No row for '.$key);
}

test('the summary gives the first and latest published value of each type, with the change', function () {
    $client = makeClient();
    $first = progPublish(['weight' => '70', 'waist' => '90'], $client, progDay(-60));
    $latest = progPublish(['weight' => '72', 'waist' => '88'], $client, progDay(-30));

    $summary = app(ProgressService::class)->summary($client);

    expect($summary['first']?->getKey())->toBe($first->getKey())
        ->and($summary['latest']?->getKey())->toBe($latest->getKey())
        ->and(progRow($summary['rows'], 'weight'))->toMatchArray(['first' => '70.00', 'latest' => '72.00', 'delta' => '+2.00'])
        ->and(progRow($summary['rows'], 'waist')['delta'])->toBe('-2.00');
});

test('a type with a single value has no change to show as a movement', function () {
    $client = makeClient();
    progPublish(['chest' => '95'], $client, progDay(-10));

    $row = progRow(app(ProgressService::class)->summary($client)['rows'], 'chest');

    expect($row)->toMatchArray(['first' => '95.00', 'latest' => '95.00', 'delta' => '0.00']);
});

test('a correction replaces the value it corrects in the summary and the chart', function () {
    $client = makeClient();
    $original = progPublish(['weight' => '72.5'], $client, progDay(-30));
    $specialist = progUser(StaffRole::Specialist);
    $correction = app(ReportWorkflow::class)->correct($original, $specialist, ['weight' => '70']);
    app(ReportWorkflow::class)->publish($correction, $specialist);

    $service = app(ProgressService::class);
    $summary = $service->summary($client);
    $chart = $service->chart($client, ['weight']);

    expect(progRow($summary['rows'], 'weight')['first'])->toBe('70.00')
        ->and($summary['first']?->getKey())->toBe($correction->getKey())
        ->and($chart['labels'])->toHaveCount(1)
        ->and($chart['series'][0]['data'])->toBe([70.0]);
});

test('a pending set is in neither the summary nor the chart', function () {
    $client = makeClient();
    progPending(['weight' => '90'], $client, progDay(-1));

    $service = app(ProgressService::class);
    $summary = $service->summary($client);

    expect($summary['first'])->toBeNull()
        ->and(progRow($summary['rows'], 'weight')['first'])->toBeNull()
        ->and($service->chart($client)['labels'])->toBe([]);
});

test('the chart has one label per set, oldest first, with gaps where a set has no value', function () {
    $client = makeClient();
    progPublish(['weight' => '70'], $client, progDay(-60));
    progPublish(['weight' => '72', 'waist' => '88'], $client, progDay(-30));

    $chart = app(ProgressService::class)->chart($client, ['weight', 'waist']);

    expect($chart['labels'])->toHaveCount(2)
        ->and($chart['labels'][0])->toBe(Display::jdate(progDay(-60)))
        ->and($chart['labels'][1])->toBe(Display::jdate(progDay(-30)))
        ->and($chart['series'][0]['data'])->toBe([70.0, 72.0])
        ->and($chart['series'][1]['data'])->toBe([null, 88.0]);
});

test('a chart with more than four series is refused', function () {
    expect(fn () => app(ProgressService::class)->chart(makeClient(), ['weight', 'waist', 'abdomen', 'hip', 'chest']))
        ->toThrow(InvalidArgumentException::class);
});

test('another client\'s sets never appear in the progress', function () {
    progPublish(['weight' => '70'], makeClient(), progDay(-30));

    $client = makeClient();
    $service = app(ProgressService::class);

    expect($service->summary($client)['first'])->toBeNull()
        ->and($service->chart($client)['labels'])->toBe([]);
});

test('the review page shows the chart when there is published history, and the empty message when there is none', function () {
    $client = makeClient();
    $pending = progPending(['weight' => '72'], $client, progDay(0));
    $specialist = progUser(StaffRole::Specialist);

    $this->actingAs($specialist)
        ->get(route('specialist.reviews.show', $pending))
        ->assertOk()
        ->assertSee(__('progress.chart_empty'))
        ->assertDontSee('chart.umd.js', false)
        ->assertDontSee('data-chart-line', false);

    progPublish(['weight' => '70'], $client, progDay(-30));

    $this->actingAs($specialist)
        ->get(route('specialist.reviews.show', $pending))
        ->assertOk()
        ->assertSee('data-chart-line', false)
        ->assertSee('vendor/chartjs/chart.umd.js', false)
        ->assertDontSee(__('progress.chart_empty'));
});
