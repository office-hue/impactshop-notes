<?php

declare(strict_types=1);

require_once __DIR__ . '/../wp-content/mu-plugins/sharity-points-money.php';

function money_assert_same($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

function money_assert_error(string $expected, array $actual, string $message): void
{
    money_assert_same(false, $actual['success'] ?? null, $message . ' success');
    money_assert_same($expected, $actual['error'] ?? null, $message . ' error');
}

$parsed = sharity_points_money_parse('123.4500');
money_assert_same(true, $parsed['success'], 'strict decimal parses');
money_assert_same('1234500', $parsed['digits'], 'strict decimal digits');
money_assert_same(4, $parsed['scale'], 'strict decimal scale');

foreach (['', ' 1', '1 ', '+1', '-1', '01', '.5', '1.', '1e3', '1,5', 'NaN', 'INF'] as $invalid) {
    money_assert_error('invalid_amount', sharity_points_money_parse($invalid), 'reject ' . var_export($invalid, true));
}
money_assert_error('invalid_type', sharity_points_money_parse(1.25), 'reject float authority');
money_assert_error('invalid_type', sharity_points_money_parse(true), 'reject boolean authority');
money_assert_error('precision_exceeded', sharity_points_money_parse('1.1234567'), 'reject excessive precision');

money_assert_same(['success' => true, 'points' => 123], sharity_points_money_donation_huf_to_points('123.49'), 'round below half down');
money_assert_same(['success' => true, 'points' => 124], sharity_points_money_donation_huf_to_points('123.50'), 'round half up');
money_assert_same(['success' => true, 'points' => 1], sharity_points_money_donation_huf_to_points('0.50'), 'sub-forint half rounds to one');
money_assert_error('invalid_amount', sharity_points_money_donation_huf_to_points('0.49'), 'zero-point donation is ineligible');
money_assert_error('invalid_amount', sharity_points_money_donation_huf_to_points('0'), 'zero donation is ineligible');

money_assert_same(['success' => true, 'points' => 4942], sharity_points_money_convert_to_huf_points('12.34', '400.5'), 'fixed-scale conversion rounds once');
money_assert_same(['success' => true, 'points' => 4], sharity_points_money_convert_to_huf_points('0.01', '350.00'), 'conversion uses half-up rounding');
money_assert_error('invalid_type', sharity_points_money_convert_to_huf_points(12.34, '400'), 'conversion rejects float amount');
money_assert_error('invalid_type', sharity_points_money_convert_to_huf_points('12.34', 400.0), 'conversion rejects float rate');

money_assert_same(['success' => true, 'points' => 124], sharity_points_money_minor_units_to_huf_points('12350', 2), 'minor units round half up');
money_assert_error('invalid_scale', sharity_points_money_minor_units_to_huf_points('1', 7), 'minor scale is bounded');

money_assert_same(PHP_INT_MAX, sharity_points_money_checked_add(PHP_INT_MAX - 1, 1), 'checked add accepts boundary');
money_assert_same(null, sharity_points_money_checked_add(PHP_INT_MAX, 1), 'checked add rejects overflow');
money_assert_same(PHP_INT_MIN, sharity_points_money_checked_add(PHP_INT_MIN + 1, -1), 'checked add accepts negative boundary');
money_assert_same(null, sharity_points_money_checked_add(PHP_INT_MIN, -1), 'checked add rejects negative overflow');
money_assert_same(42, sharity_points_money_checked_multiply(6, 7), 'checked multiply computes product');
money_assert_same(null, sharity_points_money_checked_multiply(PHP_INT_MAX, 2), 'checked multiply rejects overflow');
money_assert_same(null, sharity_points_money_checked_multiply(PHP_INT_MIN, -1), 'checked multiply rejects minimum negation');

money_assert_same(
    ['success' => true, 'points' => PHP_INT_MAX],
    sharity_points_money_donation_huf_to_points((string) PHP_INT_MAX),
    'point range accepts PHP integer maximum'
);
money_assert_error(
    'overflow',
    sharity_points_money_donation_huf_to_points(sharity_points_money_increment_digits((string) PHP_INT_MAX)),
    'point range rejects overflow'
);
money_assert_error('overflow', sharity_points_money_donation_huf_to_points('101', 100), 'provider ceiling rejects excess');

echo "sharity points money: PASS\n";
