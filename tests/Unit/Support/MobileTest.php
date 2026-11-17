<?php

declare(strict_types=1);

use App\Support\Mobile;

test('accepts the common mobile formats and normalises them', function (string $input) {
    expect(Mobile::normalize($input))->toBe('09121234567');
})->with([
    '+989121234567',
    '+98 912 123 4567',
    '00989121234567',
    '09121234567',
    '0912 123 4567',
    '0912-123-4567',
    '9121234567',
    '+98 0912 123 4567',
    '۰۹۱۲۱۲۳۴۵۶۷',
    '٠٩١٢١٢٣٤٥٦٧',
]);

test('rejects invalid mobile numbers', function (string $input) {
    expect(Mobile::normalize($input))->toBeNull();
})->with([
    '',
    '0812123456',
    '091212345',
    '091212345678',
    '0912abc4567',
    '+1 2025550123',
    '+98 0812 123 4567',
    '1234567890',
    '09121234567 x',
]);

test('null input is rejected', function () {
    expect(Mobile::normalize(null))->toBeNull();
});
