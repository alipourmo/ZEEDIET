<?php

/*
 * Dependency-free check for app/Support (Digits, Jalali, Mobile).
 * Runs with any PHP 8.3+ CLI, no Composer needed:  php scripts/verify-support.php
 * The same cases are covered by Pest tests in tests/Unit/Support for CI.
 */

declare(strict_types=1);

require __DIR__.'/../app/Support/Digits.php';
require __DIR__.'/../app/Support/Jalali.php';
require __DIR__.'/../app/Support/Mobile.php';

use App\Support\Digits;
use App\Support\Jalali;
use App\Support\Mobile;

$passes = 0;
$failures = [];
$check = function (bool $condition, string $label) use (&$passes, &$failures): void {
    if ($condition) {
        $passes++;

        return;
    }
    $failures[] = $label;
};

// Digits
$check(Digits::toAscii('۱۲۳') === '123', 'persian digits to ascii');
$check(Digits::toAscii('١٢٣') === '123', 'arabic digits to ascii');
$check(Digits::toAscii("\u{064A}\u{0643}") === "\u{06CC}\u{06A9}", 'arabic yeh/kaf to persian');
$check(Digits::toPersian('1403/12/30') === '۱۴۰۳/۱۲/۳۰', 'ascii to persian digits');

// Jalali anchors
$check(Jalali::fromGregorian(2025, 3, 20) === [1403, 12, 30], 'G 2025-03-20 = J 1403/12/30');
$check(Jalali::fromGregorian(2025, 3, 21) === [1404, 1, 1], 'G 2025-03-21 = J 1404/01/01');
$check(Jalali::fromGregorian(2021, 3, 21) === [1400, 1, 1], 'G 2021-03-21 = J 1400/01/01');
$check(Jalali::fromGregorian(2021, 3, 20) === [1399, 12, 30], 'G 2021-03-20 = J 1399/12/30');
$check(Jalali::toGregorian(1403, 12, 30) === [2025, 3, 20], 'J 1403/12/30 to G');
$check(Jalali::isJalaliLeap(1403) && Jalali::isJalaliLeap(1399) && ! Jalali::isJalaliLeap(1404), 'leap years');
$check(! Jalali::isValidJalali(1404, 12, 30) && Jalali::isValidJalali(1403, 12, 30), 'leap-edge validity');

// Round trip over every day 1900-2100
$end = gmmktime(0, 0, 0, 12, 31, 2100);
$days = 0;
$roundTripFailures = 0;
for ($t = gmmktime(0, 0, 0, 1, 1, 1900); $t <= $end; $t += 86400) {
    $y = (int) gmdate('Y', $t);
    $m = (int) gmdate('n', $t);
    $d = (int) gmdate('j', $t);
    [$jy, $jm, $jd] = Jalali::fromGregorian($y, $m, $d);
    if (Jalali::toGregorian($jy, $jm, $jd) !== [$y, $m, $d]) {
        $roundTripFailures++;
    }
    $days++;
}
$check($roundTripFailures === 0, "round trip over {$days} days");

// Parsing user input
$check(Jalali::parseToGregorian('۱۴۰۳/۱۲/۳۰') === '2025-03-20', 'parse persian input');
$check(Jalali::parseToGregorian('1403-12-30') === '2025-03-20', 'parse dash input');
$check(Jalali::parseToGregorian('1404/12/30') === null, 'reject non-leap 12/30');
$check(Jalali::parseToGregorian('1403/13/01') === null, 'reject month 13');

// Mobile
foreach (['+989121234567', '+98 912 123 4567', '00989121234567', '09121234567', '0912 123 4567', '9121234567', '+98 0912 123 4567', '۰۹۱۲۱۲۳۴۵۶۷', '٠٩١٢١٢٣٤٥٦٧'] as $in) {
    $check(Mobile::normalize($in) === '09121234567', "mobile accept {$in}");
}
foreach (['', '0812123456', '091212345', '091212345678', '0912abc4567', '+1 2025550123', '+98 0812 123 4567'] as $in) {
    $check(Mobile::normalize($in) === null, "mobile reject '{$in}'");
}

echo "passes: {$passes}\n";
if ($failures !== []) {
    echo 'FAILURES: '.implode('; ', $failures)."\n";
    exit(1);
}
echo "all support checks passed\n";
