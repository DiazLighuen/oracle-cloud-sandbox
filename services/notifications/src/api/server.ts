import Fastify from 'fastify';
import websocketPlugin from '@fastify/websocket';

import { JwtVerifier }         from '../infrastructure/security/JwtVerifier.js';
import { WsRegistry }          from '../infrastructure/ws/WsRegistry.js';
import { DockerEventsService } from '../infrastructure/docker/DockerEventsService.js';
import { NotifyUseCase }       from '../application/NotifyUseCase.js';
import { DockerMonitorUseCase } from '../application/DockerMonitorUseCase.js';
import { extractTokenFromQuery } from './middleware/auth.js';

// ── Bootstrap ──────────────────────────────────────────────────────────────

const PORT   = Number(process.env['PORT'] ?? 3000);
const HOST   = process.env['HOST'] ?? '0.0.0.0';

// Infrastructure
const verifier     = new JwtVerifier();
const registry     = new WsRegistry();
const dockerEvents = new DockerEventsService('/var/run/docker.sock');

// Application
const notifyUseCase  = new NotifyUseCase(registry);
const dockerMonitor  = new DockerMonitorUseCase(dockerEvents, notifyUseCase);

// ── Fastify server ─────────────────────────────────────────────────────────

const app = Fastify({ logger: { level: 'info' } });

await app.register(websocketPlugin);

// ── Health check ───────────────────────────────────────────────────────────

app.get('/health', async (_req, reply) => {
  return reply.send({ status: 'ok', clients: registry.connectedCount() });
});

// ── WebSocket endpoint ─────────────────────────────────────────────────────

app.get('/ws', { websocket: true }, (socket, request) => {
  // Validate JWT on handshake — close immediately if invalid
  let user;
  try {
    user = extractTokenFromQuery(request, verifier);
  } catch (err) {
    socket.close(4001, 'Unauthorized');
    return;
  }

  const { sub: userId, name } = user;
  registry.add(userId, socket);
  console.log(`[ws] connected: ${name} (${userId}) — total: ${registry.connectedCount()}`);

  // Send a welcome ping so the client knows it's live
  socket.send(JSON.stringify({
    id: crypto.randomUUID(),
    type: 'test',
    severity: 'info',
    message: `Connected as ${name}`,
    timestamp: new Date().toISOString(),
  }));

  socket.on('close', () => {
    registry.remove(userId, socket);
    console.log(`[ws] disconnected: ${name} — total: ${registry.connectedCount()}`);
  });

  socket.on('error', (err: Error) => {
    console.error(`[ws] error for ${name}:`, err.message);
    registry.remove(userId, socket);
  });
});

// ── Test endpoint (dev/staging only) ──────────────────────────────────────
// Broadcasts a fake notification to all connected WebSocket clients.
// Requires a valid JWT in Authorization header.

app.post('/notifications/test', async (request, reply) => {
  const authHeader = request.headers['authorization'] ?? '';
  const token = authHeader.startsWith('Bearer ') ? authHeader.slice(7) : '';
  try {
    verifier.verify(token);
  } catch {
    return reply.code(401).send({ error: 'Unauthorized' });
  }

  const { type = 'test', severity = 'info', message = 'Test notification' } =
    (request.body as Record<string, string> | null) ?? {};

  const notification = notifyUseCase.execute({
    type: type as never,
    severity: severity as never,
    message,
  });

  return reply.send({ ok: true, notification, clients: registry.connectedCount() });
});

// ── Start ──────────────────────────────────────────────────────────────────

await app.listen({ port: PORT, host: HOST });
console.log(`[server] notifications service listening on ${HOST}:${PORT}`);

// Start Docker event monitoring after server is up
dockerMonitor.start();
