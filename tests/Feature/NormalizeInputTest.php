<?php

declare(strict_types=1);

use App\Http\Middleware\NormalizeInput;
use Illuminate\Http\Request;

function runNormalizeInput(Request $request): Request
{
    (new NormalizeInput)->handle($request, fn (Request $passed) => response('ok'));

    return $request;
}

test('query string digits are normalised to ASCII', function () {
    $request = runNormalizeInput(Request::create('/search?q='.urlencode('۰۹۱۲'), 'GET'));

    expect($request->query('q'))->toBe('0912');
});

test('form body digits and Arabic letters are normalised', function () {
    $request = runNormalizeInput(Request::create('/clients', 'POST', [
        'name' => "\u{0643}\u{0631}\u{064A}\u{0645}",
        'mobile' => '۰۹۱۲۱۲۳۴۵۶۷',
        'nested' => ['age' => '٣٥'],
    ]));

    expect($request->input('name'))->toBe("\u{06A9}\u{0631}\u{06CC}\u{0645}")
        ->and($request->input('mobile'))->toBe('09121234567')
        ->and($request->input('nested.age'))->toBe('35');
});

test('password fields are left unchanged', function () {
    $request = runNormalizeInput(Request::create('/login', 'POST', [
        'password' => '۱۲۳۴',
        'new_password_confirmation' => '۱۲۳۴',
    ]));

    expect($request->input('password'))->toBe('۱۲۳۴')
        ->and($request->input('new_password_confirmation'))->toBe('۱۲۳۴');
});

test('JSON body digits are normalised', function () {
    $request = Request::create('/api/x', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
        'mobile' => '۰۹۱۲۱۲۳۴۵۶۷',
    ], JSON_THROW_ON_ERROR));

    $request = runNormalizeInput($request);

    expect($request->input('mobile'))->toBe('09121234567');
});
