/**
 * Domain entity — represents a system notification pushed to clients.
 * This is a pure value type; no infrastructure dependencies.
 */
/** Factory — creates a Notification with a generated id and current timestamp. */
export function createNotification(params) {
    return {
        ...params,
        id: crypto.randomUUID(),
        timestamp: new Date().toISOString(),
    };
}
//# sourceMappingURL=Notification.js.map