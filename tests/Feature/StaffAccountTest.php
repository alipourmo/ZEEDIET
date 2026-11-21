<?php

declare(strict_types=1);

use App\Enums\AuditAction;
use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Inserts a session row for the user, as the database session driver would. */
function staffSession(User $user): string
{
    $id = bin2hex(random_bytes(16));
    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $user->getKey(),
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test',
        'payload' => 'x',
        'last_activity' => time(),
    ]);

    return $id;
}

function userPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'کاربر تست',
        'mobile' => '۰۹۱۲۱۱۱۲۲۳۳',
        'role' => 'secretary',
        'is_active' => '1',
    ], $overrides);
}

test('only the admin reaches the user screens', function () {
    $target = User::factory()->create();

    foreach (['secretary' => 403, 'operator' => 403, 'specialist' => 403, 'admin' => 200] as $role => $status) {
        $user = User::factory()->role(StaffRole::from($role))->create();

        $this->actingAs($user)->get('/admin/users')->assertStatus($status);
        $this->actingAs($user)->get('/admin/users/create')->assertStatus($status);
        $this->actingAs($user)->get("/admin/users/{$target->getKey()}/edit")->assertStatus($status);
    }
});

test('a non-admin cannot create, edit or reset a password', function () {
    $secretary = User::factory()->role(StaffRole::Secretary)->create();
    $target = User::factory()->create();
    $before = $target->password;

    $this->actingAs($secretary)->post('/admin/users', userPayload())->assertForbidden();
    $this->actingAs($secretary)->put("/admin/users/{$target->getKey()}", userPayload(['name' => 'x']))->assertForbidden();
    $this->actingAs($secretary)->post("/admin/users/{$target->getKey()}/reset-password")->assertForbidden();

    expect($target->refresh()->password)->toBe($before);
    expect(User::query()->where('mobile', '09121112233')->exists())->toBeFalse();
});

test('the admin creates a user with a one-time password, and the password is not stored', function () {
    $admin = User::factory()->role(StaffRole::Admin)->create();

    $response = $this->actingAs($admin)->post('/admin/users', userPayload());

    $response->assertOk()->assertSee('dir="rtl"', false);

    $user = User::query()->where('mobile', '09121112233')->firstOrFail();
    expect($user->role)->toBe(StaffRole::Secretary);
    expect($user->must_change_password)->toBeTrue();

    // The one-time password appears once, in the response. The stored value is only its hash.
    preg_match('/class="temp-password" dir="ltr">([^<]+)</', $response->getContent(), $m);
    expect($m[1] ?? '')->toHaveLength(16);
    expect(password_verify($m[1], $user->password))->toBeTrue();
    expect($user->password)->not->toBe($m[1]);
});

test('a created user can sign in with the one-time password and is then asked to change it', function () {
    $admin = User::factory()->role(StaffRole::Admin)->create();
    $response = $this->actingAs($admin)->post('/admin/users', userPayload(['mobile' => '09121119999']));

    preg_match('/class="temp-password" dir="ltr">([^<]+)</', $response->getContent(), $m);

    $this->post('/login', ['mobile' => '09121119999', 'password' => $m[1]])
        ->assertRedirect(route('password.change'));
});

test('the password reset creates a new password, forces a change, and signs the user out', function () {
    $admin = User::factory()->role(StaffRole::Admin)->create();
    $target = User::factory()->role(StaffRole::Specialist)->create(['must_change_password' => false]);
    $oldHash = $target->password;
    $session = staffSession($target);

    $response = $this->actingAs($admin)->post("/admin/users/{$target->getKey()}/reset-password");

    $response->assertOk();
    $target->refresh();

    expect($target->password)->not->toBe($oldHash);
    expect($target->must_change_password)->toBeTrue();
    expect(DB::table('sessions')->where('id', $session)->exists())->toBeFalse();
    expect(AuditLog::query()->where('action', AuditAction::PasswordReset->value)->exists())->toBeTrue();
});

test('an admin cannot reset their own password from the user screen', function () {
    $admin = User::factory()->role(StaffRole::Admin)->create();
    $before = $admin->password;

    $this->actingAs($admin)->post("/admin/users/{$admin->getKey()}/reset-password")
        ->assertRedirect(route('admin.users.edit', $admin));

    expect($admin->refresh()->password)->toBe($before);
});

test('deactivating a user removes their sessions, and they cannot sign in again', function () {
    $admin = User::factory()->role(StaffRole::Admin)->create();
    $target = User::factory()->role(StaffRole::Operator)->create(['mobile' => '09121110000']);
    $session = staffSession($target);

    $this->actingAs($admin)->put("/admin/users/{$target->getKey()}", userPayload([
        'name' => $target->name,
        'mobile' => '09121110000',
        'role' => 'operator',
        'is_active' => null,
    ]))->assertRedirect(route('admin.users.index'));

    expect($target->refresh()->is_active)->toBeFalse();
    expect(DB::table('sessions')->where('id', $session)->exists())->toBeFalse();

    auth()->logout();
    $this->post('/login', ['mobile' => '09121110000', 'password' => 'password'])
        ->assertSessionHas('error', __('auth.failed'));
    $this->assertGuest();
});

test('an admin cannot deactivate or change the role of their own account', function () {
    $admin = User::factory()->role(StaffRole::Admin)->create(['mobile' => '09121110001']);

    $this->actingAs($admin)->put("/admin/users/{$admin->getKey()}", userPayload([
        'name' => $admin->name,
        'mobile' => '09121110001',
        'role' => 'admin',
        'is_active' => null,
    ]))->assertSessionHasErrors(['is_active']);

    $this->actingAs($admin)->put("/admin/users/{$admin->getKey()}", userPayload([
        'name' => $admin->name,
        'mobile' => '09121110001',
        'role' => 'secretary',
        'is_active' => '1',
    ]))->assertSessionHasErrors(['role']);

    expect($admin->refresh()->role)->toBe(StaffRole::Admin);
    expect($admin->is_active)->toBeTrue();
});

test('the mobile must be unique and in the 09 format', function () {
    $admin = User::factory()->role(StaffRole::Admin)->create();
    User::factory()->create(['mobile' => '09121118888']);

    $this->actingAs($admin)->post('/admin/users', userPayload(['mobile' => '09121118888']))
        ->assertSessionHasErrors('mobile');

    $this->actingAs($admin)->post('/admin/users', userPayload(['mobile' => '12345']))
        ->assertSessionHasErrors('mobile');
});

test('every account change is audited, and no password is written to the log', function () {
    $admin = User::factory()->role(StaffRole::Admin)->create();

    $this->actingAs($admin)->post('/admin/users', userPayload(['mobile' => '09121117777']));
    $target = User::query()->where('mobile', '09121117777')->firstOrFail();

    $this->actingAs($admin)->put("/admin/users/{$target->getKey()}", userPayload([
        'name' => 'نام جدید',
        'mobile' => '09121117777',
        'role' => 'operator',
        'is_active' => '1',
    ]));

    $this->actingAs($admin)->post("/admin/users/{$target->getKey()}/reset-password");

    $actions = AuditLog::query()->where('subject_id', $target->getKey())->pluck('action')->map(fn ($a) => (string) $a)->all();
    expect($actions)->toContain('create', 'update', 'password_reset');

    $logged = AuditLog::query()->get()->toJson();
    expect($logged)->not->toContain('"password"');
});
