<?php
/** Trusted local WP-CLI evidence inspection and explicit observation storage. No settlement or activation. */
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

function sharity_donation_cli_finance_schema(array $args, array $assoc): void
{
    try {
        if (!defined('WP_CLI') || !WP_CLI || $args !== [] || $assoc !== ['apply' => true]
            || !function_exists('sharity_donation_finance_schema_apply') || !sharity_donation_finance_schema_apply()) { throw new RuntimeException(); }
        WP_CLI::line('DONATION_FINANCE_SCHEMA_READY');
    } catch (Throwable $error) { WP_CLI::error('DONATION_FINANCE_SCHEMA_UNAVAILABLE'); }
}

function sharity_donation_cli_payment_record(array $args, array $assoc): void
{
    try {
        if (!defined('WP_CLI') || !WP_CLI || $args !== [] || array_keys($assoc) !== ['order']
            || !is_string($assoc['order']) || !preg_match('/^do_[a-f0-9]{32}$/D', $assoc['order'])
            || !function_exists('sharity_donation_finance_record') || !function_exists('sharity_donation_runtime_adapter')) { throw new RuntimeException(); }
        $receipt = sharity_donation_finance_record($assoc['order'], sharity_donation_runtime_adapter(), time());
        WP_CLI::line(json_encode($receipt, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    } catch (Throwable $error) {
        $code = in_array($error->getMessage(), ['DONATION_FINANCE_EVIDENCE_TOO_LARGE', 'DONATION_FINANCE_PACKET_TOO_SMALL'], true)
            ? $error->getMessage() : 'DONATION_FINANCE_UNAVAILABLE';
        WP_CLI::error($code);
    }
}

function sharity_donation_cli_payment_reconcile(array $args, array $assoc): void
{
    try {
        if (!defined('WP_CLI') || !WP_CLI || $args !== [] || array_keys($assoc) !== ['order']
            || !is_string($assoc['order']) || !preg_match('/^do_[a-f0-9]{32}$/D', $assoc['order'])
            || !function_exists('sharity_donation_finance_reconcile')) { throw new RuntimeException(); }
        WP_CLI::line(json_encode(sharity_donation_finance_reconcile($assoc['order'], time()), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    } catch (Throwable $error) {
        WP_CLI::error(in_array($error->getMessage(), ['DONATION_RECONCILE_LIMIT', 'DONATION_RECONCILE_CONFLICT'], true) ? $error->getMessage() : 'DONATION_RECONCILE_UNAVAILABLE');
    }
}

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('sharity donations payment-reconcile', 'sharity_donation_cli_payment_reconcile');
    WP_CLI::add_command('sharity donations finance-schema', 'sharity_donation_cli_finance_schema');
    WP_CLI::add_command('sharity donations payment-record', 'sharity_donation_cli_payment_record');
    WP_CLI::add_command('sharity donations payment-inspect', 'sharity_donation_cli_payment_inspect');
}
