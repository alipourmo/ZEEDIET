<?php

declare(strict_types=1);

use App\Support\Jalali;

test('nowruz anchors convert correctly', function () {
    expect(Jalali::fromGregorian(2025, 3, 20))->toBe([1403, 12, 30]);
    expect(Jalali::fromGregorian(2025, 3, 21))->toBe([1404, 1, 1]);
    expect(Jalali::fromGregorian(2021, 3, 21))->toBe([1400, 1, 1]);
    expect(Jalali::fromGregorian(2021, 3, 20))->toBe([1399, 12, 30]);
    expect(Jalali::toGregorian(1403, 12, 30))->toBe([2025, 3, 20]);
    expect(Jalali::toGregorian(1404, 1, 1))->toBe([2025, 3, 21]);
});

test('leap-year edges: 1403 and 1399 are leap, 1404 is not', function () {
    expect(Jalali::isJalaliLeap(1403))->toBeTrue();
    expect(Jalali::isJalaliLeap(1399))->toBeTrue();
    expect(Jalali::isJalaliLeap(1404))->toBeFalse();
    expect(Jalali::daysInJalaliMonth(1404, 12))->toBe(29);
    expect(Jalali::isValidJalali(1403, 12, 30))->toBeTrue();
    expect(Jalali::isValidJalali(1404, 12, 30))->toBeFalse();
});

test('month lengths: first six months 31 days, next five 30', function () {
    expect(Jalali::isValidJalali(1403, 6, 31))->toBeTrue();
    expect(Jalali::isValidJalali(1403, 7, 31))->toBeFalse();
});

test('gregorian to jalali to gregorian round-trips over 1900-2100', function () {
    $end = gmmktime(0, 0, 0, 12, 31, 2100);
    for ($t = gmmktime(0, 0, 0, 1, 1, 1900); $t <= $end; $t += 86400) {
        $y = (int) gmdate('Y', $t);
        $m = (int) gmdate('n', $t);
        $d = (int) gmdate('j', $t);
        [$jy, $jm, $jd] = Jalali::fromGregorian($y, $m, $d);

        expect(Jalali::isValidJalali($jy, $jm, $jd))->toBeTrue();
        expect(Jalali::toGregorian($jy, $jm, $jd))->toBe([$y, $m, $d]);
    }
});

test('parses persian-digit and dash-separated input', function () {
    expect(Jalali::parseToGregorian('۱۴۰۳/۱۲/۳۰'))->toBe('2025-03-20');
    expect(Jalali::parseToGregorian('1403-12-30'))->toBe('2025-03-20');
});

test('rejects invalid jalali input', function () {
    expect(Jalali::parseToGregorian('1404/12/30'))->toBeNull();
    expect(Jalali::parseToGregorian('1403/13/01'))->toBeNull();
    expect(Jalali::parseToGregorian('abc'))->toBeNull();
});

test('formats gregorian as jalali', function () {
    expect(Jalali::formatGregorianAsJalali('2025-03-20'))->toBe('1403/12/30');
    expect(Jalali::formatGregorianAsJalali('2025-03-21'))->toBe('1404/01/01');
});
