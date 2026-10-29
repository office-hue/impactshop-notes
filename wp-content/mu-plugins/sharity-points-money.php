<?php

declare(strict_types=1);

/**
 * Strict, side-effect-free decimal helpers for Impact Shop point settlement.
 *
 * Monetary authority must be supplied as a decimal string or as an integer.
 * Floats are deliberately rejected because their original decimal value cannot
 * be reconstructed reliably.
 */

function sharity_points_money_parse($value, int $max_scale = 6): array
{
    if ($max_scale < 0 || $max_scale > 18) {
        return ['success' => false, 'error' => 'invalid_scale'];
    }

    if (is_int($value)) {
        if ($value < 0) {
            return ['success' => false, 'error' => 'invalid_amount'];
        }
        $value = (string) $value;
    } elseif (!is_string($value)) {
        return ['success' => false, 'error' => 'invalid_type'];
    }

    if ($value === '' || strlen($value) > 64) {
        return ['success' => false, 'error' => 'invalid_amount'];
    }

    if (!preg_match('/^(0|[1-9][0-9]*)(?:\.([0-9]+))?$/D', $value, $matches)) {
        return ['success' => false, 'error' => 'invalid_amount'];
    }

    $fraction = $matches[2] ?? '';
    if (strlen($fraction) > $max_scale) {
        return ['success' => false, 'error' => 'precision_exceeded'];
    }

    $integer = $matches[1];
    $digits = sharity_points_money_normalize_digits($integer . $fraction);

    return [
        'success' => true,
        'digits' => $digits,
        'scale' => strlen($fraction),
        'canonical' => $fraction === '' ? $integer : $integer . '.' . $fraction,
        'zero' => $digits === '0',
    ];
}

function sharity_points_money_donation_huf_to_points($donation_amount_huf, int $max_points = PHP_INT_MAX): array
{
    if ($max_points <= 0) {
        return ['success' => false, 'error' => 'invalid_limit'];
    }

    $parsed = sharity_points_money_parse($donation_amount_huf);
    if (!$parsed['success']) {
        return $parsed;
    }
    if ($parsed['zero']) {
        return ['success' => false, 'error' => 'invalid_amount'];
    }

    $rounded = sharity_points_money_round_half_up($parsed['digits'], $parsed['scale']);
    return sharity_points_money_points_result($rounded, $max_points);
}

function sharity_points_money_convert_to_huf_points($amount, $huf_per_unit, int $max_points = PHP_INT_MAX): array
{
    if ($max_points <= 0) {
        return ['success' => false, 'error' => 'invalid_limit'];
    }

    $parsed_amount = sharity_points_money_parse($amount);
    $parsed_rate = sharity_points_money_parse($huf_per_unit);
    if (!$parsed_amount['success']) {
        return $parsed_amount;
    }
    if (!$parsed_rate['success']) {
        return $parsed_rate;
    }
    if ($parsed_amount['zero'] || $parsed_rate['zero']) {
        return ['success' => false, 'error' => 'invalid_amount'];
    }

    $product = sharity_points_money_multiply_digits($parsed_amount['digits'], $parsed_rate['digits']);
    if ($product === null) {
        return ['success' => false, 'error' => 'overflow'];
    }

    $scale = $parsed_amount['scale'] + $parsed_rate['scale'];
    $rounded = sharity_points_money_round_half_up($product, $scale);
    return sharity_points_money_points_result($rounded, $max_points);
}

function sharity_points_money_minor_units_to_huf_points($minor_units, int $minor_scale, int $max_points = PHP_INT_MAX): array
{
    if ($minor_scale < 0 || $minor_scale > 6) {
        return ['success' => false, 'error' => 'invalid_scale'];
    }
    if (!is_int($minor_units) && !is_string($minor_units)) {
        return ['success' => false, 'error' => 'invalid_type'];
    }

    $digits = (string) $minor_units;
    if ($digits === '' || strlen($digits) > 64 || !preg_match('/^(0|[1-9][0-9]*)$/D', $digits)) {
        return ['success' => false, 'error' => 'invalid_amount'];
    }
    $digits = sharity_points_money_normalize_digits($digits);
    if ($digits === '0') {
        return ['success' => false, 'error' => 'invalid_amount'];
    }

    $rounded = sharity_points_money_round_half_up($digits, $minor_scale);
    return sharity_points_money_points_result($rounded, $max_points);
}

