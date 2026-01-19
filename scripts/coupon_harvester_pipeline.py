#!/usr/bin/env python3
import argparse
import base64
import csv
import datetime as dt
import glob
import json
import os
import random
import re
import sys
import time
import unicodedata
from html import unescape
from pathlib import Path
from subprocess import PIPE, CalledProcessError, run
from typing import Dict, List, Tuple, Optional
from urllib.error import HTTPError, URLError
from urllib.parse import urlencode
from urllib.request import urlopen, Request

CODE_REGEX = re.compile(r"(?P<code>[A-Z0-9][A-Z0-9_-]{3,15})")
CODE_MARKER_REGEX = re.compile(
    r"(?:kuponk(?:o|ó)d|coupon\s*code|promo\s*code|kedvezm[eé]nyk[oó]d|utalv[aá]ny\s+k[oó]dja|k[oó]d)\b\s*[:\-]?\s*"
    r"(?P<code>(?=[A-Z0-9_-]*[A-Z])[A-Z0-9][A-Z0-9_-]{3,15})",
    re.IGNORECASE,
)
CODE_MARKER_RAW_REGEX = re.compile(
    r"(?:kuponk(?:o|ó)d|coupon\s*code|promo\s*code|kedvezm[eé]nyk[oó]d|utalv[aá]ny\s+k[oó]dja|k[oó]d)\b\s*[:\-]?\s*"
    r"(?P<code>[A-Za-z0-9ÁÉÍÓÖŐÚÜŰáéíóöőúüű_-]{3,20})",
    re.IGNORECASE,
)
CODE_BEFORE_MARKER_REGEX = re.compile(
    r"(?P<code>[A-Z0-9][A-Z0-9_-]{3,15})\s*"
    r"(?:kuponk(?:o|ó)d|coupon\s*code|promo\s*code|kedvezm[eé]nyk[oó]d|utalv[aá]ny\s+k[oó]dja|k[oó]d)\b",
    re.IGNORECASE,
)
DISCOUNT_REGEX = re.compile(r"(-?\s?\d{1,2}%|\d{3,5}\s?(?:ft|eur)|ingyenes\s+szallitas)", re.IGNORECASE)
DATE_REGEX = re.compile(
    r"(20\d{2}[.\-]\d{2}[.\-]\d{2}|\d{2}[./]\d{2}[./]20\d{2})"
)
PRICE_REGEX = re.compile(
    r"\b\d{1,3}(?:[ .]\d{3})*(?:,\d{1,2})?\s?(?:ft|huf|eur|€)\b",
    re.IGNORECASE,
)

CODE_STOPWORDS = {
    "KUPON", "KUPONKOD", "KOD", "CODE", "PROMO",
    "MINDEN", "KATEG", "KATEGORIA", "JANU", "FEBR", "MARC", "APR", "MAJ",
    "JUN", "JUL", "AUG", "SEP", "OKT", "NOV", "DEC", "FENY", "SHOP",
    "HASZN", "HASZNALAT", "HASZNALATAHOZ",
    "CANNOT", "AND", "THE", "WITH", "FROM", "SAVE", "DEAL",
    "OLVAS", "OLVASOK", "OLVASO", "REGISZTR", "CENA", "JELENLEG", "BLOKKOLVA",
    "APPLIK", "TART", "MEGAD", "KOLLABOR", "ALAPJ", "UTMUTATO",
    "HIBAKOD", "VONALKOD",
    "CSAK", "HOZZ", "HOZZA", "FELHASZN", "ALKALMAZ", "ADD", "IRJ", "BEIR",
    "HASZNALD", "HASZNAL", "HASZNALATA",
}


def decode_b64(data: str) -> str:
    if not data:
        return ""
    padding = '=' * (-len(data) % 4)
    try:
        raw = base64.urlsafe_b64decode(data + padding)
        return raw.decode('utf-8', errors='ignore')
    except Exception:
        return ""


