"""Cookie file health check.

Parses /cookies/youtube.txt (Netscape format) and reports expiration status.
Used by the /health endpoint so you can monitor cookie freshness without SSH.

Netscape format (tab-separated):
  domain  include_subdomains  path  secure  expiry_unix  name  value
"""
import os
import time

_COOKIES_PATH = "/cookies/youtube.txt"
_WARN_DAYS = 7  # surface a warning this many days before expiry


def _min_expiry(path: str) -> int | None:
    """Return the earliest non-session cookie expiry (unix timestamp), or None on error."""
    min_exp: int | None = None
    try:
        with open(path) as f:
            for line in f:
                line = line.strip()
                if not line or line.startswith("#"):
                    continue
                parts = line.split("\t")
                if len(parts) < 7:
                    continue
                try:
                    exp = int(parts[4])
                except ValueError:
                    continue
                if exp == 0:  # session cookie — no fixed expiry
                    continue
                if min_exp is None or exp < min_exp:
                    min_exp = exp
    except OSError:
        return None
    return min_exp


def status() -> dict:
    if not os.path.exists(_COOKIES_PATH):
        return {"present": False, "status": "missing"}

    min_exp = _min_expiry(_COOKIES_PATH)
    if min_exp is None:
        return {"present": True, "status": "unreadable"}

    now = int(time.time())
    days_left = max(0, (min_exp - now) // 86400)

    if min_exp < now:
        cookie_status = "expired"
    elif days_left < _WARN_DAYS:
        cookie_status = "expiring_soon"
    else:
        cookie_status = "ok"

    return {
        "present": True,
        "expires_in_days": days_left,
        "status": cookie_status,
    }
