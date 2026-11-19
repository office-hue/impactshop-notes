<?php
/**
 * Plugin Name: ImpactShop TradeTracker read-only coupon pages
 * Description: Explicit private PHP reads only; no credentials, session or data are persisted.
 */
if (!defined('ABSPATH')) { exit; }

if (!function_exists('impactshop_tradetracker_clock')) {
    function impactshop_tradetracker_clock() { return microtime(true); }
}

final class ImpactShop_TradeTracker
{
    private const ENDPOINT = 'https://ws.tradetracker.com/soap-literal-wsi/affiliate';
    private const SOAP = 'http://www.w3.org/2003/05/soap-envelope';
    private const XSI = 'http://www.w3.org/2001/XMLSchema-instance';
    private const SITE = '481183';
    private $customer;
    private $passphrase;
    private $started;
    private $requests = 0;
    private $bytes = 0;
    private $cookies = [];
    private $siteReady = false;
    private $campaigns = [];
    private $failure = null;

    /** Lossless TT-safe wire encoding; the original SAT1 and its stored hash stay unchanged. */
    public static function reference_for_token($token): string
    {
        if (!is_string($token) || !preg_match('/^sat1_[A-Za-z0-9_-]{43}$/D', $token)) {
            throw new InvalidArgumentException('tt_reference');
        }
        $bytes = base64_decode(strtr(substr($token, 5), '-_', '+/') . '=', true);
        if ($bytes === false || strlen($bytes) !== 32
            || 'sat1_' . rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=') !== $token) {
            throw new InvalidArgumentException('tt_reference');
        }
        return 'tt1-' . bin2hex($bytes);
    }

    /** Decoding grants no authority: callers must verify the intent's provider/site/program binding. */
    public static function token_for_reference($reference): string
    {
        if (!is_string($reference) || !preg_match('/^tt1-[a-f0-9]{64}$/D', $reference)) {
            throw new InvalidArgumentException('tt_reference');
        }
        return 'sat1_' . rtrim(strtr(base64_encode(hex2bin(substr($reference, 4))), '+/', '-_'), '=');
    }

    public function __construct($customerID, $passphrase)
    {
        $this->customer = $this->id($customerID);
        if (!is_string($passphrase) || strlen($passphrase) < 8 || strlen($passphrase) > 4096
            || !preg_match('//u', $passphrase) || preg_match('/[\x00-\x1f\x7f]/', $passphrase)) { $this->fail('tt_input'); }
        $this->passphrase = $passphrase;
        $this->started = impactshop_tradetracker_clock();
    }

    /** Current own-site campaign evidence, not permission to publish or promise a donation. */
    public function qualify_campaign($campaignID): array
    {
        $id = $this->id($campaignID);
        $this->budget();
        $this->session();
        if (isset($this->campaigns[$id])) { return $this->campaigns[$id]; }
        $filter = array_fill_keys(['ID', 'query', 'campaignCategoryID', 'assignmentStatus',
            'policySearchEngineMarketingStatus', 'policyEmailMarketingStatus', 'policyCashbackStatus',
            'policyDiscountCodeStatus', 'limit', 'offset', 'sort', 'sortDirection', 'excludeInfo'], null);
        $filter = array_replace($filter, ['ID' => $id, 'assignmentStatus' => 'accepted', 'limit' => 1, 'offset' => 0, 'excludeInfo' => false]);
        $root = $this->request('getCampaigns', ['affiliateSiteID' => self::SITE, 'options' => $filter]);
        $rows = $this->collection($this->one($root, 'campaigns'), 'campaign', 1);
        if (count($rows) !== 1 || $this->id($this->scalar($rows[0], 'ID')) !== $id) { $this->fail('tt_scope'); }
        $info = $this->one($rows[0], 'info');
        if ($this->scalar($info, 'assignmentStatus') !== 'accepted') { $this->fail('tt_scope'); }
        $evidence = ['siteId' => self::SITE, 'campaignId' => $id, 'assignmentStatus' => 'accepted'];
        foreach (['policyCashbackStatus', 'policyDiscountCodeStatus'] as $field) {
            $value = $this->scalar($info, $field, true);
            if ($value !== null && !in_array($value, ['allowed', 'limited', 'disallowed'], true)) { $this->fail('tt_schema'); }
            $evidence[$field] = $value;
        }
        $evidence['timeZone'] = $this->scalar($info, 'timeZone', true);
        $evidence['referencesSupported'] = $this->boolean($this->scalar($info, 'referencesSupported'));
        $this->budget();
        $this->campaigns[$id] = $evidence;
        return $evidence;
    }

