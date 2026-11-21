<?php

declare(strict_types=1);

use App\Support\Decimal;

test('the difference is a signed change with two decimals, and zero has no sign', function () {
    expect(Decimal::difference('70.00', '72.50'))->toBe('+2.50')
        ->and(Decimal::difference('72.50', '70.00'))->toBe('-2.50')
        ->and(Decimal::difference('70.00', '70.00'))->toBe('0.00')
        ->and(Decimal::difference('0.10', '0.30'))->toBe('+0.20');
});

test('a missing value gives no difference', function () {
    expect(Decimal::difference(null, '70.00'))->toBeNull()
        ->and(Decimal::difference('70.00', null))->toBeNull();
});
