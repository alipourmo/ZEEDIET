<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceRequest;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Services (spec section 6.2 and 8.2). Admin only, through the catalog.manage Gate on the route.
 */
class ServiceController extends Controller
{
    public function index(): View
    {
        return view('catalog.services.index', [
            'services' => Service::query()->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('catalog.services.form', [
            'title' => __('catalog.new_service'),
            'action' => route('admin.services.store'),
            'method' => 'POST',
            'item' => null,
        ]);
    }

    public function store(ServiceRequest $request): RedirectResponse
    {
        Service::create($request->serviceData());

        return redirect()->route('admin.services.index')->with('success', __('catalog.saved'));
    }

    public function edit(Service $service): View
    {
        return view('catalog.services.form', [
            'title' => __('catalog.edit_service'),
            'action' => route('admin.services.update', $service),
            'method' => 'PUT',
            'item' => $service,
        ]);
    }

    public function update(ServiceRequest $request, Service $service): RedirectResponse
    {
        $service->update($request->serviceData());

        return redirect()->route('admin.services.index')->with('success', __('catalog.saved'));
    }
}
