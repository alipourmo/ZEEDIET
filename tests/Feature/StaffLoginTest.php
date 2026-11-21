<?php

declare(strict_types=1);

use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

test('the login page renders in right-to-left Persian', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('lang="fa"', false)
        ->assertSee('dir="rtl"', false)
        ->assertSee(__('auth.login_title'));
});

test('each role is sent to its panel after login', function (string $role, string $route) {
    $user = User::factory()->role(StaffRole::from($role))->create(['mobile' => '09121234567']);

    $this->post('/login', ['mobile' => '09121234567', 'password' => 'password'])
        ->assertRedirect(route($route));

    $this->assertAuthenticatedAs($user);
})->with([
    'admin' => ['admin', 'admin.home'],
    'secretary' => ['secretary', 'admin.home'],
    'operator' => ['operator', 'operator.home'],
    'specialist' => ['specialist', 'specialist.home'],
]);

test('Persian digits and the +98 prefix are accepted as the mobile number', function () {
    User::factory()->role(StaffRole::Secretary)->create(['mobile' => '09121234567']);

    $this->post('/login', ['mobile' => '۰۹۱۲۱۲۳۴۵۶۷', 'password' => 'password'])
        ->assertRedirect(route('admin.home'));

    Auth::logout();

    $this->post('/login', ['mobile' => '+989121234567', 'password' => 'password'])
        ->assertRedirect(route('admin.home'));
});

test('a wrong password and an unknown mobile give the same message', function () {
    User::factory()->create(['mobile' => '09121234567']);

    $this->post('/login', ['mobile' => '09121234567', 'password' => 'wrong-password'])
        ->assertSessionHas('error', __('auth.failed'));
    $this->assertGuest();

    $this->post('/login', ['mobile' => '09129999999', 'password' => 'password'])
        ->assertSessionHas('error', __('auth.failed'));
    $this->assertGuest();
});

test('a deactivated user cannot log in', function () {
    User::factory()->inactive()->create(['mobile' => '09121234567']);

    $this->post('/login', ['mobile' => '09121234567', 'password' => 'password'])
        ->assertSessionHas('error', __('auth.failed'));

    $this->assertGuest();
});

test('an active session of a deactivated user ends on the next request', function () {
    $user = User::factory()->role(StaffRole::Admin)->create();

    $this->actingAs($user);
    $user->forceFill(['is_active' => false])->save();

    $this->get('/admin')->assertRedirect(route('login'));

    $this->assertGuest();
});

test('more than five failed attempts per minute are throttled, even with the right password', function () {
    User::factory()->create(['mobile' => '09121234567']);

    for ($i = 0; $i < 5; $i++) {
        $this->post('/login', ['mobile' => '09121234567', 'password' => 'wrong-password']);
    }

    $this->post('/login', ['mobile' => '09121234567', 'password' => 'password'])
        ->assertSessionHas('error');

    $this->assertGuest();
});

test('the throttle is per mobile and IP, so another IP can still log in', function () {
    User::factory()->create(['mobile' => '09121234567']);

    for ($i = 0; $i < 5; $i++) {
        $this->post('/login', ['mobile' => '09121234567', 'password' => 'wrong-password']);
    }

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
        ->post('/login', ['mobile' => '09121234567', 'password' => 'password'])
        ->assertRedirect(route('admin.home'));
});

test('a successful login sets last_login_at', function () {
    $user = User::factory()->role(StaffRole::Operator)->create(['mobile' => '09121234567']);

    expect($user->last_login_at)->toBeNull();

    $this->post('/login', ['mobile' => '09121234567', 'password' => 'password']);

    expect($user->refresh()->last_login_at)->not->toBeNull();
});

test('logout ends the session', function () {
    $user = User::factory()->role(StaffRole::Admin)->create();

    $this->actingAs($user)->post('/logout')->assertRedirect(route('login'));

    $this->assertGuest();
});

test('a guest is sent to the login page from a panel', function () {
    $this->get('/admin')->assertRedirect(route('login'));
    $this->get('/operator')->assertRedirect(route('login'));
    $this->get('/specialist')->assertRedirect(route('login'));
});
