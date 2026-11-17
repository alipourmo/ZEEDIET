<?php

declare(strict_types=1);

use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('a seeded account with a temporary password is sent to change it on login', function () {
    User::factory()->role(StaffRole::Admin)->mustChangePassword()->create(['mobile' => '09121234567']);

    $this->post('/login', ['mobile' => '09121234567', 'password' => 'password'])
        ->assertRedirect(route('password.change'));
});

test('a signed-in user with a temporary password cannot open a panel until it is changed', function () {
    $user = User::factory()->role(StaffRole::Admin)->mustChangePassword()->create();

    $this->actingAs($user)->get('/admin')->assertRedirect(route('password.change'));
});

test('a wrong current password is refused', function () {
    $user = User::factory()->role(StaffRole::Admin)->mustChangePassword()->create();

    $this->actingAs($user)
        ->post('/password/change', [
            'current_password' => 'not-the-password',
            'password' => 'a-new-long-password',
            'password_confirmation' => 'a-new-long-password',
        ])
        ->assertSessionHasErrors('current_password');

    expect($user->refresh()->must_change_password)->toBeTrue();
});

test('a new password shorter than 10 characters is refused', function () {
    $user = User::factory()->role(StaffRole::Admin)->mustChangePassword()->create();

    $this->actingAs($user)
        ->post('/password/change', [
            'current_password' => 'password',
            'password' => 'short-one',
            'password_confirmation' => 'short-one',
        ])
        ->assertSessionHasErrors('password');
});

test('the new password must be confirmed and must differ from the current one', function () {
    $user = User::factory()->role(StaffRole::Admin)->mustChangePassword()->create();

    $this->actingAs($user)
        ->post('/password/change', [
            'current_password' => 'password',
            'password' => 'a-new-long-password',
            'password_confirmation' => 'something-else-entirely',
        ])
        ->assertSessionHasErrors('password');

    $this->actingAs($user)
        ->post('/password/change', [
            'current_password' => 'password',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->assertSessionHasErrors('password');
});

test('a valid change clears the flag, redirects to the panel and the new password works', function () {
    $user = User::factory()->role(StaffRole::Secretary)->mustChangePassword()->create(['mobile' => '09121234567']);

    $this->actingAs($user)
        ->post('/password/change', [
            'current_password' => 'password',
            'password' => 'a-new-long-password',
            'password_confirmation' => 'a-new-long-password',
        ])
        ->assertRedirect(route('admin.home'));

    $user->refresh();
    expect($user->must_change_password)->toBeFalse()
        ->and(Hash::check('a-new-long-password', $user->password))->toBeTrue();

    $this->post('/logout');
    $this->post('/login', ['mobile' => '09121234567', 'password' => 'a-new-long-password'])
        ->assertRedirect(route('admin.home'));
});

test('the change-password page needs a signed-in user', function () {
    $this->get('/password/change')->assertRedirect(route('login'));
});
