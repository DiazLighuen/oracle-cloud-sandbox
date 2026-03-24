FROM python:3.12-slim AS base

WORKDIR /app

# Install Node.js via NodeSource — required by yt-dlp to solve YouTube's
# n-parameter challenge. Without it, signature solving fails and no video
# formats are available.
RUN apt-get update \
    && apt-get install -y --no-install-recommends curl ca-certificates \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# ── Dev image (hot reload via uvicorn --reload) ──────────────────────────────
FROM base AS dev

# app/ is bind-mounted at runtime — no COPY needed
EXPOSE 8000
CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8000", "--reload"]

# ── Production image ─────────────────────────────────────────────────────────
FROM base AS production

COPY app/ ./app/

EXPOSE 8000
CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8000"]
