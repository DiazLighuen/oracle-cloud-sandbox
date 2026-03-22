<?php
declare(strict_types=1);

namespace App\Application\Actions\Notifications;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * GET /ws-test  (admin only)
 *
 * Developer test page for the WebSocket notifications service.
 * Injects the session JWT server-side so the browser can connect
 * to ws://<host>/ws?token=<JWT> without any manual token extraction.
 */
class WsTestAction
{
    public function __invoke(Request $request, Response $response): Response
    {
        $name  = htmlspecialchars($request->getAttribute('user_name') ?? 'Unknown');
        $jwt   = $_COOKIE['jwt'] ?? '';

        $html = <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>WebSocket Test — Notifications</title>
            <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&family=Syne:wght@600;700&display=swap" rel="stylesheet">
            <style>
                *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

                :root {
                    --bg:      #0b0d14;
                    --surface: #12151f;
                    --border:  #1e2235;
                    --accent:  #3b4fd8;
                    --text:    #c8d0e8;
                    --white:   #eef2ff;
                    --green:   #22c55e;
                    --yellow:  #f59e0b;
                    --red:     #ef4444;
                    --blue:    #60a5fa;
                    --dim:     #4a5580;
                    --mono:    'JetBrains Mono', monospace;
                }

                body {
                    background: var(--bg); color: var(--text);
                    font-family: 'Syne', sans-serif; min-height: 100vh;
                    display: flex; flex-direction: column; align-items: center;
                    padding: 40px 20px;
                }

                h1 { color: var(--white); font-size: 1.4rem; margin-bottom: 6px; }
                .sub { color: var(--dim); font-size: .85rem; font-family: var(--mono); margin-bottom: 32px; }

                .card {
                    background: var(--surface); border: 1px solid var(--border);
                    border-radius: 12px; padding: 24px; width: 100%; max-width: 760px;
                    margin-bottom: 20px;
                }

                .card-title {
                    font-size: .7rem; letter-spacing: .08em; text-transform: uppercase;
                    color: var(--dim); margin-bottom: 14px;
                }

                /* Status bar */
                .status-row { display: flex; align-items: center; gap: 10px; }
                .dot {
                    width: 10px; height: 10px; border-radius: 50%;
                    background: var(--dim); flex-shrink: 0;
                    transition: background .3s;
                }
                .dot.connected   { background: var(--green); box-shadow: 0 0 8px var(--green); }
                .dot.connecting  { background: var(--yellow); }
                .dot.error       { background: var(--red); }
                #status-text { font-family: var(--mono); font-size: .85rem; }

                /* Controls */
                .controls { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 16px; }
                button {
                    font-family: 'Syne', sans-serif; font-size: .8rem; font-weight: 600;
                    padding: 8px 16px; border-radius: 8px; border: none; cursor: pointer;
                    transition: opacity .15s;
                }
                button:hover { opacity: .85; }
                button:disabled { opacity: .4; cursor: not-allowed; }
                .btn-primary { background: var(--accent); color: #fff; }
                .btn-secondary { background: var(--border); color: var(--text); }
                .btn-danger { background: #3a1515; color: var(--red); border: 1px solid var(--red); }

                /* Trigger form */
                .trigger-row { display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap; }
                label { font-size: .75rem; color: var(--dim); display: block; margin-bottom: 4px; }
                select, input[type=text] {
                    background: var(--bg); border: 1px solid var(--border); color: var(--text);
                    font-family: var(--mono); font-size: .8rem; padding: 7px 10px;
                    border-radius: 6px; min-width: 140px;
                }
                select:focus, input[type=text]:focus {
                    outline: none; border-color: var(--accent);
                }

                /* Log */
                #log {
                    font-family: var(--mono); font-size: .78rem; line-height: 1.7;
                    max-height: 360px; overflow-y: auto; display: flex; flex-direction: column;
                    gap: 6px;
                }
                .log-entry {
                    padding: 8px 12px; border-radius: 6px;
                    border-left: 3px solid var(--border);
                    background: var(--bg);
                    animation: fadeIn .2s ease;
                }
                @keyframes fadeIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; } }

                .log-entry.info     { border-color: var(--blue); }
                .log-entry.warning  { border-color: var(--yellow); }
                .log-entry.critical { border-color: var(--red); }
                .log-entry.system   { border-color: var(--dim); color: var(--dim); }

