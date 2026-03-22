import { EventEmitter } from 'node:events';
export interface DockerEvent {
    status: string;
    id: string;
    Actor: {
        ID: string;
        Attributes: Record<string, string>;
    };
    Type: string;
    Action: string;
    time: number;
}
/**
 * Infrastructure — streams events from the Docker daemon via Unix socket.
 * Emits 'event' with a typed DockerEvent whenever Docker fires one.
 */
export declare class DockerEventsService extends EventEmitter {
    private readonly socketPath;
    constructor(socketPath?: string);
    /** Begin listening. Reconnects on stream end. */
    start(): void;
    private connect;
}
//# sourceMappingURL=DockerEventsService.d.ts.map