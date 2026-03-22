<?php
declare(strict_types=1);

namespace App\Application\Actions\Landing;

use App\Infrastructure\I18n\PhpTranslator;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class LandingAction
{
    public function __invoke(Request $request, Response $response): Response
    {
        $jwt = $request->getCookieParams()['jwt'] ?? null;
        if ($jwt) {
            try {
                $decoded    = JWT::decode($jwt, new Key(getenv('JWT_SECRET'), 'HS256'));
                $destination = ($decoded->is_admin ?? false) ? '/dashboard' : '/youtube';
                return $response->withHeader('Location', $destination)->withStatus(302);
            } catch (\Exception) {
                // Token inválido o expirado — mostrar landing normalmente
            }
        }

        /** @var PhpTranslator $tr */
        $tr   = $request->getAttribute('translator');
        $lang = $tr->getLang();
        $t    = fn(string $k) => htmlspecialchars($tr->t($k));

        // ── Services (add more entries here as new instances come online) ──
        $services = [
            ['icon' => '🔐', 'name' => $t('service.auth.name'), 'desc' => $t('service.auth.desc')],
            ['icon' => '🪙', 'name' => $t('service.jwt.name'),  'desc' => $t('service.jwt.desc')],
            ['icon' => '🏛️', 'name' => $t('service.hex.name'),  'desc' => $t('service.hex.desc')],
        ];

        // ── Build carousel cards ──────────────────────────────────────────
        $cardsHtml = '';
        foreach ($services as $svc) {
            $cardsHtml .= <<<HTML
            <div class="scard">
                <div class="scard-icon">{$svc['icon']}</div>
                <div class="scard-name">{$svc['name']}</div>
                <div class="scard-desc">{$svc['desc']}</div>
            </div>
            HTML;
        }

        // ── Build dots ───────────────────────────────────────────────────
        $dotsHtml = '';
        foreach ($services as $i => $_) {
            $active     = $i === 0 ? ' active' : '';
            $dotsHtml  .= "<button class=\"dot{$active}\" onclick=\"carouselGo({$i})\"></button>";
        }

        // ── Lang selector ────────────────────────────────────────────────
        $langSelector = $this->langSelector($lang);

        // ── Translations ─────────────────────────────────────────────────
        $tSubtitle  = $t('landing.subtitle');
        $tCta       = $t('landing.cta_google');
        $tInstances = $t('landing.instances');
        $tMadeBy    = $t('footer.made_by');
        $count      = count($services);

        $html = <<<HTML
        <!DOCTYPE html>
        <html lang="{$lang}">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Container Sandbox</title>
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Syne:wght@400;600;800&display=swap" rel="stylesheet">
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
                    --muted:    #3a4060;
                    --text:     #c8d0e8;
                    --dim:      #4a5580;
                    --white:    #eef2ff;
                }

                html, body { height: 100%; background: var(--bg); color: var(--text);
                    font-family: 'Syne', sans-serif; overflow-x: hidden; }

                /* Grid background */
                body::before { content: ''; position: fixed; inset: 0;
                    background-image: linear-gradient(var(--border) 1px, transparent 1px),
                        linear-gradient(90deg, var(--border) 1px, transparent 1px);
                    background-size: 48px 48px;
                    mask-image: radial-gradient(ellipse 80% 60% at 50% 0%, black 30%, transparent 100%);
                    pointer-events: none; z-index: 0; }

                /* Glow */
                body::after { content: ''; position: fixed; top: -200px; left: 50%;
                    transform: translateX(-50%); width: 700px; height: 500px;
                    background: radial-gradient(ellipse, rgba(59,79,216,.16) 0%, transparent 70%);
                    pointer-events: none; z-index: 0; }

                .wrap { position: relative; z-index: 1; min-height: 100vh;
                    display: flex; flex-direction: column; }

                /* ── Lang selector ── */
                .lang-bar { position: fixed; top: 20px; right: 24px; z-index: 100;
                    display: flex; gap: 4px; background: var(--surface);
                    border: 1px solid var(--border); border-radius: 8px; padding: 4px; }

                .lang-btn { font-family: 'JetBrains Mono', monospace; font-size: 11px;
                    font-weight: 500; letter-spacing: .06em; color: var(--dim);
                    text-decoration: none; padding: 5px 10px; border-radius: 5px;
                    transition: background .15s, color .15s; }

                .lang-btn:hover { color: var(--text); }
                .lang-btn.active { background: var(--accent-lo); color: var(--blue); }

                /* ── Hero ── */
                .hero { flex: 1; display: flex; flex-direction: column;
                    align-items: center; justify-content: center;
                    padding: 100px 24px 60px; text-align: center; }

                .badge { display: inline-flex; align-items: center; gap: 8px;
                    background: var(--accent-lo); border: 1px solid var(--border-h);
                    border-radius: 100px; padding: 6px 14px;
                    font-family: 'JetBrains Mono', monospace; font-size: 11px;
                    color: var(--blue); letter-spacing: .06em; margin-bottom: 40px;
                    animation: fade-up .5s ease both; }

                .dot-live { width: 6px; height: 6px; border-radius: 50%;
                    background: #22c55e; box-shadow: 0 0 6px #22c55e;
                    animation: blink 2.5s ease-in-out infinite; }

                h1 { font-size: clamp(52px, 9vw, 96px); font-weight: 800; line-height: 1;
                    letter-spacing: -.03em; color: var(--white); margin-bottom: 12px;
                    animation: fade-up .5s .1s ease both; }

                h1 .outline { color: transparent; -webkit-text-stroke: 1px rgba(96,165,250,.5); }

                .cursor { display: inline-block; width: 3px; height: .82em;
                    background: var(--blue); margin-left: 5px; vertical-align: middle;
                    animation: blink-c 1.1s step-end infinite; }

                .subtitle { font-size: 16px; color: var(--dim); max-width: 460px;
                    line-height: 1.75; margin: 20px auto 48px;
                    animation: fade-up .5s .2s ease both; }

                .cta { animation: fade-up .5s .3s ease both; }

                .btn-google { display: inline-flex; align-items: center; gap: 10px;
                    background: var(--accent); color: #fff;
                    font-family: 'Syne', sans-serif; font-weight: 600; font-size: 15px;
                    padding: 14px 32px; border-radius: 10px; text-decoration: none;
                    border: 1px solid rgba(255,255,255,.1);
                    box-shadow: 0 4px 24px rgba(59,79,216,.35);
                    transition: background .2s, transform .15s, box-shadow .2s; }

                .btn-google:hover { background: #4d63e8; transform: translateY(-2px);
                    box-shadow: 0 8px 32px rgba(59,79,216,.5); }

                /* ── Carousel ── */
                .services { padding: 0 40px 80px; max-width: 960px; margin: 0 auto; width: 100%; }

                .instances-label { font-family: 'JetBrains Mono', monospace; font-size: 11px;
                    color: var(--dim); letter-spacing: .1em; text-transform: uppercase;
                    text-align: center; margin-bottom: 20px; }

                .carousel-wrap { position: relative; }

                .carousel { display: flex; overflow-x: auto; scroll-snap-type: x mandatory;
                    scroll-behavior: smooth; gap: 16px; padding: 4px 2px 12px;
                    scrollbar-width: none; }
                .carousel::-webkit-scrollbar { display: none; }

                .scard { flex: 0 0 calc(33.33% - 12px); min-width: 240px;
                    scroll-snap-align: start; background: var(--surface);
                    border: 1px solid var(--border); border-radius: 14px;
                    padding: 28px 24px; transition: border-color .2s, transform .2s; }
                .scard:hover { border-color: var(--border-h); transform: translateY(-2px); }

                .scard-icon { font-size: 22px; margin-bottom: 14px; }
                .scard-name { font-size: 14px; font-weight: 600; color: var(--white); margin-bottom: 8px; }
                .scard-desc { font-family: 'JetBrains Mono', monospace; font-size: 11.5px;
                    color: var(--dim); line-height: 1.65; }

                .carousel-nav { display: flex; justify-content: center;
                    align-items: center; gap: 12px; margin-top: 20px; }

                .nav-btn { background: var(--surface); border: 1px solid var(--border);
                    color: var(--dim); border-radius: 8px; width: 34px; height: 34px;
                    cursor: pointer; display: flex; align-items: center; justify-content: center;
                    transition: border-color .2s, color .2s; }
                .nav-btn:hover { border-color: var(--border-h); color: var(--text); }

                .dots { display: flex; gap: 6px; }
                .dot { width: 6px; height: 6px; border-radius: 50%; border: none;
                    background: var(--border-h); cursor: pointer;
                    transition: background .2s, transform .2s; padding: 0; }
                .dot.active { background: var(--blue); transform: scale(1.4); }

                /* ── Footer ── */
                footer { border-top: 1px solid var(--border); padding: 20px 40px;
                    display: flex; align-items: center; justify-content: center;
                    gap: 6px; font-family: 'JetBrains Mono', monospace;
                    font-size: 12px; color: var(--dim); }

                footer a { color: var(--dim); text-decoration: none; transition: color .2s; }
                footer a:hover { color: var(--blue); }

                /* ── Animations ── */
                @keyframes fade-up {
                    from { opacity: 0; transform: translateY(16px); }
                    to   { opacity: 1; transform: translateY(0); }
                }
                @keyframes blink { 0%,100%{opacity:1} 50%{opacity:.3} }
                @keyframes blink-c { 0%,100%{opacity:1} 50%{opacity:0} }

                @media (max-width: 640px) {
                    .services { padding: 0 20px 60px; }
                    .scard { flex: 0 0 85vw; }
                    .hero { padding: 80px 20px 40px; }
                }
            </style>
        </head>
        <body>
        <div class="wrap">

            {$langSelector}

            <section class="hero">
                <div class="badge">
                    <span class="dot-live"></span>
                    container sandbox
                </div>

                <h1>
                    Container<br>
                    <span class="outline">Sandbox</span><span class="cursor"></span>
                </h1>

                <p class="subtitle">{$tSubtitle}</p>

                <div class="cta">
                    <a href="/auth/google" class="btn-google">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/>
                            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                        </svg>
                        {$tCta}
                    </a>
                </div>
            </section>

            <section class="services">
                <p class="instances-label">{$tInstances}</p>
                <div class="carousel-wrap">
                    <div class="carousel" id="carousel">
                        {$cardsHtml}
                    </div>
                </div>
                <div class="carousel-nav">
                    <button class="nav-btn" onclick="carouselNav(-1)" aria-label="prev">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="15 18 9 12 15 6"/></svg>
                    </button>
                    <div class="dots" id="dots">{$dotsHtml}</div>
                    <button class="nav-btn" onclick="carouselNav(1)" aria-label="next">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="9 18 15 12 9 6"/></svg>
                    </button>
                </div>
            </section>

            <footer>
                <span>{$tMadeBy}</span>
                <a href="https://github.com/DiazLighuen" target="_blank" rel="noopener">@DiazLighuen</a>
            </footer>

        </div>

        <script>
        (function () {
            const carousel = document.getElementById('carousel');
            const cards    = carousel.querySelectorAll('.scard');
            const dots     = document.querySelectorAll('#dots .dot');
            let current    = 0;

            function update(i) {
                current = Math.max(0, Math.min(i, cards.length - 1));
                carousel.scrollTo({ left: cards[current].offsetLeft - 2, behavior: 'smooth' });
                dots.forEach((d, idx) => d.classList.toggle('active', idx === current));
            }

            window.carouselNav = (dir) => update(current + dir);
            window.carouselGo  = (i)   => update(i);

            // Sync dots on scroll
            let ticking = false;
            carousel.addEventListener('scroll', () => {
                if (ticking) return;
                ticking = true;
                requestAnimationFrame(() => {
                    const scrollLeft = carousel.scrollLeft;
                    let closest = 0, minDist = Infinity;
                    cards.forEach((c, i) => {
                        const dist = Math.abs(c.offsetLeft - scrollLeft);
                        if (dist < minDist) { minDist = dist; closest = i; }
                    });
                    dots.forEach((d, i) => d.classList.toggle('active', i === closest));
                    current = closest;
                    ticking = false;
                });
            });
        })();
        </script>
        </body>
        </html>
        HTML;

        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html');
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
