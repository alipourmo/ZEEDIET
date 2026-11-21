<?php

declare(strict_types=1);

use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/** Admin is the only role with client.delete, and the only one that creates clients here. */
function adminUser(): User
{
    return User::factory()->role(StaffRole::Admin)->create();
}

test('an admin creates a client with a generated code, Gregorian birth date and audit row', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)->post(route('admin.clients.store'), clientPayload());

    $client = Client::query()->where('mobile', '09121234567')->firstOrFail();

    $response->assertRedirect(route('admin.clients.show', $client));
    expect($client->code)->toBe('C-000001');
    expect($client->birth_date->format('Y-m-d'))->toBe('1991-08-03');
    expect($client->consent_data_at)->not->toBeNull();
    expect($client->consent_photos_at)->not->toBeNull();
    expect($client->medical)->not->toBeNull();

    $log = AuditLog::query()->where('action', 'create')->where('client_id', $client->getKey())->firstOrFail();
    expect($log->actor_id)->toBe($admin->getKey());
    expect($log->subject_type)->toBe(Client::class);
});

test('client codes are sequential and never reused after a soft delete', function () {
    $admin = adminUser();

    $this->actingAs($admin)->post(route('admin.clients.store'), clientPayload(['mobile' => '09120000001']));
    $second = Client::query()->where('mobile', '09120000001')->firstOrFail();
    $this->actingAs($admin)->delete(route('admin.clients.destroy', $second));

    $this->actingAs($admin)->post(route('admin.clients.store'), clientPayload(['mobile' => '09120000002']));

    expect(Client::withTrashed()->where('mobile', '09120000002')->value('code'))->toBe('C-000002');
});

test('data consent is required to create a client', function () {
    $response = $this->actingAs(adminUser())->post(route('admin.clients.store'), clientPayload(['consent_data' => null]));

    $response->assertSessionHasErrors('consent_data');
    expect(Client::count())->toBe(0);
});

test('mobile numbers are normalised before they are saved', function (string $input) {
    $this->actingAs(adminUser())->post(route('admin.clients.store'), clientPayload(['mobile' => $input]));

    expect(Client::query()->value('mobile'))->toBe('09121234567');
})->with(['Persian digits' => '۰۹۱۲۱۲۳۴۵۶۷', '+98 form' => '+989121234567', 'with dashes' => '0912-123-4567']);

test('an invalid mobile number is rejected', function () {
    $this->actingAs(adminUser())->post(route('admin.clients.store'), clientPayload(['mobile' => '0812']))
        ->assertSessionHasErrors('mobile');
});

test('an invalid Jalali birth date is rejected', function () {
    $this->actingAs(adminUser())->post(route('admin.clients.store'), clientPayload(['birth_date' => '۱۳۷۰/۱۳/۱۲']))
        ->assertSessionHasErrors('birth_date');
});

test('a duplicate active mobile is refused with a link to the existing file', function () {
    $existing = makeClient(['mobile' => '09121234567']);

    $response = $this->actingAs(adminUser())->post(route('admin.clients.store'), clientPayload());

    $response->assertSessionHasErrors(['mobile' => __('clients.duplicate_mobile')]);
    $response->assertSessionHas('duplicate_client_id', $existing->getKey());
    expect(Client::withTrashed()->count())->toBe(1);
});

test('a mobile that belongs to an archived client is refused', function () {
    $archived = makeClient(['mobile' => '09121234567']);
    $archived->delete();

    $this->actingAs(adminUser())->post(route('admin.clients.store'), clientPayload())
        ->assertSessionHasErrors(['mobile' => __('clients.duplicate_mobile_archived')]);
});

test('editing a client keeps its own mobile without a duplicate error', function () {
    $client = makeClient(['mobile' => '09121234567']);

    $this->actingAs(adminUser())->put(route('admin.clients.update', $client), clientPayload([
        'first_name' => 'نرگس',
        'status' => 'active',
    ]))->assertRedirect(route('admin.clients.show', $client));

    expect($client->refresh()->first_name)->toBe('نرگس');
});

test('an update writes an audit row listing old and new values of the changed fields', function () {
    $client = makeClient(['first_name' => 'مریم']);
    $secretary = User::factory()->role(StaffRole::Secretary)->create();

    $this->actingAs($secretary)->put(route('admin.clients.update', $client), clientPayload([
        'first_name' => 'نرگس',
        'status' => 'active',
    ]))->assertRedirect();

    $log = AuditLog::query()->where('action', 'update')->where('client_id', $client->getKey())->firstOrFail();
    expect($log->actor_id)->toBe($secretary->getKey());
    expect($log->changes['new']['first_name'])->toBe('نرگس');
    expect($log->changes['old']['first_name'])->toBe('مریم');
});

test('a plain edit keeps the original photo consent time', function () {
    $given = now()->subMonth()->startOfSecond();
    $client = makeClient(['consent_photos_at' => $given]);

    // The edit form pre-ticks the box, so a plain save submits it ticked.
    $this->actingAs(adminUser())->put(route('admin.clients.update', $client), clientPayload([
        'consent_photos' => '1',
        'status' => 'active',
    ]));

    expect($client->refresh()->consent_photos_at->equalTo($given))->toBeTrue();
});

test('unticking photo consent on edit clears it', function () {
    $client = makeClient(['consent_photos_at' => now()->subMonth()]);

    $this->actingAs(adminUser())->put(route('admin.clients.update', $client), clientPayload([
        'consent_photos' => null,
        'status' => 'active',
    ]));

    expect($client->refresh()->consent_photos_at)->toBeNull();
});

test('an admin soft-deletes a client and the row remains with its audit row', function () {
    $client = makeClient();

    $this->actingAs(adminUser())->delete(route('admin.clients.destroy', $client))
        ->assertRedirect(route('admin.clients.index'));

    expect(Client::query()->whereKey($client->getKey())->exists())->toBeFalse();
    expect(Client::withTrashed()->whereKey($client->getKey())->exists())->toBeTrue();
    expect(AuditLog::query()->where('action', 'delete')->where('client_id', $client->getKey())->exists())->toBeTrue();
});

test('a client can never be hard-deleted', function () {
    $client = makeClient();

    expect(fn () => $client->forceDelete())->toThrow(LogicException::class);
    expect(Client::withTrashed()->whereKey($client->getKey())->exists())->toBeTrue();
});

test('a medical record can never be hard-deleted', function () {
    $client = makeClient();

    expect(fn () => $client->medical->delete())->toThrow(LogicException::class);
    expect($client->medical()->exists())->toBeTrue();
});

test('the gates for clients follow config/permissions.php', function () {
    $secretary = User::factory()->role(StaffRole::Secretary)->create();

    expect(Gate::forUser($secretary)->allows('client.delete'))->toBeFalse();
    expect(Gate::forUser($secretary)->allows('medical.view'))->toBeFalse();
    expect(Gate::forUser($secretary)->allows('client.update'))->toBeTrue();
});

test('after a failed save, an unticked photo consent stays unticked', function () {
    $admin = adminUser();

    $this->actingAs($admin)->from(route('admin.clients.create'))
        ->post(route('admin.clients.store'), clientPayload(['first_name' => '', 'consent_photos' => null]))
        ->assertSessionHasErrors('first_name');

    $this->actingAs($admin)->get(route('admin.clients.create'))
        ->assertDontSee('name="consent_photos" value="1" checked', false);
});
