<?php
declare(strict_types=1);

namespace App\Application\Actions\Error;

use App\Infrastructure\I18n\PhpTranslator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class UnauthorizedAction
{
    private const REDIRECT_SECONDS = 4;

    public function __invoke(Request $request, Response $response): Response
    {
        /** @var PhpTranslator $tr */
        $tr   = $request->getAttribute('translator');
        $lang = $tr->getLang();
        $t    = fn(string $k) => htmlspecialchars($tr->t($k));

        $langSelector  = $this->langSelector($lang);
        $tTitle        = $t('error.title');
        $tDesc         = $t('error.desc');
        $tRedirecting  = $t('error.redirecting');
        $secs          = self::REDIRECT_SECONDS;

        $html = <<<HTML
        <!DOCTYPE html>
        <html lang="{$lang}">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>{$tTitle} — Container Sandbox</title>
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&family=Syne:wght@400;600;800&display=swap" rel="stylesheet">
            <style>
                *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

                :root {
                    --bg:       #0b0d14;
                    --surface:  #12151f;
                    --border:   #1e2235;
                    --border-h: #2d3458;
                    --accent:   #3b4fd8;
                    --accent-lo:#1e2760;
                    --blue:     #60a5fa;
                    --dim:      #4a5580;
                    --text:     #c8d0e8;
                    --white:    #eef2ff;
                    --red:      #ef4444;
                    --red-lo:   #2d1010;
                    --red-brd:  #5a1e1e;
                }

                html, body { min-height: 100vh; background: var(--bg); color: var(--text);
                    font-family: 'Syne', sans-serif;
                    display: flex; align-items: center; justify-content: center; }

                /* Grid */
                body::before { content: ''; position: fixed; inset: 0;
                    background-image: linear-gradient(var(--border) 1px, transparent 1px),
                        linear-gradient(90deg, var(--border) 1px, transparent 1px);
                    background-size: 48px 48px;
                    mask-image: radial-gradient(ellipse 80% 60% at 50% 0%, black 30%, transparent 100%);
                    pointer-events: none; z-index: 0; }

                /* Red glow */
                body::after { content: ''; position: fixed; top: -200px; left: 50%;
                    transform: translateX(-50%); width: 600px; height: 500px;
                    background: radial-gradient(ellipse, rgba(239,68,68,.12) 0%, transparent 70%);
                    pointer-events: none; z-index: 0; }

                /* Lang selector */
                .lang-bar { position: fixed; top: 20px; right: 24px; z-index: 100;
                    display: flex; gap: 4px; background: var(--surface);
                    border: 1px solid var(--border); border-radius: 8px; padding: 4px; }
                .lang-btn { font-family: 'JetBrains Mono', monospace; font-size: 11px;
                    font-weight: 500; letter-spacing: .06em; color: var(--dim);
                    text-decoration: none; padding: 5px 10px; border-radius: 5px;
                    transition: background .15s, color .15s; }
                .lang-btn:hover { color: var(--text); }
                .lang-btn.active { background: var(--accent-lo); color: var(--blue); }

                /* Card */
                .card { position: relative; z-index: 1; display: flex; flex-direction: column;
                    align-items: center; text-align: center; max-width: 400px;
                    padding: 0 24px; animation: fade-up .5s ease both; }

                /* Icon */
                .icon-wrap { width: 72px; height: 72px; border-radius: 50%;
                    background: var(--red-lo); border: 1px solid var(--red-brd);
                    display: flex; align-items: center; justify-content: center;
                    margin-bottom: 28px;
                    animation: pulse-red 2.5s ease-in-out infinite; }

                @keyframes pulse-red {
                    0%, 100% { box-shadow: 0 0 0 0 rgba(239,68,68,.0); }
                    50%       { box-shadow: 0 0 0 12px rgba(239,68,68,.0); }
                }

                h1 { font-size: 28px; font-weight: 800; color: var(--white);
                    letter-spacing: -.02em; margin-bottom: 12px; }

                .desc { font-family: 'JetBrains Mono', monospace; font-size: 13px;
                    color: var(--dim); line-height: 1.7; margin-bottom: 40px; }

                /* Countdown */
                .countdown-wrap { width: 100%; }

                .countdown-text { font-family: 'JetBrains Mono', monospace; font-size: 12px;
                    color: var(--dim); margin-bottom: 12px;
                    display: flex; align-items: center; justify-content: center; gap: 6px; }

                .countdown-text span { font-size: 18px; font-weight: 700;
                    color: var(--red); font-family: 'Syne', sans-serif;
                    min-width: 24px; display: inline-block; }

                .bar-track { width: 100%; height: 2px; background: var(--border);
                    border-radius: 1px; overflow: hidden; }

                .bar-fill { height: 100%; width: 0%; background: var(--red);
                    border-radius: 1px;
                    transition: width 1s linear; }

                @keyframes fade-up {
                    from { opacity: 0; transform: translateY(16px); }
                    to   { opacity: 1; transform: translateY(0); }
                }
            </style>
        </head>
        <body>

            {$langSelector}

            <div class="card">
                <div class="icon-wrap">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none"
                         stroke="#ef4444" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                </div>

                <h1>{$tTitle}</h1>
                <p class="desc">{$tDesc}</p>

                <div class="countdown-wrap">
                    <p class="countdown-text">
                        {$tRedirecting}&nbsp;<span id="count">{$secs}</span>s
                    </p>
                    <div class="bar-track">
                        <div class="bar-fill" id="bar"></div>
                    </div>
                </div>
            </div>

        <script>
        (function () {
            const total = {$secs};
            let remaining = total;
            const countEl = document.getElementById('count');
            const barEl   = document.getElementById('bar');

            // Trigger bar start after paint
            requestAnimationFrame(() => {
                barEl.style.width = '100%';
            });

            const interval = setInterval(() => {
                remaining--;
                countEl.textContent = remaining;
                if (remaining <= 0) {
                    clearInterval(interval);
                    window.location.href = '/';
                }
            }, 1000);
        })();
        </script>
        </body>
        </html>
        HTML;

        $response->getBody()->write($html);
        return $response->withStatus(403)->withHeader('Content-Type', 'text/html');
    }

    private function langSelector(string $current): string
    {
        $langs = ['en' => 'EN', 'es' => 'ES', 'de' => 'DE'];
        $html  = '<div class="lang-bar">';
        foreach ($langs as $code => $label) {
            $active = $code === $current ? ' active' : '';
            $html  .= "<a href=\"/lang/{$code}\" class=\"lang-btn{$active}\">{$label}</a>";
        }
        $html .= '</div>';
        return $html;
    }
}
