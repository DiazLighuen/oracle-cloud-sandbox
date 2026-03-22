import { createNotification, type Notification, type NotificationType, type Severity } from '../domain/notification/Notification.js';
import type { WsRegistry } from '../infrastructure/ws/WsRegistry.js';

export interface NotifyParams {
  type: NotificationType;
  severity: Severity;
  containerName?: string;
  containerId?: string;
  message: string;
}

/**
 * Application use case — constructs a Notification and broadcasts it.
 * Decoupled from transport: doesn't know about WebSocket internals.
 */
export class NotifyUseCase {
  constructor(private readonly registry: WsRegistry) {}

  execute(params: NotifyParams): Notification {
    const notification = createNotification(params);
    this.registry.broadcast(notification);

    console.log(
      `[notify] ${notification.severity.toUpperCase()} ${notification.type}` +
      (notification.containerName ? ` [${notification.containerName}]` : '') +
      ` — ${notification.message}` +
      ` (${this.registry.connectedCount()} clients)`,
    );

    return notification;
  }
}
