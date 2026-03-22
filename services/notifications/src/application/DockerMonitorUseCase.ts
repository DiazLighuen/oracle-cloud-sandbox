import type { DockerEventsService, DockerEvent } from '../infrastructure/docker/DockerEventsService.js';
import type { NotifyUseCase } from './NotifyUseCase.js';
import type { NotificationType, Severity } from '../domain/notification/Notification.js';

/** Maps Docker event actions to our domain types. */
const EVENT_MAP: Record<string, { type: NotificationType; severity: Severity; message: (name: string) => string } | undefined> = {
  die:    { type: 'container_down', severity: 'critical', message: (n) => `Container "${n}" stopped unexpectedly` },
  start:  { type: 'container_up',   severity: 'info',     message: (n) => `Container "${n}" started` },
  oom:    { type: 'memory_alert',   severity: 'critical', message: (n) => `Container "${n}" was OOM-killed` },
};

/**
 * Application use case — subscribes to Docker events and translates them
 * into domain notifications via NotifyUseCase.
 */
export class DockerMonitorUseCase {
  constructor(
    private readonly dockerEvents: DockerEventsService,
    private readonly notify: NotifyUseCase,
  ) {}

  start(): void {
    this.dockerEvents.on('event', (event: DockerEvent) => this.handleEvent(event));
    this.dockerEvents.start();
    console.log('[docker-monitor] listening for container events…');
  }

  private handleEvent(event: DockerEvent): void {
    // Handle health_status events (format: "health_status: healthy/unhealthy")
    if (event.Action?.startsWith('health_status:')) {
      const status = event.Action.split(':')[1]?.trim();
      if (status === 'unhealthy') {
        const name = event.Actor.Attributes['name'] ?? event.id.slice(0, 12);
        this.notify.execute({
          type: 'health_alert',
          severity: 'warning',
          containerId: event.id,
          containerName: name,
          message: `Container "${name}" is unhealthy`,
        });
      }
      return;
    }

    const mapping = EVENT_MAP[event.status ?? event.Action];
    if (!mapping) return;

    const name = event.Actor.Attributes['name'] ?? event.id.slice(0, 12);
    this.notify.execute({
      type: mapping.type,
      severity: mapping.severity,
      containerId: event.id,
      containerName: name,
      message: mapping.message(name),
    });
  }
}
