<?php
/**
 * Plugin Name: ImpactShop Fast Data Backup (CLI)
 * Description: Hot adatbazis backup parancsok WP-CLI-hez.
 */

if (!defined('ABSPATH')) {
    return;
}

final class ImpactShop_Fast_Data_Backup
{
    private const HOT_TABLES = [
        'impactshop_ads_votes',
        'impactshop_ads_user_votes',
        'impactshop_ads_user_stats',
        'impactshop_ads_views',
        'impactshop_education_views',
        'impactshop_ads_quarters',
        'impactshop_ads_quarter_results',
        'impactshop_ads_user_ngo',
        'impactshop_offerwall_completions',
        'user_points',
        'point_transactions',
        'level_history',
        'decay_logs',
        'impact_ledger',
        'impact_ledger_audit',
        'impact_rates',
        'impact_token_audit',
        'impact_audit_log',
        'impact_pin_tokens',
        'impact_tokens',
    ];

    private const OPTION_PREFIXES = [
        'impactshop_',
        'impact_',
        'sharity_',
    ];

    private const DEFAULT_HOT_RETENTION_HOURS = 48;
    private const DEFAULT_DAILY_RETENTION_DAYS = 30;

    public function hot(array $args, array $assoc_args): void
    {
        $this->run_backup('hot', $assoc_args);
    }

    public function daily(array $args, array $assoc_args): void
    {
        $this->run_backup('daily', $assoc_args);
    }

    public function quarterly(array $args, array $assoc_args): void
    {
        $this->run_backup('quarterly', $assoc_args);
    }

    public function status(array $args, array $assoc_args): void
    {
        $root = $this->get_backup_root();
        $modes = ['hot', 'daily', 'quarterly'];
        foreach ($modes as $mode) {
            $latest = $this->read_latest_meta($root, $mode);
            if ($latest === null) {
                WP_CLI::line(sprintf('%s: nincs adat.', $mode));
                continue;
            }
            $when = $latest['created_at'] ?? 'n/a';
            $count = isset($latest['tables']) ? count((array) $latest['tables']) : 0;
            $offsite = $latest['offsite']['status'] ?? 'n/a';
            WP_CLI::line(sprintf('%s: %s | tables=%d | offsite=%s', $mode, $when, $count, $offsite));
        }
    }