class GmailClient:
    def __init__(self, cfg: dict):
        self.credentials_path = Path(cfg.get("credentials_path", ""))
        self.token_path = Path(cfg.get("token_path", ""))
        self.user_id = cfg.get("user_id", "me")
        self.base_url = cfg.get("base_url", "https://gmail.googleapis.com").rstrip("/")
        self.max_attempts = int(cfg.get("max_attempts", 4))
        self.timeout = int(cfg.get("timeout", 15))
        self.token_uri_override = cfg.get("token_uri")
        self.credentials_data = None
        self.token_data = None
        self.enabled = self.credentials_path.exists() and self.token_path.exists()

    def _load_json(self, path: Path) -> dict:
        with path.open(encoding="utf-8") as fh:
            return json.load(fh)

    def _write_json(self, path: Path, payload: dict):
        path.parent.mkdir(parents=True, exist_ok=True)
        with path.open("w", encoding="utf-8") as fh:
            json.dump(payload, fh, indent=2)

    def _client_info(self) -> dict:
        if self.credentials_data is None:
            self.credentials_data = self._load_json(self.credentials_path)
        if "installed" in self.credentials_data:
            return self.credentials_data["installed"]
        if "web" in self.credentials_data:
            return self.credentials_data["web"]
        return self.credentials_data

    @staticmethod
    def _parse_expiry(value: str) -> dt.datetime:
        if not value:
            raise ValueError("missing expiry")
        if value.endswith("Z"):
            value = value[:-1] + "+00:00"
        return dt.datetime.fromisoformat(value)

    def _token_valid(self) -> bool:
        if not self.token_data:
            return False
        expiry = self.token_data.get("expiry")
        if not expiry:
            return False
        try:
            exp_dt = self._parse_expiry(expiry)
        except ValueError:
            return False
        return exp_dt - dt.timedelta(seconds=60) > dt.datetime.now(dt.timezone.utc)

    def _refresh_token(self):
        client = self._client_info()
        if self.token_data is None:
            self.token_data = self._load_json(self.token_path)
        refresh_token = self.token_data.get("refresh_token")
        token_uri = self.token_uri_override or self.token_data.get("token_uri") or client.get("token_uri")
        if not refresh_token:
            raise RuntimeError("Gmail token.json nem tartalmaz refresh_token mezőt")
        if not token_uri:
            token_uri = "https://oauth2.googleapis.com/token"
        form = urlencode({
            "client_id": client.get("client_id"),
            "client_secret": client.get("client_secret"),
            "refresh_token": refresh_token,
            "grant_type": "refresh_token"
        }).encode("utf-8")
        req = Request(token_uri, data=form, headers={"Content-Type": "application/x-www-form-urlencoded"})
        with urlopen(req, timeout=self.timeout) as resp:
            body = json.loads(resp.read().decode("utf-8"))
        expires_in = int(body.get("expires_in", 3600))
        expiry = dt.datetime.now(dt.timezone.utc) + dt.timedelta(seconds=max(0, expires_in - 60))
        self.token_data["token"] = body.get("access_token")
        self.token_data["expiry"] = expiry.isoformat()
        self._write_json(self.token_path, self.token_data)

    def _ensure_token(self, force_refresh: bool = False):
        if self.token_data is None:
            self.token_data = self._load_json(self.token_path)
        if force_refresh or not self._token_valid():
            self._refresh_token()

    def get_access_token(self, force_refresh: bool = False) -> str:
        if not self.enabled:
            raise RuntimeError("Gmail integráció nincs engedélyezve (credentials/token hiányzik)")
        self._ensure_token(force_refresh=force_refresh)
        return self.token_data.get("token", "")

    def _request(self, endpoint: str, params: Dict[str, str] = None) -> dict:
        params = params or {}
        url = f"{self.base_url}/gmail/v1/users/{self.user_id}/{endpoint}"
        if params:
            query = urlencode(params, doseq=True)
            url = f"{url}?{query}"
        attempt = 0
        while attempt < self.max_attempts:
            attempt += 1
            token = self.get_access_token(force_refresh=attempt > 1)
            req = Request(url, headers={
                "Authorization": f"Bearer {token}",
                "Accept": "application/json"
            })
            try:
                with urlopen(req, timeout=self.timeout) as resp:
                    return json.loads(resp.read().decode("utf-8"))
            except HTTPError as exc:
                if exc.code in (401, 403):
                    # token lejárt – frissít és retry
                    self.get_access_token(force_refresh=True)
                    time.sleep(1)
                    continue
                if exc.code in (429, 500, 502, 503, 504) and attempt < self.max_attempts:
                    delay = min(2 ** attempt, 30) + random.uniform(0, 1)
                    time.sleep(delay)
                    continue
                raise
            except URLError:
                if attempt >= self.max_attempts:
                    raise
                time.sleep(min(2 ** attempt, 30))
        raise RuntimeError("Gmail API kérés többször is meghiúsult")

    def list_messages(self, params: Dict[str, str]) -> dict:
        return self._request("messages", params)

    def get_message(self, message_id: str, fmt: str = "full") -> dict:
        return self._request(f"messages/{message_id}", {"format": fmt})


def read_history_id(path: Path) -> int:
    try:
        data = json.load(path.open())
        return int(data.get("history_id", 0))
    except FileNotFoundError:
        return 0
    except Exception:
        return 0


def write_history_id(path: Path, history_id: int):
    path.parent.mkdir(parents=True, exist_ok=True)
    payload = {
        "history_id": history_id,
        "updated_at": dt.datetime.utcnow().isoformat()
    }
    path.write_text(json.dumps(payload, indent=2))


def extract_header(headers: List[dict], name: str) -> str:
    for header in headers or []:
        if header.get("name", "").lower() == name.lower():
            return header.get("value", "")
    return ""


def flatten_payload(payload: dict) -> str:
    if not payload:
        return ""
    mime = payload.get("mimeType", "").lower()
    body = payload.get("body", {})
    data = body.get("data")
    if data:
        text = decode_b64(data)
        if "html" in mime:
            text = re.sub(r"<br\s*/?>", "\n", text, flags=re.IGNORECASE)
            text = unescape(re.sub(r"<[^>]+>", " ", text))
        return text
    parts = payload.get("parts", [])
    texts = [flatten_payload(part) for part in parts]
    return "\n".join([t for t in texts if t])


def normalize_text(text: str) -> str:
    if not text:
        return ""
    if "<" in text and ">" in text:
        text = re.sub(r"<br\s*/?>", "\n", text, flags=re.IGNORECASE)
        text = unescape(re.sub(r"<[^>]+>", " ", text))
    return text


