#!/bin/bash
# Upload YouTube cookies to the Oracle Cloud server.
#
# Usage:
#   ./scripts/upload-cookies.sh                     # uses ~/Downloads/youtube.txt
#   ./scripts/upload-cookies.sh /path/to/file.txt   # custom source path
#
# Requirements:
#   - ORACLE_HOST set in .env or environment (e.g. ubuntu@1.2.3.4)
#   - ORACLE_PROJECT_PATH set in .env or environment (default: ~/oracle-cloud-sandbox)
#
# After uploading, check cookie status at: /api/youtube/health

set -e

# Load .env from project root
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$SCRIPT_DIR/.."
if [ -f "$ROOT/.env" ]; then
    set -a
    # shellcheck disable=SC1091
    source "$ROOT/.env"
    set +a
fi

# ── Config ────────────────────────────────────────────────────────────────────
SSH_KEY="$ROOT/../keys/ssh-oracle-docker.key"
HOST="${ORACLE_HOST:?ORACLE_HOST is not set. Add it to .env or export it. Example: ubuntu@1.2.3.4}"
REMOTE_DIR="${ORACLE_PROJECT_PATH:-~/oracle-cloud-sandbox}/cookies"
REMOTE_PATH="$REMOTE_DIR/youtube.txt"
LOCAL="${1:-$HOME/Downloads/youtube.txt}"

if [ ! -f "$SSH_KEY" ]; then
    echo "ERROR: SSH key not found: $SSH_KEY"
    exit 1
fi

# ── Validate ──────────────────────────────────────────────────────────────────
if [ ! -f "$LOCAL" ]; then
    echo "ERROR: Cookie file not found: $LOCAL"
    echo ""
    echo "Export cookies from your browser:"
    echo "  Chrome/Firefox extension: 'Get cookies.txt LOCALLY'"
    echo "  Export cookies for youtube.com → save as ~/Downloads/youtube.txt"
    echo ""
    echo "Usage: $0 [/path/to/cookies.txt]"
    exit 1
fi

# ── Upload ────────────────────────────────────────────────────────────────────
echo "==> Uploading cookies to $HOST..."
ssh -i "$SSH_KEY" "$HOST" "mkdir -p $REMOTE_DIR"
scp -i "$SSH_KEY" "$LOCAL" "$HOST:$REMOTE_PATH"

echo ""
echo "✓ Cookies uploaded to $HOST:$REMOTE_PATH"
echo ""

# ── Show expiry from health endpoint (best-effort) ────────────────────────────
if command -v curl &>/dev/null && command -v python3 &>/dev/null; then
    HEALTH_URL="${ORACLE_BASE_URL:-}/api/youtube/health"
    if [ -n "${ORACLE_BASE_URL:-}" ]; then
        echo "==> Checking cookie status..."
        HEALTH=$(curl -sf "$HEALTH_URL" 2>/dev/null || true)
        if [ -n "$HEALTH" ]; then
            echo "$HEALTH" | python3 -c "
import json, sys
d = json.load(sys.stdin).get('cookies', {})
st = d.get('status', '?')
days = d.get('expires_in_days')
if days is not None:
    print(f'   Status: {st} ({days} days remaining)')
else:
    print(f'   Status: {st}')
"
        fi
    fi
fi

echo "Tip: run the following anytime to check cookie freshness:"
echo "  ssh -i $SSH_KEY $HOST 'docker exec youtube_svc curl -s localhost:8000/health'"
