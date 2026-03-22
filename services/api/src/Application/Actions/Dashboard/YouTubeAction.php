<?php
declare(strict_types=1);

namespace App\Application\Actions\Dashboard;

use App\Domain\User\UserRepository;
use App\Infrastructure\I18n\PhpTranslator;
use Google\Client as GoogleClient;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class YouTubeAction
{
    public function __construct(
        private UserRepository $users,
        private GoogleClient   $googleClient
    ) {}

    public function __invoke(Request $request, Response $response): Response
    {
        /** @var PhpTranslator $tr */
        $tr   = $request->getAttribute('translator');
        $lang = $tr->getLang();

        $name    = htmlspecialchars($request->getAttribute('user_name') ?? '?');
        $email   = htmlspecialchars($request->getAttribute('user_email') ?? '');
        $avatar  = htmlspecialchars($request->getAttribute('user_avatar') ?? '');
        $initial = mb_substr($name, 0, 1);

        // JWT from cookie — injected into JS so the browser can send Bearer auth
        $jwt = htmlspecialchars($request->getCookieParams()['jwt'] ?? '');

        // Resolve a fresh Google access token from the stored OAuth token
        $userId            = $request->getAttribute('user_id');
        $googleAccessToken = $this->resolveGoogleAccessToken($userId);

        $queryParams = $request->getQueryParams();
        $ytAuth      = $queryParams['yt_auth'] ?? '';

        // Auto-redirect to OAuth if no token — unless we just came back from a failed auth attempt
        if ($googleAccessToken === '' && !in_array($ytAuth, ['denied', 'error'], true)) {
            return $response
                ->withHeader('Location', '/auth/youtube')
                ->withStatus(302);
        }

        // Safe JS values — inside single-quoted JS strings
        $jsJwt   = addslashes($jwt);
        $jsToken = addslashes($googleAccessToken);

        $avatarHtml   = $this->avatarHtml($avatar, $initial, 'u-avatar', 'u-avatar u-initials');
        $avatarLgHtml = $this->avatarHtml($avatar, $initial, 'pp-avatar-lg', 'pp-avatar-lg initials');

        $langBar = $this->langBar($lang);

        // Debug token display (first 40 chars — remove this card in production)
        $tokenPreview = $googleAccessToken !== ''
            ? htmlspecialchars(substr($googleAccessToken, 0, 40)) . '…'
            : '(none)';

        $authBanner = '';
        if ($ytAuth === 'ok') {
            $authBanner = '<div class="auth-banner ok">&#10003; YouTube connected successfully.</div>';
        } elseif ($ytAuth === 'denied') {
            $authBanner = '<div class="auth-banner warn">&#9888; Authorization denied. '
                . '<a href="/auth/youtube">Try again</a></div>';
        } elseif ($ytAuth === 'error') {
            $authBanner = '<div class="auth-banner err">&#10007; Something went wrong. '
                . '<a href="/auth/youtube">Try again</a></div>';
        }

        $html = <<<HTML
        <!DOCTYPE html>
        <html lang="{$lang}">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>YouTube Debug — Container Sandbox</title>
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
                    --yt-red:    #ff4444;
                }

                @keyframes spin { to { transform: rotate(360deg); } }
                html, body { height: 100%; background: var(--bg); color: var(--text);
                    font-family: 'Syne', sans-serif; overflow: hidden; }

                body::before { content: ''; position: fixed; inset: 0; z-index: 0;
                    background-image: linear-gradient(var(--border) 1px, transparent 1px),
                        linear-gradient(90deg, var(--border) 1px, transparent 1px);
                    background-size: 48px 48px;
                    mask-image: radial-gradient(ellipse 100% 60% at 20% 0%, black 20%, transparent 100%);
                    pointer-events: none; }

                .app { position: relative; z-index: 1; display: flex; height: 100vh; }

                /* ── Sidebar ── */
                .sidebar { width: var(--sidebar-w); flex-shrink: 0; display: flex;
                    flex-direction: column; background: var(--surface);
                    border-right: 1px solid var(--border); height: 100vh;
                    position: sticky; top: 0; overflow-y: auto; }
                .sidebar-top { padding: 24px 16px 20px; border-bottom: 1px solid var(--border); }
                .brand { font-family: 'JetBrains Mono', monospace; font-size: 11px;
                    color: var(--dim); letter-spacing: .06em; margin-bottom: 20px; }
                .brand span { color: var(--blue); }
                .u-card { display: flex; align-items: center; gap: 10px;
                    cursor: pointer; border-radius: 8px; padding: 6px; margin: -6px;
                    transition: background .15s; }
                .u-card:hover { background: var(--surface2); }
                .u-avatar { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
                .u-initials { background: var(--accent); font-size: 15px; font-weight: 700;
                    color: #fff; display: flex; align-items: center; justify-content: center; }
                .u-info { min-width: 0; }
                .u-name { font-size: 13px; font-weight: 600; color: var(--white);
                    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
                .u-email { font-family: 'JetBrains Mono', monospace; font-size: 10px;
                    color: var(--dim); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
                .sidebar-nav { flex: 1; padding: 12px 8px; }
                .nav-section { font-family: 'JetBrains Mono', monospace; font-size: 10px;
                    color: var(--dim); letter-spacing: .1em; text-transform: uppercase;
                    padding: 8px 8px 6px; }
                .nav-item { display: flex; align-items: center; gap: 10px;
                    padding: 9px 12px; border-radius: 8px; text-decoration: none;
                    font-size: 13px; font-weight: 600; color: var(--text);
                    transition: background .15s, color .15s; }
                .nav-item:hover { background: var(--surface2); }
                .nav-item.active { background: var(--accent-lo); color: var(--blue); pointer-events: none; }
                .nav-item svg { flex-shrink: 0; opacity: .7; }
                .nav-item.active svg { opacity: 1; }
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
                    transition: background .15s, color .15s; width: 100%; border: none;
                    background: none; cursor: pointer; }
                .logout-btn:hover { background: rgba(239,68,68,.08); color: var(--red); }

                /* ── Main ── */
                .main { flex: 1; overflow-y: auto; display: flex; flex-direction: column; min-width: 0; }
                .topbar { display: flex; align-items: center; justify-content: space-between;
                    padding: 20px 32px; border-bottom: 1px solid var(--border);
                    position: sticky; top: 0; background: var(--bg); z-index: 10; }
                .page-title { font-size: 18px; font-weight: 800; color: var(--white);
                    letter-spacing: -.02em; display: flex; align-items: center; gap: 10px; }
                .yt-badge { background: rgba(255,68,68,.15); color: var(--yt-red);
                    font-family: 'JetBrains Mono', monospace; font-size: 10px; font-weight: 500;
                    padding: 3px 8px; border-radius: 4px; letter-spacing: .06em; }
                .burger { display: none; background: none; border: none; cursor: pointer;
                    color: var(--text); padding: 4px; }
                @media (max-width: 768px) {
                    .sidebar { position: fixed; left: 0; top: 0; bottom: 0; z-index: 50;
                        transform: translateX(-100%); transition: transform .25s ease; }
                    .sidebar.open { transform: translateX(0); box-shadow: 4px 0 32px rgba(0,0,0,.6); }
                    .burger { display: flex; }
                    .topbar { padding: 16px 20px; }
                    .content { padding: 16px; }
                }

                /* ── Content ── */
                .content { padding: 28px 32px; display: flex; flex-direction: column; gap: 20px; }

                /* Auth banners */
                .auth-banner { font-family: 'JetBrains Mono', monospace; font-size: 11px;
                    padding: 10px 16px; border-radius: 8px; }
                .auth-banner.ok   { background: rgba(34,197,94,.1);  color: var(--green); }
                .auth-banner.warn { background: rgba(245,158,11,.1); color: var(--yellow); }
                .auth-banner.err  { background: rgba(239,68,68,.1);  color: var(--red); }
                .auth-banner a { color: inherit; }

                /* Debug token card — remove in production */
                .debug-card { background: var(--surface); border: 1px solid var(--border);
                    border-radius: 10px; padding: 12px 16px;
                    display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
                .debug-label { font-family: 'JetBrains Mono', monospace; font-size: 10px;
                    color: var(--dim); text-transform: uppercase; letter-spacing: .08em;
                    white-space: nowrap; }
                .debug-token { font-family: 'JetBrains Mono', monospace; font-size: 11px;
                    color: var(--blue); flex: 1; word-break: break-all; }
                .reconnect-link { font-family: 'JetBrains Mono', monospace; font-size: 10px;
                    color: var(--dim); text-decoration: none; white-space: nowrap;
                    padding: 4px 10px; border: 1px solid var(--border); border-radius: 6px;
                    transition: border-color .15s, color .15s; }
                .reconnect-link:hover { border-color: var(--border-h); color: var(--text); }

                /* Live cards */
                .live-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 16px; }
                .live-card { cursor: pointer; border-radius: 8px; overflow: hidden;
                    background: var(--surface); border: 1px solid var(--border);
                    transition: border-color .15s, transform .15s; }
                .live-card:hover { border-color: var(--border-h); transform: translateY(-2px); }
                .live-thumb { position: relative; aspect-ratio: 16/9; overflow: hidden; background: #111; }
                .live-thumb img { width: 100%; height: 100%; object-fit: cover; }
                .live-badge { position: absolute; top: 6px; left: 6px; display: flex;
                    align-items: center; gap: 4px; background: #e00; color: #fff;
                    font-family: 'JetBrains Mono', monospace; font-size: 10px; font-weight: 700;
                    padding: 2px 6px; border-radius: 3px; letter-spacing: .06em; }
                .live-dot { width: 6px; height: 6px; border-radius: 50%; background: #fff;
                    animation: live-pulse 1.4s ease-in-out infinite; }
                @keyframes live-pulse { 0%,100% { opacity: 1; } 50% { opacity: .25; } }
                .live-viewers { position: absolute; bottom: 6px; left: 6px;
                    background: rgba(0,0,0,.75); color: #fff;
                    font-family: 'JetBrains Mono', monospace; font-size: 10px;
                    padding: 2px 6px; border-radius: 3px; }
                /* Platform badge — top-right of thumbnail.
                   .platform-twitch (#9147ff) reserved for future Twitch integration. */
                .platform-badge { position: absolute; top: 6px; right: 6px; width: 20px; height: 20px;
                    border-radius: 4px; display: flex; align-items: center; justify-content: center;
                    font-family: 'JetBrains Mono', monospace; font-size: 8px; font-weight: 700; }
                .platform-youtube { background: #e00; color: #fff; }
                .platform-twitch  { background: #9147ff; color: #fff; }
                .live-info { padding: 10px 12px 12px; }
                .live-title { font-size: 12px; font-weight: 600; color: var(--white); line-height: 1.4;
                    margin-bottom: 6px; display: -webkit-box; -webkit-line-clamp: 2;
                    -webkit-box-orient: vertical; overflow: hidden; }
                .live-channel { font-family: 'JetBrains Mono', monospace; font-size: 10px; color: var(--dim); }
                .live-since { font-family: 'JetBrains Mono', monospace; font-size: 10px;
                    color: var(--dim); margin-top: 4px; }
                .live-empty { font-family: 'JetBrains Mono', monospace; font-size: 12px;
                    color: var(--dim); padding: 32px 0; text-align: center; }

                /* Endpoint tabs */
                .tabs { display: flex; gap: 4px; border-bottom: 1px solid var(--border); }
                .tab { font-family: 'JetBrains Mono', monospace; font-size: 11px;
                    font-weight: 500; letter-spacing: .04em; color: var(--dim);
                    padding: 10px 16px; border-radius: 8px 8px 0 0; cursor: pointer;
                    border: none; background: none; border-bottom: 2px solid transparent;
                    transition: color .15s, border-color .15s; }
                .tab:hover { color: var(--text); }
                .tab.active { color: var(--blue); border-bottom-color: var(--blue); }

                /* Endpoint panels */
                .panel { display: none; flex-direction: column; gap: 14px; margin-top: 16px; }
                .panel.active { display: flex; }

                .panel-controls { display: flex; flex-wrap: wrap; gap: 8px; align-items: flex-end; }
                .ctrl-group { display: flex; flex-direction: column; gap: 6px; }
                .ctrl-label { font-family: 'JetBrains Mono', monospace; font-size: 10px;
                    color: var(--dim); text-transform: uppercase; letter-spacing: .06em; }
                .ctrl-input { background: var(--bg); border: 1px solid var(--border);
                    border-radius: 8px; padding: 8px 12px;
                    font-family: 'JetBrains Mono', monospace; font-size: 12px;
                    color: var(--text); outline: none; transition: border-color .2s; min-width: 200px; }
                .ctrl-input:focus { border-color: var(--border-h); }
                .ctrl-select { background: var(--bg); border: 1px solid var(--border);
                    border-radius: 8px; padding: 8px 12px;
                    font-family: 'JetBrains Mono', monospace; font-size: 12px;
                    color: var(--text); outline: none; cursor: pointer; }

                .fetch-btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 18px;
                    background: var(--accent); border: none; border-radius: 8px; cursor: pointer;
                    font-family: 'JetBrains Mono', monospace; font-size: 11px; font-weight: 500;
                    color: #fff; transition: opacity .15s; white-space: nowrap; }
                .fetch-btn:hover { opacity: .85; }

                /* Result area */
                .result-wrap { position: relative; }
                .result-meta { display: flex; justify-content: space-between; align-items: center;
                    margin-bottom: 6px; }
                .result-status { font-family: 'JetBrains Mono', monospace; font-size: 10px; }
                .result-status.ok  { color: var(--green); }
                .result-status.err { color: var(--red); }
                .result-time { font-family: 'JetBrains Mono', monospace; font-size: 10px; color: var(--dim); }
                .result-pre { background: var(--surface); border: 1px solid var(--border);
                    border-radius: 10px; padding: 16px; overflow: auto; max-height: 480px;
                    font-family: 'JetBrains Mono', monospace; font-size: 12px; line-height: 1.7;
                    color: var(--text); white-space: pre; }
                .result-empty { color: var(--dim); font-style: italic; }

                /* JSON syntax highlighting */
                .j-key   { color: #a5b4fc; }
                .j-str   { color: #86efac; }
                .j-num   { color: #fbbf24; }
                .j-bool  { color: #f472b6; }
                .j-null  { color: var(--dim); }

                /* ── Video grid ── */
                .video-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 14px; }
                .video-card { cursor: pointer; border-radius: 10px; overflow: hidden;
                    background: var(--surface); border: 1px solid var(--border);
                    transition: border-color .15s, transform .15s; }
                .video-card:hover { border-color: var(--border-h); transform: translateY(-2px); }
                .video-card.watched { opacity: .45; }
                .video-card.watched:hover { opacity: .75; }
                .video-thumb { position: relative; aspect-ratio: 16/9; overflow: hidden;
                    background: var(--surface2); }
                .video-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
                .video-dur { position: absolute; bottom: 5px; right: 6px;
                    background: rgba(0,0,0,.82); color: #fff;
                    font-family: 'JetBrains Mono', monospace; font-size: 10px;
                    padding: 2px 5px; border-radius: 3px; letter-spacing: .02em; }
                .video-watched-badge { position: absolute; top: 6px; right: 6px;
                    background: var(--green); color: #fff; width: 18px; height: 18px;
                    border-radius: 50%; display: none; align-items: center; justify-content: center;
                    font-size: 9px; font-weight: 700; }
                .video-card.watched .video-watched-badge { display: flex; }
                .video-info { padding: 9px 12px 12px; }
                .video-title { font-size: 12px; font-weight: 600; color: var(--white);
                    line-height: 1.45; margin-bottom: 5px;
                    display: -webkit-box; -webkit-line-clamp: 2;
                    -webkit-box-orient: vertical; overflow: hidden; }
                .video-channel { font-family: 'JetBrains Mono', monospace; font-size: 10px;
                    color: var(--blue); margin-bottom: 3px; white-space: nowrap;
                    overflow: hidden; text-overflow: ellipsis; }
                .video-meta { font-family: 'JetBrains Mono', monospace; font-size: 10px;
                    color: var(--dim); display: flex; gap: 5px; align-items: center; }
                .video-meta-sep { color: var(--border-h); }
                .home-spinner { display: flex; align-items: center; justify-content: center;
                    gap: 10px; padding: 40px; font-family: 'JetBrains Mono', monospace;
                    font-size: 11px; color: var(--dim); }
                .home-empty { font-family: 'JetBrains Mono', monospace; font-size: 12px;
                    color: var(--dim); padding: 40px 0; text-align: center; }
                .load-more-wrap { display: flex; justify-content: center; padding: 20px 0 4px; }

                /* ── Watched checkbox on card ── */
                .video-watch-btn { position: absolute; top: 6px; left: 6px; width: 22px; height: 22px;
                    border-radius: 50%; background: rgba(0,0,0,.7); border: 1.5px solid rgba(255,255,255,.25);
                    color: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center;
                    opacity: 0; transition: opacity .15s, background .15s; z-index: 2; padding: 0; }
                .video-card:hover .video-watch-btn { opacity: 1; }
                .video-card.watched .video-watch-btn { opacity: 1; background: var(--green); border-color: var(--green); }

                /* ── Confirmation popup ── */
                .confirm-popup { position: fixed; z-index: 200; background: var(--surface2);
                    border: 1px solid var(--border-h); border-radius: 10px; padding: 12px 16px;
                    box-shadow: 0 8px 32px rgba(0,0,0,.6); display: none; flex-direction: column; gap: 10px;
                    min-width: 180px; }
                .confirm-popup.open { display: flex; }
                .confirm-text { font-family: 'JetBrains Mono', monospace; font-size: 11px; color: var(--text); }
                .confirm-actions { display: flex; gap: 6px; }
                .confirm-yes { flex: 1; padding: 6px; background: var(--green); border: none;
                    border-radius: 6px; color: #fff; font-family: 'JetBrains Mono', monospace;
                    font-size: 11px; cursor: pointer; }
                .confirm-no  { flex: 1; padding: 6px; background: none; border: 1px solid var(--border);
                    border-radius: 6px; color: var(--dim); font-family: 'JetBrains Mono', monospace;
                    font-size: 11px; cursor: pointer; }
                .confirm-no:hover { border-color: var(--border-h); color: var(--text); }

                /* ── Video player modal ── */
                .player-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.75); z-index: 110;
                    opacity: 0; pointer-events: none; transition: opacity .2s; }
                .player-overlay.open { opacity: 1; pointer-events: all; }
                .player-modal { position: fixed; top: 50%; left: 50%; z-index: 111;
                    transform: translate(-50%,-50%) scale(.97); opacity: 0; pointer-events: none;
                    transition: opacity .2s, transform .2s;
                    background: var(--surface); border: 1px solid var(--border); border-radius: 14px;
                    overflow: hidden; width: min(860px, 94vw); max-height: 90vh;
                    display: flex; flex-direction: column; }
                .player-modal.open { opacity: 1; pointer-events: all; transform: translate(-50%,-50%) scale(1); }
                .player-head { display: flex; align-items: flex-start; justify-content: space-between;
                    gap: 12px; padding: 14px 18px 0; flex-shrink: 0; }
                .player-title { font-size: 13px; font-weight: 700; color: var(--white); line-height: 1.4; }
                .player-close { background: none; border: none; cursor: pointer; color: var(--dim);
                    padding: 4px; border-radius: 4px; transition: color .15s; line-height: 0; flex-shrink: 0; }
                .player-close:hover { color: var(--text); }
                .player-video-wrap { background: #000; flex-shrink: 0; }
                .player-video-wrap video { width: 100%; max-height: 60vh; display: block; }
                .player-loading { display: flex; align-items: center; justify-content: center;
                    height: 200px; font-family: 'JetBrains Mono', monospace; font-size: 11px; color: var(--dim); gap: 10px; }
                .player-error { display: flex; align-items: center; justify-content: center;
                    height: 120px; font-family: 'JetBrains Mono', monospace; font-size: 11px; color: var(--red); }
                .player-meta { padding: 12px 18px 16px; flex-shrink: 0; border-top: 1px solid var(--border);
                    font-family: 'JetBrains Mono', monospace; font-size: 11px; color: var(--dim);
                    display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
                .player-channel { color: var(--blue); }
                .player-quality { background: var(--accent-lo); color: var(--blue);
                    padding: 2px 7px; border-radius: 4px; font-size: 10px; }

                /* Profile panel */
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
                    padding: 32px 20px 24px; border-bottom: 1px solid var(--border); gap: 10px; flex-shrink: 0; }
                .pp-avatar-lg { width: 72px; height: 72px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
                .pp-avatar-lg.initials { background: var(--accent); font-size: 28px; font-weight: 700;
                    color: #fff; display: flex; align-items: center; justify-content: center; }
                .pp-name  { font-size: 16px; font-weight: 700; color: var(--white); text-align: center; }
                .pp-email { font-family: 'JetBrains Mono', monospace; font-size: 11px;
                    color: var(--dim); text-align: center; }
                .pp-body { flex: 1; padding: 20px; overflow-y: auto; }
            </style>
        </head>
        <body>
        <div class="app">

            <!-- Sidebar -->
            <aside class="sidebar" id="sidebar">
                <div class="sidebar-top">
                    <div class="brand">~/<span>container-sandbox</span></div>
                    <div class="u-card" id="openProfileBtn" role="button" aria-label="Open profile">
                        {$avatarHtml}
                        <div class="u-info">
                            <div class="u-name">{$name}</div>
                            <div class="u-email">{$email}</div>
                        </div>
                    </div>
                </div>

                <nav class="sidebar-nav">
                    <div class="nav-section">// menu</div>
                    <a href="/dashboard" class="nav-item">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                            <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                        </svg>
                        Dashboard
                    </a>
                    <a href="/users" class="nav-item">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                        Users
                    </a>
                    <a href="/youtube" class="nav-item active">
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
                        Logout
                    </a>
                </div>
            </aside>

            <!-- Main -->
            <div class="main">
                <div class="topbar">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <button class="burger" id="burgerBtn" type="button" aria-label="menu">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                                <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
                            </svg>
                        </button>
                        <span class="page-title">
                            YouTube
                            <span class="yt-badge">debug</span>
                        </span>
                    </div>
                </div>

                <div class="content">

                    {$authBanner}

                    <!-- Debug token — remove this card in production -->
                    <div class="debug-card">
                        <span class="debug-label">// google token</span>
                        <code class="debug-token">{$tokenPreview}</code>
                        <a href="/auth/youtube" class="reconnect-link">reconnect</a>
                    </div>

                    <!-- Tabs -->
                    <div>
                        <div class="tabs" role="tablist">
                            <button class="tab active" type="button" data-tab="home">home</button>
                            <button class="tab" type="button" data-tab="live">live</button>
                            <button class="tab" type="button" data-tab="subscriptions">subscriptions</button>
                            <button class="tab" type="button" data-tab="search">search</button>
                            <button class="tab" type="button" data-tab="video">video</button>
                        </div>

                        <!-- Live panel -->
                        <div id="panel-live" class="panel" role="tabpanel">
                            <div id="live-spinner" class="home-spinner" style="display:none">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="animation:spin 1s linear infinite">
                                    <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
                                </svg>
                                Loading live streams…
                            </div>
                            <div id="live-grid" class="live-grid"></div>
                            <div id="live-empty" class="live-empty" style="display:none">No subscribed channels are live right now.</div>
                        </div>

                        <!-- Home feed panel -->
                        <div id="panel-home" class="panel active" role="tabpanel">
                            <div id="home-spinner" class="home-spinner">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="animation:spin 1s linear infinite">
                                    <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
                                </svg>
                                Loading feed…
                            </div>
                            <div id="home-grid" class="video-grid" style="display:none"></div>
                            <div id="home-empty" class="home-empty" style="display:none">No videos found.</div>
                            <div class="load-more-wrap" id="load-more-wrap" style="display:none">
                                <button type="button" class="fetch-btn" id="btn-load-more">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>
                                    Load more
                                </button>
                            </div>
                        </div>

                        <!-- Subscriptions panel -->
                        <div id="panel-subscriptions" class="panel" role="tabpanel">
                            <div class="panel-controls">
                                <div class="ctrl-group">
                                    <div class="ctrl-label">page_token (optional)</div>
                                    <input type="text" id="subs-page-token" class="ctrl-input" placeholder="CAUQAA..." style="min-width:160px">
                                </div>
                                <button type="button" class="fetch-btn" id="btn-subscriptions">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                                    Fetch
                                </button>
                            </div>
                            <div class="result-wrap">
                                <div class="result-meta" id="subs-meta" style="display:none">
                                    <span class="result-status" id="subs-status"></span>
                                    <span class="result-time"  id="subs-time"></span>
                                </div>
                                <pre class="result-pre" id="subs-result"><span class="result-empty">// result will appear here</span></pre>
                            </div>
                        </div>

                        <!-- Search panel -->
                        <div id="panel-search" class="panel" role="tabpanel">
                            <div class="panel-controls">
                                <div class="ctrl-group">
                                    <div class="ctrl-label">query</div>
                                    <input type="text" id="search-q" class="ctrl-input" placeholder="linux tutorial...">
                                </div>
                                <div class="ctrl-group">
                                    <div class="ctrl-label">type</div>
                                    <select id="search-type" class="ctrl-select">
                                        <option value="video">video</option>
                                        <option value="channel">channel</option>
                                    </select>
                                </div>
                                <div class="ctrl-group">
                                    <div class="ctrl-label">page_token (optional)</div>
                                    <input type="text" id="search-page-token" class="ctrl-input" placeholder="CAUQAA..." style="min-width:140px">
                                </div>
                                <button type="button" class="fetch-btn" id="btn-search">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                                    Fetch
                                </button>
                            </div>
                            <div class="result-wrap">
                                <div class="result-meta" id="search-meta" style="display:none">
                                    <span class="result-status" id="search-status"></span>
                                    <span class="result-time"  id="search-time"></span>
                                </div>
                                <pre class="result-pre" id="search-result"><span class="result-empty">// result will appear here</span></pre>
                            </div>
                        </div>

                        <!-- Video panel -->
                        <div id="panel-video" class="panel" role="tabpanel">
                            <div class="panel-controls">
                                <div class="ctrl-group">
                                    <div class="ctrl-label">video ID</div>
                                    <input type="text" id="video-id" class="ctrl-input" placeholder="dQw4w9WgXcQ">
                                </div>
                                <button type="button" class="fetch-btn" id="btn-video">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                                    Fetch
                                </button>
                            </div>
                            <div class="result-wrap">
                                <div class="result-meta" id="video-meta" style="display:none">
                                    <span class="result-status" id="video-status"></span>
                                    <span class="result-time"  id="video-time"></span>
                                </div>
                                <pre class="result-pre" id="video-result"><span class="result-empty">// result will appear here</span></pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Watched confirmation popup -->
        <div class="confirm-popup" id="watchedConfirm">
            <span class="confirm-text">Mark as watched?</span>
            <div class="confirm-actions">
                <button type="button" class="confirm-yes" id="confirmYes">Yes</button>
                <button type="button" class="confirm-no"  id="confirmNo">Cancel</button>
            </div>
        </div>

        <!-- Video player modal -->
        <div class="player-overlay" id="playerOverlay"></div>
        <div class="player-modal" id="playerModal">
            <div class="player-head">
                <span class="player-title" id="playerTitle"></span>
                <button type="button" class="player-close" id="playerClose" aria-label="Close">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            <div class="player-video-wrap" id="playerVideoWrap">
                <div class="player-loading" id="playerLoading">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="animation:spin 1s linear infinite">
                        <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
                    </svg>
                    Loading streams…
                </div>
            </div>
            <div class="player-meta" id="playerMeta" style="display:none">
                <span class="player-channel" id="playerChannel"></span>
                <span id="playerViews"></span>
                <span class="player-quality" id="playerQuality"></span>
            </div>
        </div>

        <!-- Profile panel -->
        <div class="profile-overlay" id="profileOverlay"></div>
        <div class="profile-panel" id="profilePanel">
            <div class="pp-head">
                <span class="pp-title">Profile</span>
                <button class="pp-close" id="closeProfileBtn" type="button" aria-label="Close">
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
            <div class="pp-body"></div>
        </div>

        <script>
        (function () {
            'use strict';

            var JWT          = '{$jsJwt}';
            var GOOGLE_TOKEN = '{$jsToken}';

            // ── Sidebar / profile ──────────────────────────────────────────
            document.getElementById('burgerBtn').addEventListener('click', function () {
                document.getElementById('sidebar').classList.toggle('open');
            });
            document.getElementById('openProfileBtn').addEventListener('click', function () {
                document.getElementById('profileOverlay').classList.add('open');
                document.getElementById('profilePanel').classList.add('open');
            });
            function closeProfile() {
                document.getElementById('profileOverlay').classList.remove('open');
                document.getElementById('profilePanel').classList.remove('open');
            }
            document.getElementById('closeProfileBtn').addEventListener('click', closeProfile);
            document.getElementById('profileOverlay').addEventListener('click', closeProfile);
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeProfile(); });

            // ── Tabs ───────────────────────────────────────────────────────
            var liveLoaded = false;
            document.querySelectorAll('.tab').forEach(function (tab) {
                tab.addEventListener('click', function () {
                    document.querySelectorAll('.tab').forEach(function (t) { t.classList.remove('active'); });
                    document.querySelectorAll('.panel').forEach(function (p) { p.classList.remove('active'); });
                    tab.classList.add('active');
                    document.getElementById('panel-' + tab.dataset.tab).classList.add('active');
                    if (tab.dataset.tab === 'live' && !liveLoaded) {
                        liveLoaded = true;
                        fetchLive();
                    }
                });
            });

            // ── Live streams ───────────────────────────────────────────────
            function fetchLive() {
                var spinner = document.getElementById('live-spinner');
                var grid    = document.getElementById('live-grid');
                var empty   = document.getElementById('live-empty');
                spinner.style.display = 'flex';
                grid.innerHTML = '';
                empty.style.display = 'none';

                fetch('/api/youtube/live', {
                    headers: { 'Authorization': 'Bearer ' + JWT, 'X-Google-Token': GOOGLE_TOKEN }
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    spinner.style.display = 'none';
                    var items = (data && data.items) ? data.items : [];
                    if (items.length === 0) {
                        empty.style.display = 'block';
                        return;
                    }
                    items.forEach(function (stream) {
                        grid.appendChild(renderLiveCard(stream));
                    });
                })
                .catch(function (e) {
                    spinner.style.display = 'none';
                    grid.innerHTML = '<div class="live-empty">Error: ' + escHtml(e.message) + '</div>';
                });
            }

            function renderLiveCard(s) {
                var card = document.createElement('div');
                card.className = 'live-card';
                card.dataset.id = s.id;

                var platformClass = s.platform === 'twitch' ? 'platform-twitch' : 'platform-youtube';
                var platformLabel = s.platform === 'twitch' ? 'TW' : 'YT';
                var viewers = s.viewer_count ? fmtViews(s.viewer_count) + ' watching' : '';
                var since   = s.started_at ? 'Live ' + relTime(s.started_at) : '';
                var thumb   = s.thumbnail || '';

                card.innerHTML =
                    '<div class="live-thumb">' +
                        (thumb ? '<img src="' + thumb + '" alt="" loading="lazy">' : '') +
                        '<div class="live-badge"><div class="live-dot"></div>LIVE</div>' +
                        '<div class="platform-badge ' + platformClass + '">' + platformLabel + '</div>' +
                        (viewers ? '<div class="live-viewers">' + escHtml(viewers) + '</div>' : '') +
                    '</div>' +
                    '<div class="live-info">' +
                        '<div class="live-title">' + escHtml(s.title) + '</div>' +
                        '<div class="live-channel">' + escHtml(s.channel_name) + '</div>' +
                        (since ? '<div class="live-since">' + since + '</div>' : '') +
                    '</div>';

                card.addEventListener('click', function () {
                    openPlayer(s.id, s.title, s.channel_name, s.viewer_count);
                });
                return card;
            }

            // ── Home feed ──────────────────────────────────────────────────
            var homePage    = 1;
            var homeLoading = false;
            // In-memory watched set — loaded from DB on page load
            var watchedIds = new Set();

            function markWatched(id) {
                watchedIds.add(id);
                fetch('/api/user/watched/' + id, {
                    method: 'PUT',
                    headers: { 'Authorization': 'Bearer ' + JWT }
                }).catch(function() {});
            }
            function unmarkWatched(id) {
                watchedIds.delete(id);
                fetch('/api/user/watched/' + id, {
                    method: 'DELETE',
                    headers: { 'Authorization': 'Bearer ' + JWT }
                }).catch(function() {});
            }
            function getWatched() { return watchedIds; }

            // Load watched IDs from DB on page init
            fetch('/api/user/watched', { headers: { 'Authorization': 'Bearer ' + JWT } })
                .then(function(r) { return r.ok ? r.json() : { video_ids: [] }; })
                .then(function(d) {
                    (d.video_ids || []).forEach(function(id) { watchedIds.add(id); });
                })
                .catch(function() {});

            function pad2(n) { return n < 10 ? '0' + n : '' + n; }
            function parseDuration(iso) {
                var m = (iso || '').match(/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/);
                if (!m) return '';
                var h = parseInt(m[1] || 0), mi = parseInt(m[2] || 0), s = parseInt(m[3] || 0);
                return h > 0 ? h + ':' + pad2(mi) + ':' + pad2(s) : mi + ':' + pad2(s);
            }
            function relTime(iso) {
                var s = (Date.now() - new Date(iso).getTime()) / 1000;
                if (s < 60)       return 'just now';
                if (s < 3600)     return Math.floor(s / 60) + 'm ago';
                if (s < 86400)    return Math.floor(s / 3600) + 'h ago';
                if (s < 604800)   return Math.floor(s / 86400) + 'd ago';
                if (s < 2592000)  return Math.floor(s / 604800) + 'w ago';
                if (s < 31536000) return Math.floor(s / 2592000) + 'mo ago';
                return Math.floor(s / 31536000) + 'y ago';
            }
            function fmtViews(n) {
                if (!n && n !== 0) return '';
                if (n >= 1e9) return (n / 1e9).toFixed(1).replace('.0', '') + 'B views';
                if (n >= 1e6) return (n / 1e6).toFixed(1).replace('.0', '') + 'M views';
                if (n >= 1e3) return (n / 1e3).toFixed(1).replace('.0', '') + 'K views';
                return n + ' views';
            }
            function escHtml(s) {
                return (s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            }

            function renderCard(v) {
                var watched = getWatched().has(v.id);
                var dur     = parseDuration(v.duration || '');
                var views   = fmtViews(v.view_count);
                var time    = v.published_at ? relTime(v.published_at) : '';
                var card    = document.createElement('div');
                card.className = 'video-card' + (watched ? ' watched' : '');
                card.dataset.id = v.id;

                // Watched checkbox button (top-left of thumbnail)
                var watchBtn = document.createElement('button');
                watchBtn.type = 'button';
                watchBtn.className = 'video-watch-btn';
                watchBtn.title = 'Mark as watched';
                watchBtn.innerHTML = '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>';
                watchBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    showWatchedConfirm(e, v.id, card, watchBtn);
                });

                var thumbWrap = document.createElement('div');
                thumbWrap.className = 'video-thumb';
                if (v.thumbnail) {
                    var img = document.createElement('img');
                    img.src = v.thumbnail; img.alt = ''; img.loading = 'lazy';
                    thumbWrap.appendChild(img);
                }
                if (dur) {
                    var durEl = document.createElement('span');
                    durEl.className = 'video-dur'; durEl.textContent = dur;
                    thumbWrap.appendChild(durEl);
                }
                thumbWrap.appendChild(watchBtn);
                var badge = document.createElement('div');
                badge.className = 'video-watched-badge'; badge.innerHTML = '&#10003;';
                thumbWrap.appendChild(badge);

                var infoWrap = document.createElement('div');
                infoWrap.className = 'video-info';
                infoWrap.innerHTML =
                    '<div class="video-title">' + escHtml(v.title) + '</div>' +
                    '<div class="video-channel">' + escHtml(v.channel_name) + '</div>' +
                    '<div class="video-meta">' +
                        (time ? '<span>' + time + '</span>' : '') +
                        (views ? '<span class="video-meta-sep">·</span><span>' + views + '</span>' : '') +
                    '</div>';

                card.appendChild(thumbWrap);
                card.appendChild(infoWrap);

                // Click card → open player
                card.addEventListener('click', function () {
                    openPlayer(v.id, v.title, v.channel_name, v.view_count);
                });
                return card;
            }

            // ── Watched confirmation popup ─────────────────────────────────
            var _confirmTarget = null;  // { videoId, card, btn, isWatched }
            var confirmPopup   = document.getElementById('watchedConfirm');
            var confirmText    = confirmPopup.querySelector('.confirm-text');

            function showWatchedConfirm(e, videoId, card, btn) {
                var isWatched = getWatched().has(videoId);
                _confirmTarget = { videoId: videoId, card: card, btn: btn, isWatched: isWatched };
                confirmText.textContent = isWatched ? 'Unmark as watched?' : 'Mark as watched?';
                var rect = btn.getBoundingClientRect();
                confirmPopup.style.top  = (rect.bottom + 6) + 'px';
                confirmPopup.style.left = rect.left + 'px';
                confirmPopup.classList.add('open');
            }
            function closeConfirm() { confirmPopup.classList.remove('open'); _confirmTarget = null; }

            document.getElementById('confirmYes').addEventListener('click', function () {
                if (_confirmTarget) {
                    if (_confirmTarget.isWatched) {
                        unmarkWatched(_confirmTarget.videoId);
                        _confirmTarget.card.classList.remove('watched');
                    } else {
                        markWatched(_confirmTarget.videoId);
                        _confirmTarget.card.classList.add('watched');
                    }
                }
                closeConfirm();
            });
            document.getElementById('confirmNo').addEventListener('click', closeConfirm);
            document.addEventListener('click', function (e) {
                if (_confirmTarget && !confirmPopup.contains(e.target)) closeConfirm();
            });
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeConfirm(); });

            // ── Video player ───────────────────────────────────────────────
            var playerOverlay = document.getElementById('playerOverlay');
            var playerModal   = document.getElementById('playerModal');

            function openPlayer(videoId, title, channelName, viewCount) {
                document.getElementById('playerTitle').textContent   = title || '';
                document.getElementById('playerChannel').textContent = channelName || '';
                document.getElementById('playerViews').textContent   = fmtViews(viewCount) || '';
                document.getElementById('playerMeta').style.display  = 'none';
                document.getElementById('playerQuality').textContent = '';
                var wrap = document.getElementById('playerVideoWrap');
                wrap.innerHTML = '<div class="player-loading" id="playerLoading">' +
                    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="animation:spin 1s linear infinite"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>' +
                    'Loading streams…</div>';
                playerOverlay.classList.add('open');
                playerModal.classList.add('open');

                // Backend extrae streams via yt-dlp y los devuelve directamente
                fetch('/api/youtube/video/' + encodeURIComponent(videoId), {
                    headers: { 'Authorization': 'Bearer ' + JWT, 'X-Google-Token': GOOGLE_TOKEN }
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var streams = data.format_streams || [];
                    var best = streams.find(function (s) {
                        return (s.quality || '').indexOf('720') !== -1;
                    }) || streams[streams.length - 1];
                    if (!best) {
                        wrap.innerHTML = '<div class="player-error">No playable stream found.</div>';
                        return;
                    }
                    var video = document.createElement('video');
                    video.controls = true; video.autoplay = true;
                    video.style.width = '100%'; video.style.maxHeight = '60vh'; video.style.display = 'block';
                    // Use the backend proxy — YouTube CDN URLs are tied to the
                    // server IP and will be rejected if played directly from the browser.
                    var quality = encodeURIComponent(best.quality || '');
                    video.src = '/api/youtube/stream/' + encodeURIComponent(videoId)
                              + (quality ? '?quality=' + quality : '');
                    wrap.innerHTML = '';
                    wrap.appendChild(video);
                    document.getElementById('playerQuality').textContent = best.quality || '';
                    document.getElementById('playerMeta').style.display = 'flex';
                    // Auto-mark as watched after 1 minute of playback
                    var _watchedFired = false;
                    video.addEventListener('timeupdate', function onTimeUpdate() {
                        if (!_watchedFired && video.currentTime >= 60) {
                            _watchedFired = true;
                            video.removeEventListener('timeupdate', onTimeUpdate);
                            markWatched(videoId);
                            var card = document.querySelector('.video-card[data-id="' + videoId + '"]');
                            if (card) card.classList.add('watched');
                        }
                    });
                })
                .catch(function (e) {
                    wrap.innerHTML = '<div class="player-error">Error: ' + escHtml(e.message) + '</div>';
                });
            }

            function closePlayer() {
                playerOverlay.classList.remove('open');
                playerModal.classList.remove('open');
                // Stop video playback
                var vid = playerModal.querySelector('video');
                if (vid) { vid.pause(); vid.src = ''; }
            }

            document.getElementById('playerClose').addEventListener('click', closePlayer);
            playerOverlay.addEventListener('click', closePlayer);
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closePlayer(); });

            async function fetchHome(page) {
                if (homeLoading) return;
                homeLoading = true;
                var grid    = document.getElementById('home-grid');
                var spinner = document.getElementById('home-spinner');
                var empty   = document.getElementById('home-empty');
                var more    = document.getElementById('load-more-wrap');
                if (page === 1) {
                    grid.innerHTML = '';
                    grid.style.display = 'none';
                    empty.style.display = 'none';
                    more.style.display  = 'none';
                    spinner.style.display = 'flex';
                }
                try {
                    var r    = await fetch('/api/youtube/home?page=' + page, {
                        headers: { 'Authorization': 'Bearer ' + JWT, 'X-Google-Token': GOOGLE_TOKEN }
                    });
                    var data = await r.json();
                    spinner.style.display = 'none';
                    if (r.status === 403) {
                        if (page === 1) { empty.textContent = 'YouTube API quota exhausted. Resets at midnight PT.'; empty.style.display = 'block'; }
                    } else if (r.ok && data.items && data.items.length > 0) {
                        grid.style.display = 'grid';
                        data.items.forEach(function (v) { grid.appendChild(renderCard(v)); });
                        more.style.display = data.has_more ? 'flex' : 'none';
                        if (data.has_more) { homePage = page + 1; }
                    } else if (page === 1) {
                        empty.style.display = 'block';
                    }
                } catch (e) {
                    spinner.style.display = 'none';
                    if (page === 1) { empty.textContent = 'Error: ' + e.message; empty.style.display = 'block'; }
                }
                homeLoading = false;
            }

            document.getElementById('btn-load-more').addEventListener('click', function () { fetchHome(homePage); });

            // Auto-load home on page load
            fetchHome(1);

            // ── API helpers ────────────────────────────────────────────────
            function syntaxHighlight(json) {
                var s = JSON.stringify(json, null, 2);
                return s.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, function (m) {
                    var c = 'j-num';
                    if (/^"/.test(m)) { c = /:$/.test(m) ? 'j-key' : 'j-str'; }
                    else if (/true|false/.test(m)) { c = 'j-bool'; }
                    else if (/null/.test(m)) { c = 'j-null'; }
                    return '<span class="' + c + '">' + m + '</span>';
                });
            }

            function showResult(prefix, status, data, ms) {
                var ok = status >= 200 && status < 300;
                document.getElementById(prefix + '-meta').style.display = 'flex';
                var st = document.getElementById(prefix + '-status');
                st.className = 'result-status ' + (ok ? 'ok' : 'err');
                st.textContent = 'HTTP ' + status;
                document.getElementById(prefix + '-time').textContent = ms + 'ms';
                document.getElementById(prefix + '-result').innerHTML = syntaxHighlight(data);
            }

            function showError(prefix, msg) {
                document.getElementById(prefix + '-meta').style.display = 'flex';
                var st = document.getElementById(prefix + '-status');
                st.className = 'result-status err';
                st.textContent = 'ERROR';
                document.getElementById(prefix + '-time').textContent = '';
                document.getElementById(prefix + '-result').textContent = msg;
            }

            async function apiFetch(url, prefix) {
                var t0 = Date.now();
                try {
                    var r    = await fetch(url, {
                        headers: {
                            'Authorization': 'Bearer ' + JWT,
                            'X-Google-Token': GOOGLE_TOKEN
                        }
                    });
                    var ms   = Date.now() - t0;
                    var text = await r.text();
                    var data;
                    try { data = JSON.parse(text); }
                    catch (_) {
                        showError(prefix, 'HTTP ' + r.status + ' — not JSON: ' + text.slice(0, 400));
                        return;
                    }
                    showResult(prefix, r.status, data, ms);
                } catch (e) {
                    showError(prefix, String(e));
                }
            }

            // ── Fetch functions ────────────────────────────────────────────
            function fetchSubscriptions() {
                var pt  = document.getElementById('subs-page-token').value.trim();
                var url = '/api/youtube/subscriptions' + (pt ? '?page_token=' + encodeURIComponent(pt) : '');
                apiFetch(url, 'subs');
            }
            function fetchSearch() {
                var q = document.getElementById('search-q').value.trim();
                if (!q) { showError('search', 'Enter a search query.'); return; }
                var type = document.getElementById('search-type').value;
                var pt   = document.getElementById('search-page-token').value.trim();
                var url  = '/api/youtube/search?q=' + encodeURIComponent(q) + '&type=' + encodeURIComponent(type);
                if (pt) url += '&page_token=' + encodeURIComponent(pt);
                apiFetch(url, 'search');
            }
            function fetchVideo() {
                var id = document.getElementById('video-id').value.trim();
                if (!id) { showError('video', 'Enter a video ID.'); return; }
                apiFetch('/api/youtube/video/' + encodeURIComponent(id), 'video');
            }

            // ── Button click listeners ─────────────────────────────────────
            document.getElementById('btn-subscriptions').addEventListener('click', fetchSubscriptions);
            document.getElementById('btn-search').addEventListener('click', fetchSearch);
            document.getElementById('btn-video').addEventListener('click', fetchVideo);

            // ── Enter key shortcuts ────────────────────────────────────────
            document.getElementById('subs-page-token').addEventListener('keydown', function (e) { if (e.key === 'Enter') fetchSubscriptions(); });
            document.getElementById('search-q').addEventListener('keydown',        function (e) { if (e.key === 'Enter') fetchSearch(); });
            document.getElementById('video-id').addEventListener('keydown',        function (e) { if (e.key === 'Enter') fetchVideo(); });

        })();
        </script>
        </body>
        </html>
        HTML;

        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html');
    }

    /**
     * Resolves a fresh Google access token from the stored OAuth token.
     * Refreshes automatically if expired. Returns '' if unavailable.
     */
    private function resolveGoogleAccessToken(string $userId): string
    {
        $user = $this->users->findById($userId);
        if (!$user || !$user->getGoogleToken()) {
            return '';
        }

        $tokenData = json_decode($user->getGoogleToken(), true);
        if (!is_array($tokenData) || empty($tokenData['access_token'])) {
            return '';
        }

        $this->googleClient->setAccessToken($tokenData);

        if ($this->googleClient->isAccessTokenExpired()) {
            $refreshToken = $tokenData['refresh_token'] ?? null;
            if (!$refreshToken) {
                return '';
            }
            $newToken = $this->googleClient->fetchAccessTokenWithRefreshToken($refreshToken);
            if (isset($newToken['error'])) {
                return '';
            }
            if (empty($newToken['refresh_token'])) {
                $newToken['refresh_token'] = $refreshToken;
            }
            $this->users->saveGoogleToken($userId, json_encode($newToken));
            $this->googleClient->setAccessToken($newToken);
        }

        return $this->googleClient->getAccessToken()['access_token'] ?? '';
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
