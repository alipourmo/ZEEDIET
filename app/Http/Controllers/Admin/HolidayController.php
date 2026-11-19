<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\HolidayRequest;
use App\Models\Holiday;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Official holidays (spec sections 6.2 and 8.2). Admin only: catalog.manage on each route.
 */
class HolidayController extends Controller
{
    public function index(): View
    {
        return view('admin.holidays.index', [
            'holidays' => Holiday::query()->orderBy('date')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.holidays.form', [
            'title' => __('scheduling.holiday_new'),
            'action' => route('admin.holidays.store'),
            'method' => 'POST',
            'holiday' => null,
        ]);
    }

    public function store(HolidayRequest $request): RedirectResponse
    {
        Holiday::query()->create($request->holidayData());

        return redirect()->route('admin.holidays.index')->with('success', __('scheduling.holiday_saved'));
    }

    public function edit(Holiday $holiday): View
    {
        return view('admin.holidays.form', [
            'title' => __('scheduling.holiday_edit'),
            'action' => route('admin.holidays.update', $holiday),
            'method' => 'PUT',
            'holiday' => $holiday,
        ]);
    }

    public function update(HolidayRequest $request, Holiday $holiday): RedirectResponse
    {
        $holiday->update($request->holidayData());

        return redirect()->route('admin.holidays.index')->with('success', __('scheduling.holiday_saved'));
    }

    /**
     * Holidays are plain calendar entries, not medical or financial records, so they may be removed.
     */
    public function destroy(Holiday $holiday): RedirectResponse
    {
        $holiday->delete();

        return redirect()->route('admin.holidays.index')->with('success', __('scheduling.holiday_deleted'));
    }
}
