<?php

use App\Support\Money\Money;

it('adds and subtracts in minor units', function () {
    $total = (new Money(49900))->plus(new Money(100))->minus(new Money(50));

    expect($total->minor)->toBe(49950);
});

it('refuses to mix currencies', function () {
    (new Money(100, 'INR'))->plus(new Money(100, 'USD'));
})->throws(InvalidArgumentException::class);

it('splits an amount so both parts add back exactly', function (int $amount, int $bps) {
    [$share, $rest] = (new Money($amount))->splitBps($bps);

    expect($share->plus($rest)->minor)->toBe($amount);
})->with([
    [49900, 5000],
    [33333, 3700],
    [1, 5000],
    [99999, 9000],
    [-49900, 5000],
]);

it('rounds half up to the nearest paisa', function () {
    // 101 * 50% = 50.5 → 51
    expect((new Money(101))->percentageBps(5000)->minor)->toBe(51);
});

it('mirrors rounding for refunds', function () {
    expect((new Money(-101))->percentageBps(5000)->minor)->toBe(-51);
});

it('rejects invalid basis points', function () {
    (new Money(100))->percentageBps(10001);
})->throws(InvalidArgumentException::class);

it('serialises as integer amount plus currency', function () {
    expect(json_encode(new Money(49900)))->toBe('{"amount":49900,"currency":"INR"}');
});
