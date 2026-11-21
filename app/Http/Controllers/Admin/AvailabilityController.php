<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AvailabilityRequest;
use App\Models\Service;
use App\Services\Scheduling\SlotFinder;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

/**
 * GET /admin/api/availability: the free start times per day for a service and its resources (spec section 8.2).
 * Read-only, so it writes no audit row. It exposes no client data.
 */
class AvailabilityController extends Controller
{
    public function __construct(private readonly SlotFinder $slots) {}

    public function slots(AvailabilityRequest $request): JsonResponse
    {
        $service = Service::query()->findOrFail((int) $request->input('service_id'));
        $from = $request->fromDate() ?? abort(422);

        $days = $this->slots->forDays(
            $service->default_duration_min,
            $request->resourceIds(),
            $from,
            $request->days(),
            (int) config('clinic.slot_step_minutes', 15),
            CarbonImmutable::now('Asia/Tehran'),
        );

        return response()->json(['days' => $days])->header('Cache-Control', 'no-store');
    }
}
