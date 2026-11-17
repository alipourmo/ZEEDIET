<?php

declare(strict_types=1);

use App\Support\Display;

test('fa converts ASCII digits to Persian', function () {
    expect(Display::fa(1403))->toBe('۱۴۰۳');
});

test('moneyNumber uses Persian digits and the Persian thousands separator', function () {
    expect(Display::moneyNumber(1234567))->toBe('۱٬۲۳۴٬۵۶۷');
    expect(Display::moneyNumber(0))->toBe('۰');
});

test('jdate formats a Gregorian date as Persian Jalali', function () {
    expect(Display::jdate('2025-03-20'))->toBe('۱۴۰۳/۱۲/۳۰');
    expect(Display::jdate('2025-03-20 23:30:00'))->toBe('۱۴۰۳/۱۲/۳۰');
});

test('jdate of a DateTime is taken in Asia/Tehran time', function () {
    // 2025-03-20 22:00 UTC is 2025-03-21 01:30 in Tehran, so the Jalali date is 1404/01/01.
    $moment = new DateTimeImmutable('2025-03-20 22:00:00', new DateTimeZone('UTC'));

    expect(Display::jdate($moment))->toBe('۱۴۰۴/۰۱/۰۱');
});

test('jdate of null or empty is empty', function () {
    expect(Display::jdate(null))->toBe('');
    expect(Display::jdate(''))->toBe('');
});
