<?php

declare(strict_types=1);

use App\Enums\StaffRole;
use App\Models\User;

/**
 * Every role against every client route (spec section 12: route matrix, 200/403).
 * Write routes return 302 when the role is allowed: a redirect after a save, or back to the form when the
 * empty body fails validation. Either way the Gate has let the request through.
 *
 * Columns: admin, secretary, operator, specialist.
 *
 * @return array<string, array{0: string, 1: string, 2: list<int>}>
 */
function clientRouteMatrix(): array
{
    return [
        'admin index' => ['GET', '/admin/clients', [200, 200, 403, 403]],
        'admin create form' => ['GET', '/admin/clients/create', [200, 200, 403, 403]],
        'admin store' => ['POST', '/admin/clients', [302, 302, 403, 403]],
        'admin show' => ['GET', '/admin/clients/{client}', [200, 200, 403, 403]],
        'admin edit form' => ['GET', '/admin/clients/{client}/edit', [200, 200, 403, 403]],
        'admin update' => ['PUT', '/admin/clients/{client}', [302, 302, 403, 403]],
        'admin delete' => ['DELETE', '/admin/clients/{client}', [302, 403, 403, 403]],
        'admin medical view' => ['GET', '/admin/clients/{client}/medical', [200, 403, 403, 403]],
        'admin medical update' => ['PUT', '/admin/clients/{client}/medical', [302, 403, 403, 403]],
        'specialist file' => ['GET', '/specialist/clients/{client}', [403, 403, 403, 200]],
        'specialist medical view' => ['GET', '/specialist/clients/{client}/medical', [403, 403, 403, 200]],
        'specialist medical update' => ['PUT', '/specialist/clients/{client}/medical', [403, 403, 403, 302]],
    ];
}

$roles = ['admin', 'secretary', 'operator', 'specialist'];

test('each role gets the expected status on each client route', function (string $method, string $uri, array $codes) use ($roles) {
    $client = makeClient();

    foreach ($roles as $index => $role) {
        $user = User::factory()->role(StaffRole::from($role))->create();

        $response = $this->actingAs($user)->call($method, str_replace('{client}', (string) $client->getKey(), $uri));

        expect($response->status())->toBe($codes[$index], "{$role} {$method} {$uri}");
    }
})->with(clientRouteMatrix());

test('a guest is redirected to login on every client route', function () {
    $client = makeClient();

    foreach (['/admin/clients', "/admin/clients/{$client->getKey()}", "/specialist/clients/{$client->getKey()}"] as $uri) {
        $this->get($uri)->assertRedirect(route('login'));
    }
});
