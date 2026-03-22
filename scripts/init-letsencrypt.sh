#!/bin/bash
# Bootstrap Let's Encrypt certificate for the first time.
# Run this ONCE on the server after deploying, before starting the full stack.
#
# Usage: bash scripts/init-letsencrypt.sh

set -e

DOMAIN="oracle-cloud-sandbox.duckdns.org"
EMAIL="tu-email@gmail.com"           # ← change to your email (for expiry alerts)
STAGING=0                            # set to 1 to test without hitting rate limits

echo "==> Creating required directories..."
mkdir -p certbot/conf certbot/www

echo "==> Downloading recommended TLS parameters from certbot..."
if [ ! -f "certbot/conf/options-ssl-nginx.conf" ]; then
    curl -s https://raw.githubusercontent.com/certbot/certbot/master/certbot-nginx/certbot_nginx/_internal/tls_configs/options-ssl-nginx.conf \
        -o certbot/conf/options-ssl-nginx.conf
fi
if [ ! -f "certbot/conf/ssl-dhparams.pem" ]; then
    curl -s https://raw.githubusercontent.com/certbot/certbot/master/certbot/certbot/ssl-dhparams.pem \
        -o certbot/conf/ssl-dhparams.pem
fi

echo "==> Creating dummy certificate so nginx can start..."
mkdir -p "certbot/conf/live/$DOMAIN"
docker run --rm \
    -v "$(pwd)/certbot/conf:/etc/letsencrypt" \
    certbot/certbot \
    certonly --non-interactive --agree-tos \
    --register-unsafely-without-email \
    --webroot -w /var/www/certbot \
    --staging \
    -d "$DOMAIN" 2>/dev/null || true

# If staging run failed (no server running yet), create a self-signed placeholder
if [ ! -f "certbot/conf/live/$DOMAIN/fullchain.pem" ]; then
    echo "==> Creating self-signed placeholder certificate..."
    docker run --rm \
        -v "$(pwd)/certbot/conf:/etc/letsencrypt" \
        --entrypoint openssl \
        certbot/certbot \
        req -x509 -nodes -newkey rsa:2048 -days 1 \
        -keyout "/etc/letsencrypt/live/$DOMAIN/privkey.pem" \
        -out "/etc/letsencrypt/live/$DOMAIN/fullchain.pem" \
        -subj "/CN=localhost" 2>/dev/null
fi

echo "==> Starting nginx (HTTP only, for ACME challenge)..."
docker compose up -d nginx

echo "==> Waiting for nginx to be ready..."
sleep 3

STAGING_FLAG=""
if [ "$STAGING" = "1" ]; then
    STAGING_FLAG="--staging"
    echo "==> Running in STAGING mode (no real certificate)"
fi

echo "==> Requesting real Let's Encrypt certificate..."
docker compose run --rm certbot certonly \
    --webroot -w /var/www/certbot \
    --email "$EMAIL" \
    --agree-tos \
    --no-eff-email \
    $STAGING_FLAG \
    -d "$DOMAIN"

echo "==> Reloading nginx with the real certificate..."
docker compose exec nginx nginx -s reload

echo ""
echo "✓ Done! HTTPS is active for https://$DOMAIN"
echo ""
echo "Next steps:"
echo "  1. Update .env: GOOGLE_REDIRECT_URL=https://$DOMAIN/auth/google/callback"
echo "  2. Update .env: GOOGLE_YOUTUBE_REDIRECT_URL=https://$DOMAIN/auth/youtube/callback"
echo "  3. Add both URIs to Google Cloud Console → Credentials → your OAuth client"
echo "  4. docker compose up -d   (start the full stack)"
