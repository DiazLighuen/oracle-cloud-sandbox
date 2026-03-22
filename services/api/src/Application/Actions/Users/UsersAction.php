<?php
declare(strict_types=1);

namespace App\Application\Actions\Users;

use App\Domain\User\User;
use App\Domain\User\UserRepository;
use App\Infrastructure\I18n\PhpTranslator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class UsersAction
{
    public function __construct(private UserRepository $users) {}

    public function __invoke(Request $request, Response $response): Response
    {
        /** @var PhpTranslator $tr */
        $tr   = $request->getAttribute('translator');
        $lang = $tr->getLang();
        $t    = fn(string $k) => htmlspecialchars($tr->t($k));

        $name   = htmlspecialchars($request->getAttribute('user_name') ?? '?');
        $email  = htmlspecialchars($request->getAttribute('user_email') ?? '');
        $avatar = htmlspecialchars($request->getAttribute('user_avatar') ?? '');

        $initial    = mb_substr($name, 0, 1);
        $avatarHtml = $this->avatarHtml($avatar, $initial, 'u-avatar', 'u-avatar u-initials');
        $langBar    = $this->langBar($lang);

        $selfId = $request->getAttribute('user_id') ?? '';
        $jwt    = $_COOKIE['jwt'] ?? '';

        $users    = $this->users->findAll();
        $listHtml = $this->buildList($users, $selfId, $tr);

        $tTitle         = $t('users.title');
        $tLogout        = $t('dashboard.logout');
        $tNavDash       = $t('nav.dashboard');
        $tNavUsers      = $t('nav.users');
        $tProfile       = $t('nav.profile');
        $tAdd           = $t('users.add');
        $tAddTitle      = $t('users.add_title');
        $tEmail         = $t('users.email');
        $tNameLabel     = $t('users.name');
        $tMakeAdmin     = $t('users.make_admin');
        $tSave          = $t('users.save');
        $tCancel        = $t('users.cancel');
        $tConfirmDelete = $t('users.confirm_delete');

        $avatarLgHtml = $this->avatarHtml($avatar, $initial, 'pp-avatar-lg', 'pp-avatar-lg initials');

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

                body::before { content: ''; position: fixed; inset: 0; z-index: 0;
                    background-image: linear-gradient(var(--border) 1px, transparent 1px),
                        linear-gradient(90deg, var(--border) 1px, transparent 1px);
                    background-size: 48px 48px;
                    mask-image: radial-gradient(ellipse 100% 60% at 20% 0%, black 20%, transparent 100%);
                    pointer-events: none; }

                .app { position: relative; z-index: 1; display: flex; height: 100vh; }

                /* Sidebar */
                .sidebar { width: var(--sidebar-w); flex-shrink: 0; display: flex;
                    flex-direction: column; background: var(--surface);
                    border-right: 1px solid var(--border); height: 100vh;
                    position: sticky; top: 0; overflow-y: auto; }

                .sidebar-top { padding: 24px 16px 20px; border-bottom: 1px solid var(--border); }

                .brand { font-family: 'JetBrains Mono', monospace; font-size: 11px;
                    color: var(--dim); letter-spacing: .06em; margin-bottom: 20px; }
                .brand span { color: var(--blue); }

                .u-card { cursor: pointer; border-radius: 8px; padding: 6px; margin: -6px;
                    transition: background .15s; display: flex; align-items: center; gap: 10px; }
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
                    transition: background .15s, color .15s; cursor: pointer; border: none;
                    background: none; width: 100%; }
                .nav-item:hover { background: var(--surface2); }
                .nav-item.active { background: var(--accent-lo); color: var(--blue); }
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
                    transition: background .15s, color .15s; width: 100%; border: none; background: none; cursor: pointer; }
                .logout-btn:hover { background: rgba(239,68,68,.08); color: var(--red); }

                /* Main */
                .main { flex: 1; overflow-y: auto; display: flex; flex-direction: column; min-width: 0; }

                .topbar { display: flex; align-items: center; justify-content: space-between;
                    padding: 20px 32px; border-bottom: 1px solid var(--border);
                    position: sticky; top: 0; background: var(--bg); z-index: 10; }

                .page-title { font-size: 18px; font-weight: 800; color: var(--white); letter-spacing: -.02em; }

                .content { padding: 28px 32px; }

                /* User list */
                .user-list { display: flex; flex-direction: column; gap: 10px; }

                .user-row { display: flex; align-items: center; gap: 14px;
                    background: var(--surface); border: 1px solid var(--border);
                    border-radius: 12px; padding: 14px 18px;
                    transition: border-color .2s; }
                .user-row:hover { border-color: var(--border-h); }

                .ur-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
                .ur-initials { background: var(--accent); font-size: 16px; font-weight: 700;
                    color: #fff; display: flex; align-items: center; justify-content: center; }

                .ur-info { flex: 1; min-width: 0; }
                .ur-name  { font-size: 14px; font-weight: 600; color: var(--white);
                    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
                .ur-email { font-family: 'JetBrains Mono', monospace; font-size: 11px;
                    color: var(--dim); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

                .ur-meta { display: flex; flex-direction: column; align-items: flex-end; gap: 6px; flex-shrink: 0; }

                .role-badge { font-family: 'JetBrains Mono', monospace; font-size: 10px; font-weight: 500;
                    padding: 3px 8px; border-radius: 100px; letter-spacing: .06em; }
                .role-badge.admin { background: rgba(59,79,216,.2); color: var(--blue); }
                .role-badge.user  { background: var(--surface2); color: var(--dim); }

                .ur-date { font-family: 'JetBrains Mono', monospace; font-size: 10px; color: var(--dim); }

                /* Action buttons */
                .ur-actions { display: flex; gap: 6px; align-items: center; flex-shrink: 0; }
                .btn-sm {
                    font-family: 'JetBrains Mono', monospace; font-size: 10px; font-weight: 500;
                    padding: 5px 10px; border-radius: 6px; border: 1px solid transparent;
                    cursor: pointer; transition: background .15s, color .15s, opacity .15s;
                    white-space: nowrap;
                }
                .btn-sm:disabled { opacity: .35; cursor: not-allowed; }
                .btn-sm.btn-grant { background: var(--accent-lo); color: var(--blue); border-color: var(--accent); }
                .btn-sm.btn-grant:hover:not(:disabled) { background: var(--accent); color: #fff; }
                .btn-sm.btn-revoke { background: rgba(245,158,11,.08); color: var(--yellow); border-color: rgba(245,158,11,.3); }
                .btn-sm.btn-revoke:hover:not(:disabled) { background: rgba(245,158,11,.2); }
                .btn-sm.btn-del { background: rgba(239,68,68,.06); color: var(--red); border-color: rgba(239,68,68,.2); }
                .btn-sm.btn-del:hover:not(:disabled) { background: rgba(239,68,68,.18); }

                /* Add button */
                .btn-add {
                    font-family: 'Syne', sans-serif; font-size: 13px; font-weight: 600;
                    padding: 8px 16px; border-radius: 8px; background: var(--accent);
                    color: #fff; border: none; cursor: pointer; transition: opacity .15s;
                    display: flex; align-items: center; gap: 6px;
                }
                .btn-add:hover { opacity: .85; }

                .state-msg { display: flex; flex-direction: column; align-items: center;
                    justify-content: center; padding: 80px 24px; text-align: center; gap: 12px;
                    color: var(--dim); font-family: 'JetBrains Mono', monospace; font-size: 13px; }

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
                .pp-email { font-family: 'JetBrains Mono', monospace; font-size: 11px; color: var(--dim); text-align: center; }
                .pp-body { flex: 1; padding: 20px; overflow-y: auto; }
                .pp-section-label { font-family: 'JetBrains Mono', monospace; font-size: 10px;
                    color: var(--dim); letter-spacing: .1em; text-transform: uppercase; margin-bottom: 12px; }
                .pp-placeholder { background: var(--surface2); border: 1px dashed var(--border);
                    border-radius: 8px; padding: 32px 20px; text-align: center;
                    font-family: 'JetBrains Mono', monospace; font-size: 11px; color: var(--dim); }

                /* Modal */
                .modal-overlay {
                    position: fixed; inset: 0; background: rgba(0,0,0,.65);
                    z-index: 200; opacity: 0; pointer-events: none; transition: opacity .2s;
                }
                .modal-overlay.open { opacity: 1; pointer-events: all; }
                .modal {
                    position: fixed; top: 50%; left: 50%;
                    transform: translate(-50%, -58%);
                    width: 400px; max-width: calc(100vw - 32px);
                    background: var(--surface); border: 1px solid var(--border);
                    border-radius: 14px; z-index: 201;
                    opacity: 0; pointer-events: none;
                    transition: opacity .2s, transform .2s;
                }
                .modal.open { opacity: 1; pointer-events: all; transform: translate(-50%, -50%); }
                .modal-head {
                    display: flex; align-items: center; justify-content: space-between;
                    padding: 18px 20px; border-bottom: 1px solid var(--border);
                }
                .modal-title { font-size: 14px; font-weight: 700; color: var(--white); }
                .modal-body { padding: 20px; display: flex; flex-direction: column; gap: 14px; }
                .form-group { display: flex; flex-direction: column; gap: 5px; }
                .form-group label {
                    font-family: 'JetBrains Mono', monospace; font-size: 11px; color: var(--dim);
                }
                .form-group input[type=email],
                .form-group input[type=text] {
                    background: var(--bg); border: 1px solid var(--border); color: var(--text);
                    font-family: 'JetBrains Mono', monospace; font-size: 13px;
                    padding: 9px 12px; border-radius: 8px; outline: none;
                    transition: border-color .15s; width: 100%;
                }
                .form-group input:focus { border-color: var(--accent); }
                .form-check { display: flex; align-items: center; gap: 8px; }
                .form-check input[type=checkbox] {
                    width: 15px; height: 15px; accent-color: var(--accent); cursor: pointer;
                }
                .form-check label {
                    font-family: 'JetBrains Mono', monospace; font-size: 12px;
                    color: var(--text); cursor: pointer;
                }
                .modal-footer {
                    display: flex; gap: 8px; justify-content: flex-end;
                    padding: 0 20px 20px;
                }
                .btn-cancel {
                    font-family: 'Syne', sans-serif; font-size: 13px; font-weight: 600;
                    padding: 8px 16px; border-radius: 8px; background: var(--border);
                    color: var(--text); border: none; cursor: pointer; transition: opacity .15s;
                }
                .btn-cancel:hover { opacity: .8; }
                .btn-save {
                    font-family: 'Syne', sans-serif; font-size: 13px; font-weight: 600;
                    padding: 8px 20px; border-radius: 8px; background: var(--accent);
                    color: #fff; border: none; cursor: pointer; transition: opacity .15s;
                }
                .btn-save:hover { opacity: .85; }
                .btn-save:disabled, .btn-cancel:disabled { opacity: .5; cursor: not-allowed; }

                .burger { display: none; background: none; border: none; cursor: pointer;
                    color: var(--text); padding: 4px; }

                @media (max-width: 768px) {
                    .sidebar { position: fixed; left: 0; top: 0; bottom: 0; z-index: 50;
                        transform: translateX(-100%); transition: transform .25s ease; }
                    .sidebar.open { transform: translateX(0); box-shadow: 4px 0 32px rgba(0,0,0,.6); }
                    .burger { display: flex; }
                    .content { padding: 20px 16px; }
                    .topbar { padding: 16px 20px; }
                    .ur-meta { display: none; }
                    .ur-actions .btn-sm { font-size: 9px; padding: 4px 7px; }
                }
            </style>
        </head>
        <body>
        <div class="app">

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
                    <a href="/dashboard" class="nav-item">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                            <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                        </svg>
                        {$tNavDash}
                    </a>
                    <a href="/users" class="nav-item active">
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
                    <button class="btn-add" onclick="openAddModal()">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        {$tAdd}
                    </button>
                </div>

                <div class="content">
                    <div class="user-list">{$listHtml}</div>
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

        <!-- Add user modal -->
        <div class="modal-overlay" id="modalOverlay" onclick="closeModal()"></div>
        <div class="modal" id="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitleText">
            <div class="modal-head">
                <span class="modal-title" id="modalTitleText">{$tAddTitle}</span>
                <button class="pp-close" onclick="closeModal()" aria-label="Close">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            <form id="addForm" onsubmit="saveUser(event)">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="inp-email">{$tEmail}</label>
                        <input type="email" id="inp-email" required autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label for="inp-name">{$tNameLabel}</label>
                        <input type="text" id="inp-name" autocomplete="off">
                    </div>
                    <div class="form-check">
                        <input type="checkbox" id="chk-admin">
                        <label for="chk-admin">{$tMakeAdmin}</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" id="btnCancel" onclick="closeModal()">{$tCancel}</button>
                    <button type="submit" class="btn-save" id="btnSave">{$tSave}</button>
                </div>
            </form>
        </div>

        <script>
        const JWT = '{$jwt}';

        /* ── Sidebar ── */
        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('open'); }

        /* ── Profile panel ── */
        function openProfile() {
            document.getElementById('profileOverlay').classList.add('open');
            document.getElementById('profilePanel').classList.add('open');
        }
        function closeProfile() {
            document.getElementById('profileOverlay').classList.remove('open');
            document.getElementById('profilePanel').classList.remove('open');
        }

        /* ── Add user modal ── */
        function openAddModal() {
            document.getElementById('inp-email').value = '';
            document.getElementById('inp-name').value = '';
            document.getElementById('chk-admin').checked = false;
            document.getElementById('modalOverlay').classList.add('open');
            document.getElementById('modal').classList.add('open');
            setTimeout(() => document.getElementById('inp-email').focus(), 50);
        }
        function closeModal() {
            document.getElementById('modalOverlay').classList.remove('open');
            document.getElementById('modal').classList.remove('open');
        }

        async function saveUser(e) {
            e.preventDefault();
            const btnSave   = document.getElementById('btnSave');
            const btnCancel = document.getElementById('btnCancel');
            btnSave.disabled = true;
            btnCancel.disabled = true;
            try {
                const res = await fetch('/api/users', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + JWT,
                    },
                    body: JSON.stringify({
                        email:    document.getElementById('inp-email').value,
                        name:     document.getElementById('inp-name').value || null,
                        is_admin: document.getElementById('chk-admin').checked,
                    }),
                });
                if (res.ok) {
                    closeModal();
                    location.reload();
                } else {
                    const data = await res.json();
                    alert(data.error || 'Error saving user');
                }
            } catch (err) {
                alert('Network error');
            } finally {
                btnSave.disabled = false;
                btnCancel.disabled = false;
            }
        }

        /* ── Toggle admin ── */
        async function toggleAdmin(btn) {
            const id      = btn.dataset.id;
            const isAdmin = btn.dataset.admin === '1';
            btn.disabled  = true;
            try {
                const res = await fetch('/api/users/' + id, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + JWT,
                    },
                    body: JSON.stringify({ is_admin: !isAdmin }),
                });
                if (res.ok) {
                    location.reload();
                } else {
                    const data = await res.json();
                    alert(data.error || 'Error updating user');
                    btn.disabled = false;
                }
            } catch (err) {
                alert('Network error');
                btn.disabled = false;
            }
        }

        /* ── Delete user ── */
        async function confirmDelete(btn) {
            if (!confirm('{$tConfirmDelete}')) return;
            const id     = btn.dataset.id;
            btn.disabled = true;
            try {
                const res = await fetch('/api/users/' + id, {
                    method: 'DELETE',
                    headers: { 'Authorization': 'Bearer ' + JWT },
                });
                if (res.ok) {
                    location.reload();
                } else {
                    const data = await res.json();
                    alert(data.error || 'Error deleting user');
                    btn.disabled = false;
                }
            } catch (err) {
                alert('Network error');
                btn.disabled = false;
            }
        }

        /* ── Global keyboard shortcuts ── */
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeProfile();
                closeModal();
            }
        });

        /* ── Close sidebar on outside click (mobile) ── */
        document.addEventListener('click', function(e) {
            const sb = document.getElementById('sidebar');
            if (sb.classList.contains('open') &&
                !sb.contains(e.target) &&
                !document.getElementById('burger').contains(e.target)) {
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

    private function buildList(array $users, string $selfId, PhpTranslator $tr): string
    {
        $t = fn(string $k) => htmlspecialchars($tr->t($k));

        if (empty($users)) {
            return "<div class=\"state-msg\"><span>{$t('users.empty')}</span></div>";
        }

        $html = '';
        foreach ($users as $user) {
            /** @var User $user */
            $uId      = $user->getId();
            $uName    = htmlspecialchars($user->getName() ?? '—');
            $uEmail   = htmlspecialchars($user->getEmail());
            $uAvatar  = htmlspecialchars($user->getAvatarUrl() ?? '');
            $initial  = mb_substr($uName, 0, 1);
            $isAdmin  = $user->isAdmin();
            $joinedRaw = $user->getCreatedAt() ?? '';
            $joined   = $joinedRaw ? date('Y-m-d', strtotime($joinedRaw)) : '—';

            $isSelf   = ($uId === $selfId);
            $disabled = $isSelf ? ' disabled' : '';

            $roleClass     = $isAdmin ? 'admin' : 'user';
            $roleLabel     = $isAdmin ? $t('users.admin') : $t('users.user');
            $adminBtnClass = $isAdmin ? 'btn-revoke' : 'btn-grant';
            $adminBtnLabel = $isAdmin ? $t('users.revoke_admin') : $t('users.make_admin');
            $adminDataVal  = $isAdmin ? '1' : '0';

            $avatarEl = $uAvatar
                ? "<img src=\"{$uAvatar}\" alt=\"\" class=\"ur-avatar\""
                  . " onerror=\"this.style.display='none';this.nextElementSibling.style.display='flex';\">"
                  . "<div class=\"ur-avatar ur-initials\" style=\"display:none\">{$initial}</div>"
                : "<div class=\"ur-avatar ur-initials\">{$initial}</div>";

            $tDelete = $t('users.delete');

            $html .= <<<HTML
            <div class="user-row">
                {$avatarEl}
                <div class="ur-info">
                    <div class="ur-name">{$uName}</div>
                    <div class="ur-email">{$uEmail}</div>
                </div>
                <div class="ur-meta">
                    <span class="role-badge {$roleClass}">{$roleLabel}</span>
                    <span class="ur-date">{$joined}</span>
                </div>
                <div class="ur-actions">
                    <button class="btn-sm {$adminBtnClass}" data-id="{$uId}" data-admin="{$adminDataVal}" onclick="toggleAdmin(this)"{$disabled}>{$adminBtnLabel}</button>
                    <button class="btn-sm btn-del" data-id="{$uId}" onclick="confirmDelete(this)"{$disabled}>{$tDelete}</button>
                </div>
            </div>
            HTML;
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
}
