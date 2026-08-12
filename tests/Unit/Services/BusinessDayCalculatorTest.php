<?php

use App\Services\BusinessDayCalculator;
use Carbon\Carbon;

beforeEach(function () {
    $this->calculator = new BusinessDayCalculator;
});

it('given a span entirely within one weekday when business days are counted then it returns the exact fraction', function () {
    $from = Carbon::parse('2026-08-10 09:00:00'); // a Monday
    $to = Carbon::parse('2026-08-10 17:00:00');

    expect($this->calculator->businessDaysBetween($from, $to))->toBe(8 / 24);
});

it('given a span across consecutive weekdays when business days are counted then every day counts fully', function () {
    $from = Carbon::parse('2026-08-10 00:00:00'); // Monday
    $to = Carbon::parse('2026-08-13 00:00:00');   // Thursday

    expect($this->calculator->businessDaysBetween($from, $to))->toBe(3.0);
});

it('given a span crossing one weekend when business days are counted then the weekend is excluded', function () {
    $from = Carbon::parse('2026-08-14 00:00:00'); // Friday
    $to = Carbon::parse('2026-08-17 00:00:00');   // Monday

    expect($this->calculator->businessDaysBetween($from, $to))->toBe(1.0);
});

it('given a span crossing two weekends when business days are counted then both are excluded', function () {
    $from = Carbon::parse('2026-08-10 00:00:00'); // Monday
    $to = Carbon::parse('2026-08-24 00:00:00');   // Monday, two weeks later

    // Mon-Fri (5) + Mon-Fri (5) + the closing Monday itself not included (exclusive end) = 10.
    expect($this->calculator->businessDaysBetween($from, $to))->toBe(10.0);
});

it('given a same-day span when business days are counted then it returns a fractional business day', function () {
    $from = Carbon::parse('2026-08-11 10:00:00'); // Tuesday
    $to = Carbon::parse('2026-08-11 13:30:00');

    expect($this->calculator->businessDaysBetween($from, $to))->toBe(3.5 / 24);
});

it('given a span that starts and ends on a weekend when business days are counted then it returns zero', function () {
    $from = Carbon::parse('2026-08-15 08:00:00'); // Saturday
    $to = Carbon::parse('2026-08-16 20:00:00');   // Sunday

    expect($this->calculator->businessDaysBetween($from, $to))->toBe(0.0);
});

it('given a to timestamp not after the from timestamp when business days are counted then it returns zero', function () {
    $now = Carbon::parse('2026-08-11 10:00:00');

    expect($this->calculator->businessDaysBetween($now, $now))->toBe(0.0)
        ->and($this->calculator->businessDaysBetween($now, $now->copy()->subHour()))->toBe(0.0);
});