                .log-time    { color: var(--dim); margin-right: 8px; }
                .log-type    { font-weight: 500; margin-right: 8px; }
                .log-badge   {
                    display: inline-block; font-size: .65rem; padding: 1px 6px;
                    border-radius: 4px; margin-right: 6px; vertical-align: middle;
                }
                .badge-info     { background: #1e2d4a; color: var(--blue); }
                .badge-warning  { background: #2e2210; color: var(--yellow); }
                .badge-critical { background: #2e1010; color: var(--red); }

                .empty-log { color: var(--dim); font-style: italic; text-align: center; padding: 20px 0; }

                /* Back link */
                .back { font-size: .8rem; color: var(--dim); text-decoration: none; margin-top: 8px; }
                .back:hover { color: var(--text); }
            </style>
        </head>
        <body>
            <h1>WebSocket Test</h1>
            <p class="sub">Notifications service — connected as {$name}</p>

            <!-- Connection card -->
            <div class="card">
                <div class="card-title">Connection</div>
                <div class="status-row">
                    <div class="dot" id="dot"></div>
                    <span id="status-text">Disconnected</span>
                </div>
                <div class="controls">
                    <button class="btn-primary" id="btn-connect">Connect</button>
                    <button class="btn-secondary" id="btn-disconnect" disabled>Disconnect</button>
                </div>
            </div>

            <!-- Trigger card -->
            <div class="card">
                <div class="card-title">Trigger notification</div>
                <div class="trigger-row">
                    <div>
                        <label for="sel-type">Type</label>
                        <select id="sel-type">
                            <option value="test">test</option>
                            <option value="container_down">container_down</option>
                            <option value="container_up">container_up</option>
                            <option value="memory_alert">memory_alert</option>
                            <option value="health_alert">health_alert</option>
                        </select>
                    </div>
                    <div>
                        <label for="sel-severity">Severity</label>
                        <select id="sel-severity">
                            <option value="info">info</option>
                            <option value="warning">warning</option>
                            <option value="critical">critical</option>
                        </select>
                    </div>
                    <div style="flex:1; min-width:180px">
                        <label for="inp-message">Message</label>
                        <input type="text" id="inp-message" value="Test notification from dashboard" style="width:100%">
                    </div>
                    <button class="btn-primary" id="btn-trigger">Send</button>
                </div>
            </div>

            <!-- Log card -->
            <div class="card">
                <div class="card-title">Live log</div>
                <div id="log"><div class="empty-log">No messages yet — connect and send a notification</div></div>
            </div>

            <a href="/dashboard" class="back">← Back to Dashboard</a>

            <script>
                const JWT = '{$jwt}';
                const WS_URL = 'ws://' + location.host + '/ws?token=' + JWT;

                let ws = null;

                const dot        = document.getElementById('dot');
                const statusText = document.getElementById('status-text');
                const btnConnect = document.getElementById('btn-connect');
                const btnDisconn = document.getElementById('btn-disconnect');
                const btnTrigger = document.getElementById('btn-trigger');
                const log        = document.getElementById('log');

                function setStatus(state, text) {
                    dot.className = 'dot ' + state;
                    statusText.textContent = text;
                }

                function addLog(entry, isSystem = false) {
                    const empty = log.querySelector('.empty-log');
                    if (empty) empty.remove();

                    const d = isSystem ? { severity: 'system', type: '—', message: entry, timestamp: new Date().toISOString() } : entry;
                    const time = new Date(d.timestamp).toLocaleTimeString();
                    const badgeClass = 'badge-' + (d.severity === 'system' ? 'info' : d.severity);

                    const el = document.createElement('div');
                    el.className = 'log-entry ' + d.severity;
                    el.innerHTML =
                        '<span class="log-time">' + time + '</span>' +
                        '<span class="log-badge ' + badgeClass + '">' + d.severity + '</span>' +
                        '<span class="log-type">' + d.type + '</span>' +
                        (d.containerName ? '<span style="color:var(--dim)">[<span style="color:var(--blue)">' + d.containerName + '</span>]</span> ' : '') +
                        d.message;
                    log.appendChild(el);
                    log.scrollTop = log.scrollHeight;
                }

                function connect() {
                    if (ws) ws.close();
                    setStatus('connecting', 'Connecting…');
                    btnConnect.disabled = true;

                    ws = new WebSocket(WS_URL);

                    ws.onopen = () => {
                        setStatus('connected', 'Connected');
                        btnConnect.disabled = true;
                        btnDisconn.disabled = false;
                        addLog('WebSocket connected', true);
                    };

                    ws.onmessage = (e) => {
                        try {
                            addLog(JSON.parse(e.data));
                        } catch {
                            addLog(e.data, true);
                        }
                    };

                    ws.onerror = () => {
                        setStatus('error', 'Connection error');
                        addLog('WebSocket error', true);
                    };

                    ws.onclose = (e) => {
                        setStatus('', 'Disconnected (code ' + e.code + ')');
                        btnConnect.disabled = false;
                        btnDisconn.disabled = true;
                        ws = null;
                        addLog('WebSocket closed (code ' + e.code + ')', true);
                    };
                }

                btnConnect.addEventListener('click', connect);
                btnDisconn.addEventListener('click', () => ws && ws.close());

                btnTrigger.addEventListener('click', async () => {
                    btnTrigger.disabled = true;
                    try {
                        const res = await fetch('/notifications/test', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Authorization': 'Bearer ' + JWT,
                            },
                            body: JSON.stringify({
                                type:     document.getElementById('sel-type').value,
                                severity: document.getElementById('sel-severity').value,
                                message:  document.getElementById('inp-message').value,
                            }),
                        });
                        const data = await res.json();
                        if (!res.ok) addLog('Trigger error: ' + JSON.stringify(data), true);
                    } finally {
                        btnTrigger.disabled = false;
                    }
                });

                // Auto-connect on load
                connect();
            </script>
        </body>
        </html>
        HTML;

        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html');
    }
}
