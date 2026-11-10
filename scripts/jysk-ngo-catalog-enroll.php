<?php
/**
 * Narrow JYSK enrollment operator. No WordPress bootstrap, hooks or migrations.
 * Credentials: JYSK_NGO_DB_HOST/PORT/NAME/USER/PASSWORD/PREFIX environment only.
 * plan --input=... ; apply --plan=... --approve=<digest> ; status --plan=...
 * See docs/protected-change-records/2026-11-10-jysk-ngo-catalog-enrollment.md for the input authority boundary.
 */
declare(strict_types=1);

final class JyskNgoEnrollment
{
    public const OPTION = 'impactshop_jysk_ngo_enrollment_v1';
    public const INPUT_SHA = 'db4db03c3802d21034877b2375ad1d5198af19d88c8cdb4427014626aaee7d1a';
    public const MAX_ID = 2147482647;
    private const SLUGS = [
        'herosz-fehervari-allatotthon-alapitvany', 'egeszsegdokk-kozhasznu-alapitvany',
        'fehervar-rugby-club-sportegyesulet', 'szocialis-erdekeket-partolo-emberek-kozossege-kozhasznu-egyesulet',
        'aranybulla-konyvtar-alapitvany', 'david-kornel-kosarlabda-akademia-alapitvany',
        'gardonyi-geza-muvelodesi-haz-es-konyvtar-egyesulet', 'vakok-es-gyengenlatok-fejleszto-magansegito-szervezete',
    ];

