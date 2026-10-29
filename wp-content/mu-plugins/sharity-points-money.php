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

/** Approved affiliate policy: commission × 50% × HUF quote/unit; round once. */
function sharity_points_money_affiliate_donation($commission, $rate, $unit = '1'): array
{
    $amount = sharity_points_money_parse($commission);
    $quote = sharity_points_money_parse($rate);
    if (!$amount['success'] || !$quote['success']) {
        return !$amount['success'] ? $amount : $quote;
    }
    if (!is_string($unit) || !preg_match('/^10{0,6}$/D', $unit)
        || $amount['zero'] || $quote['zero']) {
        return ['success' => false, 'error' => 'invalid_quote'];
    }
    $digits = sharity_points_money_multiply_digits($amount['digits'], $quote['digits']);
    $digits = $digits === null ? null : sharity_points_money_multiply_digits($digits, '5');
    if ($digits === null) { return ['success' => false, 'error' => 'overflow']; }
    return sharity_points_money_points_result(sharity_points_money_round_half_up(
        $digits, $amount['scale'] + $quote['scale'] + strlen($unit)
    ), PHP_INT_MAX);
}

/** Official MNB query table: explicit currency/unit headers, Hungarian quote dates. */
function sharity_points_mnb_parse(string $body, string $currency, string $last_day): array
{
    $previous = libxml_use_internal_errors(true);
    try {
        $dom = new DOMDocument();
        if (!$dom->loadHTML('<?xml encoding="utf-8" ?>' . $body, LIBXML_NONET)) { throw new RuntimeException(); }
        $xpath = new DOMXPath($dom);
        $tables = $xpath->query('//table[contains(concat(" ",normalize-space(@class)," ")," pricetable ")]');
        if ($tables->length !== 1) { throw new RuntimeException(); }
        $table = $tables->item(0);
        $headers = $xpath->query('./thead/tr', $table);
        if ($headers->length !== 3) { throw new RuntimeException(); }
        $code = $xpath->query('./th', $headers->item(0));
        $units = $xpath->query('./th', $headers->item(2));
        if ($code->length !== 2 || $units->length !== 2 || trim($code->item(1)->textContent) !== $currency) {
            throw new RuntimeException();
        }
        $unit = trim($units->item(1)->textContent);
        if (!preg_match('/^10{0,6}$/D', $unit)) { throw new RuntimeException(); }
        $months = ['január', 'február', 'március', 'április', 'május', 'június',
            'július', 'augusztus', 'szeptember', 'október', 'november', 'december'];
        $selected = null;
        foreach ($xpath->query('./tbody/tr', $table) as $row) {
            $cells = $xpath->query('./td', $row);
            if ($cells->length !== 2 || !preg_match('/^(\d{4})\.\s*([^ ]+)\s+(\d{1,2})\./u', trim($cells->item(0)->textContent), $parts)) {
                throw new RuntimeException();
            }
            $month = array_search($parts[2], $months, true);
            if ($month === false || !checkdate($month + 1, (int) $parts[3], (int) $parts[1])) { throw new RuntimeException(); }
            $date = sprintf('%04d-%02d-%02d', $parts[1], $month + 1, $parts[3]);
            if ($date > $last_day) { continue; }
            $rate = str_replace(',', '.', trim($cells->item(1)->textContent));
            $value = sharity_points_money_parse($rate);
            if (!$value['success'] || $value['zero']) { throw new RuntimeException(); }
            $candidate = ['success' => true, 'currency' => $currency, 'date' => $date, 'rate' => $rate, 'unit' => $unit];
            if ($selected && $date === $selected['date'] && $candidate !== $selected) { throw new RuntimeException(); }
            if (!$selected || $date > $selected['date']) { $selected = $candidate; }
        }
        return $selected ?? ['success' => false, 'error' => 'mnb_quote_missing'];
    } catch (Throwable $error) {
        return ['success' => false, 'error' => 'mnb_response_invalid'];
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
}

/** One official HTTPS request per currency/day per invocation, without retry/fallback. */
function sharity_points_mnb_quote(string $currency, string $finalized_at): array
{
    static $quotes = [];
    if (!preg_match('/^[A-Z]{3}$/D', $currency)
        || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?Z$/D', $finalized_at)) {
        return ['success' => false, 'error' => 'invalid_finalization'];
    }
    try { $final = new DateTimeImmutable($finalized_at); } catch (Exception $error) {
        return ['success' => false, 'error' => 'invalid_finalization'];
    }
    if ($final->format('Y-m-d\TH:i:s') !== substr($finalized_at, 0, 19) || $final->getTimestamp() > time()) {
        return ['success' => false, 'error' => 'invalid_finalization'];
    }
    $day = $final->setTimezone(new DateTimeZone('Europe/Budapest'))->format('Y-m-d');
    if ($currency === 'HUF') { return ['success' => true, 'currency' => 'HUF', 'date' => $day, 'rate' => '1', 'unit' => '1']; }
    $key = $currency . ':' . $day;
    if (isset($quotes[$key])) { return $quotes[$key]; }
    $cache = 'sharity_points_mnb_' . $currency . '_' . $day;
    $saved = get_transient($cache);
    if (is_array($saved) && ($saved['success'] ?? false)) { return $quotes[$key] = $saved; }
    $from = $final->setTimezone(new DateTimeZone('Europe/Budapest'))->modify('-14 days')->format('Y-m-d');
    $url = 'https://www.mnb.hu/arfolyam-tablazat?query&' . http_build_query([
        'deviza' => 'rbCurrencySelect', 'devizaSelected' => $currency,
        'datefrom' => str_replace('-', '. ', $from) . '.',
        'datetill' => str_replace('-', '. ', $day) . '.',
    ]);
    $response = wp_remote_get($url, ['timeout' => 10, 'redirection' => 0, 'sslverify' => true]);
    $quote = is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200
        ? ['success' => false, 'error' => 'mnb_request_failed']
        : sharity_points_mnb_parse((string) wp_remote_retrieve_body($response), $currency, $day);
    if ($quote['success']) {
        $today = (new DateTimeImmutable('now', new DateTimeZone('Europe/Budapest')))->format('Y-m-d');
        set_transient($cache, $quote, $day < $today ? 86400 : 300);
    }
    return $quotes[$key] = $quote;
}