    /** A private provider page. The caller owns full pagination, dates, policy and public projection. */
    public function get_coupon_page($resource, $campaignID, $offset = 0, $limit = 100): array
    {
        if (!is_string($resource) || !in_array($resource, ['voucher', 'offer'], true)
            || !is_int($offset) || $offset < 0 || $offset > 100000000
            || !is_int($limit) || $limit < 1 || $limit > 100) { $this->fail('tt_input'); }
        $id = $this->id($campaignID);
        $evidence = $this->qualify_campaign($id);
        $filter = array_fill_keys(['ID', 'query', 'campaignID', 'campaignCategoryID', 'includeUnsubscribedCampaigns',
            'materialBannerDimensionID', 'reference', 'limit', 'offset', 'sort', 'sortDirection'], null);
        $filter = array_replace($filter, ['campaignID' => $id, 'includeUnsubscribedCampaigns' => false,
            'limit' => $limit, 'offset' => $offset, 'sort' => 'ID', 'sortDirection' => 'ascending']);
        $method = $resource === 'voucher' ? 'getMaterialIncentiveVoucherItems' : 'getMaterialIncentiveOfferItems';
        $before = $this->bytes;
        $root = $this->request($method, ['affiliateSiteID' => self::SITE, 'materialOutputType' => 'html', 'options' => $filter]);
        $nodes = $this->collection($this->one($root, 'materialItems'), 'materialItem', $limit);
        $rows = []; $seen = [];
        foreach ($nodes as $node) {
            $itemID = $this->id($this->scalar($node, 'ID'));
            if (isset($seen[$itemID])) { $this->fail('tt_schema'); }
            $seen[$itemID] = true;
            $campaign = $this->one($node, 'campaign');
            if ($this->id($this->scalar($campaign, 'ID')) !== $id) { $this->fail('tt_scope'); }
            $row = ['ID' => $itemID, 'campaignID' => $id, 'resource' => $resource];
            foreach (['name', 'description', 'conditions', 'voucherCode', 'validFromDate', 'validToDate', 'code'] as $field) {
                $row[$field] = $this->scalar($node, $field, !in_array($field, ['name', 'code'], true));
            }
            $row['referenceSupported'] = $this->boolean($this->scalar($node, 'referenceSupported'));
            $rows[] = $row;
        }
        $this->budget();
        return ['siteId' => self::SITE, 'campaign' => $evidence, 'resource' => $resource, 'offset' => $offset,
            'limit' => $limit, 'rows' => $rows, 'returned' => count($rows), 'responseBytes' => $this->bytes - $before,
            'totalResponseBytes' => $this->bytes, 'requests' => $this->requests];
    }

    private function session(): void
    {
        if ($this->siteReady) { return; }
        $root = $this->request('authenticate', ['customerID' => $this->customer, 'passphrase' => $this->passphrase,
            'sandbox' => false, 'locale' => 'hu_HU', 'demo' => false]);
        if ($this->elements($root) !== [] || !$this->cookies) { $this->fail('tt_auth'); }
        $filter = array_fill_keys(['ID', 'query', 'affiliateSiteCategoryID', 'affiliateSiteTypeID',
            'affiliateSiteStatus', 'limit', 'offset', 'sort', 'sortDirection', 'excludeInfo'], null);
        $filter = array_replace($filter, ['ID' => self::SITE, 'limit' => 1, 'offset' => 0, 'excludeInfo' => false]);
        $root = $this->request('getAffiliateSites', ['options' => $filter]);
        $rows = $this->collection($this->one($root, 'affiliateSites'), 'affiliateSite', 1);
        if (count($rows) !== 1 || $this->id($this->scalar($rows[0], 'ID')) !== self::SITE
            || $this->scalar($this->one($rows[0], 'info'), 'status') !== 'accepted') { $this->fail('tt_scope'); }
        $url = parse_url($this->scalar($rows[0], 'URL'));
        if (!is_array($url) || ($url['scheme'] ?? '') !== 'https' || ($url['host'] ?? '') !== 'www.sharity.hu'
            || isset($url['user']) || isset($url['pass']) || isset($url['port'])) { $this->fail('tt_scope'); }
        $this->siteReady = true;
    }

