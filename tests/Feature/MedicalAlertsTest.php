<?php

declare(strict_types=1);

use App\Enums\StaffRole;
use App\Models\Client;
use App\Models\User;
use App\Support\ClientAlertBadges;
use Illuminate\Support\Facades\DB;

const ALERT_SECRET_TEXT = 'حساسیت شدید به بادام زمینی';

/** A client with an allergy, a contraindication and a pregnancy status, so every badge type is present. */
function clientWithAlerts(): Client
{
    $client = makeClient(['pregnancy_status' => 'pregnant']);
    $client->medical->update([
        'food_allergies' => ALERT_SECRET_TEXT,
        'device_contraindications' => ['pacemaker'],
    ]);

    return $client;
}

test('a secretary sees the alert badges on the file, but not the allergy text', function () {
    $client = clientWithAlerts();

    $this->actingAs(User::factory()->role(StaffRole::Secretary)->create())
        ->get(route('admin.clients.show', $client))
        ->assertOk()
        ->assertSee(__('alerts.allergy_yes'))
        ->assertSee(__('clients.contraindications.pacemaker'))
        ->assertSee(__('clients.pregnancy_options.pregnant'))
        ->assertDontSee(ALERT_SECRET_TEXT);
});

test('a secretary sees the badges in the client list, but not the allergy text', function () {
    clientWithAlerts();

    $this->actingAs(User::factory()->role(StaffRole::Secretary)->create())
        ->get(route('admin.clients.index'))
        ->assertOk()
        ->assertSee(__('alerts.column'))
        ->assertSee(__('alerts.allergy_yes'))
        ->assertDontSee(ALERT_SECRET_TEXT);
});

test('a client with no allergy gets the neutral badge', function (?string $allergy) {
    $client = makeClient();
    $client->medical->update(['food_allergies' => $allergy]);

    $badges = ClientAlertBadges::fromClient($client);

    expect($badges->hasAllergy)->toBeFalse();

    $this->actingAs(User::factory()->role(StaffRole::Secretary)->create())
        ->get(route('admin.clients.show', $client))
        ->assertSee(__('alerts.allergy_no'));
})->with([
    'empty' => [null],
    'blank' => ['   '],
]);

test('the badges carry flags, not the free text', function () {
    $client = clientWithAlerts();

    $badges = ClientAlertBadges::fromClient($client);

    expect($badges->hasAllergy)->toBeTrue();
    expect($badges->contraindications)->toBe(['pacemaker']);
    expect($badges->pregnancy->value)->toBe('pregnant');
    expect($client->medical->getAttributes())->not->toHaveKey('food_allergies');
});

test('the client list loads the medical table in one query, not one per client', function () {
    for ($i = 0; $i < 5; $i++) {
        clientWithAlerts();
    }

    $sql = [];
    DB::listen(function ($query) use (&$sql): void {
        $sql[] = $query->sql;
    });

    $this->actingAs(User::factory()->role(StaffRole::Secretary)->create())
        ->get(route('admin.clients.index'))
        ->assertOk();

    expect(array_filter($sql, fn (string $statement): bool => str_contains($statement, 'client_medical')))->toHaveCount(1);
});

test('the medical tab shows for admin and specialist, and not for a secretary', function () {
    $client = clientWithAlerts();

    $this->actingAs(User::factory()->role(StaffRole::Admin)->create())
        ->get(route('admin.clients.show', $client))
        ->assertSee(route('admin.clients.medical.edit', $client));

    $this->actingAs(User::factory()->role(StaffRole::Secretary)->create())
        ->get(route('admin.clients.show', $client))
        ->assertDontSee(route('admin.clients.medical.edit', $client))
        ->assertDontSee(__('clients.medical_link'));

    $this->actingAs(User::factory()->role(StaffRole::Specialist)->create())
        ->get(route('specialist.clients.show', $client))
        ->assertSee(route('specialist.clients.medical.edit', $client));
});

test('without medical.view_alerts, the badges and their column are hidden', function () {
    $client = clientWithAlerts();

    config()->set('permissions.roles.secretary', array_values(array_diff(
        config('permissions.roles.secretary'),
        ['medical.view_alerts'],
    )));

    $secretary = User::factory()->role(StaffRole::Secretary)->create();

    $this->actingAs($secretary)->get(route('admin.clients.show', $client))
        ->assertOk()
        ->assertDontSee(__('alerts.label'))
        ->assertDontSee(__('alerts.allergy_yes'));

    $this->actingAs($secretary)->get(route('admin.clients.index'))
        ->assertOk()
        ->assertDontSee(__('alerts.column'));
});
