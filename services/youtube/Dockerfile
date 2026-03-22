FROM python:3.12-slim AS base

WORKDIR /app

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
