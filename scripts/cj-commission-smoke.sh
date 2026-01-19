#!/usr/bin/env bash
set -euo pipefail

CJ_API_URL="${CJ_API_URL:-https://commissions.api.cj.com/query}"
CJ_PAT="${CJ_PAT:-}"
FOR_PUBLISHERS="${FOR_PUBLISHERS:-${CJ_PUBLISHER_ID:-}}"
SINCE_EVENT_DATE="${SINCE_EVENT_DATE:-$(date -u -v-1d '+%Y-%m-%dT%H:%M:%SZ')}"
BEFORE_EVENT_DATE="${BEFORE_EVENT_DATE:-$(date -u '+%Y-%m-%dT%H:%M:%SZ')}"
SINCE_POSTING_DATE="${SINCE_POSTING_DATE:-}"
BEFORE_POSTING_DATE="${BEFORE_POSTING_DATE:-}"
SINCE_LOCKING_DATE="${SINCE_LOCKING_DATE:-}"
BEFORE_LOCKING_DATE="${BEFORE_LOCKING_DATE:-}"
VALIDATION_STATUS="${VALIDATION_STATUS:-PENDING}"
SINCE_COMMISSION_ID="${SINCE_COMMISSION_ID:-}"
ADVERTISER_IDS="${ADVERTISER_IDS:-}"
WEBSITE_IDS="${WEBSITE_IDS:-}"

if [ -z "$CJ_PAT" ]; then
  echo "Missing CJ_PAT env var." >&2
  exit 1
fi
if [ -z "$FOR_PUBLISHERS" ]; then
  echo "Missing FOR_PUBLISHERS (publisher id) env var." >&2
  exit 1
fi

payload=$(python3 - <<'PY'
import json
import os

query = """query ImpactshopCjPublisherCommissions(
  $forPublishers: [String!]!
  $sinceEventDate: String
  $beforeEventDate: String
  $sincePostingDate: String
  $beforePostingDate: String
  $sinceLockingDate: String
  $beforeLockingDate: String
  $validationStatuses: [ValidationStatus!]
  $sinceCommissionId: String
) {
  publisherCommissions(
    forPublishers: $forPublishers
    sinceEventDate: $sinceEventDate
    beforeEventDate: $beforeEventDate
    sincePostingDate: $sincePostingDate
    beforePostingDate: $beforePostingDate
    sinceLockingDate: $sinceLockingDate
    beforeLockingDate: $beforeLockingDate
    validationStatuses: $validationStatuses
    sinceCommissionId: $sinceCommissionId
  ) {
    count
    limit
    maxCommissionId
    payloadComplete
    records {
      commissionId
      eventDate
      postingDate
      lockingDate
      validationStatus
      advertiserId
      advertiserName
      sid
      pubCommissionAmountPubCurrency
      pubCommissionAmountUsd
      saleAmountPubCurrency
      saleAmountUsd
      orderId
      actionType
      actionStatus
    }
  }
}
"""

variables = {
    "forPublishers": [os.environ["FOR_PUBLISHERS"]],
    "sinceEventDate": os.environ["SINCE_EVENT_DATE"],
    "beforeEventDate": os.environ["BEFORE_EVENT_DATE"],
    "sincePostingDate": os.environ.get("SINCE_POSTING_DATE") or None,
    "beforePostingDate": os.environ.get("BEFORE_POSTING_DATE") or None,
    "sinceLockingDate": os.environ.get("SINCE_LOCKING_DATE") or None,
    "beforeLockingDate": os.environ.get("BEFORE_LOCKING_DATE") or None,
    "validationStatuses": [os.environ["VALIDATION_STATUS"]],
}
since_commission_id = os.environ.get("SINCE_COMMISSION_ID")
if since_commission_id:
    variables["sinceCommissionId"] = since_commission_id
advertiser_ids = os.environ.get("ADVERTISER_IDS")
if advertiser_ids:
    variables["advertiserIds"] = advertiser_ids
website_ids = os.environ.get("WEBSITE_IDS")
if website_ids:
    variables["websiteIds"] = website_ids

print(json.dumps({"query": query, "variables": variables}))
PY
)

curl -sS "$CJ_API_URL" \
  -H "Authorization: Bearer $CJ_PAT" \
  -H "Content-Type: application/json" \
  -d "$payload"
