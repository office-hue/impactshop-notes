#!/usr/bin/env python3
"""Generate latency trend chart from .codex/deploy-log.txt."""

import argparse
import csv
import sys
from datetime import datetime
from pathlib import Path
from typing import List

try:
    import matplotlib
    matplotlib.use("Agg")
    import matplotlib.pyplot as plt
except Exception as exc:  # pragma: no cover
    print(f"⚠️  matplotlib unavailable: {exc}")
    sys.exit(0)

DEFAULT_LOG = Path(".codex/deploy-log.txt")


class Entry:
    __slots__ = ("timestamp", "env", "status", "warns", "max_latency", "ok", "rest_fail", "exit_code")

    def __init__(self, timestamp: datetime, env: str, status: str, warns: int, max_latency: float,
                 ok: int, rest_fail: int, exit_code: int) -> None:
        self.timestamp = timestamp
        self.env = env
        self.status = status
        self.warns = warns
        self.max_latency = max_latency
        self.ok = ok
        self.rest_fail = rest_fail
        self.exit_code = exit_code


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="ImpactShop preflight latency chart generator")
    parser.add_argument("--log", default=str(DEFAULT_LOG), help="CSV input file")
    parser.add_argument("--env", default="staging", help="Environment filter (default: staging)")
    parser.add_argument("--last", type=int, default=200, help="Number of most recent entries to plot")
    parser.add_argument("--out", default=".codex/deploy-latency.png", help="Output PNG path")
    parser.add_argument("--csv", default=None, help="Optional CSV export of filtered data")
    parser.add_argument("--warn", type=float, default=None, help="Warn threshold (seconds)")
    parser.add_argument("--fail", type=float, default=None, help="Fail threshold (seconds)")
    parser.add_argument("--ma", type=int, default=10, help="Moving average window (0 disables)")
    parser.add_argument("--no-legend", action="store_true", help="Hide legend")
    return parser.parse_args()


def read_entries(path: Path, env: str) -> List[Entry]:
    entries: List[Entry] = []
    if not path.exists():
        print(f"⚠️  No log found at {path}")
        return entries

    with path.open() as fh:
        reader = csv.DictReader(fh)
        for row in reader:
            if row.get("env") != env:
                continue
            ts_raw = row.get("timestamp")
            lat_raw = row.get("max_latency_s")
            if not ts_raw or not lat_raw:
                continue
            try:
                ts = datetime.strptime(ts_raw, "%Y-%m-%d %H:%M:%S")
                latency = float(lat_raw)
            except Exception:
                continue
            try:
                warns = int(row.get("warns", 0))
            except Exception:
                warns = 0
            try:
                ok = int(row.get("endpoints_ok", 0))
            except Exception:
                ok = 0
            try:
                rest_fail = int(row.get("rest_fail_count", 0))
            except Exception:
                rest_fail = 0
            try:
                exit_code = int(row.get("exit_code", 0))
            except Exception:
                exit_code = 0
            status = row.get("status", "") or "PASS"
            entries.append(Entry(ts, env, status, warns, latency, ok, rest_fail, exit_code))
    return entries


def moving_average(values: List[float], window: int) -> List[float]:
    if window <= 1 or len(values) < window:
        return []
    averages = []
    for idx in range(window - 1, len(values)):
        window_vals = values[idx - window + 1: idx + 1]
        averages.append(sum(window_vals) / window)
    return averages


def ensure_parent(path: Path) -> None:
    if path.parent and not path.parent.exists():
        path.parent.mkdir(parents=True, exist_ok=True)


def plot(entries: List[Entry], out_path: Path, args: argparse.Namespace) -> None:
    if not entries:
        ensure_parent(out_path)
        plt.figure(figsize=(12, 5))
        plt.text(0.5, 0.5, "No data", ha="center", va="center", fontsize=16)
        plt.axis("off")
        plt.savefig(out_path)
        plt.close()
        print(f"⚠️  No data for env={args.env}; blank chart saved -> {out_path}")
        return

    entries.sort(key=lambda e: e.timestamp)
    if args.last > 0 and len(entries) > args.last:
        entries = entries[-args.last:]

    times = [e.timestamp for e in entries]
    latencies = [e.max_latency for e in entries]
    statuses = [e.status for e in entries]

    ensure_parent(out_path)
    plt.figure(figsize=(12, 5))
    ax = plt.gca()

    markers = {
        "PASS": ("o", "#2ca02c", True),
        "PASS_WITH_WARN": ("o", "#ff7f0e", False),
        "FAIL": ("x", "#d62728", True),
    }

    for ts, lat, status in zip(times, latencies, statuses):
        marker, color, filled = markers.get(status, ("o", "#1f77b4", True))
        ax.plot(ts, lat, marker=marker, color=color,
                markerfacecolor=color if filled else "none", linestyle="None", markersize=6)

    warn_line = args.warn if args.warn and args.warn > 0 else None
    fail_line = args.fail if args.fail and args.fail > 0 else None

    if warn_line is not None:
        ax.axhline(warn_line, color="gold", linestyle="--", linewidth=1, label=f"WARN {warn_line:.1f}s")
    if fail_line is not None:
        ax.axhline(fail_line, color="red", linestyle=":", linewidth=1, label=f"FAIL {fail_line:.1f}s")

    if args.ma and args.ma > 1 and len(latencies) >= args.ma:
        ma_vals = moving_average(latencies, args.ma)
        if ma_vals:
            ma_times = times[args.ma - 1:]
            ax.plot(ma_times, ma_vals, color="#1f77b4", linestyle="-", linewidth=1.5,
                    label=f"MA({args.ma})")

    ax.set_title(f"ImpactShop Preflight Max Latency — {args.env}")
    ax.set_xlabel("Timestamp")
    ax.set_ylabel("Max latency (s)")
    plt.xticks(rotation=45, ha="right")

    if not args.no_legend:
        ax.legend(loc="upper left")

    plt.tight_layout()
    plt.savefig(out_path)
    plt.close()
    print(f"✅ Latency chart saved -> {out_path}")


def export(entries: List[Entry], path: Path) -> None:
    ensure_parent(path)
    with path.open("w", newline="") as fh:
        writer = csv.writer(fh)
        writer.writerow(["timestamp", "env", "status", "warns", "max_latency_s", "endpoints_ok", "rest_fail_count", "exit_code"])
        for e in entries:
            writer.writerow([
                e.timestamp.strftime("%Y-%m-%d %H:%M:%S"),
                e.env,
                e.status,
                e.warns,
                f"{e.max_latency:.3f}",
                e.ok,
                e.rest_fail,
                e.exit_code,
            ])
    print(f"ℹ️  Exported {len(entries)} rows -> {path}")


def main() -> None:
    args = parse_args()
    entries = read_entries(Path(args.log), args.env)
    if args.csv:
        export(entries, Path(args.csv))
    plot(entries, Path(args.out), args)


if __name__ == "__main__":
    main()
