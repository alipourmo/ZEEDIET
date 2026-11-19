<?php

declare(strict_types=1);

use App\Support\Weekday;
use Carbon\CarbonImmutable;

test('weekday 0 is Saturday and 6 is Friday, as in spec section 6.2', function () {
    expect(Weekday::fromDate(CarbonImmutable::parse('2026-11-21')))->toBe(0); // Saturday
    expect(Weekday::fromDate(CarbonImmutable::parse('2026-11-22')))->toBe(1); // Sunday
    expect(Weekday::fromDate(CarbonImmutable::parse('2026-11-25')))->toBe(4); // Wednesday
    expect(Weekday::fromDate(CarbonImmutable::parse('2026-11-26')))->toBe(5); // Thursday
    expect(Weekday::fromDate(CarbonImmutable::parse('2026-11-27')))->toBe(6); // Friday
});

test('the weekday numbering covers all seven days once', function () {
    $seen = [];
    for ($day = 0; $day < 7; $day++) {
        $seen[] = Weekday::fromDate(CarbonImmutable::parse('2026-11-21')->addDays($day));
    }

    expect($seen)->toBe([0, 1, 2, 3, 4, 5, 6]);
});