function sharity_points_money_checked_add(int $left, int $right): ?int
{
    if (($right > 0 && $left > PHP_INT_MAX - $right)
        || ($right < 0 && $left < PHP_INT_MIN - $right)) {
        return null;
    }

    return $left + $right;
}

function sharity_points_money_checked_multiply(int $left, int $right): ?int
{
    if ($left === 0 || $right === 0) {
        return 0;
    }
    if (($left === -1 && $right === PHP_INT_MIN) || ($right === -1 && $left === PHP_INT_MIN)) {
        return null;
    }

    $product = $left * $right;
    if (!is_int($product) || intdiv($product, $right) !== $left) {
        return null;
    }

    return $product;
}

function sharity_points_money_points_result(string $digits, int $max_points): array
{
    $digits = sharity_points_money_normalize_digits($digits);
    if ($digits === '0') {
        return ['success' => false, 'error' => 'invalid_amount'];
    }

    $max_digits = (string) min($max_points, PHP_INT_MAX);
    if (sharity_points_money_compare_digits($digits, $max_digits) > 0) {
        return ['success' => false, 'error' => 'overflow'];
    }

    return ['success' => true, 'points' => (int) $digits];
}

function sharity_points_money_round_half_up(string $digits, int $scale): string
{
    $digits = sharity_points_money_normalize_digits($digits);
    if ($scale <= 0) {
        return $digits;
    }

    if (strlen($digits) <= $scale) {
        $digits = str_pad($digits, $scale + 1, '0', STR_PAD_LEFT);
    }

    $cut = strlen($digits) - $scale;
    $integer = sharity_points_money_normalize_digits(substr($digits, 0, $cut));
    $rounding_digit = (int) $digits[$cut];

    return $rounding_digit >= 5
        ? sharity_points_money_increment_digits($integer)
        : $integer;
}

function sharity_points_money_multiply_digits(string $left, string $right): ?string
{
    $left = sharity_points_money_normalize_digits($left);
    $right = sharity_points_money_normalize_digits($right);
    if ($left === '0' || $right === '0') {
        return '0';
    }

    if (strlen($left) + strlen($right) > 128) {
        return null;
    }

    $result = array_fill(0, strlen($left) + strlen($right), 0);
    for ($i = strlen($left) - 1; $i >= 0; --$i) {
        for ($j = strlen($right) - 1; $j >= 0; --$j) {
            $position = $i + $j + 1;
            $value = $result[$position] + ((int) $left[$i] * (int) $right[$j]);
            $result[$position] = $value % 10;
            $result[$position - 1] += intdiv($value, 10);
        }
    }

    return sharity_points_money_normalize_digits(implode('', $result));
}

function sharity_points_money_increment_digits(string $digits): string
{
    $chars = str_split(sharity_points_money_normalize_digits($digits));
    for ($i = count($chars) - 1; $i >= 0; --$i) {
        if ($chars[$i] !== '9') {
            $chars[$i] = (string) ((int) $chars[$i] + 1);
            return implode('', $chars);
        }
        $chars[$i] = '0';
    }

    return '1' . implode('', $chars);
}

function sharity_points_money_compare_digits(string $left, string $right): int
{
    $left = sharity_points_money_normalize_digits($left);
    $right = sharity_points_money_normalize_digits($right);
    if (strlen($left) !== strlen($right)) {
        return strlen($left) <=> strlen($right);
    }

    return strcmp($left, $right) <=> 0;
}

function sharity_points_money_normalize_digits(string $digits): string
{
    $normalized = ltrim($digits, '0');
    return $normalized === '' ? '0' : $normalized;
}
