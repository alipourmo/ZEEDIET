<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeviceRequest;
use App\Models\Device;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Devices (spec section 6.2 and 8.2). Admin only, through the catalog.manage Gate on the route.
 */
class DeviceController extends Controller
{
    public function index(): View
    {
        return view('catalog.devices.index', [
            'devices' => Device::query()->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('catalog.devices.form', [
            'title' => __('catalog.new_device'),
            'action' => route('admin.devices.store'),
            'method' => 'POST',
            'item' => null,
        ]);
    }

    public function store(DeviceRequest $request): RedirectResponse
    {
        Device::create($request->deviceData());

        return redirect()->route('admin.devices.index')->with('success', __('catalog.saved'));
    }

    public function edit(Device $device): View
    {
        return view('catalog.devices.form', [
            'title' => __('catalog.edit_device'),
            'action' => route('admin.devices.update', $device),
            'method' => 'PUT',
            'item' => $device,
        ]);
    }

    public function update(DeviceRequest $request, Device $device): RedirectResponse
    {
        $device->update($request->deviceData());

        return redirect()->route('admin.devices.index')->with('success', __('catalog.saved'));
    }
}
