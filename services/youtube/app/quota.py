"""YouTube Data API v3 quota tracker.

Tracks daily unit consumption and resets at midnight Pacific time,
which is when Google resets the quota.

Costs (units):
  subscriptions.list  → 1
  channels.list       → 1
  playlistItems.list  → 1
  videos.list         → 1
  search.list         → 100
"""
import json
import os
import threading
from datetime import datetime
from zoneinfo import ZoneInfo

_QUOTA_FILE = "/tmp/yt_quota.json"
_DAILY_LIMIT = 10_000
_PT = ZoneInfo("America/Los_Angeles")
_lock = threading.Lock()

COST_LIST        = 1    # subscriptions, channels, playlistItems, videos
COST_SEARCH      = 100  # search.list


def _today_pt() -> str:
    return datetime.now(_PT).strftime("%Y-%m-%d")


def _load() -> dict:
    try:
        with open(_QUOTA_FILE) as f:
            data = json.load(f)
        if data.get("date") == _today_pt():
            return data
    except (FileNotFoundError, json.JSONDecodeError):
        pass
    return {"date": _today_pt(), "used": 0}


def _save(data: dict) -> None:
    with open(_QUOTA_FILE, "w") as f:
        json.dump(data, f)


def add(units: int) -> None:
    """Record consumed quota units."""
    with _lock:
        data = _load()
        data["used"] += units
        _save(data)


def status() -> dict:
    """Return current quota status."""
    with _lock:
        data = _load()
    used = data["used"]
    return {
        "used":       used,
        "limit":      _DAILY_LIMIT,
        "remaining":  max(0, _DAILY_LIMIT - used),
        "percent":    round(used / _DAILY_LIMIT * 100, 1),
        "reset_date": data["date"],
    }
