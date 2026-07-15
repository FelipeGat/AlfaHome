<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Baixar o app — AlfaHome</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
    <style>
        :root {
            --color-primary: #57BA87;
            --color-primary-hover: #47a877;
            --color-bg: #F7F9FC;
            --color-bg-card: #FFFFFF;
            --color-border: #E5E7EB;
            --color-text: #1F2937;
            --color-text-muted: #6B7280;
            --color-text-subtle: #9CA3AF;
            --color-warning: #d97706;
            --color-warning-soft: #fef3c7;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Figtree', system-ui, sans-serif;
            background: var(--color-bg);
            color: var(--color-text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .wrap {
            width: 100%;
            max-width: 440px;
            padding: 48px 20px 40px;
            text-align: center;
        }
        .logo { width: 72px; height: 72px; margin-bottom: 18px; }
        h1 { font-size: 1.5rem; font-weight: 800; margin: 0 0 6px; }
        .subtitle { color: var(--color-text-muted); font-size: 0.95rem; margin: 0 0 32px; }

        .card {
            background: var(--color-bg-card);
            border: 1px solid var(--color-border);
            border-radius: 18px;
            padding: 24px;
            margin-bottom: 16px;
            text-align: left;
        }
        .card-title { display: flex; align-items: center; gap: 10px; font-weight: 700; font-size: 1.05rem; margin-bottom: 4px; }
        .card-title svg { flex-shrink: 0; }
        .version-tag {
            display: inline-block;
            background: rgba(87, 186, 135, 0.14);
            color: var(--color-primary-hover);
            font-weight: 700;
            font-size: 0.78rem;
            padding: 3px 10px;
            border-radius: 99px;
            margin-bottom: 14px;
        }
        .changelog {
            font-size: 0.85rem;
            color: var(--color-text-muted);
            line-height: 1.5;
            white-space: pre-line;
            margin-bottom: 16px;
        }
        .btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 14px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.95rem;
            text-decoration: none;
            border: none;
            cursor: pointer;
        }
        .btn-primary { background: var(--color-primary); color: #fff; }
        .btn-primary:hover { background: var(--color-primary-hover); }
        .btn-disabled { background: var(--color-border); color: var(--color-text-subtle); cursor: not-allowed; }
        .hint { font-size: 0.78rem; color: var(--color-text-subtle); margin-top: 10px; text-align: center; }

        .ios-note {
            background: var(--color-warning-soft);
            color: #92400e;
            border-radius: 12px;
            padding: 12px 14px;
            font-size: 0.82rem;
            line-height: 1.5;
        }
        .skeleton { height: 14px; border-radius: 6px; background: var(--color-border); animation: pulse 1.2s ease-in-out infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
        .back-link { margin-top: 28px; font-size: 0.85rem; color: var(--color-text-muted); text-decoration: none; }
        .back-link:hover { color: var(--color-text); }
    </style>
</head>
<body>
    <div class="wrap">
        <img class="logo" src="/alfa-home-logo/alfa-home-logo.png" alt="AlfaHome">
        <h1>Baixar o app AlfaHome</h1>
        <p class="subtitle">Sua vida financeira em um só lugar, direto no celular.</p>

        <div class="card">
            <div class="card-title">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M5 16V6a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v10" stroke="#57BA87" stroke-width="2" stroke-linecap="round"/><rect x="4" y="16" width="16" height="5" rx="1.5" stroke="#57BA87" stroke-width="2"/><path d="M9 21h6" stroke="#57BA87" stroke-width="2" stroke-linecap="round"/></svg>
                Android
            </div>
            <div id="android-body">
                <div class="skeleton" style="width: 90px; margin: 6px 0 14px;"></div>
                <div class="skeleton" style="width: 100%; margin-bottom: 6px;"></div>
                <div class="skeleton" style="width: 70%; margin-bottom: 16px;"></div>
                <div class="skeleton" style="height: 46px; border-radius: 12px;"></div>
            </div>
        </div>

        <div class="card">
            <div class="card-title">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M16.5 3c.3 1.3-.2 2.7-1 3.6-.8.9-2.1 1.6-3.4 1.5-.2-1.2.3-2.6 1.1-3.5C14 3.6 15.3 3 16.5 3Z" stroke="#1F2937" stroke-width="1.6"/><path d="M20.5 17.3c-.5 1.2-.8 1.7-1.5 2.7-1 1.4-2.3 3.1-4 3.1-1.5 0-1.9-1-4-1s-2.6 1-4 1c-1.7 0-3.1-1.6-4-2.9C1 17.6.4 14.2 1.7 11.7c.9-1.8 2.5-2.9 4.2-2.9 1.5 0 2.5 1 3.8 1s2.1-1 4-1c1.3 0 2.9.6 3.9 1.9-3.4 1.9-2.9 6.8 1.9 8.6Z" stroke="#1F2937" stroke-width="1.6"/></svg>
                iPhone
            </div>
            <div class="ios-note">
                No iPhone, a distribuição fora da App Store não é possível. Fale com o suporte pra saber como testar a versão iOS.
            </div>
        </div>

        <a href="/login" class="back-link">Já tem conta? Entrar pelo navegador →</a>
    </div>

    <script>
        (async function () {
            const body = document.getElementById('android-body');
            try {
                const res = await fetch('/api/app/version');
                if (res.status === 204) {
                    body.innerHTML = '<p style="color:var(--color-text-muted);font-size:0.9rem;margin:0;">O download direto ainda não está disponível — em breve por aqui.</p>';
                    return;
                }
                if (!res.ok) throw new Error('status ' + res.status);
                const { data } = await res.json();
                const changelog = (data.changelog || '').trim();
                body.innerHTML = `
                    <span class="version-tag">Versão ${data.versao}</span>
                    ${changelog ? `<p class="changelog">${changelog.replace(/</g, '&lt;')}</p>` : ''}
                    <a class="btn btn-primary" href="${data.url}" download>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M12 3v13m0 0-4-4m4 4 4-4" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M5 19h14" stroke="#fff" stroke-width="2" stroke-linecap="round"/></svg>
                        Baixar APK
                    </a>
                    <p class="hint">Depois de baixar, talvez seja preciso habilitar "Instalar apps desconhecidos" nas configurações do celular.</p>
                `;
            } catch (e) {
                body.innerHTML = '<p style="color:var(--color-text-muted);font-size:0.9rem;margin:0;">Não foi possível carregar as informações do app agora. Tenta de novo em instantes.</p>';
            }
        })();
    </script>
</body>
</html>
