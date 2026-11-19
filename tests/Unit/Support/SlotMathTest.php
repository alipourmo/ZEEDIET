<?php

declare(strict_types=1);

use App\Services\Scheduling\SlotMath;

test('times convert to minutes after midnight, with or without seconds', function () {
    expect(SlotMath::toMinutes('09:30'))->toBe(570);
    expect(SlotMath::toMinutes('09:30:00'))->toBe(570);
    expect(SlotMath::toMinutes('00:00'))->toBe(0);
    expect(SlotMath::toMinutes('20:00:00'))->toBe(1200);
});

test('an appointment that ends exactly when the next one starts does not overlap it', function () {
    // 09:00-10:00 and 10:00-10:30 touch at 10:00.
    expect(SlotMath::overlaps(540, 600, 600, 630))->toBeFalse();
    expect(SlotMath::overlaps(600, 630, 540, 600))->toBeFalse();
});

test('any shared minute is an overlap', function () {
    expect(SlotMath::overlaps(540, 600, 599, 630))->toBeTrue();   // one minute shared
    expect(SlotMath::overlaps(540, 600, 550, 560))->toBeTrue();   // contained
    expect(SlotMath::overlaps(540, 600, 500, 700))->toBeTrue();   // contains
    expect(SlotMath::overlaps(540, 600, 540, 600))->toBeTrue();   // identical
});

test('an interval must lie completely inside a window, boundaries included', function () {
    expect(SlotMath::isInside(540, 600, 540, 1200))->toBeTrue();   // starts at opening
    expect(SlotMath::isInside(1140, 1200, 540, 1200))->toBeTrue(); // ends at closing
    expect(SlotMath::isInside(530, 600, 540, 1200))->toBeFalse();  // starts before opening
    expect(SlotMath::isInside(1150, 1210, 540, 1200))->toBeFalse(); // ends after closing
});
