<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\RoomRequest;
use App\Models\Room;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Rooms (spec section 6.2 and 8.2). Admin only, through the catalog.manage Gate on the route.
 */
class RoomController extends Controller
{
    public function index(): View
    {
        return view('catalog.rooms.index', [
            'rooms' => Room::query()->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('catalog.rooms.form', [
            'title' => __('catalog.new_room'),
            'action' => route('admin.rooms.store'),
            'method' => 'POST',
            'item' => null,
        ]);
    }

    public function store(RoomRequest $request): RedirectResponse
    {
        Room::create($request->roomData());

        return redirect()->route('admin.rooms.index')->with('success', __('catalog.saved'));
    }

    public function edit(Room $room): View
    {
        return view('catalog.rooms.form', [
            'title' => __('catalog.edit_room'),
            'action' => route('admin.rooms.update', $room),
            'method' => 'PUT',
            'item' => $room,
        ]);
    }

    public function update(RoomRequest $request, Room $room): RedirectResponse
    {
        $room->update($request->roomData());

        return redirect()->route('admin.rooms.index')->with('success', __('catalog.saved'));
    }
}