    private function request($method, array $params): DOMElement
    {
        $remaining = $this->budget();
        $remainingBytes = 1048576 - $this->bytes;
        if ($this->requests >= 20 || $remainingBytes <= 0) { $this->fail('tt_limits'); }
        $doc = new DOMDocument('1.0', 'UTF-8');
        $envelope = $doc->appendChild($doc->createElementNS(self::SOAP, 'soap:Envelope'));
        $envelope->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:tt', self::ENDPOINT);
        $envelope->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', self::XSI);
        $body = $envelope->appendChild($doc->createElementNS(self::SOAP, 'soap:Body'));
        $operation = $body->appendChild($doc->createElementNS(self::ENDPOINT, 'tt:' . $method));
        $this->append($doc, $operation, $params);
        $this->requests++;
        $response = wp_remote_post(self::ENDPOINT, ['timeout' => min(8, $remaining), 'redirection' => 0,
            'sslverify' => true, 'limit_response_size' => $remainingBytes + 1, 'cookies' => array_values($this->cookies),
            'headers' => ['Content-Type' => 'application/soap+xml; charset=utf-8; action="' . self::ENDPOINT . '/' . $method . '"'],
            'body' => $doc->saveXML()]);
        if (is_wp_error($response) || !is_array($response)) { $this->fail('tt_transport'); }
        $status = wp_remote_retrieve_response_code($response);
        if ($status === 401 || $status === 403) { $this->fail('tt_auth'); }
        if ($status === 429) { $this->fail('tt_rate_limit'); }
        if ($status !== 200 && $status !== 500) { $this->fail('tt_transport'); }
        $raw = wp_remote_retrieve_body($response);
        if (!is_string($raw)) { $this->fail('tt_schema'); }
        $this->bytes += strlen($raw);
        if ($this->bytes > 1048576) { $this->fail('tt_limits'); }
        $this->budget();
        $root = $this->parse($raw, $method);
        if ($status !== 200) { $this->fail('tt_transport'); }
        foreach ($response['cookies'] ?? [] as $cookie) {
            if (!is_object($cookie) || !is_string($cookie->name ?? null) || !is_string($cookie->value ?? null)
                || !preg_match('/^[A-Za-z0-9_-]{1,100}$/D', $cookie->name) || strlen($cookie->value) > 4096
                || preg_match('/[\x00-\x20\x7f;]/', $cookie->value)
                || !in_array(ltrim(strtolower($cookie->domain ?? ''), '.'), ['', 'ws.tradetracker.com', 'tradetracker.com'], true)) { $this->fail('tt_schema'); }
            if ($cookie->value === '') { unset($this->cookies[$cookie->name]); }
            else { $this->cookies[$cookie->name] = clone $cookie; }
            if (count($this->cookies) > 16) { $this->fail('tt_limits'); }
        }
        return $root;
    }

    private function append(DOMDocument $doc, DOMElement $parent, array $params): void
    {
        foreach ($params as $name => $value) {
            $element = $parent->appendChild($doc->createElementNS(self::ENDPOINT, 'tt:' . $name));
            if (is_array($value)) { $this->append($doc, $element, $value); }
            elseif ($value === null) { $element->setAttributeNS(self::XSI, 'xsi:nil', 'true'); }
            else { $element->appendChild($doc->createTextNode(is_bool($value) ? ($value ? 'true' : 'false') : (string) $value)); }
        }
    }

    private function parse($raw, $method): DOMElement
    {
        if (strpos($raw, "\0") !== false || !preg_match('//u', $raw) || preg_match('/<!\s*(?:DOCTYPE|ENTITY)\b/i', $raw)
            || (preg_match('/<\?xml[^>]*encoding\s*=\s*[\'"]([^\'"]+)/i', $raw, $m) && strtoupper($m[1]) !== 'UTF-8')) { $this->fail('tt_schema'); }
        $doc = new DOMDocument();
        $doc->resolveExternals = false; $doc->substituteEntities = false;
        $old = libxml_use_internal_errors(true);
        try { $ok = $raw !== '' && $doc->loadXML($raw, LIBXML_NONET); }
        catch (Throwable $e) { $ok = false; }
        finally { libxml_clear_errors(); libxml_use_internal_errors($old); }
        if (!$ok || !$doc->documentElement || $doc->documentElement->localName !== 'Envelope'
            || $doc->documentElement->namespaceURI !== self::SOAP) { $this->fail('tt_schema'); }
        $bodies = $this->elements($doc->documentElement);
        if (count($bodies) !== 1 || $bodies[0]->localName !== 'Body' || $bodies[0]->namespaceURI !== self::SOAP) { $this->fail('tt_schema'); }
        $operations = $this->elements($bodies[0]);
        if (count($operations) !== 1) { $this->fail('tt_schema'); }
        $root = $operations[0];
        if ($root->localName === 'Fault' && $root->namespaceURI === self::SOAP) { $this->fail($method === 'authenticate' ? 'tt_auth' : 'tt_fault'); }
        if ($root->localName !== $method . 'Response' || $root->namespaceURI !== self::ENDPOINT) { $this->fail('tt_schema'); }
        foreach ($root->getElementsByTagName('*') as $element) {
            if ($element->namespaceURI !== self::ENDPOINT) { $this->fail('tt_schema'); }
        }
        $containers = ['getAffiliateSites' => 'affiliateSites', 'getCampaigns' => 'campaigns',
            'getMaterialIncentiveVoucherItems' => 'materialItems', 'getMaterialIncentiveOfferItems' => 'materialItems'];
        if (isset($containers[$method])) {
            $children = $this->elements($root);
            if (count($children) !== 1 || $children[0]->localName !== $containers[$method]) { $this->fail('tt_schema'); }
        }
        return $root;
    }