    private function run_backup(string $mode, array $assoc_args): void
    {
        if (!defined('WP_CLI') || !WP_CLI) {
            return;
        }

        set_time_limit(0);
        umask(077);

        $root = $this->get_backup_root();
        $lock = $this->acquire_lock($root, $mode);

        $started = microtime(true);
        $timestamp = $this->get_timestamp($mode);
        $run_dir = $this->get_run_dir($root, $mode, $timestamp);

        wp_mkdir_p($run_dir);
        $this->ensure_log_dir($root);

        $this->log_line($root, sprintf('start mode=%s dir=%s', $mode, $run_dir));
        WP_CLI::log(sprintf('Backup indul: %s', $run_dir));

        global $wpdb;
        $tables = $this->expand_tables($wpdb->prefix);
        $existing = $this->filter_existing_tables($tables);
        $missing = array_values(array_diff($tables, $existing));
        if (empty($existing)) {
            $this->release_lock($lock);
            WP_CLI::error('Nincs elerheto hot tabla a backuphoz.');
        }

        $engines = $this->get_table_engines($existing);
        $non_innodb = $engines['non_innodb'] ?? [];
        $use_single = empty($non_innodb);
        if (!empty($non_innodb)) {
            WP_CLI::log('Figyelem: nem InnoDB tablak eszlelve: ' . implode(', ', $non_innodb));
        }

        $sql_path = $run_dir . '/dump.sql';
        $export_meta = $this->export_tables($existing, $sql_path, $use_single);

        $gz_path = $this->gzip_file($sql_path);
        $sha_path = $this->write_sha256($gz_path);
        $options_path = $this->write_options_snapshot($run_dir, $wpdb);

        $meta = [
            'mode' => $mode,
            'created_at' => gmdate('c'),
            'dir' => $run_dir,
            'tables' => $existing,
            'missing_tables' => $missing,
            'engine' => [
                'single_transaction' => $use_single,
                'non_innodb' => $non_innodb,
            ],
            'dump' => basename($gz_path),
            'sha256' => basename($sha_path),
            'options' => basename($options_path),
            'export' => $export_meta,
            'offsite' => [
                'status' => 'skipped',
            ],
        ];

        $meta_path = $run_dir . '/meta.json';
        file_put_contents($meta_path, wp_json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->update_latest($root, $mode, $meta_path, $timestamp);
        $this->cleanup_local($root, $mode);

        $offsite = $this->sync_remote($root, $mode, $timestamp, $run_dir, $meta_path);
        if ($offsite !== null) {
            $meta['offsite'] = $offsite;
            file_put_contents($meta_path, wp_json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->update_latest($root, $mode, $meta_path, $timestamp);
        }

        $elapsed = (int) round((microtime(true) - $started) * 1000);
        $this->log_line($root, sprintf('done mode=%s dir=%s ms=%d', $mode, $run_dir, $elapsed));
        $this->release_lock($lock);

        WP_CLI::success(sprintf('Backup kesz (%s) %d ms', $mode, $elapsed));
    }

    private function get_backup_root(): string
    {
        $root = getenv('IMPACTSHOP_FAST_BACKUP_ROOT');
        if (!$root) {
            $root = rtrim(dirname(dirname(WP_CONTENT_DIR)), '/') . '/impactshop-backups';
        }
        $root = rtrim($root, '/');
        wp_mkdir_p($root);
        return $root;
    }

    private function ensure_log_dir(string $root): void
    {
        wp_mkdir_p($root . '/logs');
    }

    private function get_timestamp(string $mode): string
    {
        if ($mode === 'daily') {
            return gmdate('Y-m-d');
        }
        return gmdate('Y-m-d_His');
    }

    private function get_run_dir(string $root, string $mode, string $timestamp): string
    {
        return $root . '/' . $mode . '/' . $timestamp;
    }

    private function expand_tables(string $prefix): array
    {
        $tables = [];
        foreach (self::HOT_TABLES as $table) {
            $tables[] = $prefix . $table;
        }
        return $tables;
    }

    private function filter_existing_tables(array $tables): array
    {
        global $wpdb;
        $existing = [];
        foreach ($tables as $table) {
            $name = $wpdb->get_var($wpdb->prepare(
                'SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = %s',
                $table
            ));
            if ($name) {
                $existing[] = $table;
            }
        }
        return $existing;
    }

    private function get_table_engines(array $tables): array
    {
        global $wpdb;
        $non_innodb = [];
        foreach ($tables as $table) {
            $engine = $wpdb->get_var($wpdb->prepare(
                'SELECT ENGINE FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = %s',
                $table
            ));
            if ($engine && strcasecmp($engine, 'InnoDB') !== 0) {
                $non_innodb[] = $table . ':' . $engine;
            }
        }
        return [
            'non_innodb' => $non_innodb,
        ];
    }

    private function export_tables(array $tables, string $sql_path, bool $single_transaction): array
    {
        $flags = $single_transaction ? '--single-transaction' : '--lock-tables';
        $command = sprintf(
            'db export %s --tables=%s %s',
            escapeshellarg($sql_path),
            implode(',', $tables),
            $flags
        );

        $result = WP_CLI::runcommand($command, [
            'return' => 'all',
            'exit_error' => false,
        ]);

        $meta = [
            'command' => $command,
            'return_code' => null,
            'stderr' => null,
        ];

        if (is_array($result)) {
            $meta['return_code'] = $result['return_code'] ?? null;
            $meta['stderr'] = $result['stderr'] ?? null;
        } elseif (is_object($result)) {
            $meta['return_code'] = $result->return_code ?? null;
            $meta['stderr'] = $result->stderr ?? null;
        }

        if (!file_exists($sql_path) || filesize($sql_path) === 0) {
            WP_CLI::error('DB export sikertelen (ures dump).');
        }

        if (!empty($meta['stderr'])) {
            WP_CLI::log('Figyelem: db export stderr: ' . $meta['stderr']);
        }

        return $meta;
    }

    private function gzip_file(string $sql_path): string
    {
        $gz_path = $sql_path . '.gz';
        if ($this->command_exists('gzip')) {
            $this->run_shell(sprintf('gzip -f %s', escapeshellarg($sql_path)), 'gzip');
            return $gz_path;
        }

        $in = fopen($sql_path, 'rb');
        $out = gzopen($gz_path, 'wb9');
        if (!$in || !$out) {
            WP_CLI::error('Gzip nem elerheto, es stream fallback sem mukodik.');
        }
        while (!feof($in)) {
            $chunk = fread($in, 1048576);
            if ($chunk === false) {
                break;
            }
            gzwrite($out, $chunk);
        }
        fclose($in);
        gzclose($out);
        unlink($sql_path);

        return $gz_path;
    }

    private function write_sha256(string $gz_path): string
    {
        $hash = hash_file('sha256', $gz_path);
        $sha_path = $gz_path . '.sha256';
        $line = $hash . '  ' . basename($gz_path) . "\n";
        file_put_contents($sha_path, $line);
        return $sha_path;
    }

    private function write_options_snapshot(string $run_dir, wpdb $wpdb): string
    {
        $options = [];
        foreach (self::OPTION_PREFIXES as $prefix) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name LIKE %s",
                    $prefix . '%'
                ),
                ARRAY_A
            );
            foreach ($rows as $row) {
                $options[$row['option_name']] = $row;
            }
        }
        ksort($options);
        $payload = [
            'generated_at' => gmdate('c'),
            'count' => count($options),
            'options' => $options,
        ];
        $path = $run_dir . '/options.json';
        file_put_contents($path, wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $path;
    }

    private function update_latest(string $root, string $mode, string $meta_path, string $timestamp): void
    {
        $mode_root = $root . '/' . $mode;
        wp_mkdir_p($mode_root);
        copy($meta_path, $mode_root . '/latest.json');
        file_put_contents($mode_root . '/latest.txt', $timestamp . "\n");
    }

