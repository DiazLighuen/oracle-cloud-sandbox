import type { WebSocket } from '@fastify/websocket';
import type { Notification } from '../../domain/notification/Notification.js';

/**
 * Infrastructure — tracks active WebSocket connections per user.
 * Provides broadcast primitives for the application layer.
 */
export class WsRegistry {
  /** userId → set of open sockets */
  private readonly clients = new Map<string, Set<WebSocket>>();

  add(userId: string, socket: WebSocket): void {
    if (!this.clients.has(userId)) {
      this.clients.set(userId, new Set());
    }
    this.clients.get(userId)!.add(socket);
  }

  remove(userId: string, socket: WebSocket): void {
    const sockets = this.clients.get(userId);
    if (!sockets) return;
    sockets.delete(socket);
    if (sockets.size === 0) this.clients.delete(userId);
  }

  /** Send a notification to every connected client (all users). */
  broadcast(notification: Notification): void {
    const payload = JSON.stringify(notification);
    for (const sockets of this.clients.values()) {
      for (const socket of sockets) {
        if (socket.readyState === socket.OPEN) {
          socket.send(payload);
        }
      }
    }
  }

  /** Send a notification to a specific user's connections only. */
  sendToUser(userId: string, notification: Notification): void {
    const payload = JSON.stringify(notification);
    const sockets = this.clients.get(userId);
    if (!sockets) return;
    for (const socket of sockets) {
      if (socket.readyState === socket.OPEN) {
        socket.send(payload);
      }
    }
  }

  connectedCount(): number {
    let total = 0;
    for (const sockets of this.clients.values()) total += sockets.size;
    return total;
  }
}
