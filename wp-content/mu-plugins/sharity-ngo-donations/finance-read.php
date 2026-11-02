<?php
/** Trusted local WP-CLI read only. No route, schema, ledger writer or activation. */
declare(strict_types=1);

function sharity_donation_cli_payment_inspect(array $args, array $assoc): void
{
    try {
        $keys = array_keys($assoc); sort($keys);
        $adjustments = array_key_exists('adjustments', $assoc);
        if (!defined('WP_CLI') || !WP_CLI || $args !== []
            || $keys !== ($adjustments ? ['adjustments', 'order'] : ['order'])
            || ($adjustments && $assoc['adjustments'] !== true)
            || !is_string($assoc['order']) || !preg_match('/^do_[a-f0-9]{32}$/D', $assoc['order'])) { throw new RuntimeException(); }
        foreach (['sharity_donation_order_autocommit', 'sharity_donation_webhook_order', 'sharity_donation_order_status', 'sharity_donation_runtime_adapter'] as $function) {
            if (!function_exists($function)) { throw new RuntimeException(); }
        }
        $row = sharity_donation_webhook_order($assoc['order']);
        $status = sharity_donation_order_status($row['subject_ref'], $assoc['order'], time());
        if ($status['state'] !== 'session_recorded' || $row['state'] !== 'session_recorded') { throw new RuntimeException(); }
        $envelope = sharity_donation_order_decode($row);
        sharity_donation_order_autocommit();
        $adapter = sharity_donation_runtime_adapter();
        $result = $adjustments ? $adapter->retrieveAdjustmentEvidence($row['provider_session_id'], $envelope)
            : $adapter->retrievePaymentEvidence($row['provider_session_id'], $envelope);
        WP_CLI::line(json_encode($result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    } catch (Throwable $error) { WP_CLI::error('DONATION_EVIDENCE_UNAVAILABLE'); }
}

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('sharity donations payment-inspect', 'sharity_donation_cli_payment_inspect');
}