def extract_context_window(text: str, start: int, end: int, window: int = 200) -> str:
    if not text:
        return ""
    lo = max(0, start - window)
    hi = min(len(text), end + window)
    snippet = text[lo:hi]
    snippet = re.sub(r"\s+", " ", snippet).strip()
    return snippet[:500]


def extract_coupon_context_fallback(text: str, code: str, window: int = 200) -> str:
    if not text or not code:
        return ""
    normalized = normalize_text(text)
    haystack = normalized.upper()
    needle = code.upper()
    idx = haystack.find(needle)
    if idx == -1:
        snippet = re.sub(r"\s+", " ", normalized).strip()
        return snippet[:500]
    return extract_context_window(normalized, idx, idx + len(needle), window)


def fill_coupon_context(items: List[dict], text: str) -> None:
    if not items:
        return
    for item in items:
        if not item.get("coupon_code"):
            continue
        if item.get("description"):
            continue
        context = extract_coupon_context_fallback(text, item.get("coupon_code", ""))
        if context:
            item["description"] = context

def parse_price_value(raw: str) -> Optional[float]:
    if not raw:
        return None
    cleaned = raw.lower().replace("€", "eur").replace("huf", "ft")
    cleaned = re.sub(r"[^0-9,\. ]", "", cleaned).strip()
    if not cleaned:
        return None
    cleaned = cleaned.replace(" ", "")
    if cleaned.count(",") == 1 and cleaned.count(".") == 0:
        cleaned = cleaned.replace(",", ".")
    try:
        return float(cleaned)
    except ValueError:
        return None

def extract_price_pair(text: str) -> Tuple[str, str]:
    if not text:
        return ("", "")
    matches = [m.group(0) for m in PRICE_REGEX.finditer(text)]
    if len(matches) < 2:
        return ("", "")
    priced = []
    for raw in matches:
        value = parse_price_value(raw)
        if value is None:
            continue
        priced.append((value, raw))
    if len(priced) < 2:
        return ("", "")
    priced.sort(key=lambda item: item[0])
    new_price = priced[0][1]
    old_price = priced[-1][1]
    return (old_price, new_price)


def normalize_code_token(token: str) -> str:
    if not token:
        return ""
    stripped = unicodedata.normalize("NFKD", token)
    ascii_only = stripped.encode("ascii", "ignore").decode("ascii")
    cleaned = re.sub(r"[^A-Z0-9_-]", "", ascii_only.upper())
    return cleaned


def extract_marker_codes_raw(text: str) -> List[dict]:
    codes = []
    for match in CODE_MARKER_RAW_REGEX.finditer(text):
        raw_code = match.group("code")
        normalized = normalize_code_token(raw_code)
        if normalized:
            codes.append({
                "code": normalized,
                "start": match.start(),
                "end": match.end(),
                "marker": match.group(0),
            })
    return codes


def extract_image_urls(html: str, limit: int = 5) -> List[str]:
    if not html:
        return []
    urls = []
    for match in re.finditer(r"""<img[^>]+src=["']([^"']+)["']""", html, re.IGNORECASE):
        url = match.group(1)
        if not url or url.startswith("data:"):
            continue
        if url.startswith("//"):
            url = f"https:{url}"
        if not (url.startswith("http://") or url.startswith("https://")):
            continue
        lower_url = url.lower()
        if any(token in lower_url for token in ("favicon", "logo", "icon", "sprite")):
            continue
        urls.append(url)
    if not urls:
        return []
    promo_tokens = ("banner", "promo", "akcio", "akció", "sale", "deal", "discount", "hero")
    urls.sort(key=lambda u: 0 if any(t in u.lower() for t in promo_tokens) else 1)
    return urls[:limit]


def extract_html_payload(payload: dict) -> str:
    if not payload:
        return ""
    mime = payload.get("mimeType", "").lower()
    body = payload.get("body", {})
    data = body.get("data")
    if data and "html" in mime:
        return decode_b64(data)
    parts = payload.get("parts", [])
    html_parts = [extract_html_payload(part) for part in parts]
    return "\n".join([h for h in html_parts if h])

def extract_html_coupon_codes(raw_html: str) -> List[str]:
    if not raw_html:
        return []
    codes = []
    pattern = re.compile(
        r"""data-(?:copy-value|clipboard-text|coupon-code|couponcode|coupon|promo-code|promocode|code)\s*=\s*["']([^"']{3,30})["']""",
        re.IGNORECASE,
    )
    for match in pattern.finditer(raw_html):
        raw_code = match.group(1)
        normalized = normalize_code_token(raw_code)
        if normalized:
            codes.append(normalized)
    if not codes:
        return []
    return list(dict.fromkeys(codes))


