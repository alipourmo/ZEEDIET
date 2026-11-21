<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ScheduleResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateScheduleRequest;
use App\Services\Scheduling\ScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Weekly working hours of specialists, devices and rooms (spec sections 6.2 and 8.2). Admin only: catalog.manage.
 */
class ScheduleController extends Controller
{
    public function __construct(private readonly ScheduleService $schedules) {}

    public function index(): View
    {
        return view('admin.schedules.index', [
            'groups' => $this->schedules->groups(),
        ]);
    }

    public function edit(string $type, int $id): View
    {
        $resource = ScheduleResource::from($type);
        $owner = $this->schedules->owner($resource, $id);

        return view('admin.schedules.form', [
            'name' => (string) $owner->getAttribute('name'),
            'days' => $this->schedules->daysFor($resource, $id),
            'action' => route('admin.schedules.update', [$type, $id]),
        ]);
    }

    public function update(UpdateScheduleRequest $request, string $type, int $id): RedirectResponse
    {
        $resource = ScheduleResource::from($type);
        $this->schedules->owner($resource, $id);

        $this->schedules->replace($resource, $id, $request->rows());

        return redirect()->route('admin.schedules.index')->with('success', __('scheduling.saved'));
    }
}
