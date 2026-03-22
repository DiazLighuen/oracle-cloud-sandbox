# oracle-cloud-sandbox

Multi-service platform deployed on Oracle Cloud. Centralized authentication via Google OAuth 2.0 + JWT.

## Stack

| Service | Runtime | Framework | Port |
|---------|---------|-----------|------|
| `api` | PHP 8.3-fpm-alpine | Slim 4 + PHP-DI | 9000 (FPM) |
| `notifications` | Node 22-alpine | Fastify + WebSocket | 3000 |
| `youtube` | Python 3.12-slim | FastAPI + uvicorn | 8000 |
| `db` | PostgreSQL 16 | — | 5432 |
| `nginx` | nginx:alpine | reverse proxy | 80 |

## Project structure

```
oracle-cloud-sandbox/
├── docker-compose.yml           # Orchestrator: defines the 4 services
├── docker/                      # Shared infra (runtime config, not app code)
│   ├── nginx/default.conf       # Routes, WebSocket proxy, PHP-FPM upstream
│   └── postgres/
│       ├── init.sql             # Initial schema
│       └── migrate.sql          # Cumulative migrations
└── services/                   # Application services
    ├── api/                     # PHP service
    │   ├── src/
    │   │   ├── Domain/          # Entities and interfaces (no external dependencies)
    │   │   ├── Application/     # Slim action handlers (__invoke)
    │   │   └── Infrastructure/  # PDO, JWT, Google OAuth, Docker metrics
    │   ├── config/container.php # PHP-DI definitions
    │   ├── public/index.php     # Entry point: routes + middlewares
    │   ├── bin/whitelist.php    # CLI: user and admin management
    │   ├── composer.json
    │   └── Dockerfile
    ├── notifications/           # Node.js service
    │   ├── src/
    │   │   ├── domain/          # Domain types (Notification)
    │   │   ├── application/     # Use cases (NotifyUseCase, DockerMonitorUseCase)
    │   │   └── infrastructure/  # WsRegistry, JwtVerifier, DockerEventsService
    │   ├── package.json
    │   ├── tsconfig.json
    │   └── Dockerfile
    └── youtube/                 # Python service
        ├── app/
        │   ├── main.py          # FastAPI entry point, routes
        │   ├── auth.py          # JWT validation (python-jose)
        │   ├── cache.py         # File-based cache /tmp/yt_cache/ with TTL
        │   └── clients/
        │       ├── youtube.py   # YouTube Data API v3 wrapper (subscriptions, feed, search, metadata)
        │       └── invidious.py # Invidious client — stream URLs only (never cached)
        ├── requirements.txt
        └── Dockerfile
```

## Routes (api service)

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/` | No | Landing page |
| GET | `/auth/google` | No | Redirect to Google OAuth |
| GET | `/auth/google/callback` | No | Web callback: upsert user, set JWT cookie |
| POST | `/auth/google/mobile` | No | Mobile auth: receives `{id_token}`, returns `{token, user}` |
| GET | `/auth/logout` | No | Clears JWT cookie |
| GET | `/dashboard` | JWT + Admin | Docker metrics dashboard (SSR) |
| GET | `/users` | JWT + Admin | Authorized users page (SSR) |
| GET | `/ws-test` | JWT + Admin | WebSocket test page (SSR) |
| GET | `/api/containers` | JWT + Admin | Docker container metrics as JSON |
| GET | `/api/users` | JWT + Admin | Users list as JSON |
| POST | `/api/users` | JWT + Admin | Create user |
| PATCH | `/api/users/{id}` | JWT + Admin | Update user (name, is_admin) |
| DELETE | `/api/users/{id}` | JWT + Admin | Delete user |

## Routes (youtube service)

All endpoints require `Authorization: Bearer <JWT>` + `X-Google-Token: <Google access token>`.

| Method | Path | Auth | Cache TTL | Description |
|--------|------|------|-----------|-------------|
| GET | `/api/youtube/subscriptions?page_token=` | JWT + Google | 1h | User's YouTube channel subscriptions |
| GET | `/api/youtube/feed?page=1` | JWT + Google | 15min | Recent videos from subscriptions (Shorts excluded) |
| GET | `/api/youtube/search?q=&type=video\|channel&page_token=` | JWT + Google | 5min | YouTube search |
| GET | `/api/youtube/video/{id}` | JWT + Google | metadata 1h, streams never | Video metadata + playback streams |
| GET | `/api/youtube/health` | No | — | `{"status":"ok"}` |

## Routes (notifications service)

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| WS | `/ws?token=<JWT>` | JWT (query param) | Real-time Docker events WebSocket |
| GET | `/health` | No | `{"status":"ok","clients":<n>}` |
| POST | `/notifications/test` | JWT (Bearer) | Broadcast a test notification |

## Commands

```bash
# Start all services
docker compose up -d

