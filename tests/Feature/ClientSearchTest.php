<?php

declare(strict_types=1);

use App\Enums\StaffRole;
use App\Models\Client;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->role(StaffRole::Admin)->create();

    makeClient(['first_name' => 'مریم', 'last_name' => 'احمدی', 'mobile' => '09121234567']);
    makeClient(['first_name' => 'علی', 'last_name' => 'رضایی', 'mobile' => '09351112233']);
});

test('search finds a client by a mobile typed with Persian digits', function () {
    $this->actingAs($this->admin)->get('/admin/clients?q=۰۹۱۲۱۲۳۴۵۶۷')
        ->assertOk()
        ->assertSee('مریم')
        ->assertDontSee('رضایی');
});

test('search finds a client by a mobile typed with ASCII digits', function () {
    $this->actingAs($this->admin)->get('/admin/clients?q=09121234567')
        ->assertOk()
        ->assertSee('مریم')
        ->assertDontSee('رضایی');
});

test('a partial mobile in Persian digits finds the client', function () {
    $this->actingAs($this->admin)->get('/admin/clients?q=۰۹۱۲۱۲')
        ->assertOk()
        ->assertSee('مریم');
});

test('search finds a client by name and by code', function () {
    $code = Client::query()->where('mobile', '09351112233')->value('code');

    $this->actingAs($this->admin)->get('/admin/clients?q=رضایی')->assertSee('علی')->assertDontSee('مریم');
    $this->actingAs($this->admin)->get('/admin/clients?q='.$code)->assertSee('علی')->assertDontSee('مریم');
});

test('a search term is matched literally, not as a wildcard', function () {
    $this->actingAs($this->admin)->get('/admin/clients?q=%25')->assertOk()->assertDontSee('مریم');
});