def run_ocr_on_images(
    image_urls: List[str],
    ocr_cfg: dict,
    stats: dict,
    max_images_override: Optional[int] = None,
) -> str:
    if not ocr_cfg or not ocr_cfg.get("enabled"):
        return ""
    provider = ocr_cfg.get("provider", "google")
    if provider == "google" and not os.environ.get("GOOGLE_APPLICATION_CREDENTIALS"):
        print("⚠️  OCR kihagyva: GOOGLE_APPLICATION_CREDENTIALS nincs beállítva.", file=sys.stderr)
        return ""
    max_images = int(max_images_override or ocr_cfg.get("max_images", 2))
    timeout = int(ocr_cfg.get("timeout_sec", 20))
    language_hint = ocr_cfg.get("language_hint", "hu")
    ocr_texts = []
    for image_url in image_urls[:max_images]:
        if not image_url:
            continue
        if image_url.startswith(("tmp/", "fixtures/", "./", "/")) and not Path(image_url).exists():
            stats["ocr_images_failed"] += 1
            print(f"⚠️  OCR kihagyva, hiányzó kép: {image_url}", file=sys.stderr)
            continue
        stats["ocr_images_checked"] += 1
        try:
            result = run(
                [
                    "npx",
                    "tsx",
                    "tools/vision/banner-detector.ts",
                    "--image",
                    image_url,
                    "--provider",
                    provider,
                    "--language-hint",
                    language_hint,
                    "--json",
                ],
                check=True,
                stdout=PIPE,
                stderr=PIPE,
                timeout=timeout,
                text=True,
            )
            payload = json.loads(result.stdout)
            detected = payload.get("detectedText", "")
            if detected:
                ocr_texts.append(detected)
        except (CalledProcessError, json.JSONDecodeError, TimeoutError) as exc:
            stats["ocr_images_failed"] += 1
            print(f"⚠️  OCR hiba ({image_url}): {exc}", file=sys.stderr)
            continue
    return " ".join(ocr_texts)


def slugify(value: str) -> str:
    slug = re.sub(r"[^a-z0-9]+", "-", value.lower())
    return slug.strip("-") or "unknown-shop"


def extract_domain(sender: str) -> str:
    match = re.search(r"@([^>]+)", sender or "")
    if match:
        return match.group(1).lower()
    sender = (sender or "").strip().lower()
    if " " in sender:
        sender = sender.split()[-1]
    return sender.strip("<>")


def map_domain(domain: str, whitelist: Dict[str, str]) -> Tuple[str, str]:
    for entry, slug in whitelist.items():
        if domain.endswith(entry):
            return slug, entry
    fallback = slugify(domain.split(".")[0])
    return fallback, domain


def is_probable_coupon(code: str, subject: str, shop_slug: str, require_digit: bool) -> bool:
    if not code:
        return False
    if require_digit and not any(ch.isdigit() for ch in code):
        return False
    cleaned = code.replace("-", "")
    if cleaned.isdigit():
        return False
    if cleaned.endswith("IG") and cleaned[:-2].isdigit():
        return False
    if len(code) > 12:
        return False
    if code in CODE_STOPWORDS:
        return False
    if shop_slug and code in shop_slug.replace("-", "").upper():
        return False
    if subject:
        subject_upper = subject.upper()
        if code in subject_upper and code.isalpha() and len(code) <= 6:
            return False
    return True

def marker_context_ok(text_upper: str, marker_text: str, start: int, end: int) -> bool:
    if not text_upper:
        return False
    marker_ascii = unicodedata.normalize("NFKD", marker_text).encode("ascii", "ignore").decode("ascii")
    if re.search(r"(kupon|coupon|promo|kedvezm|utalv)", marker_ascii, re.IGNORECASE):
        return True
    window_start = max(0, start - 40)
    window_end = min(len(text_upper), end + 40)
    window = text_upper[window_start:window_end]
    window_ascii = unicodedata.normalize("NFKD", window).encode("ascii", "ignore").decode("ascii").upper()
    return bool(re.search(
        r"(KUPON|KEDVEZM|UTALV|AKCIO|PROMO|COUPON|INGYENES|AJANDEK|FELHASZNAL|BEIR|ERVENY|KOSAR)",
        window_ascii,
    ))


def build_coupon_entries(
    codes: List[str],
    subject: str,
    sender: str,
    whitelist: Dict[str, str],
    discount_label: str,
    expires_at: str,
    source_type: str,
    description_map: Optional[Dict[str, str]] = None,
    price_map: Optional[Dict[str, Tuple[str, str]]] = None,
) -> List[dict]:
    domain = extract_domain(sender)
    slug, mapped_domain = map_domain(domain, whitelist)
    coupons = []
    for code in codes:
        description = ""
        old_price = ""
        new_price = ""
        if description_map:
            description = description_map.get(code, "")
        if price_map:
            old_price, new_price = price_map.get(code, ("", ""))
        coupons.append({
            "shop_slug": slug,
            "shop_name": slug.replace("-", " ").title(),
            "logo_url": f"https://cdn.impactshop.test/logos/{slug}.png",
            "coupon_code": code,
            "discount_label": discount_label or "",
            "title": subject[:140] if subject else f"Kupon {slug}",
            "description": description or "",
            "cta_url": f"https://{mapped_domain}",
            "starts_at": dt.datetime.utcnow().isoformat(),
            "expires_at": expires_at,
            "old_price": old_price,
            "new_price": new_price,
            "coupon_type": "coupon",
            "priority": 5,
            "source_type": source_type,
            "source_ref": sender
        })
    return coupons