# View logs
docker compose logs -f app
docker compose logs -f notifications

# Stop
docker compose down

# Rebuild image after Dockerfile changes
docker compose up -d --build app
docker compose up -d --build notifications
docker compose up -d --build youtube

# User CLI (run from host)
docker exec php_app php bin/whitelist.php list
docker exec php_app php bin/whitelist.php grant-admin user@gmail.com
docker exec php_app php bin/whitelist.php revoke-admin user@gmail.com
```

## Environment variables

Copy `.env.example` → `.env` at the project root.

| Variable | Description |
|----------|-------------|
| `DB_NAME` | Postgres database name |
| `DB_USER` | Postgres user |
| `DB_PASSWORD` | Postgres password |
| `APP_PORT` | Port exposed by Nginx (default: `80`) |
| `GOOGLE_CLIENT_ID` | Google OAuth web client ID |
| `GOOGLE_CLIENT_SECRET` | Google OAuth client secret |
| `GOOGLE_REDIRECT_URL` | Callback URL registered in Google Cloud Console |
| `GOOGLE_IOS_CLIENT_ID` | Google OAuth iOS client ID (for mobile token verification) |
| `JWT_SECRET` | Shared secret between `api`, `notifications`, and `youtube` |
| `INVIDIOUS_BASE_URL` | Public Invidious instance URL (e.g. `https://invidious.io.lol`) |

## Code conventions

### PHP (services/api)
- `declare(strict_types=1)` in every file.
- Layered architecture: **Domain → Application → Infrastructure**.
- Action handlers implement `__invoke(Request, Response): Response`.
- PDO with prepared statements for all queries.
- HTTP errors return JSON `{"error": "message"}`.
- Middleware order in Slim 4 is LIFO: last `.add()` runs first. Always chain `->add(AdminMiddleware::class)->add(JwtMiddleware::class)` so JWT validates before admin check.

### TypeScript (services/notifications)
- `strict: true` in tsconfig.
- Same layered architecture: **domain → application → infrastructure**.
- ES modules (`"type": "module"` in package.json).

### Python (services/youtube)
- FastAPI with `async def` handlers — all I/O is async (httpx, Google API).
- Auth: `verify_jwt` dependency validates our JWT; `X-Google-Token` header is passed to YouTube API calls.
- Cache: file-based in `/tmp/yt_cache/` — MD5-keyed JSON files with timestamp + TTL.
- Stream URLs from Invidious are **never cached** (they expire).
- Shorts filtered server-side: ISO 8601 duration parsed, videos < 60s excluded from feed and search results.

## Database

Schema and migrations: `docker/postgres/init.sql`, `docker/postgres/migrate.sql`.

## Contract system

This project uses a shared contract system with the iOS app at `../contracts/`.

### Before starting any task
1. Read `../contracts/AGENTS.md` — system rules.
2. Read `../contracts/index.json` — all contracts and their current status.
3. Only read the full `current.json` if the task involves that specific resource.

### When implementing or modifying an endpoint
1. Read `../contracts/{resource}/current.json` to see the agreed schema.
2. Implement against the contract — don't add fields that aren't in it.
3. If the real backend differs from the contract (extra error codes, renamed fields, etc.), create `../contracts/{resource}/v{new_version}.json` with `status: "pending_approval"` and update `index.json`.
4. Never modify `current.json` directly — only the iOS agent approves contracts.

### When adding a new endpoint
1. Create `../contracts/{resource}/v1.0.0.json` with `status: "pending_approval"`.
2. Add the entry to `../contracts/index.json` with `status: "pending_approval"` and `last_actor: "backend"`.
3. The iOS agent reads the proposal and either approves it (copies to `current.json`, sets `agreed`) or requests changes.

### Versioning rules
| Change | Bump |
|--------|------|
| Examples or summary only | patch (1.0.0 → 1.0.1) |
| New optional field or new endpoint | minor (1.0.0 → 1.1.0) |
| Breaking change (renamed/removed field, changed type) | major (1.0.0 → 2.0.0) |

## Adding a new service

1. Create `services/<name>/` with source code and `Dockerfile`.
2. Add the service in `docker-compose.yml` with `context: ./services/<name>`.
3. If it needs to be reachable from outside, add a `location` block in `docker/nginx/default.conf`.
4. If it shares JWT auth, pass `JWT_SECRET` as an environment variable.

## Adding new routes to the api service

1. Check `../contracts/index.json` — if the endpoint is contracted, read `current.json` first.
2. Create an Action in `services/api/src/Application/Actions/<Feature>/`.
3. Register it in `services/api/public/index.php`.
4. If it needs new dependencies, add them in `services/api/config/container.php`.
5. Add `->add(JwtMiddleware::class)` if authentication is required.
6. Add `->add(AdminMiddleware::class)` (before JwtMiddleware in chain) if admin role is required.
7. Update or create the corresponding contract in `../contracts/`.
