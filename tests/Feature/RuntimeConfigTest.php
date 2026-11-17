<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;

test('lazy loading is prevented outside production', function () {
    expect(Model::preventsLazyLoading())->toBeTrue();
});

test('the jdatetime Blade directive renders Persian date and time', function () {
    $output = Blade::render('@jdatetime($moment)', ['moment' => '2025-03-20 14:05:00']);

    expect($output)->toBe('۱۴۰۳/۱۲/۳۰ ۱۴:۰۵');
});

test('the fa and money Blade directives render Persian output', function () {
    expect(Blade::render('@fa(1403)'))->toBe('۱۴۰۳')
        ->and(Blade::render('@money(1234567)'))->toContain('۱٬۲۳۴٬۵۶۷');
});
