import type { WebSocket } from '@fastify/websocket';
import type { Notification } from '../../domain/notification/Notification.js';
/**
 * Infrastructure — tracks active WebSocket connections per user.
 * Provides broadcast primitives for the application layer.
 */
export declare class WsRegistry {
    /** userId → set of open sockets */
    private readonly clients;
    add(userId: string, socket: WebSocket): void;
    remove(userId: string, socket: WebSocket): void;
    /** Send a notification to every connected client (all users). */
    broadcast(notification: Notification): void;
    /** Send a notification to a specific user's connections only. */
    sendToUser(userId: string, notification: Notification): void;
    connectedCount(): number;
}
//# sourceMappingURL=WsRegistry.d.ts.map