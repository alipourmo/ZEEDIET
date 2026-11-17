<?php

use App\Models\Client;
use App\Models\ClientMedical;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');

// Feature tests run every migration on a fresh in-memory database, so a broken migration fails the suite.
uses(RefreshDatabase::class)->in('Feature');

/**
 * A client with its medical record, as ClientService::create() leaves it.
 *
 * @param  array<string, mixed>  $attributes
 */
function makeClient(array $attributes = []): Client
{
    $client = Client::factory()->create($attributes);

    ClientMedical::create([
        'client_id' => $client->getKey(),
        'device_contraindications' => [],
    ]);

    return $client;
}

/**
 * A valid create-client form body. Persian digits are used on purpose, as a receptionist would type them.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function clientPayload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'مریم',
        'last_name' => 'احمدی',
        'mobile' => '۰۹۱۲۱۲۳۴۵۶۷',
        'birth_date' => '۱۳۷۰/۰۵/۱۲',
        'gender' => 'female',
        'height_cm' => '165',
        'pregnancy_status' => 'none',
        'referral_source' => '',
        'internal_note' => '',
        'consent_data' => '1',
        'consent_photos' => '1',
    ], $overrides);
}
