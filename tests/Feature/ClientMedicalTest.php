<?php

declare(strict_types=1);

use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Models\ClientMedical;
use App\Models\User;

const SECRET_MEDICAL_TEXT = 'حساسیت شدید به بادام زمینی';

test('opening the medical record writes a view audit row for the specialist', function () {
    $client = makeClient();
    $specialist = User::factory()->role(StaffRole::Specialist)->create();

    $this->actingAs($specialist)->get(route('specialist.clients.medical.edit', $client))->assertOk();

    $log = AuditLog::query()->where('action', 'view')->where('client_id', $client->getKey())->firstOrFail();
    expect($log->actor_id)->toBe($specialist->getKey());
    expect($log->subject_type)->toBe(ClientMedical::class);
});

test('opening the medical record from the admin panel is audited too', function () {
    $client = makeClient();

    $this->actingAs(User::factory()->role(StaffRole::Admin)->create())
        ->get(route('admin.clients.medical.edit', $client))
        ->assertOk();

    expect(AuditLog::query()->where('action', 'view')->where('client_id', $client->getKey())->count())->toBe(1);
});

test('a secretary cannot read the medical text by any route', function () {
    $client = makeClient();
    $client->medical->update(['food_allergies' => SECRET_MEDICAL_TEXT]);
    $secretary = User::factory()->role(StaffRole::Secretary)->create();

    $this->actingAs($secretary)->get(route('admin.clients.medical.edit', $client))->assertForbidden();
    $this->actingAs($secretary)->put(route('admin.clients.medical.update', $client), ['food_allergies' => 'x'])->assertForbidden();
    $this->actingAs($secretary)->get(route('admin.clients.show', $client))->assertOk()->assertDontSee(SECRET_MEDICAL_TEXT);
    $this->actingAs($secretary)->get(route('admin.clients.edit', $client))->assertOk()->assertDontSee(SECRET_MEDICAL_TEXT);
    $this->actingAs($secretary)->get(route('admin.clients.index', ['q' => 'x']))->assertOk()->assertDontSee(SECRET_MEDICAL_TEXT);
});

test('a refused medical read writes no view audit row', function () {
    $client = makeClient();

    $this->actingAs(User::factory()->role(StaffRole::Secretary)->create())
        ->get(route('admin.clients.medical.edit', $client))
        ->assertForbidden();

    expect(AuditLog::query()->where('action', 'view')->exists())->toBeFalse();
});

test('the medical update audit row names the changed fields but never their values', function () {
    $client = makeClient();
    $specialist = User::factory()->role(StaffRole::Specialist)->create();

    $this->actingAs($specialist)->put(route('specialist.clients.medical.update', $client), [
        'food_allergies' => SECRET_MEDICAL_TEXT,
        'device_contraindications' => ['pacemaker'],
    ])->assertRedirect();

    expect($client->medical->refresh()->food_allergies)->toBe(SECRET_MEDICAL_TEXT);
    expect($client->medical->device_contraindications)->toBe(['pacemaker']);

    $log = AuditLog::query()->where('action', 'update')->where('client_id', $client->getKey())->firstOrFail();
    expect($log->actor_id)->toBe($specialist->getKey());
    expect($log->changes['changed_fields'])->toEqualCanonicalizing(['food_allergies', 'device_contraindications']);
    expect(json_encode($log->changes, JSON_UNESCAPED_UNICODE))->not->toContain(SECRET_MEDICAL_TEXT);
});

test('a medical save with no change writes no audit row', function () {
    $client = makeClient();

    $this->actingAs(User::factory()->role(StaffRole::Specialist)->create())
        ->put(route('specialist.clients.medical.update', $client), ['food_allergies' => ''])
        ->assertRedirect();

    expect(AuditLog::query()->where('action', 'update')->exists())->toBeFalse();
});

test('an unknown device contraindication is rejected', function () {
    $client = makeClient();

    $this->actingAs(User::factory()->role(StaffRole::Admin)->create())
        ->put(route('admin.clients.medical.update', $client), ['device_contraindications' => ['unicorn']])
        ->assertSessionHasErrors('device_contraindications.0');
});

test('the specialist file has no admin edit links', function () {
    $client = makeClient();

    $this->actingAs(User::factory()->role(StaffRole::Specialist)->create())
        ->get(route('specialist.clients.show', $client))
        ->assertOk()
        ->assertDontSee(route('admin.clients.edit', $client))
        ->assertDontSee(route('admin.clients.index'));
});

test('after a failed save, an unticked contraindication stays unticked', function () {
    $client = makeClient();
    $client->medical->update(['device_contraindications' => ['pacemaker']]);
    $specialist = User::factory()->role(StaffRole::Specialist)->create();

    // The allergy text is longer than the limit, so the save fails. The pacemaker box is unticked.
    $this->actingAs($specialist)->from(route('specialist.clients.medical.edit', $client))
        ->put(route('specialist.clients.medical.update', $client), [
            'food_allergies' => str_repeat('آ', 5001),
            'device_contraindications' => [],
        ])
        ->assertSessionHasErrors('food_allergies');

    $this->actingAs($specialist)->get(route('specialist.clients.medical.edit', $client))
        ->assertDontSee('value="pacemaker" checked', false);
});
