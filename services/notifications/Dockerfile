FROM node:22-alpine AS builder

WORKDIR /app

COPY package*.json ./
RUN npm ci

COPY tsconfig.json ./
COPY src/ ./src/
RUN npm run build

# ── Dev image (hot reload via tsx watch) ────────────────────────────────────
FROM node:22-alpine AS dev

WORKDIR /app

COPY package*.json ./
RUN npm ci                        # includes devDependencies (tsx, typescript)

COPY tsconfig.json ./
# src/ is bind-mounted at runtime — no COPY needed

EXPOSE 3000
CMD ["npm", "run", "dev"]

# ── Production image ─────────────────────────────────────────────────────────
FROM node:22-alpine AS production

WORKDIR /app

COPY package*.json ./
RUN npm ci --omit=dev && npm cache clean --force

COPY --from=builder /app/dist ./dist

EXPOSE 3000
CMD ["node", "dist/api/server.js"]