    public function __construct(private mysqli $db, private array $target)
    {
        foreach (['database', 'prefix', 'host', 'port'] as $key) {
            if (!isset($target[$key])) { throw new RuntimeException('target_missing'); }
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/D', $target['prefix'])
            || $this->rows('SELECT DATABASE() AS db')[0]['db'] !== $target['database']) {
            throw new RuntimeException('target_mismatch');
        }
        $this->sql('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $this->db->set_charset('utf8mb4');
    }

    public static function digest(array $value): string
    {
        return hash('sha256', json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    public static function jsonFile(string $path): array
    {
        if (is_link($path) || !is_file($path)) { throw new RuntimeException('input_file_invalid'); }
        $v = json_decode(file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($v)) { throw new RuntimeException('input_object_required'); }
        return $v;
    }

    private function sql(string $sql, array $params = []): mysqli_result|bool
    {
        try { $result = $this->db->execute_query($sql, $params ?: null); }
        catch (Throwable $e) { throw new RuntimeException('enrollment_sql_failed'); }
        if ($result === false) { throw new RuntimeException('enrollment_sql_failed'); }
        return $result;
    }

    private function rows(string $sql, array $params = []): array
    {
        return $this->sql($sql, $params)->fetch_all(MYSQLI_ASSOC);
    }

    private function table(string $suffix): string { return $this->target['prefix'] . $suffix; }

    private static function nameKey(string $s): string
    {
        $s = html_entity_decode(mb_strtolower(trim($s)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Conservative duplicate signal, never proof of legal identity.
        $s = iconv('UTF-8', 'ASCII//TRANSLIT', $s);
        if ($s === false) { throw new RuntimeException('name_normalization_failed'); }
        return trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower($s)));
    }

    private static function siteKey(string $s): string
    {
        $p = parse_url($s);
        if (!is_array($p) || !in_array($p['scheme'] ?? '', ['https','http'], true) || empty($p['host'])
            || isset($p['user']) || isset($p['pass'])) { throw new RuntimeException('https_site_required'); }
        return preg_replace('/^www\./', '', strtolower($p['host'])) . rtrim($p['path'] ?? '', '/');
    }

    private function schema(array $input): void
    {
        foreach (['sharity_ngo_catalog', 'sharity_ngo_campaign_flags', 'options'] as $suffix) {
            $row = $this->rows('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', [$this->table($suffix)]);
            if (count($row) !== 1 || strtolower($row[0]['ENGINE']) !== 'innodb') { throw new RuntimeException('schema_requires_innodb'); }
        }
        // Caller must classify ALL discovered NGO-like columns, not silently ignore new consumers.
        $columns = $this->rows("SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND (COLUMN_NAME LIKE '%ngo%id%' OR COLUMN_NAME LIKE '%beneficiary%') ORDER BY TABLE_NAME,COLUMN_NAME");
        $actual = array_map(static fn($r) => $r['TABLE_NAME'] . '.' . $r['COLUMN_NAME'], $columns);
        $declared = array_keys($input['references'] ?? []); sort($declared); sort($actual);
        if ($declared !== $actual) { throw new RuntimeException('reference_inventory_drift'); }
        foreach ($input['references'] as $column => $classification) {
            if (!preg_match('/^[a-zA-Z0-9_]+\.[a-zA-Z0-9_]+$/D', $column)
                || !in_array($classification, ['canonical', 'different-namespace'], true)) {
                throw new RuntimeException('reference_classification_required');
            }
        }
    }

    private function inputs(array $input, bool $fresh): array
    {
        if (($input['version'] ?? null) !== 1 || ($input['target'] ?? null) !== $this->target
            || !preg_match('/^[a-f0-9]{64}$/D', (string) ($input['authorityEvidenceSha256'] ?? ''))) {
            throw new RuntimeException('input_authority_or_target_missing');
        }
        if ($fresh) {
            $at = strtotime((string) ($input['verifiedAt'] ?? ''));
            if (!$at || $at > time() || time() - $at > 600) { throw new RuntimeException('input_readback_expired'); }
        }
        foreach (['directory', 'csv', 'dataset', 'runtime'] as $key) {
            $file = $input[$key] ?? [];
            if (!is_string($file['path'] ?? null) || is_link($file['path']) || !is_file($file['path'])
                || !is_string($file['sha256'] ?? null) || !hash_equals($file['sha256'], hash_file('sha256', $file['path']))) {
                throw new RuntimeException('source_hash_mismatch_' . $key);
            }
        }
        if ($input['directory']['sha256'] !== self::INPUT_SHA) { throw new RuntimeException('not_the_approved_eight'); }
        $source = file_get_contents($input['runtime']['path']);
        if (!str_contains($source, 'catalog_reserved_identity_conflict_id_')
            || preg_match('/define\s*\(\s*[\'"]IMPACTSHOP_NGO_CATALOG_RECOVERY[\'"]\s*,\s*true/i', $source)) {
            throw new RuntimeException('normal_runtime_required');
        }
        $entries = self::jsonFile($input['directory']['path']);
        if (count($entries) !== 8) { throw new RuntimeException('exact_eight_required'); }
        $slugs = array_column($entries, 'slug'); $expected = self::SLUGS; sort($slugs); sort($expected);
        if ($slugs !== $expected) { throw new RuntimeException('exact_slugs_required'); }
        $csv = fopen($input['csv']['path'], 'r');
        $header = fgetcsv($csv, null, ',', '"', chr(92));
        if (!$header) { fclose($csv); throw new RuntimeException('csv_header_missing'); }
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        $idIndex = array_search('Azonosító', $header, true);
        $nameIndex = array_search('Név', $header, true);
        $siteIndex = array_search('Honlap', $header, true);
        if ($idIndex === false || $nameIndex === false) { fclose($csv); throw new RuntimeException('csv_identity_missing'); }
        $identities = [];
        while (($row = fgetcsv($csv, null, ',', '"', chr(92))) !== false) {
            if (count(array_filter($row, static fn($x) => trim((string) $x) !== '')) === 0) { continue; }
            $id = $row[$idIndex] ?? '';
            if (!ctype_digit($id) || (int) $id < 1 || (int) $id > self::MAX_ID) { fclose($csv); throw new RuntimeException('csv_id_invalid'); }
            $identities[] = ['sharity_ngo_id' => (int) $id, 'name' => $row[$nameIndex] ?? '', 'slug' => '', 'website_url' => $siteIndex === false ? '' : (string)($row[$siteIndex] ?? '')];
        }
        fclose($csv);
        if (!$identities) { throw new RuntimeException('csv_empty'); }
        // Dataset names/slugs participate in the importer projection too.
        $dataset = self::jsonFile($input['dataset']['path']);
        foreach ($dataset as $slug => $item) {
            if (!is_array($item) || !is_string($item['name'] ?? null)) { throw new RuntimeException('dataset_shape_invalid'); }
            $ownMetadata = array_filter($entries, static fn($e) => $e['slug'] === (string)($item['slug'] ?? $slug)
                && self::nameKey($e['name']) === self::nameKey($item['name']));
            if (empty($item['ngo_id']) && count($ownMetadata) === 1) { continue; }
            $identities[] = ['sharity_ngo_id'=>(int)($item['ngo_id'] ?? 0),
                'name'=>$item['name'], 'slug'=>(string)($item['slug'] ?? $slug),
                'website_url'=>(string)($item['website_url'] ?? '')];
        }
        return [$entries, $identities];
    }

    private function state(array $input): array
    {
        $this->schema($input);
        $catalog = $this->rows('SELECT * FROM ' . $this->table('sharity_ngo_catalog') . ' ORDER BY sharity_ngo_id');
        $flags = $this->rows('SELECT * FROM ' . $this->table('sharity_ngo_campaign_flags') . ' ORDER BY campaign_key,sharity_ngo_id');
        // Periodic importer timestamps do not change identity. All other fields bind the plan.
        foreach ($catalog as &$r) { unset($r['source_last_synced_at'], $r['updated_at']); } unset($r);
        foreach ($flags as &$r) { unset($r['updated_at']); } unset($r);
        $refs = [];
        foreach ($input['references'] as $column => $kind) {
            if ($kind !== 'canonical') { continue; }
            [$table, $field] = explode('.', $column);
            $refs[$column] = $this->rows('SELECT DISTINCT ' . $field . ' AS ngo_id FROM ' . $table . ' WHERE ' . $field . ' IS NOT NULL ORDER BY ' . $field);
        }
        ksort($refs);
        return ['catalog' => $catalog, 'flags' => $flags, 'references' => $refs,
            'reservation' => $this->rows('SELECT option_value,autoload FROM ' . $this->table('options') . ' WHERE option_name=?', [self::OPTION])];
    }

    private function collision(array $entries, array $existing): void
    {
        $ids = $slugs = $names = $sites = [];
        foreach ($entries as $e) {
            if (!is_int($e['ngo_id'] ?? null) || $e['ngo_id'] < 1 || $e['ngo_id'] > self::MAX_ID) { throw new RuntimeException('candidate_id_invalid'); }
            $name = self::nameKey($e['name']); $site = self::siteKey($e['website_url']);
            if (isset($ids[$e['ngo_id']]) || isset($slugs[$e['slug']]) || isset($names[$name]) || isset($sites[$site])) {
                throw new RuntimeException('candidate_duplicate');
            }
            $ids[$e['ngo_id']] = $slugs[$e['slug']] = $names[$name] = $sites[$site] = true;
            foreach ($existing as $old) {
                if ((int) ($old['sharity_ngo_id'] ?? $old['ngo_id'] ?? 0) === $e['ngo_id']
                    || ($old['slug'] ?? '') === $e['slug']
                    || (!empty($old['name']) && self::nameKey($old['name']) === $name)
                    || (!empty($old['website_url']) && preg_match('#^https?://#i', $old['website_url'])
                        && self::siteKey($old['website_url']) === $site)) {
                    throw new RuntimeException('existing_identity_conflict');
                }
            }
        }
    }

    public function plan(array $input): array
    {
        [$entries, $csv] = $this->inputs($input, true);
        $this->sql('START TRANSACTION READ ONLY');
        try {
            $state = $this->state($input);
            if ($state['reservation']) { throw new RuntimeException('reservation_exists_use_status'); }
            $existing = array_merge($state['catalog'], $csv, ...array_values($state['references']));
            $used = array_map(static fn($r) => (int) ($r['sharity_ngo_id'] ?? $r['ngo_id'] ?? 0), $existing);
            foreach ($entries as &$entry) {
                do { $id = random_int(1, self::MAX_ID); } while (in_array($id, $used, true));
                $entry['ngo_id'] = $id; $used[] = $id;
            } unset($entry);
            $this->collision($entries, $existing);
            $plan = ['version' => 1, 'input' => $input, 'operatorSha256' => hash_file('sha256', __FILE__),
                'prestateDigest' => self::digest($state), 'entries' => $entries];
            return $plan + ['digest' => self::digest($plan)];
        } finally { $this->sql('ROLLBACK'); }
    }

    private function validatePlan(array $plan, bool $currentSources = true): void
    {
        $payload = $plan; unset($payload['digest']);
        if (!is_string($plan['digest'] ?? null) || !hash_equals(self::digest($payload), $plan['digest'])
            || ($plan['operatorSha256'] ?? '') !== hash_file('sha256', __FILE__)) { throw new RuntimeException('plan_digest_or_operator_mismatch'); }
        if (($plan['version'] ?? null) !== 1 || ($plan['input']['target'] ?? null) !== $this->target) { throw new RuntimeException('plan_target_mismatch'); }
        if (!is_array($plan['entries'] ?? null) || count($plan['entries']) !== 8) { throw new RuntimeException('exact_eight_required'); }
        $slugs = array_column($plan['entries'], 'slug'); $expected = self::SLUGS; sort($slugs); sort($expected);
        if ($slugs !== $expected) { throw new RuntimeException('exact_slugs_required'); }
        if ($currentSources) {
            [$approved] = $this->inputs($plan['input'], false);
            foreach ($plan['entries'] as $i => $e) {
                $withoutId = $e; unset($withoutId['ngo_id']);
                if ($withoutId !== $approved[$i]) { throw new RuntimeException('approved_metadata_changed'); }
            }
        }
        $this->collision($plan['entries'], []);
    }

    private function result(array $plan): array
    {
        $rows = $this->rows('SELECT option_value FROM ' . $this->table('options') . ' WHERE option_name=?', [self::OPTION]);
        if (!$rows) { return ['status' => 'not-applied', 'writes' => false]; }
        $reservation = json_decode($rows[0]['option_value'], true, 64, JSON_THROW_ON_ERROR);
        $bindings = array_map(static fn($e) => array_intersect_key($e, array_flip(['ngo_id','slug','name','website_url'])), $plan['entries']);
        if (($reservation['planDigest'] ?? '') !== $plan['digest'] || ($reservation['entries'] ?? null) !== $bindings) {
            throw new RuntimeException('reservation_conflict');
        }
        foreach ($plan['entries'] as $e) {
            $r = $this->rows('SELECT * FROM ' . $this->table('sharity_ngo_catalog') . ' WHERE sharity_ngo_id=?', [$e['ngo_id']]);
            $f = $this->rows('SELECT * FROM ' . $this->table('sharity_ngo_campaign_flags') . ' WHERE campaign_key=? AND sharity_ngo_id=?', ['vb2026',$e['ngo_id']]);
            if (count($r) !== 1 || count($f) !== 1 || $r[0]['slug'] !== $e['slug'] || $r[0]['name'] !== $e['name']
                || $r[0]['website_url'] !== $e['website_url'] || (int) $r[0]['is_active'] !== 1
                || (int) $f[0]['allow_public_listing'] !== 1 || (int) $f[0]['allow_user_selection'] !== 0
                || (int) $f[0]['is_featured'] !== 0 || (int) $f[0]['is_race_visible'] !== 0 || $f[0]['campaign_state'] !== 'active') {
                throw new RuntimeException('applied_binding_drift');
            }
        }
        return ['status' => 'applied', 'digest' => $plan['digest'], 'count' => 8, 'writes' => false];
    }

    public function status(array $plan): array
    {
        // Readback must still work after the normal runtime has been replaced by recovery.
        $this->validatePlan($plan, false);
        $this->sql('START TRANSACTION READ ONLY');
        try { return $this->result($plan); }
        finally { $this->sql('ROLLBACK'); }
    }

    public function apply(array $plan, string $approval): array
    {
        $this->validatePlan($plan);
        if (!hash_equals($plan['digest'], $approval)) { throw new RuntimeException('exact_approval_required'); }
        $lock = 'ngo-enroll:' . substr(hash('sha256', $this->target['database'] . ':' . $this->target['prefix']), 0, 40);
        if ((int) $this->rows('SELECT GET_LOCK(?,0) AS acquired', [$lock])[0]['acquired'] !== 1) { throw new RuntimeException('catalog_writer_busy'); }
        $tx = false;
        try {
            $this->sql('START TRANSACTION'); $tx = true;
            $previous = $this->result($plan);
            if ($previous['status'] === 'applied') { $this->sql('ROLLBACK'); $tx = false; return $previous; }
            [, $csv] = $this->inputs($plan['input'], true);
            $state = $this->state($plan['input']);
            if (!hash_equals($plan['prestateDigest'], self::digest($state))) { throw new RuntimeException('prestate_drift'); }
            $this->collision($plan['entries'], array_merge($state['catalog'], $csv, ...array_values($state['references'])));
            $now = gmdate('Y-m-d H:i:s');
            foreach ($plan['entries'] as $e) {
                $this->sql('INSERT INTO ' . $this->table('sharity_ngo_catalog')
                    . ' (sharity_ngo_id,slug,slug_source,name,city,short_mission,website_url,share_url,details_url,is_active,source_status_label,source_last_synced_at,source_row_hash,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    [$e['ngo_id'],$e['slug'],'jysk_enrollment',$e['name'],$e['city'] ?? null,$e['short_mission'] ?? null,
                     $e['website_url'],$e['share_url'] ?? null,$e['details_url'] ?? null,1,'Aktív',$now,self::digest($e),$now,$now]);
                $this->sql('INSERT INTO ' . $this->table('sharity_ngo_campaign_flags')
                    . ' (campaign_key,sharity_ngo_id,is_featured,is_race_visible,allow_public_listing,allow_user_selection,display_priority,campaign_state,updated_at) VALUES (?,?,0,0,1,0,?,?,?)',
                    ['vb2026',$e['ngo_id'],1000+$e['ngo_id'],'active',$now]);
            }
            $bindings = array_map(static fn($e) => array_intersect_key($e, array_flip(['ngo_id','slug','name','website_url'])), $plan['entries']);
            $reservation = ['version'=>1,'planDigest'=>$plan['digest'],'entries'=>$bindings,'appliedAt'=>gmdate('c')];
            $this->sql('INSERT INTO ' . $this->table('options') . ' (option_name,option_value,autoload) VALUES (?,?,?)',
                [self::OPTION,json_encode($reservation, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),'no']);
            try { $this->sql('COMMIT'); }
            catch (Throwable $e) { throw new RuntimeException('commit_uncertain_run_status_with_new_connection'); }
            $tx = false;
            return ['status'=>'committed-readback-required','digest'=>$plan['digest'],'writes'=>true];
        } finally {
            if ($tx) { try { $this->db->query('ROLLBACK'); } catch (Throwable $ignored) {} }
            try { $this->sql('SELECT RELEASE_LOCK(?)', [$lock]); } catch (Throwable $ignored) {}
        }
    }
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        $mode = $argv[1] ?? ''; $args = [];
        foreach (array_slice($argv, 2) as $arg) {
            if (!preg_match('/^--(input|plan|approve)=(.+)$/D', $arg, $m) || isset($args[$m[1]])) { throw new RuntimeException('invalid_arguments'); }
            $args[$m[1]] = $m[2];
        }
        if (!in_array($mode, ['plan','apply','status'], true)) { throw new RuntimeException('invalid_mode'); }
        $target = ['host'=>(string)getenv('JYSK_NGO_DB_HOST'),'port'=>(int)getenv('JYSK_NGO_DB_PORT'),
            'database'=>(string)getenv('JYSK_NGO_DB_NAME'),'prefix'=>(string)getenv('JYSK_NGO_DB_PREFIX')];
        if ($target['host'] === '' || $target['database'] === '' || $target['port'] < 1
            || !getenv('JYSK_NGO_DB_USER') || getenv('JYSK_NGO_DB_PASSWORD') === false) { throw new RuntimeException('protected_connection_environment_required'); }
        $db = new mysqli($target['host'], getenv('JYSK_NGO_DB_USER'), getenv('JYSK_NGO_DB_PASSWORD'), $target['database'], $target['port']);
        if ($db->connect_errno) { throw new RuntimeException('connection_failed'); }
        $operator = new JyskNgoEnrollment($db, $target);
        $input = JyskNgoEnrollment::jsonFile($args[$mode === 'plan' ? 'input' : 'plan'] ?? '');
        $answer = match ($mode) {
            'plan' => $operator->plan($input), 'status' => $operator->status($input),
            'apply' => $operator->apply($input, $args['approve'] ?? ''),
        };
        echo json_encode($answer, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
    } catch (Throwable $e) {
        $code = $e instanceof RuntimeException && preg_match('/^[a-z0-9_]+$/D', $e->getMessage())
            ? $e->getMessage() : 'operation_failed';
        fwrite(STDERR, json_encode(['status'=>'blocked','error'=>$code]) . "\n"); exit(1);
    }
}
