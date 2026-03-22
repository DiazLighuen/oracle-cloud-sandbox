<?php
declare(strict_types=1);

namespace App\Application\Actions\Dashboard;

use App\Infrastructure\Docker\DockerMetricsService;
use App\Infrastructure\I18n\PhpTranslator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class DashboardAction
{
    public function __construct(private DockerMetricsService $docker) {}

    public function __invoke(Request $request, Response $response): Response
    {
        /** @var PhpTranslator $tr */
        $tr   = $request->getAttribute('translator');
        $lang = $tr->getLang();
        $t    = fn(string $k) => htmlspecialchars($tr->t($k));

        $name   = htmlspecialchars($request->getAttribute('user_name') ?? '?');
        $email  = htmlspecialchars($request->getAttribute('user_email') ?? '');
        $avatar = htmlspecialchars($request->getAttribute('user_avatar') ?? '');

        $initial      = mb_substr($name, 0, 1);
        $avatarHtml   = $this->avatarHtml($avatar, $initial, 'u-avatar', 'u-avatar u-initials');
        $avatarLgHtml = $this->avatarHtml($avatar, $initial, 'pp-avatar-lg', 'pp-avatar-lg initials');

        $metrics    = $this->docker->getMetrics();
        $langBar    = $this->langBar($lang);
        $ytQuota    = $this->fetchYtQuota();
        $totalsHtml = $this->buildTotals($metrics, $tr, $ytQuota);
        $cardsHtml  = $this->buildCards($metrics, $tr);

        $tTitle      = $t('metrics.title');
        $tLogout     = $t('dashboard.logout');
        $tNavDash    = $t('nav.dashboard');
        $tNavUsers   = $t('nav.users');
        $tProfile    = $t('nav.profile');

        $html = <<<HTML
        <!DOCTYPE html>
        <html lang="{$lang}">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>{$tTitle} — Container Sandbox</title>
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&family=Syne:wght@400;600;700;800&display=swap" rel="stylesheet">
            <style>
                *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

                :root {
                    --bg:        #0b0d14;
                    --surface:   #12151f;
                    --surface2:  #191d2e;
                    --border:    #1e2235;
                    --border-h:  #2d3458;
                    --accent:    #3b4fd8;
                    --accent-lo: #1e2760;
                    --blue:      #60a5fa;
                    --dim:       #4a5580;
                    --text:      #c8d0e8;
                    --white:     #eef2ff;
                    --green:     #22c55e;
                    --yellow:    #f59e0b;
                    --red:       #ef4444;
                    --sidebar-w: 240px;
                }

                html, body { height: 100%; background: var(--bg); color: var(--text);
                    font-family: 'Syne', sans-serif; overflow: hidden; }

                /* ── Grid bg ──────────────────────────────────────── */
                body::before { content: ''; position: fixed; inset: 0; z-index: 0;
                    background-image: linear-gradient(var(--border) 1px, transparent 1px),
                        linear-gradient(90deg, var(--border) 1px, transparent 1px);
                    background-size: 48px 48px;
                    mask-image: radial-gradient(ellipse 100% 60% at 20% 0%, black 20%, transparent 100%);
                    pointer-events: none; }

                /* ── App shell ────────────────────────────────────── */
                .app { position: relative; z-index: 1; display: flex; height: 100vh; }

                /* ── Sidebar ──────────────────────────────────────── */
                .sidebar { width: var(--sidebar-w); flex-shrink: 0; display: flex;
                    flex-direction: column; background: var(--surface);
                    border-right: 1px solid var(--border); height: 100vh;
                    position: sticky; top: 0; overflow-y: auto; }

                .sidebar-top { padding: 24px 16px 20px; border-bottom: 1px solid var(--border); }

                .brand { font-family: 'JetBrains Mono', monospace; font-size: 11px;
                    color: var(--dim); letter-spacing: .06em; margin-bottom: 20px; }
                .brand span { color: var(--blue); }

                .u-card { display: flex; align-items: center; gap: 10px; }
                .u-avatar { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
                .u-initials { background: var(--accent); font-size: 15px; font-weight: 700;
                    color: #fff; display: flex; align-items: center; justify-content: center; }
                .u-info { min-width: 0; }
                .u-name { font-size: 13px; font-weight: 600; color: var(--white);
                    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
                .u-email { font-family: 'JetBrains Mono', monospace; font-size: 10px;
                    color: var(--dim); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

                /* Nav */
                .sidebar-nav { flex: 1; padding: 12px 8px; }

                .nav-section { font-family: 'JetBrains Mono', monospace; font-size: 10px;
                    color: var(--dim); letter-spacing: .1em; text-transform: uppercase;
                    padding: 8px 8px 6px; }

                .nav-item { display: flex; align-items: center; gap: 10px;
                    padding: 9px 12px; border-radius: 8px; text-decoration: none;
                    font-size: 13px; font-weight: 600; color: var(--text);
                    transition: background .15s, color .15s; cursor: pointer; border: none;
                    background: none; width: 100%; }
                .nav-item:hover { background: var(--surface2); }
                .nav-item.active { background: var(--accent-lo); color: var(--blue); }
                .nav-item svg { flex-shrink: 0; opacity: .7; }
                .nav-item.active svg { opacity: 1; }

                /* Sidebar bottom */
                .sidebar-bottom { padding: 12px 8px 16px; border-top: 1px solid var(--border); }

                .lang-row { display: flex; gap: 4px; padding: 4px 4px 12px; }
                .lang-btn { font-family: 'JetBrains Mono', monospace; font-size: 10px;
                    font-weight: 500; letter-spacing: .06em; color: var(--dim);
                    text-decoration: none; padding: 4px 8px; border-radius: 4px;
                    transition: background .15s, color .15s; }
                .lang-btn:hover { color: var(--text); }
                .lang-btn.active { background: var(--accent-lo); color: var(--blue); }

                .logout-btn { display: flex; align-items: center; gap: 10px;
                    padding: 9px 12px; border-radius: 8px; text-decoration: none;
                    font-size: 13px; font-weight: 600; color: var(--dim);
                    transition: background .15s, color .15s; width: 100%; border: none; background: none; cursor: pointer; }
                .logout-btn:hover { background: rgba(239,68,68,.08); color: var(--red); }

                /* ── Main ─────────────────────────────────────────── */
                .main { flex: 1; overflow-y: auto; display: flex; flex-direction: column;
                    min-width: 0; }

                .topbar { display: flex; align-items: center; justify-content: space-between;
                    padding: 20px 32px; border-bottom: 1px solid var(--border);
                    position: sticky; top: 0; background: var(--bg); z-index: 10; }

                .page-title { font-size: 18px; font-weight: 800; color: var(--white);
                    letter-spacing: -.02em; }

                .refresh-btn { display: flex; align-items: center; gap: 6px;
                    font-family: 'JetBrains Mono', monospace; font-size: 11px;
                    color: var(--dim); background: var(--surface); border: 1px solid var(--border);
                    padding: 6px 12px; border-radius: 6px; cursor: pointer;
                    transition: border-color .2s, color .2s; }
                .refresh-btn:hover { border-color: var(--border-h); color: var(--text); }

                /* ── Totals bar ───────────────────────────────────── */
                .totals-bar { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; }

                .total-card { background: var(--surface); border: 1px solid var(--border);
                    border-radius: 10px; padding: 12px 16px; flex: 1; min-width: 130px; }
                .total-label { font-family: 'JetBrains Mono', monospace; font-size: 9px;
                    color: var(--dim); text-transform: uppercase; letter-spacing: .1em; margin-bottom: 6px; }
                .total-val { font-family: 'JetBrains Mono', monospace; font-size: 14px;
                    font-weight: 500; color: var(--white); margin-bottom: 6px; }
                .total-sub { font-family: 'JetBrains Mono', monospace; font-size: 10px; color: var(--dim); }

                /* ── Container grid ───────────────────────────────── */
                .content { padding: 28px 32px; }

                .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
                    gap: 16px; }

                /* ── Container card ───────────────────────────────── */
                .ccard { background: var(--surface); border: 1px solid var(--border);
                    border-radius: 14px; padding: 20px; transition: border-color .2s; }
                .ccard:hover { border-color: var(--border-h); }

                .ccard-head { display: flex; align-items: flex-start;
                    justify-content: space-between; margin-bottom: 18px; gap: 8px; }

                .ccard-name { font-size: 14px; font-weight: 700; color: var(--white);
                    margin-bottom: 3px; }
                .ccard-image { font-family: 'JetBrains Mono', monospace; font-size: 10px;
                    color: var(--dim); }

                .status-badge { display: inline-flex; align-items: center; gap: 5px;
                    font-family: 'JetBrains Mono', monospace; font-size: 10px;
                    padding: 3px 8px; border-radius: 100px; white-space: nowrap; }
                .status-badge.running { background: rgba(34,197,94,.12); color: var(--green); }
                .status-badge.stopped { background: rgba(239,68,68,.1);  color: var(--red); }
                .status-dot { width: 5px; height: 5px; border-radius: 50%; background: currentColor; }

                /* Metric row */
                .metric { margin-bottom: 14px; }
                .metric:last-child { margin-bottom: 0; }

                .metric-head { display: flex; justify-content: space-between;
                    align-items: baseline; margin-bottom: 5px; }
                .metric-label { font-family: 'JetBrains Mono', monospace; font-size: 10px;
                    color: var(--dim); text-transform: uppercase; letter-spacing: .06em; }
                .metric-val { font-family: 'JetBrains Mono', monospace; font-size: 11px;
                    color: var(--text); }

                .bar-track { height: 4px; background: var(--border); border-radius: 2px; overflow: hidden; }
                .bar-fill { height: 100%; border-radius: 2px; transition: width .4s ease; }
                .bar-fill.low    { background: var(--blue); }
                .bar-fill.medium { background: var(--yellow); }
                .bar-fill.high   { background: var(--red); }

                /* IO row */
                .io-row { display: flex; gap: 12px; }
                .io-cell { flex: 1; background: var(--bg); border: 1px solid var(--border);
                    border-radius: 6px; padding: 8px 10px; }
                .io-label { font-family: 'JetBrains Mono', monospace; font-size: 9px;
                    color: var(--dim); text-transform: uppercase; letter-spacing: .06em;
                    margin-bottom: 3px; }
                .io-val { font-family: 'JetBrains Mono', monospace; font-size: 12px; color: var(--text); }

                /* Divider */
                .metric-divider { border: none; border-top: 1px solid var(--border); margin: 14px 0; }

                /* Empty / error states */
                .state-msg { display: flex; flex-direction: column; align-items: center;
                    justify-content: center; padding: 80px 24px; text-align: center; gap: 12px;
                    color: var(--dim); font-family: 'JetBrains Mono', monospace; font-size: 13px; }
                .state-msg svg { opacity: .3; }

                /* ── Profile panel ────────────────────────────────── */
                .profile-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.5);
                    z-index: 100; opacity: 0; pointer-events: none; transition: opacity .2s; }
                .profile-overlay.open { opacity: 1; pointer-events: all; }

                .profile-panel { position: fixed; right: 0; top: 0; bottom: 0; width: 300px;
                    background: var(--surface); border-left: 1px solid var(--border);
                    z-index: 101; transform: translateX(100%); transition: transform .25s ease;
                    display: flex; flex-direction: column; overflow: hidden; }
                .profile-panel.open { transform: translateX(0); }

                .pp-head { display: flex; align-items: center; justify-content: space-between;
                    padding: 20px; border-bottom: 1px solid var(--border); flex-shrink: 0; }
                .pp-title { font-size: 13px; font-weight: 600; color: var(--white); }
                .pp-close { background: none; border: none; cursor: pointer; color: var(--dim);
                    padding: 4px; border-radius: 4px; transition: color .15s; line-height: 0; }
                .pp-close:hover { color: var(--text); }

                .pp-avatar-section { display: flex; flex-direction: column; align-items: center;
                    padding: 32px 20px 24px; border-bottom: 1px solid var(--border); gap: 10px;
                    flex-shrink: 0; }
                .pp-avatar-lg { width: 72px; height: 72px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
                .pp-avatar-lg.initials { background: var(--accent); font-size: 28px; font-weight: 700;
                    color: #fff; display: flex; align-items: center; justify-content: center; }
                .pp-name  { font-size: 16px; font-weight: 700; color: var(--white); text-align: center; }
                .pp-email { font-family: 'JetBrains Mono', monospace; font-size: 11px;
                    color: var(--dim); text-align: center; }

                .pp-body { flex: 1; padding: 20px; overflow-y: auto; }
                .pp-section-label { font-family: 'JetBrains Mono', monospace; font-size: 10px;
                    color: var(--dim); letter-spacing: .1em; text-transform: uppercase; margin-bottom: 12px; }
                .pp-placeholder { background: var(--surface2); border: 1px dashed var(--border);
                    border-radius: 8px; padding: 32px 20px; text-align: center;
                    font-family: 'JetBrains Mono', monospace; font-size: 11px; color: var(--dim); }

                /* Make user card in sidebar clickable */
                .u-card { cursor: pointer; border-radius: 8px; padding: 6px; margin: -6px;
                    transition: background .15s; }
                .u-card:hover { background: var(--surface2); }

                /* Mobile sidebar toggle */
                .burger { display: none; background: none; border: none; cursor: pointer;
                    color: var(--text); padding: 4px; }

                @media (max-width: 768px) {
                    .sidebar { position: fixed; left: 0; top: 0; bottom: 0; z-index: 50;
                        transform: translateX(-100%); transition: transform .25s ease; }
                    .sidebar.open { transform: translateX(0); box-shadow: 4px 0 32px rgba(0,0,0,.6); }
                    .burger { display: flex; }
                    .content { padding: 20px 16px; }
                    .topbar { padding: 16px 20px; }
                }
            </style>
        </head>
        <body>
        <div class="app">

            <!-- Sidebar -->
            <aside class="sidebar" id="sidebar">
                <div class="sidebar-top">
                    <div class="brand">~/<span>container-sandbox</span></div>
                    <div class="u-card" onclick="openProfile()" role="button" aria-label="Open profile">
                        {$avatarHtml}
                        <div class="u-info">
                            <div class="u-name">{$name}</div>
                            <div class="u-email">{$email}</div>
                        </div>
                    </div>
                </div>

                <nav class="sidebar-nav">
                    <div class="nav-section">// menu</div>
                    <a href="/dashboard" class="nav-item active">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                            <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                        </svg>
                        {$tNavDash}
                    </a>
                    <a href="/users" class="nav-item">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                        {$tNavUsers}
                    </a>
                    <a href="/youtube" class="nav-item">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22.54 6.42a2.78 2.78 0 0 0-1.95-1.96C18.88 4 12 4 12 4s-6.88 0-8.59.46A2.78 2.78 0 0 0 1.46 6.42 29 29 0 0 0 1 12a29 29 0 0 0 .46 5.58 2.78 2.78 0 0 0 1.95 1.96C5.12 20 12 20 12 20s6.88 0 8.59-.46a2.78 2.78 0 0 0 1.95-1.96A29 29 0 0 0 23 12a29 29 0 0 0-.46-5.58z"/>
                            <polygon points="9.75 15.02 15.5 12 9.75 8.98 9.75 15.02"/>
                        </svg>
                        YouTube
                    </a>
                </nav>

                <div class="sidebar-bottom">
                    <div class="lang-row">{$langBar}</div>
                    <a href="/auth/logout" class="logout-btn">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                            <polyline points="16 17 21 12 16 7"/>
                            <line x1="21" y1="12" x2="9" y2="12"/>
                        </svg>
                        {$tLogout}
                    </a>
                </div>
            </aside>

            <!-- Main -->
            <div class="main">
                <div class="topbar">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <button class="burger" id="burger" onclick="toggleSidebar()" aria-label="menu">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                                <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
                            </svg>
                        </button>
                        <span class="page-title">{$tTitle}</span>
                    </div>
                    <button class="refresh-btn" onclick="location.reload()">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="23 4 23 10 17 10"/>
                            <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
                        </svg>
                        Refresh
                    </button>
                </div>

                <div class="content">
                    {$totalsHtml}
                    <div class="grid">{$cardsHtml}</div>
                </div>
            </div>
        </div>

        <!-- Profile panel -->
        <div class="profile-overlay" id="profileOverlay" onclick="closeProfile()"></div>
        <div class="profile-panel" id="profilePanel">
            <div class="pp-head">
                <span class="pp-title">{$tProfile}</span>
                <button class="pp-close" onclick="closeProfile()" aria-label="Close">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            <div class="pp-avatar-section">
                {$avatarLgHtml}
                <div class="pp-name">{$name}</div>
                <div class="pp-email">{$email}</div>
            </div>
            <div class="pp-body">
                <div class="pp-section-label">// info</div>
                <div class="pp-placeholder">— coming soon —</div>
            </div>
        </div>

        <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
        }
        function openProfile() {
            document.getElementById('profileOverlay').classList.add('open');
            document.getElementById('profilePanel').classList.add('open');
        }
        function closeProfile() {
            document.getElementById('profileOverlay').classList.remove('open');
            document.getElementById('profilePanel').classList.remove('open');
        }
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeProfile();
        });
        document.addEventListener('click', function(e) {
            const sb = document.getElementById('sidebar');
            if (sb.classList.contains('open') && !sb.contains(e.target) && !document.getElementById('burger').contains(e.target)) {
                sb.classList.remove('open');
            }
        });
        // Prevent reload when clicking the already-active nav item
        document.querySelectorAll('.nav-item.active').forEach(function(link) {
            link.addEventListener('click', function(e) { e.preventDefault(); });
        });
        </script>
        </body>
        </html>
        HTML;

        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html');
    }

    private function buildCards(array $metrics, PhpTranslator $tr): string
    {
        $t = fn(string $k) => htmlspecialchars($tr->t($k));

        if (!$metrics['available']) {
            return <<<HTML
            <div class="state-msg" style="grid-column:1/-1">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
                    <rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-4 0v2"/><line x1="12" y1="12" x2="12" y2="16"/>
                </svg>
                <span>{$t('metrics.no_socket')}</span>
            </div>
            HTML;
        }

        if (empty($metrics['containers'])) {
            return "<div class=\"state-msg\" style=\"grid-column:1/-1\"><span>{$t('metrics.no_data')}</span></div>";
        }

        $html = '';
        foreach ($metrics['containers'] as $c) {
            $statusClass = $c['running'] ? 'running' : 'stopped';
            $statusLabel = $c['running'] ? $t('metrics.running') : $t('metrics.stopped');
            $name        = htmlspecialchars($c['name']);
            $image       = htmlspecialchars($c['image']);
            $status      = htmlspecialchars($c['status']);

            $cpuPct  = $c['cpu_pct'];
            $memPct  = $c['mem_pct'];
            $cpuBar  = $this->barClass($cpuPct);
            $memBar  = $this->barClass($memPct);

            $memUsed  = DockerMetricsService::fmt($c['mem_rss']);
            $memTotal = DockerMetricsService::fmt($c['mem_limit']);
            $tOf      = $t('metrics.of');

            $netRx = DockerMetricsService::fmt($c['net_rx']);
            $netTx = DockerMetricsService::fmt($c['net_tx']);
            $blkR  = DockerMetricsService::fmt($c['blk_read']);
            $blkW  = DockerMetricsService::fmt($c['blk_write']);

            $pids = $c['pids'];

            $tCpu  = $t('metrics.cpu');
            $tMem  = $t('metrics.memory');
            $tNet  = $t('metrics.network');
            $tDisk = $t('metrics.disk');
            $tProc = $t('metrics.processes');
            $tRx   = $t('metrics.rx');
            $tTx   = $t('metrics.tx');
            $tRead = $t('metrics.read');
            $tWrt  = $t('metrics.write');

            $html .= <<<HTML
            <div class="ccard">
                <div class="ccard-head">
                    <div>
                        <div class="ccard-name">{$name}</div>
                        <div class="ccard-image">{$image}</div>
                    </div>
                    <span class="status-badge {$statusClass}">
                        <span class="status-dot"></span>{$statusLabel}
                    </span>
                </div>

                <div class="metric">
                    <div class="metric-head">
                        <span class="metric-label">{$tCpu}</span>
                        <span class="metric-val">{$cpuPct}%</span>
                    </div>
                    <div class="bar-track"><div class="bar-fill {$cpuBar}" style="width:{$cpuPct}%"></div></div>
                </div>

                <div class="metric">
                    <div class="metric-head">
                        <span class="metric-label">{$tMem}</span>
                        <span class="metric-val">{$memUsed} {$tOf} {$memTotal} &middot; {$memPct}%</span>
                    </div>
                    <div class="bar-track"><div class="bar-fill {$memBar}" style="width:{$memPct}%"></div></div>
                </div>

                <hr class="metric-divider">

                <div class="metric">
                    <div class="metric-label" style="margin-bottom:6px">{$tNet}</div>
                    <div class="io-row">
                        <div class="io-cell"><div class="io-label">↓ {$tRx}</div><div class="io-val">{$netRx}</div></div>
                        <div class="io-cell"><div class="io-label">↑ {$tTx}</div><div class="io-val">{$netTx}</div></div>
                    </div>
                </div>

                <div class="metric" style="margin-top:10px">
                    <div class="metric-label" style="margin-bottom:6px">{$tDisk}</div>
                    <div class="io-row">
                        <div class="io-cell"><div class="io-label">{$tRead}</div><div class="io-val">{$blkR}</div></div>
                        <div class="io-cell"><div class="io-label">{$tWrt}</div><div class="io-val">{$blkW}</div></div>
                    </div>
                </div>

                <hr class="metric-divider">

                <div class="metric-head">
                    <span class="metric-label">{$tProc}</span>
                    <span class="metric-val">{$pids}</span>
                </div>
            </div>
            HTML;
        }

        return $html;
    }

    private function barClass(float $pct): string
    {
        return match (true) {
            $pct >= 80 => 'high',
            $pct >= 50 => 'medium',
            default    => 'low',
        };
    }

    private function langBar(string $current): string
    {
        $langs = ['en' => 'EN', 'es' => 'ES', 'de' => 'DE'];
        $html  = '';
        foreach ($langs as $code => $label) {
            $active = $code === $current ? ' active' : '';
            $html  .= "<a href=\"/lang/{$code}\" class=\"lang-btn{$active}\">{$label}</a>";
        }
        return $html;
    }

    private function buildTotals(array $metrics, PhpTranslator $tr, ?array $ytQuota = null): string
    {
        if (!$metrics['available'] || empty($metrics['containers'])) {
            return '';
        }
        $t   = fn(string $k) => htmlspecialchars($tr->t($k));
        $cs  = $metrics['containers'];
        $run = count(array_filter($cs, fn($c) => $c['running']));
        $tot = count($cs);
        $cpu = round(array_sum(array_column($cs, 'cpu_pct')), 1);
        $memU = array_sum(array_column($cs, 'mem_rss'));
        $memL = array_sum(array_column($cs, 'mem_limit'));
        $memP = $memL > 0 ? round($memU / $memL * 100, 1) : 0;
        $cpuBar = $this->barClass(min($cpu, 100));
        $memBar = $this->barClass($memP);
        $fMemU = DockerMetricsService::fmt($memU);
        $fMemL = DockerMetricsService::fmt($memL);
        $fRx   = DockerMetricsService::fmt(array_sum(array_column($cs, 'net_rx')));
        $fTx   = DockerMetricsService::fmt(array_sum(array_column($cs, 'net_tx')));
        $fBlkR = DockerMetricsService::fmt(array_sum(array_column($cs, 'blk_read')));
        $fBlkW = DockerMetricsService::fmt(array_sum(array_column($cs, 'blk_write')));
        $tContainers = $t('metrics.containers');
        $tCpu  = $t('metrics.cpu');
        $tMem  = $t('metrics.memory');
        $tNet  = $t('metrics.network');
        $tDisk = $t('metrics.disk');
        $tRun  = $t('metrics.running');
        $tOf   = $t('metrics.of');
        $cpuW  = min($cpu, 100);
        $ytQuotaHtml = '';
        if ($ytQuota !== null) {
            $qUsed  = number_format($ytQuota['used']);
            $qLimit = number_format($ytQuota['limit']);
            $qPct   = min((float)$ytQuota['percent'], 100);
            $qBar   = $this->barClass($qPct);
            $ytQuotaHtml = <<<QUOTA
            <div class="total-card">
                <div class="total-label">YT Quota</div>
                <div class="total-val">{$qUsed} / {$qLimit}</div>
                <div class="bar-track" style="margin-top:4px"><div class="bar-fill {$qBar}" style="width:{$qPct}%"></div></div>
            </div>
            QUOTA;
        }
        return <<<HTML
        <div class="totals-bar">
            <div class="total-card">
                <div class="total-label">{$tContainers}</div>
                <div class="total-val">{$run}/{$tot}</div>
                <div class="total-sub">{$tRun}</div>
            </div>
            <div class="total-card">
                <div class="total-label">{$tCpu}</div>
                <div class="total-val">{$cpu}%</div>
                <div class="bar-track" style="margin-top:4px"><div class="bar-fill {$cpuBar}" style="width:{$cpuW}%"></div></div>
            </div>
            <div class="total-card">
                <div class="total-label">{$tMem}</div>
                <div class="total-val">{$fMemU} {$tOf} {$fMemL}</div>
                <div class="bar-track" style="margin-top:4px"><div class="bar-fill {$memBar}" style="width:{$memP}%"></div></div>
            </div>
            <div class="total-card">
                <div class="total-label">{$tNet}</div>
                <div class="total-val">↓{$fRx}</div>
                <div class="total-sub">↑{$fTx}</div>
            </div>
            <div class="total-card">
                <div class="total-label">{$tDisk}</div>
                <div class="total-val">R:{$fBlkR}</div>
                <div class="total-sub">W:{$fBlkW}</div>
            </div>
            {$ytQuotaHtml}
        </div>
        HTML;
    }

    private function fetchYtQuota(): ?array
    {
        $url = 'http://youtube_svc:8000/quota';
        $ctx = stream_context_create(['http' => ['timeout' => 2, 'ignore_errors' => true]]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) return null;
        $data = json_decode($raw, true);
        return is_array($data) && isset($data['used']) ? $data : null;
    }

    private function avatarHtml(string $avatar, string $initial, string $imgClass, string $divClass): string
    {
        if ($avatar) {
            return "<img src=\"{$avatar}\" alt=\"\" class=\"{$imgClass}\""
                 . " onerror=\"this.style.display='none';this.nextElementSibling.style.display='flex';\">"
                 . "<div class=\"{$divClass}\" style=\"display:none\">{$initial}</div>";
        }
        return "<div class=\"{$divClass}\">{$initial}</div>";
    }
}
