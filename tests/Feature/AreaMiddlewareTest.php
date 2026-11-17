<?php

declare(strict_types=1);

use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware('area:admin')->get('/_test/admin-area', fn () => 'ok');
});

test('a guest cannot open the admin area', function () {
    $this->get('/_test/admin-area')->assertForbidden();
});

test('secretary and admin can open the admin area', function () {
    $this->actingAs(User::factory()->role(StaffRole::Secretary)->create())
        ->get('/_test/admin-area')->assertOk();

    $this->actingAs(User::factory()->role(StaffRole::Admin)->create())
        ->get('/_test/admin-area')->assertOk();
});

test('operator and specialist cannot open the admin area', function () {
    $this->actingAs(User::factory()->role(StaffRole::Operator)->create())
        ->get('/_test/admin-area')->assertForbidden();

    $this->actingAs(User::factory()->role(StaffRole::Specialist)->create())
        ->get('/_test/admin-area')->assertForbidden();
});
