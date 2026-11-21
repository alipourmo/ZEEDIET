<?php

declare(strict_types=1);

use App\Enums\StaffRole;
use App\Models\User;

/**
 * Role × panel matrix (spec section 5 and 12): each role gets 200 on its own panel and 403 on the others.
 */
test('each role reaches only its own panel', function (string $role, string $panelRoute) {
    $user = User::factory()->role(StaffRole::from($role))->create();

    $panels = [
        'admin.home' => '/admin',
        'operator.home' => '/operator',
        'specialist.home' => '/specialist',
    ];

    foreach ($panels as $name => $path) {
        $expected = $name === $panelRoute ? 200 : 403;

        $this->actingAs($user)->get($path)->assertStatus($expected);
    }
})->with([
    'admin' => ['admin', 'admin.home'],
    'secretary' => ['secretary', 'admin.home'],
    'operator' => ['operator', 'operator.home'],
    'specialist' => ['specialist', 'specialist.home'],
]);
