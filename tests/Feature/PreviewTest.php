<?php

declare(strict_types=1);

test('home page renders in Persian right-to-left', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('lang="fa"', false)
        ->assertSee('dir="rtl"', false)
        ->assertSee('noindex', false);
});

test('jalali tool converts a Persian-digit date', function () {
    $this->get('/tools/jalali?date='.urlencode('۱۴۰۳/۱۲/۳۰'))
        ->assertOk()
        ->assertSee('۱۴۰۳/۱۲/۳۰')
        ->assertSee('۲۰۲۵-۰۳-۲۰');
});

test('jalali tool reports an invalid date without a server error', function () {
    $this->get('/tools/jalali?date='.urlencode('1404/12/30'))
        ->assertOk()
        ->assertSee('aria-invalid', false);
});

test('mobile tool normalises a number with a country code prefix', function () {
    $this->get('/tools/mobile?mobile='.urlencode('+98 912 123 4567'))
        ->assertOk()
        ->assertSee('۰۹۱۲۱۲۳۴۵۶۷');
});

test('mobile tool flags an invalid number', function () {
    $this->get('/tools/mobile?mobile='.urlencode('0812123456'))
        ->assertOk()
        ->assertSee('aria-invalid', false);
});

test('styleguide is not served outside the local environment', function () {
    $this->get('/_styleguide')->assertNotFound();
});

test('health endpoint returns ok', function () {
    $this->getJson('/healthz')
        ->assertOk()
        ->assertExactJson(['status' => 'ok']);
});

test('robots.txt is served as a disallow-all file', function () {
    $this->get('/robots.txt')
        ->assertOk()
        ->assertSee('Disallow: /', false);
});
