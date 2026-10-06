<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#0b1220">

        <title>{{ config('app.name', 'AlfaHome') }}</title>
        <link rel="icon" type="image/png" href="/favicon.png">
        <link rel="icon" type="image/svg+xml" href="/marca/simbolo.svg">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800|plus-jakarta-sans:700,800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            .g-body { min-height:100vh; background:#0b1220; color:#e5edf5; font-family:'Figtree',system-ui,sans-serif; position:relative; overflow-x:hidden; }
            .g-body::before { content:''; position:fixed; inset:0; pointer-events:none;
                background:
                    radial-gradient(900px 600px at 15% 85%, rgba(20,184,166,.16), transparent 60%),
                    radial-gradient(700px 500px at 85% 10%, rgba(16,185,129,.10), transparent 60%),
                    linear-gradient(160deg, #0b1220 0%, #0d1a2b 55%, #08111c 100%); }
            .g-wrap { position:relative; min-height:100vh; display:grid; grid-template-columns:1.1fr .9fr; align-items:center; gap:48px; max-width:1240px; margin:0 auto; padding:48px 40px; }

            /* Lado da marca */
            .g-marca img { height:40px; width:auto; }
            .g-traco { width:36px; height:3px; border-radius:3px; background:#2DD4BF; margin:22px 0 26px; }
            .g-titulo { font-size:clamp(34px,4.2vw,56px); line-height:1.05; font-weight:800; letter-spacing:-.02em; color:#fff; margin:0; }
            .g-titulo span { color:#5EEAD4; }
            .g-sub { margin-top:18px; font-size:18px; line-height:1.5; color:#9fb0c3; max-width:470px; }

            /* Ilustração do painel (decorativa) */
            .g-ilus { position:relative; margin-top:44px; height:330px; perspective:1400px; }
            .g-painel { position:absolute; left:0; top:0; width:560px; height:300px; border-radius:18px; background:rgba(15,27,43,.92);
                border:1px solid rgba(96,165,250,.22); box-shadow:0 30px 80px rgba(0,0,0,.45);
                transform:rotateY(14deg) rotateX(6deg) rotateZ(-3deg); transform-origin:left center; display:grid; grid-template-columns:130px 1fr; overflow:hidden; }
            .g-menu { border-right:1px solid rgba(255,255,255,.06); padding:16px 12px; font-size:11px; color:#8ea2b8; }
            .g-menu b { display:block; font-size:12px; color:#E2E8F0; margin-bottom:14px; letter-spacing:.02em; }
            .g-menu div { padding:7px 8px; border-radius:7px; margin-bottom:3px; }
            .g-menu div.ativo { background:rgba(96,165,250,.16); color:#DBEAFE; }
            .g-conteudo { padding:16px 18px; }
            .g-conteudo small { color:#8ea2b8; font-size:10px; }
            .g-saldo { font-size:22px; font-weight:800; color:#fff; margin:2px 0 4px; }
            .g-up { font-size:9px; color:#34d399; }
            .g-grafico { margin-top:6px; }
            .g-linha { display:flex; justify-content:space-between; font-size:10px; padding:7px 0; border-top:1px solid rgba(255,255,255,.05); color:#c7d3df; }
            .g-linha span:last-child.e { color:#34d399; } .g-linha span:last-child.s { color:#fb7185; }
            .g-flutua { position:absolute; border-radius:14px; padding:12px 16px; display:flex; gap:12px; align-items:center; background:rgba(13,30,40,.95);
                box-shadow:0 18px 40px rgba(0,0,0,.45); }
            .g-flutua i { width:38px; height:38px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-style:normal; font-size:18px; font-weight:800; }
            .g-flutua small { display:block; font-size:11px; color:#9fb0c3; }
            .g-flutua strong { font-size:17px; color:#fff; }
            .g-entradas { left:70px; top:262px; border:1px solid rgba(52,211,153,.35); }
            .g-entradas i { background:rgba(16,185,129,.2); color:#34d399; }
            .g-saidas { left:430px; top:150px; border:1px solid rgba(96,165,250,.35); }
            .g-saidas i { background:rgba(244,63,94,.18); color:#fb7185; }

            /* Cartão do formulário */
            .g-cartao { background:rgba(17,26,40,.88); border:1px solid rgba(255,255,255,.08); border-radius:22px; padding:44px 44px 34px;
                box-shadow:0 30px 80px rgba(0,0,0,.4); backdrop-filter:blur(8px); width:100%; max-width:540px; justify-self:end; }
            .g-cartao h1 { font-size:32px; font-weight:800; color:#fff; text-align:center; margin:0; letter-spacing:-.01em; }
            .g-cartao .g-cartao-sub { text-align:center; color:#9fb0c3; font-size:16px; line-height:1.45; margin:10px auto 30px; max-width:360px; }
            .g-campo { margin-bottom:20px; }
            .g-campo label { display:block; font-size:15px; font-weight:600; color:#e5edf5; margin-bottom:8px; }
            .g-entrada { position:relative; }
            .g-entrada svg.g-ic { position:absolute; left:16px; top:50%; transform:translateY(-50%); width:20px; height:20px; color:#8ea2b8; pointer-events:none; }
            .g-entrada input { width:100%; height:54px; border-radius:12px; background:#1a2638; border:1px solid #2b3a50; color:#fff; font-size:16px; padding:0 48px 0 50px; }
            .g-entrada input::placeholder { color:#6f8197; }
            .g-entrada input:focus { outline:none; border-color:#60A5FA; box-shadow:0 0 0 3px rgba(96,165,250,.28); }
            .g-olho { position:absolute; right:10px; top:50%; transform:translateY(-50%); width:36px; height:36px; display:flex; align-items:center; justify-content:center; background:none; border:0; color:#8ea2b8; cursor:pointer; border-radius:8px; }
            .g-olho:hover { color:#e5edf5; }
            .g-opcoes { display:flex; justify-content:space-between; align-items:center; gap:12px; margin:4px 0 26px; font-size:15px; }
            .g-opcoes label { display:flex; align-items:center; gap:10px; color:#e5edf5; cursor:pointer; }
            .g-opcoes input[type=checkbox] { width:20px; height:20px; border-radius:5px; accent-color:#2563EB; background-color:#1a2638; border:1px solid #3a4b63; color:#2563EB; }
            .g-opcoes input[type=checkbox]:checked { background-color:#2563EB; border-color:#2563EB; }
            .g-opcoes input[type=checkbox]:focus { box-shadow:0 0 0 3px rgba(96,165,250,.3); outline:none; }
            .g-link { color:#60A5FA; font-weight:600; text-decoration:none; }
            .g-link:hover { color:#93C5FD; }
            .g-botao { width:100%; height:56px; border:0; border-radius:12px; font-size:18px; font-weight:700; color:#fff; cursor:pointer;
                background:linear-gradient(90deg,#2563EB,#1D4ED8); display:flex; align-items:center; justify-content:center; gap:10px; box-shadow:0 10px 30px rgba(37,99,235,.28); }
            .g-botao:hover { filter:brightness(1.08); }
            .g-botao:focus-visible, .g-link:focus-visible, .g-olho:focus-visible { outline:2px solid #93C5FD; outline-offset:2px; }
            .g-ou { display:flex; align-items:center; gap:14px; color:#6f8197; font-size:14px; margin:26px 0 18px; }
            .g-ou::before, .g-ou::after { content:''; flex:1; height:1px; background:#26354a; }
            .g-seguro { display:flex; gap:14px; align-items:center; justify-content:center; color:#8ea2b8; font-size:13px; line-height:1.45; }
            .g-erro { color:#fda4af; font-size:13px; margin-top:6px; }
            .g-status { background:rgba(96,165,250,.12); border:1px solid rgba(96,165,250,.35); color:#BFDBFE; border-radius:10px; padding:10px 14px; font-size:14px; margin-bottom:18px; }

            @media (max-width: 980px) {
                .g-wrap { grid-template-columns:1fr; gap:28px; padding:32px 20px calc(32px + env(safe-area-inset-bottom)); }
                .g-ilus, .g-sub { display:none; }
                .g-titulo { font-size:30px; }
                .g-traco { margin:16px 0; }
                .g-cartao { justify-self:center; padding:30px 22px 26px; }
                .g-cartao h1 { font-size:26px; }
            }
        </style>
    </head>
    <body class="antialiased">
        <div class="g-body">
            <div class="g-wrap">
                <section aria-hidden="false">
                    <a href="/" class="g-marca" style="text-decoration:none;--marca-1:#E2E8F0;--marca-2:#60A5FA;--marca-3:#2DD4BF;--marca-texto:#F8FAFC;"><x-marca :tamanho="46" /></a>
                    <div class="g-traco"></div>
                    <h2 class="g-titulo">Sua vida financeira,<br><span>mais simples.</span></h2>
                    <p class="g-sub">Controle suas entradas, saídas e saldo da conta em um só lugar, de forma simples e organizada.</p>

                    {{-- Ilustração (valores de exemplo, não são dados de ninguém) --}}
                    <div class="g-ilus" aria-hidden="true">
                        <div class="g-painel">
                            <div class="g-menu">
                                <b>alfahome</b>
                                <div class="ativo">Início</div><div>Extrato</div><div>Contas</div><div>Planejamento</div><div>Alertas</div>
                            </div>
                            <div class="g-conteudo">
                                <small>Saldo total</small>
                                <div class="g-saldo">R$ 4.850,00</div>
                                <svg class="g-grafico" viewBox="0 0 380 70" width="100%" height="70">
                                    <defs><linearGradient id="gArea" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#60A5FA" stop-opacity=".35"/><stop offset="1" stop-color="#60A5FA" stop-opacity="0"/></linearGradient></defs>
                                    <path d="M0 60 C40 58 60 40 100 44 S170 30 200 34 S260 10 300 18 S350 4 380 2 L380 70 L0 70 Z" fill="url(#gArea)"/>
                                    <path d="M0 60 C40 58 60 40 100 44 S170 30 200 34 S260 10 300 18 S350 4 380 2" fill="none" stroke="#60A5FA" stroke-width="2.5"/>
                                </svg>
                                <small>Últimas movimentações</small>
                                <div class="g-linha"><span>Salário</span><span class="e">+R$ 4.500,00</span></div>
                                <div class="g-linha"><span>Supermercado</span><span class="s">-R$ 320,00</span></div>
                                <div class="g-linha"><span>Internet</span><span class="s">-R$ 99,90</span></div>
                            </div>
                        </div>
                        <div class="g-flutua g-entradas"><i>↑</i><div><small>Entradas</small><strong>R$ 7.200,00</strong></div></div>
                        <div class="g-flutua g-saidas"><i>↓</i><div><small>Saídas</small><strong>R$ 2.350,00</strong></div></div>
                    </div>
                </section>

                <main class="g-cartao">
                    @isset($titulo)
                        <h1>{{ $titulo }}</h1>
                    @endisset
                    @isset($subtitulo)
                        <p class="g-cartao-sub">{{ $subtitulo }}</p>
                    @endisset
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
