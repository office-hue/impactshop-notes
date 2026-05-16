#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TARGET_FILE="${1:-${ROOT_DIR}/wp-content/mu-plugins/impactshop-event-auction-widget.php}"

if [[ ! -f "${TARGET_FILE}" ]]; then
  echo "❌ JVK lot guard: missing target file: ${TARGET_FILE}" >&2
  exit 1
fi

EXPECTED_COUNT=11
EXPECTED_SPECS=(
  "1:szentpeteri-toth-marta-forgiveness"
  "2:simon-m-veronika-kek-sugarzas"
  "3:tarcsi-daniel-part-iii"
  "4:ghyczy-gyorgy-elindulok-a-csillagokhoz"
  "5:szabo-anna-cseresznye"
  "6:szabo-anna-a-no-turkizben"
  "7:dimenzio-ingatlan-sirocco-elmenyvitorlazas"
  "8:n28-wine-kitchen-uzleti-ebed"
  "9:dedikalt-meccslabda-magyarorszag-argentina-2005"
  "10:balla-gemma-ecoprint-selyemsal-nyaklac"
  "11:kocsis-katica-weiler-peter-dedikalt-konyv"
)

php /dev/stdin "${TARGET_FILE}" "${EXPECTED_COUNT}" "${EXPECTED_SPECS[@]}" <<'PHP'
<?php
$targetFile = $argv[1];
$expectedCount = (int) $argv[2];
$expectedSpecs = array_slice($argv, 3);
$contents = file_get_contents($targetFile);

if ($contents === false) {
    fwrite(STDERR, "❌ JVK lot guard: unable to read target file\n");
    exit(1);
}

$actualCount = preg_match_all("/'lot_number'\\s*=>\\s*\\d+/", $contents, $matches);
if ($actualCount < $expectedCount) {
    fwrite(STDERR, "❌ JVK lot guard: only {$actualCount} lots found, expected at least {$expectedCount}\n");
    exit(1);
}

$failed = false;
foreach ($expectedSpecs as $spec) {
    [$lotNumber, $lotSlug] = explode(':', $spec, 2);
    $pattern = "/'item_slug'\\s*=>\\s*'" . preg_quote($lotSlug, "/") . "'(?:(?!\\[|\\],).)*'lot_number'\\s*=>\\s*" . preg_quote($lotNumber, "/") . "/s";
    if (!preg_match($pattern, $contents)) {
        fwrite(STDERR, "❌ JVK lot guard: missing or mismatched lot {$lotNumber} ({$lotSlug})\n");
        $failed = true;
    }
}

if ($failed) {
    exit(1);
}

fwrite(STDOUT, "✅ JVK lot guard passed: {$actualCount} lots present, canonical 1-11 set intact\n");
PHP
