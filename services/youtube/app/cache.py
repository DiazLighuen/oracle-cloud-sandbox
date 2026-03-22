import hashlib
import json
import os
import time

_CACHE_DIR = "/tmp/yt_cache"


def _key_path(key: str) -> str:
    os.makedirs(_CACHE_DIR, exist_ok=True)
    h = hashlib.md5(key.encode()).hexdigest()
    return os.path.join(_CACHE_DIR, h + ".json")


def get(key: str, ttl: int) -> dict | list | None:
    path = _key_path(key)
    try:
        with open(path) as f:
            entry = json.load(f)
        if time.time() - entry["ts"] < ttl:
            return entry["data"]
    except (FileNotFoundError, KeyError, json.JSONDecodeError):
        pass
    return None


def set(key: str, data: dict | list) -> None:
    path = _key_path(key)
    with open(path, "w") as f:
        json.dump({"ts": time.time(), "data": data}, f)
