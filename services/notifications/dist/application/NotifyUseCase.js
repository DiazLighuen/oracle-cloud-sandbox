import { createNotification } from '../domain/notification/Notification.js';
/**
 * Application use case — constructs a Notification and broadcasts it.
 * Decoupled from transport: doesn't know about WebSocket internals.
 */
export class NotifyUseCase {
    registry;
    constructor(registry) {
        this.registry = registry;
    }
    execute(params) {
        const notification = createNotification(params);
        this.registry.broadcast(notification);
        console.log(`[notify] ${notification.severity.toUpperCase()} ${notification.type}` +
            (notification.containerName ? ` [${notification.containerName}]` : '') +
            ` — ${notification.message}` +
            ` (${this.registry.connectedCount()} clients)`);
        return notification;
    }
}
//# sourceMappingURL=NotifyUseCase.js.map