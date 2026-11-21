<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\MeasurementSet;
use App\Services\Portal\ClientOwnedScope;
use App\Services\Progress\ProgressService;
use App\Support\Decimal;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The client's published reports (spec 8.5). Only published, current sets are listed or opened. A set that is
 * pending, superseded or belongs to another client is not found: 404.
 */
class PortalReportController extends Controller
{
    public function __construct(private readonly ProgressService $progress) {}

    public function index(Request $request): View
    {
        $client = $this->client($request);

        return view('portal.reports.index', [
            'sets' => ClientOwnedScope::apply(MeasurementSet::query()->current(), $client)
                ->orderByDesc('measured_at')
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function show(Request $request, int $set): View
    {
        $client = $this->client($request);

        $report = ClientOwnedScope::apply(MeasurementSet::query()->current()->with('measurements.type'), $client)
            ->whereKey($set)
            ->firstOrFail();

        // Each value is compared with the client's first published value of the same measurement, which is in the
        // earliest current set that has it (the same rule as ProgressService::summary()).
        $first = [];

        foreach ($this->progress->currentSets($client) as $earlier) {
            foreach ($earlier->valuesByKey() as $key => $value) {
                $first[$key] ??= $value;
            }
        }

        $rows = [];

        foreach ($report->measurements as $measurement) {
            $type = $measurement->type;

            if ($type === null) {
                continue;
            }

            $key = (string) $type->getAttribute('key');
            $value = (string) $measurement->getAttribute('value');
            $firstValue = $first[$key] ?? null;

            $rows[] = [
                'label' => $type->label(),
                'unit' => (string) $type->getAttribute('unit'),
                'value' => $value,
                'first' => $firstValue,
                'delta' => Decimal::difference($firstValue, $value),
            ];
        }

        return view('portal.reports.show', [
            'report' => $report,
            'rows' => $rows,
        ]);
    }

    protected function client(Request $request): Client
    {
        $client = $request->user('client');

        if (! $client instanceof Client) {
            throw new HttpException(401);
        }

        return $client;
    }
}