def extract_coupon(
    text: str,
    subject: str,
    sender: str,
    whitelist: Dict[str, str],
    require_marker: bool = False,
    require_digit_markers: bool = False,
    allow_code_before_marker: bool = True,
    source_type: str = "gmail",
):
    if not text:
        return []
    text = normalize_text(text)
    domain = extract_domain(sender)
    slug, mapped_domain = map_domain(domain, whitelist)
    text_upper = text.upper()
    text_upper = re.sub(DATE_REGEX, " ", text_upper)
    text_upper = re.sub(r"\b\d{1,2}\s*-?\s*IG\b", " ", text_upper)
    marker_candidates = [{
        "code": m.group("code"),
        "start": m.start(),
        "end": m.end(),
        "marker": m.group(0),
    } for m in CODE_MARKER_REGEX.finditer(text_upper)]
    if not marker_candidates:
        marker_candidates = extract_marker_codes_raw(text)
    extra_candidates = []
    if allow_code_before_marker:
        extra_candidates = [{
            "code": m.group("code"),
            "start": m.start(),
            "end": m.end(),
            "marker": m.group(0),
        } for m in CODE_BEFORE_MARKER_REGEX.finditer(text_upper)]
    if extra_candidates:
        marker_candidates.extend(extra_candidates)
    if marker_candidates:
        context_map = {}
        price_map = {}
        for cand in marker_candidates:
            code = cand["code"]
            if code in context_map:
                continue
            context = extract_context_window(text, cand["start"], cand["end"])
            context_map[code] = context
            price_map[code] = extract_price_pair(context)
        codes = [
            cand["code"]
            for cand in marker_candidates
            if is_probable_coupon(cand["code"], subject, slug, require_digit_markers)
            and marker_context_ok(text_upper, cand["marker"], cand["start"], cand["end"])
        ]
        if codes:
            codes = list(dict.fromkeys(codes))
        else:
            context_map = {}
            price_map = {}
    elif require_marker:
        codes = []
    else:
        codes = [m.group("code") for m in CODE_REGEX.finditer(text_upper)]
        codes = [code for code in codes if is_probable_coupon(code, subject, slug, True)]
    if not codes:
        return []
    if len(codes) > 5:
        codes = codes[:5]
    discount = DISCOUNT_REGEX.search(text)
    expiry_match = DATE_REGEX.search(text)
    expires_at = ""
    if expiry_match:
        raw = expiry_match.group(1).replace(".", "-")
        parts = raw.split("-")
        if len(parts[0]) == 2:
            day, month, year = raw.split("-")
            expires_at = f"{year}-{month}-{day}"
        elif len(parts[0]) == 4:
            expires_at = raw
    discount_label = discount.group(1).replace(" ", "") if discount else ""
    if discount_label.endswith("%"):
        try:
            pct = float(discount_label.strip("%").replace(",", "."))
            if pct <= 1:
                discount_label = ""
        except ValueError:
            pass
    return build_coupon_entries(
        codes,
        subject,
        sender,
        whitelist,
        discount_label,
        expires_at,
        source_type,
        context_map if marker_candidates else None,
        price_map if marker_candidates else None,
    )


def detect_sale_signal(text: str, raw_html: str) -> bool:
    if not text and not raw_html:
        return False
    html_lower = (raw_html or "").lower()
    html_signal = (
        bool(re.search(r"<\s*(del|s)\b", html_lower))
        or "old-price" in html_lower
        or "price-old" in html_lower
        or "was-price" in html_lower
        or "strike" in html_lower
    )
    price_tokens = PRICE_REGEX.findall(text or "")
    percent_signal = re.search(r"\b\d{1,2}\s?%\b", text or "")
    keyword_signal = re.search(
        r"\b(akci[oó]|le[aá]raz|kedvezm[eé]ny|arengedm[eé]ny|sale)\b",
        text or "",
        re.IGNORECASE,
    )
    if html_signal and price_tokens:
        return True
    if percent_signal:
        return True
    if len(price_tokens) >= 2:
        return True
    if keyword_signal and price_tokens:
        return True
    return False


def extract_sale_event(text: str, subject: str, sender: str, whitelist: Dict[str, str], raw_html: str = "") -> List[dict]:
    if not text:
        return []
    text = normalize_text(text)
    domain = extract_domain(sender)
    slug, mapped_domain = map_domain(domain, whitelist)
    discount = DISCOUNT_REGEX.search(text)
    old_price, new_price = extract_price_pair(text)
    if not detect_sale_signal(text, raw_html):
        return []
    discount_label = discount.group(1).replace(" ", "") if discount else ""
    if discount_label.endswith("%"):
        try:
            pct = float(discount_label.strip("%").replace(",", "."))
            if pct <= 1:
                discount_label = ""
        except ValueError:
            pass
    return [{
        "shop_slug": slug,
        "shop_name": slug.replace("-", " ").title(),
        "logo_url": f"https://cdn.impactshop.test/logos/{slug}.png",
        "coupon_code": "",
        "discount_label": discount_label or "",
        "title": subject[:140] if subject else f"Akció {slug}",
        "cta_url": f"https://{mapped_domain}",
        "starts_at": dt.datetime.utcnow().isoformat(),
        "expires_at": "",
        "old_price": old_price,
        "new_price": new_price,
        "coupon_type": "sale_event",
        "priority": 4,
        "source_type": "gmail",
        "source_ref": sender
    }]


