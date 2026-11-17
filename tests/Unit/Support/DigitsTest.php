<?php

declare(strict_types=1);

use App\Support\Digits;

test('persian digits convert to ascii', function () {
    expect(Digits::toAscii("۱۲۳"))->toBe('123');
});

test('arabic-indic digits convert to ascii', function () {
    expect(Digits::toAscii("١٢٣"))->toBe('123');
});

test('arabic yeh and kaf map to persian letters', function () {
    expect(Digits::toAscii("\u{064A}\u{0643}"))->toBe("\u{06CC}\u{06A9}");
});

test('ascii digits are converted to persian for display', function () {
    expect(Digits::toPersian('1403/12/30'))->toBe('۱۴۰۳/۱۲/۳۰');
});
