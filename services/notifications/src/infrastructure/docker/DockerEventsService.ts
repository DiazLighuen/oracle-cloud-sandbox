import http from 'node:http';
import { EventEmitter } from 'node:events';

export interface DockerEvent {
  status: string;       // 'start' | 'die' | 'oom' | 'health_status' | ...
  id: string;           // container id (short)
  Actor: {
    ID: string;
    Attributes: Record<string, string>;
  };
  Type: string;         // 'container' | 'image' | 'network' | ...
  Action: string;
  time: number;
}

/**
 * Infrastructure — streams events from the Docker daemon via Unix socket.
 * Emits 'event' with a typed DockerEvent whenever Docker fires one.
 */
export class DockerEventsService extends EventEmitter {
  private readonly socketPath: string;

  constructor(socketPath = '/var/run/docker.sock') {
    super();
    this.socketPath = socketPath;
  }

  /** Begin listening. Reconnects on stream end. */
  start(): void {
    this.connect();
  }

  private connect(): void {
    const req = http.request(
      {
        socketPath: this.socketPath,
        path: '/events?filters=%7B%22type%22%3A%5B%22container%22%5D%7D',
        method: 'GET',
      },
      (res) => {
        res.setEncoding('utf8');

        let buffer = '';
        res.on('data', (chunk: string) => {
          buffer += chunk;
          const lines = buffer.split('\n');
          buffer = lines.pop() ?? '';

          for (const line of lines) {
            const trimmed = line.trim();
            if (!trimmed) continue;
            try {
              const event: DockerEvent = JSON.parse(trimmed);
              this.emit('event', event);
            } catch {
              // malformed line — skip
            }
          }
        });

        res.on('end', () => {
          console.warn('[docker-events] stream ended — reconnecting in 3s');
          setTimeout(() => this.connect(), 3000);
        });

        res.on('error', (err: Error) => {
          console.error('[docker-events] stream error:', err.message);
          setTimeout(() => this.connect(), 3000);
        });
      },
    );

    req.on('error', (err: Error) => {
      console.error('[docker-events] connection error:', err.message);
      setTimeout(() => this.connect(), 3000);
    });

    req.end();
  }
}