def read_file(path: Path) -> str:
    return path.read_text(encoding="utf-8", errors="ignore")


def fetch_url(url: str) -> str:
    req = Request(url, headers={"User-Agent": "ImpactShopCouponHarvester/1.0"})
    with urlopen(req, timeout=15) as resp:
        return resp.read().decode("utf-8", errors="ignore")


def process_gmail_fixtures(cfg: dict, whitelist: Dict[str, str], stats: dict, ocr_cfg: dict) -> List[dict]:
    directory = cfg.get("gmail_fixture_dir")
    if not directory:
        return []
    coupons: List[dict] = []
    for path in sorted(glob.glob(os.path.join(directory, "*.json"))):
        data = json.load(open(path, encoding="utf-8"))
        html = data.get("html") or ""
        text = html or data.get("text") or ""
        sender = data.get("from", "")
        subject = data.get("subject", "")
        stats["gmail_messages_checked"] += 1
        stats["gmail_messages_matched"] += 1
        gmail_max_images = int(ocr_cfg.get("gmail_max_images", ocr_cfg.get("max_images", 2)))
        ocr_text = run_ocr_on_images(extract_image_urls(html), ocr_cfg, stats, gmail_max_images)
        combined = " ".join([subject, text, ocr_text]).strip()
        found = extract_coupon(combined, subject, sender, whitelist, True, False, True, "gmail")
        if not found:
            found = extract_sale_event(combined, subject, sender, whitelist, html)
        fill_coupon_context(found, combined)
        coupons.extend(found)
    return coupons


def process_html_sources(
    cfg: dict,
    whitelist: Dict[str, str],
    slug_domains: Dict[str, str],
    stats: dict,
    ocr_cfg: dict,
) -> List[dict]:
    sources = cfg.get("html_sources", [])
    coupons: List[dict] = []
    for src in sources:
        slug = src.get("slug")
        content = ""
        if src.get("type") == "url" and src.get("url"):
            try:
                content = fetch_url(src["url"])
            except Exception as exc:
                print(f"⚠️  Nem sikerült letölteni {src['url']}: {exc}", file=sys.stderr)
                continue
        elif src.get("type") == "file" and src.get("path"):
            try:
                content = read_file(Path(src["path"]))
            except FileNotFoundError:
                print(f"⚠️  Hiányzó HTML fixture: {src['path']}", file=sys.stderr)
                continue
        else:
            continue
        stats["html_sources_scanned"] += 1
        domain = slug_domains.get(slug or "", f"{slug or 'impactshop'}.test")
        sender = f"{slug or 'web'}@{domain}"
        subject = src.get("title", f"Kupon {slug}")
        ocr_text = run_ocr_on_images(extract_image_urls(content), ocr_cfg, stats)
        combined = " ".join([content, ocr_text]).strip()
        found = []
        html_codes = extract_html_coupon_codes(content)
        if html_codes:
            html_codes = [
                code for code in html_codes
                if is_probable_coupon(code, subject, slug or "", False)
            ]
            if html_codes:
                found.extend(build_coupon_entries(
                    html_codes,
                    subject,
                    sender,
                    whitelist,
                    "",
                    "",
                    "html",
                ))
        if not found:
            found = extract_coupon(combined, subject, sender, whitelist, True, False, False, "html")
        if not found:
            found = extract_sale_event(combined, subject, sender, whitelist, content)
        coupons.extend(found)
    return coupons


def process_ocr_sources(
    cfg: dict,
    whitelist: Dict[str, str],
    slug_domains: Dict[str, str],
    stats: dict,
    ocr_cfg: dict,
) -> List[dict]:
    sources = cfg.get("ocr_sources", [])
    coupons: List[dict] = []
    for src in sources:
        image = src.get("image")
        slug = src.get("slug") or "promo"
        if not image:
            continue
        domain = slug_domains.get(slug, f"{slug}.test")
        sender = f"{slug}@{domain}"
        subject = src.get("title", f"Akció {slug}")
        ocr_text = run_ocr_on_images([image], ocr_cfg, stats)
        if not ocr_text:
            continue
        found = extract_coupon(ocr_text, subject, sender, whitelist, True, False, False, "ocr")
        if not found:
            found = extract_sale_event(ocr_text, subject, sender, whitelist, "")
        cleaned = []
        for item in found:
            code = (item.get("coupon_code") or "").strip()
            if not code:
                cleaned.append(item)
                continue
            if len(code) < 4 or len(code) > 12:
                continue
            if re.search(r"(?:0123|1234|2345|3456|4567|5678|6789|7890)", code):
                continue
            if re.match(r"^\\d{4,}[A-Z]", code):
                continue
            cleaned.append(item)
        for item in cleaned:
            item["source_type"] = "ocr"
        coupons.extend(cleaned)
    return coupons


