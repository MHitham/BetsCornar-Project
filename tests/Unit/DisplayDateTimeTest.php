<?php

use Carbon\Carbon;
use Carbon\CarbonImmutable;

test('date time is displayed in Cairo with Arabic twelve hour periods', function (string $utc, string $expected) {
    expect(displayDateTime(CarbonImmutable::parse($utc, 'UTC')))->toBe($expected);
})->with([
    ['2026-11-20 17:15:00', '2026-11-20 7:15 م'],
    ['2026-11-20 10:00:00', '2026-11-20 12:00 م'],
    ['2026-11-20 22:05:00', '2026-11-21 12:05 ص'],
    ['2026-07-20 17:15:00', '2026-07-20 8:15 م'],
]);

test('display formatting preserves the original date and handles missing dates', function () {
    $date = Carbon::parse('2026-11-20 17:15:00', 'UTC');
    expect(displayDateTime($date, 'm/d'))->toBe('11/20 7:15 م');
    expect($date->format('Y-m-d H:i e'))->toBe('2026-11-20 17:15 UTC');
    expect(displayDateTime(null))->toBe('');
});
