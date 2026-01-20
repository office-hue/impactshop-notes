<?php
/**
 * Plugin Name: ImpactShop Dognet Conversions Helpers
 * Description: Dognet raw-transactions list helpers (batch + all) if missing.
 * Version: 1.0.0
 * Author: Sharity
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('dognet__status_map')) {
    function dognet__status_map(string $status): array
    {
        $status = strtolower(trim($status));
        if ($status === 'approved') {
            return ['A'];
        }
        if ($status === 'pending') {
            return ['P'];
        }
        if ($status === 'rejected') {
            return ['D'];
        }
        return [];
    }
}

if (!function_exists('dognet_api_list_conversions_batch')) {
    function dognet_api_list_conversions_batch(
        string $from,
        string $to,
        string $status = 'all',
        ?int $lastId = null,
        int $perPage = 200
    ): array {
        $fromDt = $from . ' 00:00:00';
        $toDt = $to . ' 23:59:59';

        $filter = [
            ['created_at' => ['gte' => $fromDt]],
            ['created_at' => ['lte' => $toDt]],
        ];

        $rstatus = dognet__status_map($status);
        if ($rstatus) {
            $filter[] = ['rstatus' => ['in' => $rstatus]];
        }

        if (defined('DOGNET_AD_CHANNEL_ID') && DOGNET_AD_CHANNEL_ID) {
            $filter[] = ['ad_channel_id' => ['eq' => intval(DOGNET_AD_CHANNEL_ID)]];
        }

        $body = [
            'per-page' => max(1, min(1000, intval($perPage))),
            'filter'   => $filter,
        ];

        if ($lastId !== null) {
            $body['last_id'] = intval($lastId);
        }

        $resp = dognet_api_request('POST', '/raw-transactions/filter', $body);
        if (is_wp_error($resp)) {
            return ['error' => $resp];
        }

        $items = [];
        if (isset($resp['data']) && is_array($resp['data'])) {
            $items = $resp['data'];
        } elseif (isset($resp['items']) && is_array($resp['items'])) {
            $items = $resp['items'];
        }

        $nextLastId = null;
        if (isset($resp['meta']['last_id'])) {
            $nextLastId = intval($resp['meta']['last_id']);
        } elseif ($items) {
            $maxId = null;
            foreach ($items as $it) {
                foreach (['id', 'transaction_id', 'tid'] as $key) {
                    if (isset($it[$key]) && is_numeric($it[$key])) {
                        $maxId = max(intval($it[$key]), intval($maxId));
                        break;
                    }
                }
            }
            if ($maxId !== null) {
                $nextLastId = $maxId;
            }
        }

        return ['items' => $items, 'last_id' => $nextLastId];
    }
}

if (!function_exists('dognet_api_list_conversions_all')) {
    function dognet_api_list_conversions_all(
        string $from,
        string $to,
        string $status = 'all',
        int $maxBatches = 200,
        int $perPage = 200
    ): array {
        $all = [];
        $lastId = null;
        for ($i = 0; $i < $maxBatches; $i++) {
            $batch = dognet_api_list_conversions_batch($from, $to, $status, $lastId, $perPage);
            if (isset($batch['error']) && is_wp_error($batch['error'])) {
                return ['error' => $batch['error']];
            }
            $items = $batch['items'] ?? [];
            if (!$items) {
                break;
            }
            $all = array_merge($all, $items);
            $lastId = $batch['last_id'] ?? null;
            if ($lastId === null) {
                break;
            }
        }
        return ['items' => $all];
    }
}
