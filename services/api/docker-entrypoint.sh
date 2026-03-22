#!/bin/sh
set -e

# Auto-detect the Docker socket GID from inside the container and grant
# www-data access. This approach works on any host OS (macOS, Linux)
# because it reads the real GID as seen inside the Alpine container,
# regardless of what the host reports.
if [ -S /var/run/docker.sock ]; then
    SOCK_GID=$(stat -c '%g' /var/run/docker.sock)

    # Find an existing group with this GID, or create one named "dockersock"
    SOCK_GROUP=$(grep "^[^:]*:[^:]*:${SOCK_GID}:" /etc/group | cut -d: -f1 | head -1)
    if [ -z "$SOCK_GROUP" ]; then
        addgroup -g "$SOCK_GID" dockersock 2>/dev/null && SOCK_GROUP="dockersock"
    fi

    # Add www-data to the socket's group (idempotent, errors suppressed)
    [ -n "$SOCK_GROUP" ] && addgroup www-data "$SOCK_GROUP" 2>/dev/null || true
fi

exec "$@"
