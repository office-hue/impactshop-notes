#!/usr/bin/env python3
"""Whitelist/shops registry generator for the coupon harvester pipeline."""

import argparse
import csv
import json
import os
from dataclasses import dataclass, asdict
from pathlib import Path
from typing import Dict, Iterable, List, Optional
from urllib.parse import urlparse
from urllib.request import urlopen

ROOT = Path(__file__).resolve().parents[1]
DEFAULT_SECRETS = ROOT.parent / "tools" / "secrets" / "gmail"
DEFAULT_CONFIG = ROOT / ".codex/cron/coupon-harvester-config.json"
DEFAULT_REGISTRY = ROOT / "tools/shops_registry.json"
DEFAULT_HISTORY = ROOT / ".codex/state/gmail-history.json"


@dataclass
class RegistryEntry:
    slug: str
    domain: str
    program_id: Optional[str]
    network: str
    default_d1: Optional[str] = None
    default_cta_url: Optional[str] = None
    source: Optional[str] = None

    def to_whitelist(self) -> Dict[str, str]:
        return {"slug": self.slug, "domain": self.domain}


def slugify(value: str) -> str:
    import re

    slug = re.sub(r"[^a-z0-9]+", "-", (value or "").lower()).strip("-")
    return slug or "unknown"


def normalize_domain(text: str) -> str:
    text = (text or "").strip()
    if not text:
        return ""
    candidate = text
    if "/" in text and not text.startswith("http"):
        candidate = f"https://{text.lstrip('/')}"
    if candidate.startswith("http"):
        parsed = urlparse(candidate)
        host = parsed.netloc or parsed.path
    else:
        host = candidate
    host = host.split(":")[0]
    if host.startswith("www."):
        host = host[4:]
    return host.lower().lstrip("*.")


def read_csv(source: str) -> Iterable[Dict[str, str]]:
    data = None
    if source.startswith("http://") or source.startswith("https://"):
        with urlopen(source, timeout=20) as resp:
            data = resp.read().decode("utf-8", errors="ignore")
    else:
        path = Path(source)
        if not path.exists():
            print(f"⚠️  Feed hiányzik: {source}")
            return []
        data = path.read_text(encoding="utf-8", errors="ignore")
    rows: List[Dict[str, str]] = []
    reader = csv.DictReader(data.splitlines())
    for row in reader:
        rows.append(row)
    return rows


def extract_entry(row: Dict[str, str], network: str, source: str) -> Optional[RegistryEntry]:
    domain = normalize_domain(
        row.get("domain")
        or row.get("Domain")
        or row.get("url")
        or row.get("landing_url")
        or row.get("program_url")
    )
    if not domain:
        return None
    slug = slugify(
        row.get("slug")
        or row.get("merchant")
        or row.get("program")
        or row.get("name")
        or domain.split(".")[0]
    )
    program_id = row.get("program_id") or row.get("Program ID") or row.get("id") or row.get("merchant_id")
    default_d1 = row.get("default_d1") or row.get("ngo_slug") or row.get("d1")
    cta = row.get("cta_url") or row.get("landing_url") or row.get("url")
    return RegistryEntry(
        slug=slug,
        domain=domain,
        program_id=program_id,
        network=network,
        default_d1=default_d1,
        default_cta_url=cta,
        source=source
    )


def build_registry(dognet_feeds: List[str], cj_feeds: List[str]) -> List[RegistryEntry]:
    registry: Dict[str, RegistryEntry] = {}
    for source in dognet_feeds:
        for row in read_csv(source):
            entry = extract_entry(row, "dognet", source)
            if not entry:
                continue
            registry.setdefault(entry.domain, entry)
    for source in cj_feeds:
        for row in read_csv(source):
            entry = extract_entry(row, "cj", source)
            if not entry:
                continue
            registry.setdefault(entry.domain, entry)
    return sorted(registry.values(), key=lambda e: (e.slug, e.domain))


def update_config(config_path: Path, whitelist: List[Dict[str, str]], gmail_cfg: Dict[str, str]):
    config = {}
    if config_path.exists():
        config = json.load(config_path.open())
    config["whitelist"] = whitelist
    config.setdefault("out_dir", "tmp/coupon-harvester")
    config.setdefault("html_sources", [])
    config["gmail"] = gmail_cfg
    config_path.parent.mkdir(parents=True, exist_ok=True)
    with config_path.open("w", encoding="utf-8") as fh:
        json.dump(config, fh, indent=2, ensure_ascii=False)


def main():
    parser = argparse.ArgumentParser(description="Generate Dognet/CJ whitelist → config")
    parser.add_argument("--dognet-feed", action="append", default=[], help="Dognet CSV feed (path or URL)")
    parser.add_argument("--cj-feed", action="append", default=[], help="CJ CSV feed (path or URL)")
    parser.add_argument("--output", default=str(DEFAULT_REGISTRY))
    parser.add_argument("--config", default=str(DEFAULT_CONFIG))
    parser.add_argument("--gmail-credentials", default=str(DEFAULT_SECRETS / "credentials.json"))
    parser.add_argument("--gmail-token", default=str(DEFAULT_SECRETS / "token.json"))
    parser.add_argument("--gmail-history", default=str(DEFAULT_HISTORY))
    parser.add_argument("--gmail-query", default="(kupon OR coupon OR kedvezmény) newer_than:14d")
    parser.add_argument("--gmail-label", action="append", default=["INBOX", "CATEGORY_PROMOTIONS"])
    parser.add_argument("--max-results", type=int, default=200)
    args = parser.parse_args()

    dognet = args.dognet_feed or []
    cj = args.cj_feed or []
    if not dognet and not cj:
        print("❌ Adj meg legalább egy Dognet vagy CJ feedet", flush=True)
        return 1
    registry = build_registry(dognet, cj)
    if not registry:
        print("❌ Nem sikerült registry-t építeni – add meg a feed URL-eket")
        return 1

    output_path = Path(args.output)
    output_path.parent.mkdir(parents=True, exist_ok=True)
    payload = [asdict(entry) for entry in registry]
    output_path.write_text(json.dumps(payload, indent=2, ensure_ascii=False))

    whitelist = [entry.to_whitelist() for entry in registry]
    gmail_cfg = {
        "credentials_path": os.path.abspath(args.gmail_credentials),
        "token_path": os.path.abspath(args.gmail_token),
        "history_file": os.path.abspath(args.gmail_history),
        "query": args.gmail_query,
        "label_ids": args.gmail_label,
        "max_results": args.max_results,
        "allowed_domains": [item["domain"] for item in whitelist]
    }
    update_config(Path(args.config), whitelist, gmail_cfg)

    print(f"✅ Registry írás: {output_path} ({len(payload)} sor)")
    print(f"✅ Config frissítve: {args.config}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
