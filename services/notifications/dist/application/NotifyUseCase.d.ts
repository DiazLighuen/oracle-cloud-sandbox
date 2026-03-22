import { type Notification, type NotificationType, type Severity } from '../domain/notification/Notification.js';
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
export declare class NotifyUseCase {
    private readonly registry;
    constructor(registry: WsRegistry);
    execute(params: NotifyParams): Notification;
}
//# sourceMappingURL=NotifyUseCase.d.ts.map