    private function cleanup_local(string $root, string $mode): void
    {
        if ($mode === 'quarterly') {
            return;
        }

        $retain_hours = (int) (getenv('IMPACTSHOP_FAST_BACKUP_HOT_RETENTION_HOURS') ?: self::DEFAULT_HOT_RETENTION_HOURS);
        $retain_days = (int) (getenv('IMPACTSHOP_FAST_BACKUP_DAILY_RETENTION_DAYS') ?: self::DEFAULT_DAILY_RETENTION_DAYS);

        $mode_root = $root . '/' . $mode;
        if (!is_dir($mode_root)) {
            return;
        }

        $entries = glob($mode_root . '/*', GLOB_ONLYDIR | GLOB_NOSORT);
        if (!$entries) {
            return;
        }

        $now = time();
        foreach ($entries as $dir) {
            $mtime = filemtime($dir);
            if ($mtime === false) {
                continue;
            }
            if ($mode === 'hot') {
                if ($retain_hours > 0 && ($now - $mtime) > ($retain_hours * 3600)) {
                    $this->delete_dir($dir);
                }
            } elseif ($mode === 'daily') {
                if ($retain_days > 0 && ($now - $mtime) > ($retain_days * 86400)) {
                    $this->delete_dir($dir);
                }
            }
        }
    }

    private function sync_remote(string $root, string $mode, string $timestamp, string $run_dir, string $meta_path): ?array
    {
        $remote_root = getenv('IMPACTSHOP_FAST_BACKUP_REMOTE');
        if (!$remote_root) {
            $workspace_remote = getenv('WORKSPACE_BACKUP_REMOTE');
            if ($workspace_remote) {
                $remote_root = rtrim($workspace_remote, '/') . '/impactshop-db-backups';
            }
        }

        if (!$remote_root) {
            return null;
        }

        if (!$this->command_exists('rclone')) {
            WP_CLI::log('Figyelem: rclone nem elerheto, offsite kihagyva.');
            return [
                'status' => 'fail',
                'error' => 'rclone missing',
            ];
        }

        $dest = rtrim($remote_root, '/') . '/' . $mode . '/' . $timestamp;
        $opts = getenv('IMPACTSHOP_FAST_BACKUP_RCLONE_OPTS') ?: '';
        $cmd = sprintf('rclone copy %s %s %s', escapeshellarg($run_dir), escapeshellarg($dest), $opts);
        $status = $this->run_shell($cmd, 'rclone');

        if ($status !== 0) {
            WP_CLI::log('Figyelem: rclone copy sikertelen.');
            return [
                'status' => 'fail',
                'error' => 'rclone copy failed',
            ];
        }

        $latest_dest = rtrim($remote_root, '/') . '/' . $mode . '/latest.json';
        $latest_txt = rtrim($remote_root, '/') . '/' . $mode . '/latest.txt';
        $this->run_shell(sprintf('rclone copyto %s %s %s', escapeshellarg($meta_path), escapeshellarg($latest_dest), $opts), 'rclone');
        $this->run_shell(sprintf('printf %s | rclone rcat %s %s', escapeshellarg($timestamp), escapeshellarg($latest_txt), $opts), 'rclone');

        return [
            'status' => 'ok',
            'remote' => $dest,
        ];
    }

    private function acquire_lock(string $root, string $mode)
    {
        $lock_path = $root . '/.impactshop-fast-backup-' . $mode . '.lock';
        $fp = fopen($lock_path, 'c');
        if (!$fp) {
            WP_CLI::error('Lock file nem hozhato letre.');
        }
        if (!flock($fp, LOCK_EX | LOCK_NB)) {
            fclose($fp);
            WP_CLI::error('Backup mar fut (lock aktiv).');
        }
        return $fp;
    }

    private function release_lock($fp): void
    {
        if ($fp) {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    private function read_latest_meta(string $root, string $mode): ?array
    {
        $path = $root . '/' . $mode . '/latest.json';
        if (!is_file($path)) {
            return null;
        }
        $raw = file_get_contents($path);
        if (!$raw) {
            return null;
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    private function log_line(string $root, string $message): void
    {
        $path = $root . '/logs/impactshop-fast-backup.log';
        $line = gmdate('c') . ' | ' . $message . "\n";
        file_put_contents($path, $line, FILE_APPEND);
    }

    private function command_exists(string $binary): bool
    {
        $output = [];
        $code = 0;
        @exec('command -v ' . escapeshellarg($binary) . ' 2>/dev/null', $output, $code);
        return $code === 0 && !empty($output);
    }

    private function run_shell(string $command, string $label = 'cmd'): int
    {
        $output = [];
        $code = 0;
        @exec($command . ' 2>&1', $output, $code);
        if (!empty($output)) {
            foreach ($output as $line) {
                if (trim($line) === '') {
                    continue;
                }
                WP_CLI::log($label . ': ' . $line);
            }
        }
        return $code;
    }

    private function delete_dir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($dir);
    }
}

if (defined('WP_CLI') && WP_CLI && class_exists('WP_CLI')) {
    WP_CLI::add_command('impactshop backup', 'ImpactShop_Fast_Data_Backup');
}
