/**
 * Domain entity — represents a system notification pushed to clients.
 * This is a pure value type; no infrastructure dependencies.
 */
export type NotificationType = 'container_down' | 'container_up' | 'memory_alert' | 'health_alert' | 'test';
export type Severity = 'info' | 'warning' | 'critical';
export interface Notification {
    id: string;
    type: NotificationType;
    severity: Severity;
    containerName?: string;
    containerId?: string;
    message: string;
    timestamp: string;
}
/** Factory — creates a Notification with a generated id and current timestamp. */
export declare function createNotification(params: Omit<Notification, 'id' | 'timestamp'>): Notification;
//# sourceMappingURL=Notification.d.ts.map