def process_gmail_api(cfg: dict, whitelist: Dict[str, str], stats: dict, ocr_cfg: dict) -> List[dict]:
    gmail_cfg = cfg.get("gmail") or cfg.get("gmail_api") or {}
    if not gmail_cfg:
        return []
    client = GmailClient(gmail_cfg)
    if not client.enabled:
        print("⚠️  Gmail credentials/token nem található, fixture mód marad.", file=sys.stderr)
        return []
    history_path = Path(gmail_cfg.get("history_file", ".codex/state/gmail-history.json"))
    last_history = read_history_id(history_path) if history_path else 0
    max_history = last_history
    query = gmail_cfg.get("query", "(kupon OR coupon OR kedvezmény) newer_than:14d")
    label_ids = gmail_cfg.get("label_ids", [])
    allowed_domains = [d.lower() for d in gmail_cfg.get("allowed_domains", []) if d]
    max_results = int(gmail_cfg.get("max_results", 100))
    coupons: List[dict] = []
    page_token = None
    fetched = 0
    while fetched < max_results:
        page_size = min(100, max_results - fetched)
        params: Dict[str, object] = {"q": query, "maxResults": page_size}
        if label_ids:
            params["labelIds"] = label_ids
        if page_token:
            params["pageToken"] = page_token
        try:
            resp = client.list_messages(params)
        except Exception as exc:
            stats["gmail_errors"] += 1
            print(f"❌ Gmail üzenetlista hiba: {exc}", file=sys.stderr)
            break
        messages = resp.get("messages", [])
        page_token = resp.get("nextPageToken")
        if not messages:
            break
        fetched += len(messages)
        for msg in messages:
            msg_id = msg.get("id")
            if not msg_id:
                continue
            try:
                message = client.get_message(msg_id)
            except Exception as exc:
                stats["gmail_errors"] += 1
                print(f"❌ Gmail üzenet letöltés hiba ({msg_id}): {exc}", file=sys.stderr)
                continue
            stats["gmail_messages_checked"] += 1
            history_id = int(message.get("historyId", 0) or 0)
            if last_history and history_id and history_id <= last_history:
                continue
            max_history = max(max_history, history_id)
            headers = message.get("payload", {}).get("headers", [])
            sender = extract_header(headers, "From")
            if allowed_domains:
                domain = extract_domain(sender)
                if not any(domain.endswith(dom) for dom in allowed_domains):
                    continue
            snippet = message.get("snippet", "")
            subject = extract_header(headers, "Subject") or snippet
            payload = message.get("payload") or {}
            body = flatten_payload(payload) or snippet
            if not body:
                continue
            html = extract_html_payload(payload)
            gmail_max_images = int(ocr_cfg.get("gmail_max_images", ocr_cfg.get("max_images", 2)))
            ocr_text = run_ocr_on_images(extract_image_urls(html), ocr_cfg, stats, gmail_max_images)
            combined = " ".join([subject, snippet, body, ocr_text]).strip()
            stats["gmail_messages_matched"] += 1
            found = extract_coupon(combined, subject, sender, whitelist, False, False, True, "gmail")
            if found:
                fill_coupon_context(found, combined)
                stats["gmail_coupons"] += len(found)
                coupons.extend(found)
            else:
                coupons.extend(extract_sale_event(combined, subject, sender, whitelist, html))
        if not page_token:
            break
    if history_path and max_history and max_history > last_history:
        write_history_id(history_path, max_history)
    return coupons


def dedup_coupons(items: List[dict]) -> List[dict]:
    seen = set()
    unique = []
    for item in items:
        code = (item.get("coupon_code") or "").strip()
        if code:
            key = (item.get("shop_slug"), code)
        else:
            key = (item.get("shop_slug"), item.get("title"), item.get("cta_url"))
        if key in seen:
            continue
        seen.add(key)
        unique.append(item)
    return unique


def write_csv(path: Path, rows: List[dict]):
    columns = [
        "shop_slug","shop_name","logo_url","coupon_code","discount_label","title",
        "cta_url","starts_at","expires_at","old_price","new_price","coupon_type","priority"
    ]
    with path.open("w", newline="", encoding="utf-8") as fh:
        writer = csv.DictWriter(fh, fieldnames=columns)
        writer.writeheader()
        for row in rows:
            writer.writerow({col: row.get(col, "") for col in columns})

def write_shops_csv(path: Path, rows: List[dict]):
    columns = ["shop_slug", "shop_name", "logo_url", "cta_url"]
    with path.open("w", newline="", encoding="utf-8") as fh:
        writer = csv.DictWriter(fh, fieldnames=columns)
        writer.writeheader()
        for row in rows:
            writer.writerow({col: row.get(col, "") for col in columns})


def to_normalized_coupon(record: dict) -> dict:
    slug = (record.get("shop_slug") or "unknown").lower()
    title = record.get("title") or record.get("discount_label") or ""
    coupon_code = record.get("coupon_code") or ""
    expires_at = record.get("expires_at") or ""
    seed_parts = [slug, coupon_code, expires_at, title]
    reliability_seed = "|".join([part for part in seed_parts if part])
    starts_at = record.get("starts_at") or dt.datetime.utcnow().isoformat()
    return {
        "source": "gmail_structured",
        "shop_slug": slug,
        "shop_name": record.get("shop_name") or slug,
        "type": "coupon_code" if coupon_code else "sale_event",
        "coupon_code": coupon_code or None,
        "discount_label": record.get("discount_label") or None,
        "title": title or None,
        "description": record.get("description") or None,
        "cta_url": record.get("cta_url") or None,
        "fillout_url": record.get("fillout_url") or None,
        "starts_at": starts_at,
        "expires_at": expires_at or None,
        "old_price": record.get("old_price") or None,
        "new_price": record.get("new_price") or None,
        "scraped_at": starts_at,
        "source_variant": record.get("source_type") or "gmail-harvester",
        "validation_status": (record.get("validation_status") or "untested").lower(),
        "reliability_seed": reliability_seed,
        "raw": record,
      }


