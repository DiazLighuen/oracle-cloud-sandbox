/**
 * Infrastructure — tracks active WebSocket connections per user.
 * Provides broadcast primitives for the application layer.
 */
export class WsRegistry {
    /** userId → set of open sockets */
    clients = new Map();
    add(userId, socket) {
        if (!this.clients.has(userId)) {
            this.clients.set(userId, new Set());
        }
        this.clients.get(userId).add(socket);
    }
    remove(userId, socket) {
        const sockets = this.clients.get(userId);
        if (!sockets)
            return;
        sockets.delete(socket);
        if (sockets.size === 0)
            this.clients.delete(userId);
    }
    /** Send a notification to every connected client (all users). */
    broadcast(notification) {
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
    sendToUser(userId, notification) {
        const payload = JSON.stringify(notification);
        const sockets = this.clients.get(userId);
        if (!sockets)
            return;
        for (const socket of sockets) {
            if (socket.readyState === socket.OPEN) {
                socket.send(payload);
            }
        }
    }
    connectedCount() {
        let total = 0;
        for (const sockets of this.clients.values())
            total += sockets.size;
        return total;
    }
}
//# sourceMappingURL=WsRegistry.js.map