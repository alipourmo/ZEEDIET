<?php

declare(strict_types=1);

// Security headers and robots (spec sections 10.11 and 10.12).

test('every web page carries the security headers', function () {
    $response = $this->get(route('login'))->assertOk()
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'same-origin')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    expect($response->headers->get('Content-Security-Policy'))
        ->toContain("default-src 'self'")
        ->toContain("script-src 'self'")
        ->toContain("frame-ancestors 'none'")
        ->not->toContain('unsafe-inline');
});

test('redirects carry the headers too', function () {
    $this->get(route('admin.home'))
        ->assertRedirect()
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

test('the portal pages are not indexed', function () {
    $this->get(route('portal.login'))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('noindex, nofollow', false);
});

test('robots.txt disallows every crawler', function () {
    $robots = (string) file_get_contents(public_path('robots.txt'));

    expect($robots)->toContain('User-agent: *')->toContain('Disallow: /');
});