def write_normalized_json(path: Path, coupons: List[dict]):
    normalized = [to_normalized_coupon(coupon) for coupon in coupons]
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(normalized, indent=2, ensure_ascii=False))

def load_cj_domains(path: Path) -> List[Tuple[str, str]]:
    if not path.exists():
        return []
    rows = []
    with path.open("r", encoding="utf-8") as fh:
        reader = csv.DictReader(fh)
        for row in reader:
            slug = (row.get("slug") or "").strip().lower()
            domain = (row.get("domain") or "").strip().lower()
            if not slug or not domain:
                continue
            rows.append((domain, slug))
    return rows


def parse_args():
    parser = argparse.ArgumentParser(description="ImpactShop coupon harvester pipeline")
    parser.add_argument("--config", required=True)
    parser.add_argument("--out-dir", required=True)
    parser.add_argument("--dry-run", action="store_true", default=False)
    parser.add_argument("--log-json", help="Path to write JSON summary")
    parser.add_argument("--log-text", help="Path to append human log line")
    parser.add_argument("--json-out", help="Write normalized Gmail JSON compatible with ai-agent ingest")
    return parser.parse_args()


def main():
    args = parse_args()
    config = json.load(open(args.config, encoding="utf-8"))
    whitelist = {entry.get("domain", "").lower(): entry.get("slug", "")
                 for entry in config.get("whitelist", []) if entry.get("domain")}
    cj_domains = load_cj_domains(Path("tools/cj_shops.csv"))
    for domain, slug in cj_domains:
        whitelist.setdefault(domain, slug)
    gmail_cfg = config.get("gmail", {}) or {}
    allowed_domains = gmail_cfg.get("allowed_domains") or []
    if allowed_domains:
        for domain, _slug in cj_domains:
            if domain not in allowed_domains:
                allowed_domains.append(domain)
        gmail_cfg["allowed_domains"] = allowed_domains
        config["gmail"] = gmail_cfg
    slug_domains = {}
    for domain, slug in whitelist.items():
        if slug and slug not in slug_domains:
            slug_domains[slug] = domain
    ocr_cfg = config.get("ocr", {}) or {}
    stats = {
        "gmail_messages_checked": 0,
        "gmail_messages_matched": 0,
        "gmail_coupons": 0,
        "gmail_errors": 0,
        "html_sources_scanned": 0,
        "ocr_images_checked": 0,
        "ocr_images_failed": 0,
    }
    coupons = []
    coupons.extend(process_gmail_api(config, whitelist, stats, ocr_cfg))
    coupons.extend(process_gmail_fixtures(config, whitelist, stats, ocr_cfg))
    coupons.extend(process_html_sources(config, whitelist, slug_domains, stats, ocr_cfg))
    coupons.extend(process_ocr_sources(config, whitelist, slug_domains, stats, ocr_cfg))
    coupons = dedup_coupons(coupons)

    shop_rows = []
    shop_seen = set()
    for coupon in coupons:
        slug = (coupon.get("shop_slug") or "").strip().lower()
        if not slug:
            continue
        cta_url = (coupon.get("cta_url") or "").strip()
        key = (slug, cta_url)
        if key in shop_seen:
            continue
        shop_seen.add(key)
        shop_rows.append({
            "shop_slug": slug,
            "shop_name": coupon.get("shop_name") or slug,
            "logo_url": coupon.get("logo_url") or "",
            "coupon_code": "",
            "discount_label": "",
            "title": "",
            "cta_url": cta_url,
            "starts_at": "",
            "expires_at": "",
            "coupon_type": "",
            "priority": ""
        })

    out_dir = Path(args.out_dir)
    out_dir.mkdir(parents=True, exist_ok=True)
    ts = dt.datetime.utcnow().strftime("%Y-%m-%dT%H%M%S")
    csv_path = out_dir / f"manual_coupons_draft-{ts}.csv"
    shops_path = out_dir / f"shops_manual_draft-{ts}.csv"
    # always write outputs; shops list is unique per slug/url for mapping/review
    write_csv(csv_path, coupons)
    write_shops_csv(shops_path, shop_rows)

    summary = {
        "timestamp": ts,
        "dry_run": args.dry_run,
        "config": os.path.abspath(args.config),
        "out_dir": str(out_dir),
        "coupon_count": len(coupons),
        "csv_path": str(csv_path),
        "shops_path": str(shops_path),
        "stats": stats
    }
    if args.json_out:
        write_normalized_json(Path(args.json_out), coupons)
    if args.log_json:
        Path(args.log_json).write_text(json.dumps(summary, indent=2))
    if args.log_text:
        with open(args.log_text, "a", encoding="utf-8") as fh:
            fh.write(f"{ts} | coupons={len(coupons)} | dry_run={args.dry_run}\n")
    print(json.dumps(summary))


if __name__ == "__main__":
    main()
