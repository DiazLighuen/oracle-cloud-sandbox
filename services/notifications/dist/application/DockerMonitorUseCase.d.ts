import type { DockerEventsService } from '../infrastructure/docker/DockerEventsService.js';
import type { NotifyUseCase } from './NotifyUseCase.js';
/**
 * Application use case — subscribes to Docker events and translates them
 * into domain notifications via NotifyUseCase.
 */
export declare class DockerMonitorUseCase {
    private readonly dockerEvents;
    private readonly notify;
    constructor(dockerEvents: DockerEventsService, notify: NotifyUseCase);
    start(): void;
    private handleEvent;
}
//# sourceMappingURL=DockerMonitorUseCase.d.ts.map