    private function elements(DOMElement $parent): array
    {
        $elements = [];
        foreach ($parent->childNodes as $node) {
            if ($node instanceof DOMElement) { $elements[] = $node; }
            elseif (($node instanceof DOMText || $node instanceof DOMCdataSection) && trim($node->nodeValue) !== '') { $this->fail('tt_schema'); }
            elseif ($node->nodeType !== XML_TEXT_NODE) { $this->fail('tt_schema'); }
        }
        return $elements;
    }

    private function one(DOMElement $parent, $name): DOMElement
    {
        $found = [];
        foreach ($this->elements($parent) as $child) { if ($child->localName === $name && $child->namespaceURI === self::ENDPOINT) { $found[] = $child; } }
        if (count($found) !== 1) { $this->fail('tt_schema'); }
        if ($found[0]->hasAttributeNS(self::XSI, 'nil')) {
            $nil = $found[0]->getAttributeNS(self::XSI, 'nil');
            if (!in_array($nil, ['true', '1', 'false', '0'], true)
                || (($nil === 'true' || $nil === '1') && $found[0]->childNodes->length !== 0)) { $this->fail('tt_schema'); }
        }
        return $found[0];
    }

    private function scalar(DOMElement $parent, $name, $nullable = false)
    {
        $node = $this->one($parent, $name);
        foreach ($node->attributes as $attribute) {
            if ($attribute->namespaceURI !== 'http://www.w3.org/2000/xmlns/'
                && !($attribute->namespaceURI === self::XSI && $attribute->localName === 'nil')) { $this->fail('tt_schema'); }
        }
        foreach ($node->childNodes as $child) { if (!$child instanceof DOMText && !$child instanceof DOMCdataSection) { $this->fail('tt_schema'); } }
        if ($node->hasAttributeNS(self::XSI, 'nil')) {
            $nil = $node->getAttributeNS(self::XSI, 'nil');
            if (!in_array($nil, ['true', '1', 'false', '0'], true)) { $this->fail('tt_schema'); }
            if ($nil === 'true' || $nil === '1') {
                if (!$nullable || $node->textContent !== '') { $this->fail('tt_schema'); }
                return null;
            }
        }
        return $node->textContent;
    }

    private function collection(DOMElement $parent, $name, $limit): array
    {
        if ($parent->hasAttributeNS(self::XSI, 'nil')) { $this->fail('tt_schema'); }
        $rows = $this->elements($parent);
        if (count($rows) > $limit) { $this->fail('tt_schema'); }
        foreach ($rows as $row) { if ($row->localName !== $name || $row->namespaceURI !== self::ENDPOINT) { $this->fail('tt_schema'); } }
        return $rows;
    }

    private function id($value): string
    {
        if (is_int($value)) { $value = (string) $value; }
        if (!is_string($value) || !preg_match('/^[1-9]\d{0,15}$/D', $value)) { $this->fail('tt_input'); }
        return $value;
    }

    private function boolean($value): bool
    {
        if (!in_array($value, ['true', 'false', '1', '0'], true)) { $this->fail('tt_schema'); }
        return $value === 'true' || $value === '1';
    }

    private function budget(): float
    {
        if ($this->failure !== null) { throw new RuntimeException($this->failure); }
        $elapsed = impactshop_tradetracker_clock() - $this->started;
        if (!is_finite($elapsed) || $elapsed < 0 || $elapsed >= 60) { $this->fail('tt_limits'); }
        return 60 - $elapsed;
    }

    private function fail($code): void
    {
        $this->failure = $code;
        throw new RuntimeException($code);
    }
}
