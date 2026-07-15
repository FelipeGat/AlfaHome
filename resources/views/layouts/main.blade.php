<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'AlfaHome') }} — @yield('title', 'Dashboard')</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    {{-- ─── PWA ────────────────────────────────────────────────────── --}}
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" sizes="180x180" href="/icons/apple-touch-icon.png">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="AlfaHome">
    <meta name="theme-color" content="#0b1120" id="meta-theme-color">
    <meta name="vapid-public-key" content="{{ config('services.vapid.public_key') }}">
    {{-- ─── End PWA ─────────────────────────────────────────────────── --}}
    <script>if(localStorage.getItem('alfahome-theme')==='dark')document.documentElement.classList.add('ah-dark-preload');</script>
    <style>.ah-dark-preload body,.ah-dark-preload{background:#0b1120 !important;color:#e2e8f0 !important;}</style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        /* ─── Reset & Base ─────────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --sidebar-w: 240px;
            --sidebar-w-collapsed: 70px;
            --topbar-h: 64px;
            --bottom-nav-h: 60px;
            --color-primary: #57BA87;
            --color-primary-hover: #47a877;
            --color-success: #16a34a;
            --color-danger:  #dc2626;
            --color-warning: #d97706;
            --color-info:    #0ea5e9;
            --color-violet:  #7c3aed;
            --color-indigo:  #4f46e5;
            --color-amber:   #f59e0b;
            --color-sidebar: #0f172a;
            /* Light mode — 3-level surface hierarchy */
            --color-bg:          #F7F9FC;   /* page background   */
            --color-bg-card:     #FFFFFF;   /* card surface      */
            --color-border:      #E5E7EB;   /* all borders       */
            --color-text:        #1F2937;   /* primary text      */
            --color-text-muted:  #6B7280;   /* secondary text    */
            --color-text-subtle: #9CA3AF;   /* placeholder/hints */
            /* Soft tinted backgrounds (icon chips, mini-cards) */
            --color-success-soft: #dcfce7;
            --color-danger-soft:  #fee2e2;
            --color-warning-soft: #fef3c7;
            --color-info-soft:    #e0f2fe;
            --color-violet-soft:  #ede9fe;
            --radius-card: 8px;
            --radius-btn: 9999px;
            --radius-badge: 4px;
            --shadow-card: 0 2px 8px rgba(0,0,0,.05), 0 1px 2px rgba(0,0,0,.03);
        }

        html { font-size: 15px; }
        body {
            font-family: system-ui, -apple-system, 'Segoe UI', sans-serif;
            background: var(--color-bg);
            color: var(--color-text);
            line-height: 1.5;
        }
        /* Prevent flash of unstyled bg on dark mode reload */
        .ah-dark-preload body,
        .ah-dark-preload {
            background: #0b1120 !important;
        }

        /* ─── Sidebar ───────────────────────────────────────────── */
        .sidebar {
            position: fixed; top: 0; left: 0;
            width: var(--sidebar-w); height: 100vh;
            background: var(--color-bg-card, #fff);
            border-right: 1px solid var(--color-border);
            display: flex; flex-direction: column;
            z-index: 200;
            transition: width .25s ease, transform .25s ease, background .22s ease, border-color .22s ease;
            overflow-x: hidden;
            box-shadow: 1px 0 4px rgba(0,0,0,.04);
        }
        .sidebar.collapsed { width: var(--sidebar-w-collapsed); }

        .sidebar-logo {
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 16px;
            height: var(--topbar-h);
            border-bottom: 1px solid var(--color-border);
            border-top: 3px solid var(--color-primary);
            flex-shrink: 0;
            position: relative;
        }
        .sidebar-logo-img {
            height: 34px; width: auto;
            object-fit: contain;
            flex-shrink: 0;
            transition: opacity .2s;
        }
        .sidebar-logo-img-full { display: block; }
        .sidebar-logo-img-icon { display: none; }
        .sidebar.collapsed .sidebar-logo-img-full { display: none; }
        .sidebar.collapsed .sidebar-logo-img-icon { display: block; }

        /* Collapse toggle button */
        .sidebar-collapse-btn {
            position: fixed;
            left: calc(var(--sidebar-w) - 12px);
            top: calc(var(--topbar-h) / 2);
            transform: translateY(-50%);
            width: 24px; height: 24px;
            background: var(--color-bg-card, #fff);
            border: 1px solid var(--color-border);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; color: #94a3b8; font-size: 11px;
            z-index: 210;
            transition: background .15s, color .15s, border-color .15s;
            box-shadow: 0 1px 4px rgba(0,0,0,.12);
        }
        .sidebar-collapse-btn:hover { background: var(--color-primary); color: #fff; border-color: var(--color-primary); }
        .sidebar-collapse-btn i { pointer-events: none; }
        .sidebar-collapse-btn.collapsed { left: calc(var(--sidebar-w-collapsed) - 12px); }

        .sidebar-section-label {
            padding: 14px 16px 4px;
            font-size: 10px; font-weight: 700;
            color: #94a3b8; text-transform: uppercase; letter-spacing: .07em;
            white-space: nowrap; overflow: hidden;
            transition: opacity .2s, color .22s;
        }
        .sidebar.collapsed .sidebar-section-label { opacity: 0; height: 0; padding: 0; }

        .sidebar-nav { padding: 6px 8px; flex: 1; overflow-y: auto; overflow-x: hidden; }
        .sidebar-link {
            display: flex; align-items: center; gap: 10px;
            padding: 9px 10px; border-radius: 20px;
            color: #64748b; text-decoration: none;
            font-size: 14px; font-weight: 500;
            transition: background .15s, color .15s;
            white-space: nowrap; overflow: hidden;
            position: relative;
        }
        .sidebar-link i { width: 20px; text-align: center; font-size: 14px; flex-shrink: 0; }
        .sidebar-link span { transition: opacity .2s; }
        .sidebar.collapsed .sidebar-link { justify-content: center; gap: 0; padding: 11px 0; }
        .sidebar.collapsed .sidebar-link i { font-size: 18px; width: auto; }
        .sidebar.collapsed .sidebar-link span { display: none; }
        .sidebar-link:hover { background: #f1f5f9; color: #1e293b; }
        .sidebar-link.active { background: var(--color-primary); color: #fff; }
        .sidebar-link.active i { color: #fff; }

        /* Tooltip on collapsed */
        .sidebar.collapsed .sidebar-link:hover::after {
            content: attr(data-label);
            position: absolute; left: calc(var(--sidebar-w-collapsed) + 6px); top: 50%; transform: translateY(-50%);
            background: #1e293b; color: #e2e8f0;
            padding: 5px 10px; border-radius: 6px;
            font-size: 13px; white-space: nowrap;
            box-shadow: 0 2px 8px rgba(0,0,0,.3);
            z-index: 300; pointer-events: none;
        }

        .sidebar-user {
            padding: 12px 8px 10px;
            border-top: 1px solid var(--color-border);
            flex-shrink: 0;
        }
        .sidebar-user-info {
            display: flex; align-items: center; gap: 10px;
            padding: 6px 10px; margin-bottom: 4px;
            overflow: hidden;
        }
        .sidebar-user-avatar {
            width: 30px; height: 30px; flex-shrink: 0;
            border-radius: 50%; background: rgba(79,70,229,.15);
            display: flex; align-items: center; justify-content: center;
            color: var(--color-primary); font-size: 12px;
        }
        .sidebar-user-details { transition: opacity .2s; min-width: 0; }
        .sidebar.collapsed .sidebar-user-details { opacity: 0; width: 0; overflow: hidden; }
        .sidebar-user-name { color: #1e293b; font-size: 13px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sidebar-user-email { color: #94a3b8; font-size: 11px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sidebar-user-actions { display: flex; gap: 6px; padding: 8px 10px 0; margin-top: 4px; }
        .sidebar-user-action {
            flex: 1; display: flex; align-items: center; justify-content: center; gap: 6px;
            height: 32px; padding: 0 10px;
            border: 1px solid transparent; border-radius: 9999px;
            background: transparent; color: #94a3b8;
            font-size: 12px; font-weight: 500; text-decoration: none; cursor: pointer;
            transition: background .15s, color .15s, border-color .15s;
        }
        .sidebar-user-action i { font-size: 12px; }
        .sidebar-user-action:hover { background: rgba(148,163,184,.1); color: var(--color-primary); border-color: var(--color-border); }
        .sidebar-user-action--danger:hover { color: #ef4444; border-color: var(--color-border); }
        .sidebar-user-actions form { flex: 1; display: flex; }
        .sidebar.collapsed .sidebar-user-info { justify-content: center; padding: 6px 0; gap: 0; }
        .sidebar.collapsed .sidebar-user-actions { flex-direction: column; gap: 4px; padding: 8px 0 0; align-items: center; }
        .sidebar.collapsed .sidebar-user-actions form { flex: none; width: 36px; }
        .sidebar.collapsed .sidebar-user-action { height: 36px; width: 36px; padding: 0; flex: none; }
        .sidebar.collapsed .sidebar-user-action i { font-size: 14px; }
        .sidebar.collapsed .sidebar-user-action span { display: none; }

        /* ─── Overlay (mobile) ──────────────────────────────────── */
        .sidebar-overlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,.5);
            z-index: 190;
        }
        .sidebar-overlay.active { display: block; }

        /* ─── Main Layout ───────────────────────────────────────── */
        .main-content {
            margin-left: var(--sidebar-w);
            min-height: 100vh;
            display: flex; flex-direction: column;
            transition: margin-left .25s ease;
        }
        .main-content.sidebar-collapsed { margin-left: var(--sidebar-w-collapsed); }

        /* ─── Topbar ─────────────────────────────────────────────── */
        .topbar {
            position: sticky; top: 0; z-index: 100;
            height: var(--topbar-h);
            background: var(--color-bg-card, #fff);
            border-bottom: 1px solid var(--color-border);
            box-shadow: 0 1px 3px rgba(0,0,0,.06);
            display: flex; align-items: center;
            padding: 0 20px; gap: 12px;
            transition: background .22s ease, border-color .22s ease, box-shadow .22s ease;
        }
        .topbar-hamburger {
            display: none;
            width: 36px; height: 36px;
            border: none; background: none; cursor: pointer;
            border-radius: 9999px; color: #64748b;
            align-items: center; justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
            transition: background .15s, color .15s;
        }
        .topbar-hamburger:hover { background: #f1f5f9; color: #1e293b; }
        .topbar-title {
            flex: 1;
            font-size: 15px; font-weight: 600; color: #1e293b;
            transition: color .2s;
        }
        .topbar-actions { display: flex; align-items: center; gap: 8px; }
        .topbar-actions .btn-secondary {
            background: #f1f5f9; color: #64748b;
            border-color: #e2e8f0;
        }
        .topbar-actions .btn-secondary:hover { background: #e2e8f0; color: #1e293b; }
        .topbar-logo-mobile { display: none; height: 26px; width: auto; object-fit: contain; opacity: .92; }

        /* ─── Botão Tema ─────────────────────────────────────────── */
        .theme-toggle {
            display: flex; align-items: center; justify-content: center;
            width: 36px; height: 36px;
            border: 1px solid var(--color-border);
            border-radius: 9999px;
            background: var(--color-bg);
            color: #64748b;
            cursor: pointer;
            transition: background .15s, border-color .15s, color .15s;
            flex-shrink: 0;
        }
        .theme-toggle:hover { background: var(--color-border); color: var(--color-text); }
        /* Padrão (claro): mostra lua → clicar vai para escuro */
        .theme-toggle .icon-sun  { display: none; }
        .theme-toggle .icon-moon { display: block; }
        /* Dark mode: mostra sol → clicar vai para claro */
        body.dark-mode .theme-toggle .icon-sun  { display: block; }
        body.dark-mode .theme-toggle .icon-moon { display: none; }
        body.dark-mode .theme-toggle { background: rgba(255,255,255,.07); border-color: rgba(255,255,255,.1); color: #94a3b8; }
        body.dark-mode .theme-toggle:hover { background: rgba(255,255,255,.14); color: #e2e8f0; border-color: rgba(255,255,255,.2); }

        /* ═══════════════════════════════════════════════════════════
           DARK MODE COMPLETO — cobre todos os elementos do sistema
           ═══════════════════════════════════════════════════════════ */
        body.dark-mode {
            /* ── Paleta 1:1 do AlfaGym (dark) ── */
            --color-bg:            #0b1120;   /* bg-body    */
            --color-bg-container:  #0f172a;   /* bg-section */
            --color-bg-card:       #1e293b;   /* bg-card    */
            --color-bg-elevated:   #263548;   /* faq-hover  */
            --color-bg-hover:      #263548;
            --color-bg-inset:      #0b1120;

            --color-border:         #334155;
            --color-border-strong:  #334155;
            --color-border-focus:   rgba(22, 150, 163, 0.6);

            --color-text:         #e2e8f0;
            --color-text-muted:   #cbd5e1;
            --color-text-subtle:  #94a3b8;
            --color-text-faint:   #64748b;

            /* Soft tinted backgrounds (ícones semânticos) */
            --color-success-soft: rgba(34, 197, 94, 0.14);
            --color-danger-soft:  rgba(239, 68, 68, 0.14);
            --color-warning-soft: rgba(245, 158, 11, 0.16);
            --color-info-soft:    rgba(56, 189, 248, 0.14);
            --color-violet-soft:  rgba(139, 92, 246, 0.16);

            /* Semantic accents — tons claros para leitura no card escuro */
            --color-success: #4ade80;
            --color-danger:  #f87171;
            --color-warning: #fbbf24;
            --color-info:    #38bdf8;
            --color-violet:  #a78bfa;
            --color-indigo:  #818cf8;
            --color-amber:   #fbbf24;

            --shadow-card: 0 2px 12px rgba(0, 0, 0, 0.2);

            background: var(--color-bg);
            color: var(--color-text);
        }

        /* ── Sidebar dark ── */
        body.dark-mode .sidebar {
            background: var(--color-bg-container);
            border-right: 1px solid var(--color-border);
            box-shadow: 2px 0 12px rgba(0,0,0,.3);
        }
        body.dark-mode .sidebar-logo {
            border-bottom-color: rgba(255,255,255,.06);
        }
        body.dark-mode .sidebar-collapse-btn {
            background: var(--color-bg-card, #1F2937); border-color: var(--color-border); color: #94a3b8;
            box-shadow: 0 1px 4px rgba(0,0,0,.4);
        }
        body.dark-mode .sidebar-collapse-btn:hover { background: var(--color-primary); border-color: var(--color-primary); color: #fff; }
        body.dark-mode .sidebar-section-label { color: #475569; }
        body.dark-mode .sidebar-link { color: #9CA3AF; }
        body.dark-mode .sidebar-link:hover { background: var(--color-bg-hover, #273449); color: #E5E7EB; }
        body.dark-mode .sidebar-link.active {
            background: var(--color-primary); color: #fff;
            box-shadow: 0 2px 12px rgba(79,70,229,.3);
        }
        body.dark-mode .sidebar.collapsed .sidebar-link:hover::after {
            background: #172135; color: #e2e8f0; border: 1px solid #1e2e44;
        }
        body.dark-mode .sidebar-user { border-top-color: rgba(255,255,255,.06); }
        body.dark-mode .sidebar-user-avatar { background: rgba(79,70,229,.3); color: #a5b4fc; }
        body.dark-mode .sidebar-user-name { color: #cbd5e1; }
        body.dark-mode .sidebar-user-email { color: #94a3b8; }
        body.dark-mode .sidebar-user-action { color: #94a3b8; }
        body.dark-mode .sidebar-user-action:hover { background: rgba(255,255,255,.06); border-color: rgba(255,255,255,.1); }

        /* ── Topbar dark ── */
        body.dark-mode .topbar {
            background: var(--color-bg-container);
            border-bottom: 1px solid var(--color-border);
            box-shadow: 0 1px 0 rgba(255,255,255,.03), 0 2px 8px rgba(0,0,0,.2);
        }
        body.dark-mode .topbar-title { color: #e2e8f0; }
        body.dark-mode .topbar-hamburger { color: #94a3b8; }
        body.dark-mode .topbar-hamburger:hover { background: rgba(255,255,255,.06); color: #e2e8f0; }
        body.dark-mode .topbar-actions .btn-secondary {
            background: var(--color-bg-card, #1F2937); color: #9CA3AF; border-color: var(--color-border);
        }
        body.dark-mode .topbar-actions .btn-secondary:hover { background: var(--color-bg-hover, #273449); color: #E5E7EB; }

        /* ── Cards ── */
        body.dark-mode .card {
            background: var(--color-bg-card, #1F2937);
            border-color: var(--color-border);
            box-shadow: 0 6px 18px rgba(0,0,0,.35), 0 2px 6px rgba(0,0,0,.2);
        }
        body.dark-mode .card:hover {
            box-shadow: 0 10px 28px rgba(0,0,0,.45), 0 3px 8px rgba(0,0,0,.25);
        }
        body.dark-mode .card-title { color: #e2e8f0; }
        body.dark-mode .card-title i { color: #64748b; }

        /* ── KPIs ── */
        body.dark-mode .kpi-value { color: #e2e8f0; }
        body.dark-mode .kpi-label { color: #94a3b8; }
        body.dark-mode .kpi-sub   { color: #64748b; border-top-color: #1e2e44; }
        body.dark-mode .kpi-icon  { opacity: .9; }

        /* ── Títulos e textos gerais ── */
        body.dark-mode .section-header { color: #e2e8f0; }
        body.dark-mode .section-header h2 { color: #e2e8f0; }
        body.dark-mode .text-muted   { color: #94a3b8; }
        body.dark-mode .text-subtle  { color: #64748b; }
        body.dark-mode .fw-600 { color: #e2e8f0; }
        body.dark-mode p, body.dark-mode span, body.dark-mode label,
        body.dark-mode h1, body.dark-mode h2, body.dark-mode h3,
        body.dark-mode h4, body.dark-mode h5 { color: inherit; }

        /* ── Botões secundários / ghost ── */
        body.dark-mode .btn-secondary {
            background: var(--color-bg-card, #1F2937); color: #9CA3AF; border-color: var(--color-border);
        }
        body.dark-mode .btn-secondary:hover { background: var(--color-bg-hover, #273449); color: #E5E7EB; }
        body.dark-mode .btn-ghost { color: #9CA3AF; }
        body.dark-mode .btn-ghost:hover { background: var(--color-bg-card, #1F2937); color: #E5E7EB; }

        /* ── Badges ── */
        body.dark-mode .badge-green, body.dark-mode .badge-success {
            background: rgba(22,163,74,.18); color: #4ade80; }
        body.dark-mode .badge-red, body.dark-mode .badge-danger {
            background: rgba(220,38,38,.18); color: #f87171; }
        body.dark-mode .badge-amber, body.dark-mode .badge-warning, body.dark-mode .badge-yellow {
            background: rgba(217,119,6,.18); color: #fbbf24; }
        body.dark-mode .badge-blue, body.dark-mode .badge-info {
            background: rgba(37,99,235,.18); color: #60a5fa; }
        body.dark-mode .badge-slate, body.dark-mode .badge-gray {
            background: rgba(71,85,105,.22); color: #94a3b8; }
        body.dark-mode .badge-purple {
            background: rgba(109,40,217,.18); color: #a78bfa; }

        /* ── Alertas ── */
        body.dark-mode .alert-success {
            background: rgba(34,197,94,.07); color: #4ade80;
            border: 1px solid rgba(34,197,94,.15); border-left: 4px solid #22c55e; }
        body.dark-mode .alert-danger {
            background: rgba(239,68,68,.07); color: #f87171;
            border: 1px solid rgba(239,68,68,.15); border-left: 4px solid #ef4444; }
        body.dark-mode .alert-critical {
            background: var(--color-bg-card, #1F2937); color: var(--color-text);
            border: 1px solid var(--color-border); border-left: 4px solid #ef4444; }
        body.dark-mode .alert-critical .alert-title { color: #E5E7EB; }
        body.dark-mode .alert-warning {
            background: rgba(245,158,11,.07); color: #fbbf24;
            border: 1px solid rgba(245,158,11,.15); border-left: 4px solid #f59e0b; }
        body.dark-mode .alert-info {
            background: rgba(59,130,246,.07); color: #60a5fa;
            border: 1px solid rgba(59,130,246,.15); border-left: 4px solid #3b82f6; }

        /* ── Formulários ── */
        body.dark-mode .form-control {
            background: var(--color-bg-container, #111827); border-color: var(--color-border);
            color: #E5E7EB;
        }
        body.dark-mode input[type="date"],
        body.dark-mode input[type="datetime-local"],
        body.dark-mode input[type="month"],
        body.dark-mode input[type="time"] {
            color-scheme: dark;
        }
        body.dark-mode .form-control::placeholder { color: #6B7280; }
        body.dark-mode .form-control:focus {
            border-color: var(--color-primary); background: var(--color-bg-container, #111827);
            box-shadow: 0 0 0 3px rgba(79,70,229,.15);
        }
        body.dark-mode select.form-control option { background: var(--color-bg-card, #1F2937); color: #E5E7EB; }
        body.dark-mode .form-label { color: #9CA3AF; }
        body.dark-mode .form-check { color: #e2e8f0; }

        /* ── Tabelas (.table) ── */
        body.dark-mode .table thead th {
            background: var(--color-bg-container, #111827); color: #6B7280; border-bottom-color: var(--color-border);
        }
        body.dark-mode .table tbody td {
            color: #E5E7EB; border-bottom-color: var(--color-border);
        }
        body.dark-mode .table tbody tr:hover td { background: rgba(255,255,255,.03); }

        /* ── Modal ── */
        body.dark-mode .modal-backdrop { background: rgba(0,0,0,.75); }
        body.dark-mode .modal {
            background: var(--color-bg-card, #1F2937); border: 1px solid var(--color-border);
            box-shadow: 0 24px 48px rgba(0,0,0,.55);
        }
        body.dark-mode .modal-header { border-bottom-color: var(--color-border); }
        body.dark-mode .modal-header h3 { color: #E5E7EB; }
        body.dark-mode .modal-close { color: #6B7280; }
        body.dark-mode .modal-close:hover { background: var(--color-bg-hover, #273449); color: #9CA3AF; }
        body.dark-mode .modal-body { color: #E5E7EB; }
        body.dark-mode .modal-footer { border-top-color: var(--color-border); }

        /* ── Progress bar ── */
        body.dark-mode .progress-bar { background: var(--color-bg-hover, #273449); }

        /* ── Empty state ── */
        body.dark-mode .empty-state { color: #64748b; }

        /* ── Bottom nav ── */
        body.dark-mode .bottom-nav { background: var(--color-bg-container, #111827); border-top-color: var(--color-border); }
        body.dark-mode .bottom-nav a { color: #64748b; }
        body.dark-mode .bottom-nav a.active,
        body.dark-mode .bottom-nav a:hover { color: var(--color-primary); }

        /* ── Extrato (ext-*) ── */
        body.dark-mode .ext-header { border-bottom-color: #1e2e44; }
        body.dark-mode .ext-date-header {
            background: var(--color-bg-container, #111827); border-color: var(--color-border); }
        body.dark-mode .ext-date-label { color: #6B7280; }
        body.dark-mode .ext-row { border-bottom-color: var(--color-border); }
        body.dark-mode .ext-row:hover { background: rgba(255,255,255,.035); }
        body.dark-mode .ext-icone.ext-credito { background: rgba(22,163,74,.15); }
        body.dark-mode .ext-icone.ext-debito  { background: rgba(239,68,68,.15); }
        body.dark-mode .ext-desc { color: #e2e8f0; }
        body.dark-mode .ext-conta-pill { color: #64748b; }
        body.dark-mode .ext-tag-cat { background: var(--color-bg-hover, #273449); color: #6B7280; }
        body.dark-mode .ext-tag-rec { background: rgba(109,40,217,.15); color: #a78bfa; }
        body.dark-mode .ext-tag-doc { background: rgba(3,105,161,.15); color: #60a5fa; }
        body.dark-mode .ext-del-btn  { color: #64748b; }
        body.dark-mode .ext-edit-btn { color: #64748b; }
        body.dark-mode .ext-del-btn:hover  { background: rgba(239,68,68,.15); color: #f87171; }
        body.dark-mode .ext-edit-btn:hover { background: rgba(79,70,229,.15); color: #818cf8; }
        body.dark-mode .ext-footer { background: var(--color-bg-container, #111827); border-top-color: var(--color-border); }

        /* ── Filtros ── */
        body.dark-mode .filtros-bar { background: var(--color-bg-card, #1F2937); border-color: var(--color-border); }
        body.dark-mode .mes-label-btn { color: #E5E7EB; background: transparent; border: none; }
        body.dark-mode .nav-mes-btn { color: #9CA3AF; background: var(--color-bg-container, #111827); }
        body.dark-mode .nav-mes-btn:hover { background: var(--color-bg-hover, #273449); color: var(--color-primary); }
        body.dark-mode .seg-control { border-color: var(--color-border); }
        body.dark-mode .seg-btn { background: var(--color-bg-container, #111827); color: #9CA3AF; }
        body.dark-mode .seg-btn.ativo { background: var(--color-primary); color: #fff; }
        body.dark-mode .separador-v { background: var(--color-border); }
        body.dark-mode .filtro-grupo { border-bottom-color: var(--color-border); }
        body.dark-mode .av-nome { color: #64748b; }

        /* ── Fluxo de Caixa filtros ── */
        body.dark-mode .fc-atalho-btn:not(.ativo) {
            background: var(--color-bg-container, #111827); border-color: var(--color-border); color: #9CA3AF; }
        body.dark-mode .fc-atalho-btn:not(.ativo):hover { background: var(--color-bg-hover, #273449); color: #E5E7EB; }
        body.dark-mode .fc-periodo-label { color: #6B7280; border-top-color: var(--color-border); }

        /* ── Dashboard saldos ── */
        body.dark-mode .db-section-label { color: #64748b; }
        body.dark-mode .db-section-label::after { background: #1e2e44; }
        body.dark-mode .db-banco-nome { color: #e2e8f0; }
        body.dark-mode .db-banco-tipo { color: #64748b; }
        body.dark-mode .db-banco-item { border-bottom-color: #172135; }

        /* ── Investimentos KPI ── */
        body.dark-mode .inv-kpi-row .card { background: var(--color-bg-card, #1F2937); border-color: var(--color-border); }

        /* ── Fluxo de Caixa KPI ── */
        body.dark-mode .fc-kpi-grid .card { background: var(--color-bg-card, #1F2937); border-color: var(--color-border); }

        /* ── Sidebar tooltip (collapsed) ── */
        body.dark-mode .sidebar.collapsed .sidebar-link:hover::after {
            background: var(--color-bg-card, #1F2937); color: #E5E7EB; border: 1px solid var(--color-border); }

        /* ── Paginação ── */
        body.dark-mode nav[role="navigation"] { color: #94a3b8; }
        body.dark-mode nav[role="navigation"] a,
        body.dark-mode nav[role="navigation"] button { color: #9CA3AF; border-color: var(--color-border); background: var(--color-bg-card, #1F2937); }
        body.dark-mode nav[role="navigation"] span[aria-current] { background: var(--color-primary); color: #fff; border-color: var(--color-primary); }

        /* ── Transições suaves de tema (cores) ── */
        body, .topbar, .modal, .form-control, .btn-secondary,
        .table thead th, .bottom-nav,
        .ext-date-header, .ext-footer, .filtros-bar,
        .mes-label-btn, .nav-mes-btn, .seg-btn, .progress-bar {
            transition: background-color .22s ease, border-color .22s ease, color .18s ease;
        }
        /* Micro-interações globais — percepção de qualidade */
        button, a, .sidebar-link, .badge, .nav-mes-btn, .seg-btn,
        .av-item, .ext-del-btn, .ext-edit-btn {
            transition: background .18s ease, color .15s ease,
                        box-shadow .18s ease, transform .15s ease,
                        border-color .15s ease, opacity .15s ease;
        }
        button:active, .sidebar-link:active { transform: scale(.97); }

        /* ─── Page content ──────────────────────────────────────── */
        .page-content {
            padding: 24px;
            flex: 1;
            overflow-x: hidden;
            min-width: 0;
        }

        /* ─── Cards ─────────────────────────────────────────────── */
        .card {
            background: var(--color-bg-card, #fff);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-card);
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,.06), 0 1px 3px rgba(0,0,0,.03);
            transition: transform .2s ease, box-shadow .2s ease,
                        background-color .22s ease, border-color .22s ease;
        }
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,.09), 0 2px 6px rgba(0,0,0,.04);
        }
        .card-title {
            font-size: 13px; font-weight: 600; color: var(--color-text);
            display: flex; align-items: center; gap: 8px;
            margin-bottom: 16px; letter-spacing: .01em;
        }
        .card-title i { color: var(--color-text-muted); font-size: 13px; }

        /* ─── KPI ────────────────────────────────────────────────── */
        .kpi-label { font-size: 0.8rem; font-weight: 500; color: var(--color-text-muted); margin-bottom: 6px; letter-spacing: .02em; text-transform: uppercase; }
        .kpi-value { font-size: 1.9rem; font-weight: 700; line-height: 1.05; letter-spacing: -.03em; }
        .kpi-sub { font-size: 12px; color: var(--color-text-muted); margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--color-border); }
        .kpi-icon { width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; }

        /* ─── Buttons ────────────────────────────────────────────── */
        .btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 7px 14px; font-size: 13px; font-weight: 500;
            border-radius: var(--radius-btn); border: none;
            cursor: pointer; text-decoration: none;
            transition: background .18s ease, box-shadow .18s ease,
                        transform .15s ease, filter .15s ease, color .15s ease;
            white-space: nowrap;
        }
        .btn:active { transform: scale(.97); }
        .btn-primary {
            background: var(--color-primary); color: #fff; font-weight: 600;
            box-shadow: 0 2px 6px rgba(79,70,229,.25);
        }
        .btn-primary:hover {
            background: var(--color-primary-hover); color: #fff;
            box-shadow: 0 6px 16px rgba(79,70,229,.35);
            filter: brightness(1.05);
        }
        body.dark-mode .btn-primary:hover {
            box-shadow: 0 0 16px rgba(99,102,241,.55);
        }
        .btn-success { background: #16a34a; color: #fff; box-shadow: 0 2px 6px rgba(22,163,74,.2); }
        .btn-success:hover { background: #15803d; color: #fff; box-shadow: 0 6px 14px rgba(22,163,74,.3); }
        .btn-amber { background: #d97706; color: #fff; box-shadow: 0 2px 6px rgba(217,119,6,.2); }
        .btn-amber:hover { background: #b45309; color: #fff; box-shadow: 0 6px 14px rgba(217,119,6,.3); }
        .btn-secondary {
            background: var(--color-bg-card, #fff); color: var(--color-text-muted);
            border: 1px solid var(--color-border);
        }
        .btn-secondary:hover { background: var(--color-bg); color: var(--color-text); }
        .btn-danger { background: var(--color-danger); color: #fff; box-shadow: 0 2px 6px rgba(220,38,38,.2); }
        .btn-danger:hover { background: #b91c1c; box-shadow: 0 6px 14px rgba(220,38,38,.3); }
        .btn-ghost {
            background: none; border: none; cursor: pointer;
            color: var(--color-text-muted); padding: 5px 8px;
            border-radius: var(--radius-btn); font-size: 13px;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-ghost:hover { background: var(--color-bg); color: var(--color-text); }
        .btn-sm { padding: 4px 9px; font-size: 12px; }
        .btn-icon { padding: 6px 8px; }
        .btn-icon.btn-sm { padding: 4px 6px; }

        /* ─── Badges ─────────────────────────────────────────────── */
        .badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 2px 7px; border-radius: var(--radius-badge);
            font-size: 11px; font-weight: 600;
        }
        .badge-green { background: rgba(22,163,74,.1); color: #15803d; }
        .badge-red { background: rgba(220,38,38,.09); color: #b91c1c; }
        .badge-amber { background: rgba(217,119,6,.1); color: #92400e; }
        .badge-blue { background: rgba(37,99,235,.1); color: #1d4ed8; }
        .badge-slate { background: rgba(100,116,139,.1); color: #475569; }
        .badge-purple { background: rgba(109,40,217,.1); color: #6d28d9; }
        /* legacy aliases */
        .badge-success { background: rgba(22,163,74,.1); color: #15803d; }
        .badge-danger  { background: rgba(220,38,38,.09); color: #b91c1c; }
        .badge-warning { background: rgba(217,119,6,.1); color: #92400e; }
        .badge-info    { background: rgba(37,99,235,.1); color: #1d4ed8; }
        .badge-gray    { background: rgba(100,116,139,.1); color: #475569; }

        /* ─── Alerts ─────────────────────────────────────────────── */
        .alert {
            display: flex; align-items: flex-start; gap: 12px;
            padding: 14px 16px; border-radius: 8px;
            font-size: 13px; margin-bottom: 16px;
        }
        .alert-success {
            background: rgba(22,163,74,.06); color: #15803d;
            border: 1px solid rgba(22,163,74,.18); border-left: 4px solid #16a34a;
        }
        .alert-danger {
            background: rgba(220,38,38,.06); color: #991b1b;
            border: 1px solid rgba(220,38,38,.18); border-left: 4px solid #dc2626;
        }
        /* alert-critical: strong left-border, neutral bg — non-alarmist */
        .alert-critical {
            background: var(--color-bg-card, #fff); color: var(--color-text);
            border: 1px solid var(--color-border); border-left: 4px solid #ef4444;
            padding: 16px 18px;
        }
        .alert-critical .alert-title { font-weight: 700; font-size: 14px; margin-bottom: 4px; color: var(--color-text); }
        .alert-critical .alert-icon  { font-size: 18px; color: #ef4444; flex-shrink: 0; margin-top: 1px; }
        .alert-warning {
            background: rgba(245,158,11,.06); color: #92400e;
            border: 1px solid rgba(245,158,11,.18); border-left: 4px solid #f59e0b;
        }
        .alert-info {
            background: rgba(37,99,235,.06); color: #1d4ed8;
            border: 1px solid rgba(37,99,235,.18); border-left: 4px solid #2563eb;
        }
        .alert i { margin-top: 1px; flex-shrink: 0; }

        /* ─── Forms ──────────────────────────────────────────────── */
        .form-group { display: flex; flex-direction: column; gap: 4px; }
        .form-label {
            font-size: 11px; font-weight: 600; color: var(--color-text-muted);
            text-transform: uppercase; letter-spacing: .04em;
        }
        .form-control {
            width: 100%; padding: 8px 10px;
            border: 1px solid var(--color-border);
            border-radius: 6px; font-size: 13px;
            color: var(--color-text); background: var(--color-bg-card, #fff); outline: none;
            transition: border-color .15s, box-shadow .15s;
        }
        .form-control:focus {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(79,70,229,.1);
        }
        select.form-control { cursor: pointer; }
        textarea.form-control { resize: vertical; }

        /* ─── Table ──────────────────────────────────────────────── */
        .table-wrapper { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .table thead th {
            padding: 9px 12px; text-align: left;
            font-size: 11px; font-weight: 600; color: var(--color-text-subtle);
            text-transform: uppercase; letter-spacing: .04em;
            border-bottom: 1px solid var(--color-border);
            background: var(--color-bg); white-space: nowrap;
        }
        .table tbody td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--color-border);
            color: var(--color-text); vertical-align: middle;
            transition: background .15s;
        }
        .table tbody tr:last-child td { border-bottom: none; }
        .table tbody tr:nth-child(even) td { background: rgba(0,0,0,.018); }
        .table tbody tr:hover td { background: rgba(0,0,0,.035) !important; }
        body.dark-mode .table tbody tr:nth-child(even) td { background: rgba(255,255,255,.022); }
        body.dark-mode .table tbody tr:hover td { background: rgba(255,255,255,.045) !important; }

        /* ─── Section hierarchy ─────────────────────────────────── */
        .section {
            margin-top: 28px;
            padding-top: 20px;
            border-top: 1px solid var(--color-border);
        }
        .section-alt {
            background: rgba(0,0,0,.018);
            border-radius: var(--radius-card);
            padding: 16px;
            margin-bottom: 16px;
        }
        body.dark-mode .section-alt { background: rgba(255,255,255,.022); }

        /* ─── Grids ──────────────────────────────────────────────── */
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        .grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }

        /* ─── Modal ──────────────────────────────────────────────── */
        .modal-backdrop {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,.5); z-index: 300;
            align-items: flex-start; justify-content: center;
            padding: 48px 16px 16px;
            overflow-y: auto;
        }
        .modal-backdrop.active { display: flex; }
        .modal {
            background: var(--color-bg-card, #fff); border-radius: 10px;
            width: 100%; max-width: 520px;
            box-shadow: 0 20px 48px rgba(0,0,0,.14), 0 4px 12px rgba(0,0,0,.08);
            animation: modal-in .18s ease;
            flex-shrink: 0;
        }
        @keyframes modal-in { from { transform: translateY(-16px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .modal-header {
            display: flex; align-items: center; gap: 10px;
            padding: 16px 20px; border-bottom: 1px solid var(--color-border);
        }
        .modal-header h3 { font-size: 15px; font-weight: 600; flex: 1; color: var(--color-text); }
        .modal-close {
            width: 28px; height: 28px; display: flex; align-items: center; justify-content: center;
            border: none; background: none; cursor: pointer;
            color: var(--color-text-subtle); border-radius: 4px; font-size: 18px; line-height: 1;
        }
        .modal-close:hover { background: var(--color-bg); }
        .modal-body { padding: 20px; }
        .modal-footer { display: flex; gap: 8px; justify-content: flex-end; padding-top: 16px; }

        /* ─── Form grid in modal ─────────────────────────────────── */
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .form-grid .span-2 { grid-column: span 2; }
        .form-check { display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer; }
        .form-check input { cursor: pointer; }

        /* ─── Utils ──────────────────────────────────────────────── */
        .text-green { color: var(--color-success); }
        .text-red { color: var(--color-danger); }
        .text-amber { color: var(--color-warning); }
        .text-muted { color: var(--color-text-muted); }
        .text-subtle { color: var(--color-text-subtle); }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-mono { font-family: 'SF Mono', 'Fira Code', monospace; }
        .fw-600 { font-weight: 600; }
        .fw-700 { font-weight: 700; }
        .mt-1 { margin-top: 4px; }
        .mt-2 { margin-top: 8px; }
        .mt-3 { margin-top: 12px; }
        .mt-4 { margin-top: 16px; }
        .mb-1 { margin-bottom: 4px; }
        .mb-2 { margin-bottom: 8px; }
        .mb-3 { margin-bottom: 12px; }
        .mb-4 { margin-bottom: 16px; }
        .mb-5 { margin-bottom: 24px; }
        .gap-2 { gap: 8px; }
        .d-flex { display: flex; }
        .align-center { align-items: center; }
        .justify-between { justify-content: space-between; }
        .flex-wrap { flex-wrap: wrap; }
        .overflow-hidden { overflow: hidden; }

        /* progress bar */
        .progress-bar { height: 6px; background: var(--color-border); border-radius: 999px; overflow: hidden; }
        .progress-bar-fill { height: 100%; border-radius: 999px; transition: width .5s cubic-bezier(.4,0,.2,1); }

        /* Logout button (looks like sidebar-link) */
        .sidebar-logout {
            display: flex; align-items: center; gap: 10px;
            padding: 8px 10px; border-radius: 9999px; width: 100%;
            background: none; border: none; cursor: pointer;
            color: #64748b; font-size: 14px; font-weight: 500;
            transition: background .15s, color .15s;
        }
        .sidebar-logout:hover { background: rgba(220,38,38,.15); color: #fca5a5; }
        .sidebar-logout i { width: 16px; text-align: center; font-size: 14px; }

        /* ─── Bottom navigation (mobile only) ───────────────────── */
        .bottom-nav {
            display: none;
            position: fixed; bottom: 0; left: 0; right: 0;
            height: var(--bottom-nav-h);
            background: var(--color-bg-card, #fff); border-top: 1px solid var(--color-border);
            transition: background .22s ease, border-color .22s ease;
            z-index: 180;
        }
        .bottom-nav ul {
            display: flex; height: 100%; list-style: none;
        }
        .bottom-nav li { flex: 1; }
        .bottom-nav a {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            height: 100%; gap: 3px; text-decoration: none;
            color: var(--color-text-subtle); font-size: 10px; font-weight: 500;
            transition: color .15s;
        }
        .bottom-nav a i { font-size: 18px; }
        .bottom-nav a.active,
        .bottom-nav a:hover { color: var(--color-primary); }

        /* Chart container */
        .chart-box { position: relative; height: 260px; }
        .chart-box-sm { position: relative; height: 220px; }

        /* ─── Section header ─────────────────────────────────────── */
        .section-header {
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 10px; margin-bottom: 18px;
        }
        .section-header h2 { font-size: 15px; font-weight: 700; letter-spacing: -.01em; }

        /* ─── Empty state ────────────────────────────────────────── */
        .empty-state { text-align: center; padding: 40px 20px; color: var(--color-text-subtle); }
        .empty-state i { font-size: 36px; display: block; margin-bottom: 12px; opacity: .5; }
        .empty-state p { font-size: 13px; }

        /* ─── Badge yellow (alias) ───────────────────────────────── */
        .badge-yellow { background: rgba(217,119,6,.1); color: #92400e; }

        /* ─── Semantic bg utilities ──────────────────────────────── */
        .bg-success { background: rgba(34,197,94,.1);  }
        .bg-danger  { background: rgba(239,68,68,.1);  }
        .bg-warning { background: rgba(245,158,11,.1); }
        .bg-info    { background: rgba(59,130,246,.1); }
        .bg-primary { background: rgba(79,70,229,.1);  }
        /* Text semantic */
        .text-success { color: var(--color-success); }
        .text-danger  { color: var(--color-danger);  }
        .text-warning { color: var(--color-warning); }
        .text-primary { color: var(--color-primary); }

        /* ─── Extrato / List rows (shared across pages) ─────────── */
        .ext-card { padding:0; overflow:hidden; }
        .ext-header { display:flex; justify-content:space-between; align-items:center; padding:13px 16px; border-bottom:1px solid #f1f5f9; }
        .ext-date-header { display:flex; align-items:center; justify-content:space-between; padding:6px 16px; background:#f8fafc; border-top:1px solid #f1f5f9; border-bottom:1px solid #f1f5f9; position:sticky; top:0; z-index:2; }
        .ext-date-label { font-size:10px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:.06em; }
        .ext-row { display:flex; align-items:center; gap:12px; padding:11px 16px; border-bottom:1px solid var(--color-border); transition:background .15s ease, transform .12s ease; position:relative; }
        .ext-row:hover { background: rgba(79,70,229,.04); }
        .ext-row:nth-child(even) { background: rgba(0,0,0,.015); }
        .ext-row:nth-child(even):hover { background: rgba(79,70,229,.04); }
        body.dark-mode .ext-row:nth-child(even) { background: rgba(255,255,255,.018); }
        body.dark-mode .ext-row:nth-child(even):hover { background: rgba(255,255,255,.045); }
        .ext-row:last-of-type { border-bottom:none; }
        .ext-row::before { content:''; position:absolute; left:0; top:8px; bottom:8px; width:3px; border-radius:0 3px 3px 0; }
        .ext-credito::before { background:#16a34a; }
        .ext-debito::before  { background:#ef4444; }
        .ext-icone { width:40px; height:40px; border-radius:11px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .ext-icone.ext-credito { background:#f0fdf4; }
        .ext-icone.ext-debito  { background:#fff1f2; }
        .ext-info { flex:1; min-width:0; }
        .ext-desc { font-size:13px; font-weight:600; color:#1e293b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; line-height:1.3; }
        .ext-meta { display:flex; align-items:center; gap:5px; margin-top:4px; flex-wrap:wrap; }
        .ext-conta-pill { display:inline-flex; align-items:center; gap:4px; font-size:11px; color:#94a3b8; }
        .ext-dot { width:7px; height:7px; border-radius:50%; flex-shrink:0; }
        .ext-tag { font-size:10px; padding:1px 7px; border-radius:20px; font-weight:600; line-height:1.6; white-space:nowrap; }
        .ext-tag-cat { background:#f1f5f9; color:#475569; }
        .ext-tag-rec { background:#ede9fe; color:#7c3aed; }
        .ext-tag-doc { background:#e0f2fe; color:#0369a1; }
        .ext-valor-col { text-align:right; flex-shrink:0; min-width:100px; }
        .ext-valor { font-size:14px; font-weight:700; white-space:nowrap; line-height:1.2; }
        .ext-valor.ext-credito { color:#16a34a; }
        .ext-valor.ext-debito  { color:#ef4444; }
        .ext-status { font-size:10px; font-weight:600; margin-top:3px; display:flex; align-items:center; justify-content:flex-end; gap:3px; }
        .s-ok   { color:#16a34a; }
        .s-venc { color:#ef4444; }
        .s-pend { color:#d97706; }
        .ext-actions { display:flex; align-items:center; gap:2px; flex-shrink:0; }
        .ext-del-btn, .ext-edit-btn { opacity:0; transition:opacity .15s; background:none; border:none; cursor:pointer; padding:6px; border-radius:9999px; line-height:1; }
        .ext-del-btn  { color:#94a3b8; }
        .ext-edit-btn { color:#94a3b8; }
        .ext-del-btn:hover  { background:#fff1f2; color:#ef4444; }
        .ext-edit-btn:hover { background:#f0f9ff; color:var(--color-primary); }
        .ext-row:hover .ext-del-btn,
        .ext-row:hover .ext-edit-btn { opacity:1; }
        .ext-footer { display:flex; align-items:center; gap:16px; padding:10px 16px; background:#f8fafc; border-top:2px solid #f1f5f9; }
        .ext-footer-item { display:flex; align-items:center; gap:6px; }
        .ext-footer-dot  { width:8px; height:8px; border-radius:50%; }
        @media (max-width:640px) {
            .ext-row { padding:10px 14px 10px 18px; gap:10px; }
            .ext-icone { width:36px; height:36px; border-radius:9px; }
            .ext-desc { font-size:12px; }
            .ext-valor { font-size:13px; }
            .ext-valor-col { min-width:80px; }
            .ext-del-btn, .ext-edit-btn { opacity:1; }
            .ext-tag-cat { display:none; }
        }

        /* ─── Filter Bar (shared across pages) ───────────────────── */
        .filtros-bar  { padding:12px 16px; margin-bottom:18px; }
        .filtros-lanc { display:flex; align-items:center; gap:10px; justify-content:space-between; }
        .filtro-grupo { display:flex; align-items:center; gap:8px; flex-shrink:0; }
        .filtro-grupo-centro { justify-content:center; }

        /* Avatares */
        .av-grupo   { display:flex; align-items:flex-start; gap:6px; flex-wrap:wrap; }
        .av-item    { display:flex; flex-direction:column; align-items:center; gap:3px; text-decoration:none; flex-shrink:0; transition:opacity .15s; }
        .av-item:hover { opacity:.8; }
        .av-circulo { width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; overflow:hidden; transition:all .2s; flex-shrink:0; }
        .av-nome    { font-size:9.5px; white-space:nowrap; max-width:42px; overflow:hidden; text-overflow:ellipsis; color:#94a3b8; text-align:center; }

        /* Month nav */
        .nav-mes-btn { display:flex; align-items:center; justify-content:center; width:36px; height:36px; color:#64748b; text-decoration:none; background:var(--color-bg-card,#fff); transition:background .15s; }
        .nav-mes-btn:hover { background:var(--color-bg); color:var(--color-primary); }
        .mes-label-btn { padding:6px 16px; font-weight:700; font-size:13px; color:var(--color-text); white-space:nowrap; cursor:pointer; user-select:none; background:var(--color-bg-card,#fff); border:none; font-family:inherit; }

        /* Segmented control */
        .seg-control { display:flex; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; }
        .seg-btn { padding:6px 13px; font-size:12px; font-weight:600; border:none; cursor:pointer; white-space:nowrap; transition:background .15s, color .15s; }

        /* Vertical separator */
        .separador-v { width:1px; height:30px; background:#e2e8f0; flex-shrink:0; align-self:center; }

        /* Mobile — shared filter rules */
        @media (max-width:640px) {
            .filtros-lanc       { flex-direction:column; align-items:stretch; gap:0; }
            .filtro-grupo       { width:100%; padding:10px 0; border-bottom:1px solid #f1f5f9; }
            .filtro-grupo:last-child { border-bottom:none; padding-bottom:2px; }
            .filtro-grupo-centro  { justify-content:space-between; }
            .filtro-grupo-members { justify-content:center; order:4; }
            .filtro-grupo-bancos  { order:3; }
            .filtro-grupo-tipo    { order:2; }
            .av-grupo  { flex-wrap:nowrap; overflow-x:auto; scrollbar-width:none; -ms-overflow-style:none; gap:10px; padding-bottom:2px; justify-content:center; }
            .av-grupo::-webkit-scrollbar { display:none; }
            .av-circulo { width:40px; height:40px; }
            .av-nome    { font-size:10px; max-width:44px; }
            .separador-v { display:none !important; }
            .seg-btn { padding:8px 4px; font-size:13px; }
        }

        /* ─── Responsive ──────────────────────────────────────────── */
        @media (max-width: 1024px) {
            .grid-4 { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); width: var(--sidebar-w) !important; height: 100vh; height: 100dvh; }
            .sidebar.open { transform: translateX(0); }
            .sidebar-nav { padding: 4px 8px; }
            .sidebar-link { padding: 8px 10px; font-size: 13px; }
            .sidebar-section-label { padding: 10px 16px 2px; font-size: 9px; }
            .sidebar-user { padding: 8px 8px 6px; }
            .sidebar-user-info { padding: 4px 10px; margin-bottom: 2px; }
            .main-content { margin-left: 0 !important; }
            .sidebar-collapse-btn { display: none; }
            .topbar-hamburger { display: flex; }
            .topbar-logo-mobile { display: block; }
            .grid-2, .grid-3 { grid-template-columns: 1fr; }
            .grid-4 { grid-template-columns: 1fr 1fr; }
            .page-content { padding: 14px 12px calc(var(--bottom-nav-h) + 14px); }
            .bottom-nav { display: block; }
            .bottom-nav ul { padding: 0 4px; }
            .bottom-nav a { font-size: 9px; gap: 2px; }
            .bottom-nav a i { font-size: 16px; }
            .form-grid { grid-template-columns: 1fr; }
            .form-grid .span-2 { grid-column: span 1; }
            /* Hide some table columns on mobile */
            .table .hide-mobile { display: none; }
            .hide-mobile { display: none !important; }
            .modal-backdrop { padding: 0; align-items: flex-end; }
            .modal { border-radius: 14px 14px 0 0; max-width: 100%; max-height: 92vh; overflow-y: auto; }
            .modal-body { padding: 16px; }
            /* Section header — centered on mobile */
            .section-header {
                flex-direction: column;
                align-items: center;
                gap: 10px;
            }
            .section-header h2 { font-size: 14px; text-align: center; }
            .section-header .btn { width: 100%; justify-content: center; }
            .section-header form { width: 100%; }
            .section-header form .d-flex { justify-content: center; }
            /* Table action buttons wrap on mobile */
            .table .actions-cell { flex-wrap: wrap; }
            /* Date inputs shrink on mobile */
            input[type="date"].form-control { min-width: 0; width: 100%; }
            /* KPI smaller on mobile */
            .kpi-value { font-size: 1.4rem; }
            /* Permissions grid scroll */
            .perm-grid-wrapper { overflow-x: auto; -webkit-overflow-scrolling: touch; }
            .perm-grid-wrapper table { min-width: 480px; }
            /* Charts smaller on mobile */
            .chart-box { height: 220px; }
            .chart-box-sm { height: 180px; }
        }
        @media (max-width: 480px) {
            .grid-4 { grid-template-columns: 1fr; }
            .topbar { padding: 0 12px; gap: 10px; }
            .kpi-value { font-size: 1.2rem; }
            .card { padding: 12px; }
            .btn { padding: 8px 12px; font-size: 12px; }
            .btn-sm { padding: 6px 10px; font-size: 11px; }
            /* Sidebar even more compact */
            .sidebar-link { padding: 7px 10px; font-size: 12px; gap: 8px; }
            .sidebar-link i { font-size: 13px; }
            .sidebar-section-label { padding: 8px 16px 2px; font-size: 9px; }
            .sidebar-user { padding: 6px 8px 4px; }
            /* Modal adjustments for small phones */
            .modal-header { padding: 14px 16px; }
            .modal-header h3 { font-size: 14px; }
            .modal-body { padding: 14px; }
            .modal-footer { flex-wrap: wrap; }
            .modal-footer .btn { flex: 1; min-width: 0; justify-content: center; }
            /* Table tighter on small phones */
            .table thead th { padding: 7px 8px; font-size: 10px; }
            .table tbody td { padding: 8px; font-size: 12px; }
            /* Badge smaller */
            .badge { font-size: 10px; padding: 2px 5px; }
            /* Bottom nav safe area for notch phones */
            .bottom-nav { padding-bottom: env(safe-area-inset-bottom, 0); height: calc(var(--bottom-nav-h) + env(safe-area-inset-bottom, 0)); }
            .page-content { padding-bottom: calc(var(--bottom-nav-h) + env(safe-area-inset-bottom, 0) + 14px); }
            /* KPI icon smaller */
            .kpi-icon { width: 34px; height: 34px; font-size: 14px; }
            /* Form controls tighter */
            .form-control { padding: 7px 9px; font-size: 13px; }
            .form-label { font-size: 10px; }
        }
    </style>
    @stack('styles')
</head>
<body>
<script>if(localStorage.getItem('alfahome-theme')==='dark')document.body.classList.add('dark-mode');</script>

{{-- ─── PWA Install Banner ───────────────────────────────────────────── --}}
<div x-data="pwaInstall()" x-show="visible" x-cloak
     style="position:fixed;bottom:calc(var(--bottom-nav-h,60px) + 12px);left:12px;right:12px;z-index:9999;
            background:#1e2535;color:#e5e7eb;border:1px solid #2d3e56;border-radius:14px;
            padding:14px 16px;display:flex;align-items:center;gap:12px;
            box-shadow:0 8px 24px rgba(0,0,0,.45);font-size:14px;">
  <img src="/favicon.png" style="width:38px;height:38px;border-radius:10px;flex-shrink:0;" alt="">
  <div style="flex:1;min-width:0;">
    <div style="font-weight:700;font-size:13px;color:#f1f5f9;">Instalar AlfaHome</div>
    <div style="font-size:12px;color:#6b7280;margin-top:2px;">Acesso rápido na tela inicial</div>
  </div>
  <button @click="install()"
          style="background:#2563eb;color:#fff;border:none;border-radius:9999px;padding:7px 14px;font-size:12px;font-weight:600;cursor:pointer;flex-shrink:0;">
    Instalar
  </button>
  <button @click="close()"
          style="background:none;border:none;color:#6b7280;cursor:pointer;font-size:20px;padding:0 4px;flex-shrink:0;line-height:1;">
    &times;
  </button>
</div>
{{-- ─── End PWA Install Banner ────────────────────────────────────────── --}}

{{-- ─── Sidebar ──────────────────────────────────────────────────────── --}}
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <img src="/alfa-home-logo/alfa-home-logo.png" alt="AlfaHome" class="sidebar-logo-img sidebar-logo-img-full" style="max-width: 140px;">
        <img src="/alfa-home-logo/alfa-home-logo-2.png" alt="AlfaHome" class="sidebar-logo-img sidebar-logo-img-icon" style="height: 34px; width: auto;">
    </div>

    <nav class="sidebar-nav">
        @if(Auth::user()->isSuperAdmin())
        {{-- ─── Sidebar Super Admin ──────────────────────────────────────── --}}
        <div class="sidebar-section-label">Painel SaaS</div>
        <a href="{{ route('admin.dashboard') }}" class="sidebar-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" data-label="Dashboard">
            <i class="fa-solid fa-chart-pie"></i> <span>Dashboard</span>
        </a>
        <a href="{{ route('admin.revendas.index') }}" class="sidebar-link {{ request()->routeIs('admin.revendas.*') ? 'active' : '' }}" data-label="Revendas">
            <i class="fa-solid fa-building"></i> <span>Revendas</span>
        </a>
        <a href="{{ route('admin.planos.index') }}" class="sidebar-link {{ request()->routeIs('admin.planos.*') ? 'active' : '' }}" data-label="Planos">
            <i class="fa-solid fa-credit-card"></i> <span>Planos</span>
        </a>
        <a href="{{ route('admin.manutencao.index') }}" class="sidebar-link {{ request()->routeIs('admin.manutencao.*') ? 'active' : '' }}" data-label="Manutenção">
            <i class="fa-solid fa-wrench"></i> <span>Manutenção</span>
        </a>

        @elseif(Auth::user()->isAdminRevenda())
        {{-- ─── Sidebar Admin Revenda ────────────────────────────────────── --}}
        <div class="sidebar-section-label">Minha Revenda</div>
        <a href="{{ route('revenda.dashboard') }}" class="sidebar-link {{ request()->routeIs('revenda.dashboard') ? 'active' : '' }}" data-label="Dashboard">
            <i class="fa-solid fa-chart-pie"></i> <span>Dashboard</span>
        </a>
        <a href="{{ route('revenda.clientes.index') }}" class="sidebar-link {{ request()->routeIs('revenda.clientes.*') ? 'active' : '' }}" data-label="Clientes">
            <i class="fa-solid fa-store"></i> <span>Clientes</span>
        </a>

        @else
        {{-- ─── Sidebar Tenant (Master/Membro) ──────────────────────────── --}}
        <div class="sidebar-section-label">Visão Geral</div>
        <a href="{{ route('dashboard') }}" class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" data-label="Dashboard">
            <i class="fa-solid fa-gauge-high"></i> <span>Dashboard</span>
        </a>
        <a href="{{ route('alertas.index') }}" class="sidebar-link {{ request()->routeIs('alertas.*') ? 'active' : '' }}" data-label="Alertas" style="position:relative;">
            <i class="fa-solid fa-bell"></i>
            <span>Alertas</span>
        </a>
        <a href="{{ route('fluxo-caixa.index') }}" class="sidebar-link {{ request()->routeIs('fluxo-caixa.*') ? 'active' : '' }}" data-label="Contas a Pagar/Receber">
            <i class="fa-solid fa-arrows-left-right"></i> <span>Contas / Baixas</span>
        </a>

        <div class="sidebar-section-label">Lançamentos</div>
        @if(Auth::user()->temPermissao('despesas', 'criar'))
        <a href="{{ route('lancamentos.index') }}" class="sidebar-link {{ request()->routeIs('lancamentos.*') ? 'active' : '' }}" data-label="Lançamentos">
            <i class="fa-solid fa-list-ul"></i> <span>Lançamentos</span>
        </a>
        @endif
        @if(Auth::user()->temPermissao('despesas', 'ver'))
        <a href="{{ route('despesas.index') }}" class="sidebar-link {{ request()->routeIs('despesas.*') ? 'active' : '' }}" data-label="Despesas">
            <i class="fa-solid fa-arrow-trend-down"></i> <span>Despesas</span>
        </a>
        @endif
        @if(Auth::user()->temPermissao('receitas', 'ver'))
        <a href="{{ route('receitas.index') }}" class="sidebar-link {{ request()->routeIs('receitas.*') ? 'active' : '' }}" data-label="Receitas">
            <i class="fa-solid fa-arrow-trend-up"></i> <span>Receitas</span>
        </a>
        @endif
        @if(Auth::user()->temPermissao('investimentos', 'ver'))
        <a href="{{ route('investimentos.index') }}" class="sidebar-link {{ request()->routeIs('investimentos.*') ? 'active' : '' }}" data-label="Investimentos">
            <i class="fa-solid fa-seedling"></i> <span>Investimentos</span>
        </a>
        @endif

        <div class="sidebar-section-label">Cadastros</div>
        @if(Auth::user()->temPermissao('bancos', 'ver'))
        <a href="{{ route('bancos.index') }}" class="sidebar-link {{ request()->routeIs('bancos.*') ? 'active' : '' }}" data-label="Contas Bancárias">
            <i class="fa-solid fa-building-columns"></i> <span>Contas Bancárias</span>
        </a>
        @endif
        @if(Auth::user()->temPermissao('familiares', 'ver'))
        <a href="{{ route('familiares.index') }}" class="sidebar-link {{ request()->routeIs('familiares.*') ? 'active' : '' }}" data-label="Membros">
            <i class="fa-solid fa-users"></i> <span>Membros</span>
        </a>
        @endif
        @if(Auth::user()->temPermissao('fornecedores', 'ver'))
        <a href="{{ route('fornecedores.index') }}" class="sidebar-link {{ request()->routeIs('fornecedores.*') ? 'active' : '' }}" data-label="Fornecedores">
            <i class="fa-solid fa-store"></i> <span>Fornecedores</span>
        </a>
        @endif
        @if(Auth::user()->temPermissao('categorias', 'ver'))
        <a href="{{ route('categorias.index') }}" class="sidebar-link {{ request()->routeIs('categorias.*') ? 'active' : '' }}" data-label="Categorias">
            <i class="fa-solid fa-tags"></i> <span>Categorias</span>
        </a>
        @endif

        <div class="sidebar-section-label">Indicação</div>
        <a href="{{ route('cupons.index') }}" class="sidebar-link {{ request()->routeIs('cupons.*') ? 'active' : '' }}" data-label="Indicar Amigos">
            <i class="fa-solid fa-gift"></i> <span>Indicar Amigos</span>
        </a>
        @endif
    </nav>

    <div class="sidebar-user">
        <div class="sidebar-user-info">
            <div class="sidebar-user-avatar">
                @if(Auth::user()->foto)
                    <img src="{{ asset('storage/' . Auth::user()->foto) }}" alt="Foto" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                @else
                    <i class="fa-solid fa-user"></i>
                @endif
            </div>
            <div class="sidebar-user-details">
                <div class="sidebar-user-name">{{ Auth::user()->name }}</div>
                <div class="sidebar-user-email">{{ Auth::user()->email }}</div>
            </div>
        </div>
        <div class="sidebar-user-actions">
            <a href="{{ route('profile.edit') }}" class="sidebar-user-action" title="Editar perfil">
                <i class="fa-solid fa-pen-to-square"></i><span>Editar</span>
            </a>
            <a href="{{ route('download-app') }}" class="sidebar-user-action" title="Baixar o app" target="_blank" rel="noopener">
                <i class="fa-solid fa-mobile-screen-button"></i><span>App</span>
            </a>
            <form method="POST" action="{{ route('logout') }}" style="flex:1; display:flex;">
                @csrf
                <button type="submit" class="sidebar-user-action sidebar-user-action--danger" title="Sair">
                    <i class="fa-solid fa-right-from-bracket"></i><span>Sair</span>
                </button>
            </form>
        </div>
    </div>
</aside>

{{-- ─── Collapse button (outside sidebar to avoid overflow-x clipping) ── --}}
<button class="sidebar-collapse-btn" id="sidebar-collapse-btn" title="Recolher menu">
    <i class="fa-solid fa-chevron-left" id="sidebar-collapse-icon"></i>
</button>

{{-- ─── Mobile overlay ────────────────────────────────────────────────── --}}
<div class="sidebar-overlay" id="sidebar-overlay"></div>

{{-- ─── Main content ──────────────────────────────────────────────────── --}}
<div class="main-content" id="main-content">
    @include('components.manutencao-banner')
    <header class="topbar">
        <button class="topbar-hamburger" id="hamburger" aria-label="Menu">
            <i class="fa-solid fa-bars"></i>
        </button>
        <span class="topbar-title">@yield('page-title', 'Dashboard')</span>
        <div class="topbar-actions">
            {{-- Push notification toggle --}}
            <button id="push-toggle"
                    onclick="alfaPushToggle(this)"
                    title="Ativar notificações"
                    aria-label="Ativar notificações push"
                    style="display:flex;align-items:center;justify-content:center;width:36px;height:36px;border:1px solid var(--color-border);border-radius:9999px;background:var(--color-bg);color:#9ca3af;cursor:pointer;transition:background .15s,color .15s;flex-shrink:0;">
                <i class="fa-regular fa-bell" style="font-size:15px;"></i>
            </button>
            <button class="theme-toggle" id="theme-toggle" title="Alternar tema" aria-label="Alternar modo claro/escuro">
                <i class="fa-solid fa-sun  icon-sun"  style="font-size:15px;"></i>
                <i class="fa-solid fa-moon icon-moon" style="font-size:15px;"></i>
            </button>
            <img src="/alfa-home-logo/alfa-home-logo-2.png" alt="AlfaHome" class="topbar-logo-mobile">
        </div>
    </header>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var btn = document.getElementById('theme-toggle');
            if (!btn) return;
            btn.addEventListener('click', function () {
                var isDark = document.body.classList.toggle('dark-mode');
                document.documentElement.classList.toggle('ah-dark-preload', isDark);
                localStorage.setItem('alfahome-theme', isDark ? 'dark' : 'light');
                /* Atualiza cores padrão do Chart.js para os gráficos já renderizados */
                if (typeof Chart !== 'undefined') {
                    var tickColor  = isDark ? '#64748b' : '#94a3b8';
                    var gridColor  = isDark ? '#1e2e44' : '#f1f5f9';
                    Chart.defaults.color = tickColor;
                    Chart.defaults.borderColor = gridColor;
                    /* Re-renderiza todos os charts registrados */
                    Object.values(Chart.instances || {}).forEach(function(c) {
                        if (c.options.scales) {
                            Object.values(c.options.scales).forEach(function(s) {
                                if (s.ticks)  s.ticks.color  = tickColor;
                                if (s.grid)   s.grid.color   = gridColor;
                            });
                        }
                        if (c.options.plugins && c.options.plugins.legend && c.options.plugins.legend.labels)
                            c.options.plugins.legend.labels.color = tickColor;
                        c.update('none');
                    });
                }
            });
        });
    </script>

    <main class="page-content">
        @if(session('success'))
            <div class="alert alert-success">
                <i class="fa-solid fa-circle-check"></i>
                <span>{{ session('success') }}</span>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">
                <i class="fa-solid fa-circle-xmark"></i>
                <span>{{ session('error') }}</span>
            </div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger" style="flex-direction:column;align-items:flex-start;">
                <strong><i class="fa-solid fa-triangle-exclamation"></i> Verifique os erros:</strong>
                <ul style="margin-top:6px;padding-left:16px;font-size:13px;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if(session('show_indicacao'))
        <div id="toast-indicacao" style="position:fixed;top:20px;right:20px;z-index:9999;background:#fff;border:1px solid #e2e8f0;border-left:4px solid var(--color-primary);border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.12);padding:14px 16px;display:flex;align-items:center;gap:12px;max-width:380px;animation:slideInRight .3s ease-out;">
            <div style="width:38px;height:38px;border-radius:50%;background:#f0fdf4;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fa-solid fa-gift" style="font-size:16px;color:#16a34a;"></i>
            </div>
            <div style="flex:1;min-width:0;">
                <div style="font-size:13px;font-weight:700;color:#1e293b;">Indique e ganhe 20% de desconto!</div>
                <div style="font-size:12px;color:#64748b;margin-top:2px;">Compartilhe seu cupom com amigos e ganhe desconto na mensalidade.</div>
                <a href="{{ route('cupons.index') }}" style="font-size:12px;font-weight:600;color:var(--color-primary);text-decoration:none;margin-top:4px;display:inline-block;">Ver meu cupom &rarr;</a>
            </div>
            <button onclick="document.getElementById('toast-indicacao').remove()" style="background:none;border:none;cursor:pointer;padding:4px;flex-shrink:0;color:#94a3b8;font-size:16px;line-height:1;" title="Fechar">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <style>
        @keyframes slideInRight { from { transform:translateX(120%);opacity:0; } to { transform:translateX(0);opacity:1; } }
        </style>
        <script>setTimeout(function(){ var t=document.getElementById('toast-indicacao'); if(t) t.style.transition='opacity .3s',t.style.opacity='0',setTimeout(function(){t.remove()},300); }, 5000);</script>
        @endif

        @yield('content')
    </main>
</div>

{{-- ─── Bottom navigation (mobile) ───────────────────────────────────── --}}
<nav class="bottom-nav" aria-label="Navegação mobile">
    <ul>
        @if(Auth::user()->isSuperAdmin())
            <li>
                <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                    <i class="fa-solid fa-gauge-high"></i> Dashboard
                </a>
            </li>
            <li>
                <a href="{{ route('admin.revendas.index') }}" class="{{ request()->routeIs('admin.revendas.*') ? 'active' : '' }}">
                    <i class="fa-solid fa-building"></i> Revendas
                </a>
            </li>
            <li>
                <a href="{{ route('admin.planos.index') }}" class="{{ request()->routeIs('admin.planos.*') ? 'active' : '' }}">
                    <i class="fa-solid fa-tags"></i> Planos
                </a>
            </li>
            <li>
                <a href="{{ route('admin.manutencao.index') }}" class="{{ request()->routeIs('admin.manutencao.*') ? 'active' : '' }}">
                    <i class="fa-solid fa-wrench"></i> Manutenção
                </a>
            </li>
        @elseif(Auth::user()->isAdminRevenda())
            <li>
                <a href="{{ route('revenda.dashboard') }}" class="{{ request()->routeIs('revenda.dashboard') ? 'active' : '' }}">
                    <i class="fa-solid fa-chart-pie"></i> Dashboard
                </a>
            </li>
            <li>
                <a href="{{ route('revenda.clientes.index') }}" class="{{ request()->routeIs('revenda.clientes.*') ? 'active' : '' }}">
                    <i class="fa-solid fa-store"></i> Clientes
                </a>
            </li>
            <li>
                <a href="{{ route('profile.edit') }}" class="{{ request()->routeIs('profile.*') ? 'active' : '' }}">
                    <i class="fa-solid fa-user"></i> Perfil
                </a>
            </li>
        @else
            <li>
                <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <i class="fa-solid fa-gauge-high"></i> Dashboard
                </a>
            </li>
            @if(Auth::user()->temPermissao('despesas', 'criar'))
            <li>
                <a href="{{ route('lancamentos.index') }}" class="{{ request()->routeIs('lancamentos.*') ? 'active' : '' }}">
                    <i class="fa-solid fa-list-ul"></i> Lançamentos
                </a>
            </li>
            @endif
            @if(Auth::user()->temPermissao('despesas', 'ver'))
            <li>
                <a href="{{ route('despesas.index') }}" class="{{ request()->routeIs('despesas.*') ? 'active' : '' }}">
                    <i class="fa-solid fa-arrow-trend-down"></i> Despesas
                </a>
            </li>
            @endif
            @if(Auth::user()->temPermissao('receitas', 'ver'))
            <li>
                <a href="{{ route('receitas.index') }}" class="{{ request()->routeIs('receitas.*') ? 'active' : '' }}">
                    <i class="fa-solid fa-arrow-trend-up"></i> Receitas
                </a>
            </li>
            @endif
            @if(Auth::user()->temPermissao('bancos', 'ver'))
            <li>
                <a href="{{ route('bancos.index') }}" class="{{ request()->routeIs('bancos.*') ? 'active' : '' }}">
                    <i class="fa-solid fa-building-columns"></i> Contas
                </a>
            </li>
            @endif
        @endif
    </ul>
</nav>

@stack('scripts')
<script>
    const sidebar     = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');
    const overlay     = document.getElementById('sidebar-overlay');
    const hamburger   = document.getElementById('hamburger');
    const collapseBtn = document.getElementById('sidebar-collapse-btn');
    const collapseIcon = document.getElementById('sidebar-collapse-icon');

    // ── Desktop collapse (persisted) ──────────────────────────────
    const STORAGE_KEY = 'alfahome_sidebar_collapsed';

    function applyCollapse(collapsed) {
        if (collapsed) {
            sidebar.classList.add('collapsed');
            mainContent.classList.add('sidebar-collapsed');
            collapseBtn.classList.add('collapsed');
            collapseIcon.classList.replace('fa-chevron-left', 'fa-chevron-right');
        } else {
            sidebar.classList.remove('collapsed');
            mainContent.classList.remove('sidebar-collapsed');
            collapseBtn.classList.remove('collapsed');
            collapseIcon.classList.replace('fa-chevron-right', 'fa-chevron-left');
        }
    }

    // Load persisted state (desktop only)
    if (window.innerWidth > 768) {
        applyCollapse(localStorage.getItem(STORAGE_KEY) === 'true');
    }

    let collapseAnimating = false;
    collapseBtn.addEventListener('pointerdown', (e) => {
        e.preventDefault();
        if (collapseAnimating) return;
        collapseAnimating = true;
        const collapsed = !sidebar.classList.contains('collapsed');
        applyCollapse(collapsed);
        localStorage.setItem(STORAGE_KEY, collapsed);
        setTimeout(() => { collapseAnimating = false; }, 350);
    });

    // ── Mobile open/close ─────────────────────────────────────────
    function openSidebar()  { sidebar.classList.add('open');  overlay.classList.add('active');  document.body.style.overflow = 'hidden'; }
    function closeSidebar() { sidebar.classList.remove('open'); overlay.classList.remove('active'); document.body.style.overflow = ''; }

    hamburger.addEventListener('click', () => sidebar.classList.contains('open') ? closeSidebar() : openSidebar());
    overlay.addEventListener('click', closeSidebar);

    // Close sidebar when a link is clicked on mobile
    sidebar.querySelectorAll('.sidebar-link, .sidebar-logout').forEach(el => {
        el.addEventListener('click', () => { if (window.innerWidth <= 768) closeSidebar(); });
    });

    // Auto-hide alerts
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(el => {
            el.style.transition = 'opacity .4s';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 400);
        });
    }, 5000);

    // Modal helpers
    function openModal(id) {
        document.getElementById(id).classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    function closeModal(id) {
        document.getElementById(id).classList.remove('active');
        document.body.style.overflow = '';
    }
    document.addEventListener('click', e => {
        if (e.target.classList.contains('modal-backdrop')) {
            e.target.classList.remove('active');
            document.body.style.overflow = '';
        }
    });
</script>

{{-- ─── PWA: SW · Install Banner · Push · Background Sync · Theme ─────── --}}
<script>
(function () {
    'use strict';

    // ════════════════════════════════════════════════════════════════════
    //  1. Alpine component — Install Banner
    // ════════════════════════════════════════════════════════════════════
    window.pwaInstall = function () {
        return {
            visible: false,
            _prompt: null,

            init() {
                var self = this;

                // 1) Já está instalado como app standalone? Nunca mostrar.
                var isStandalone = window.matchMedia('(display-mode: standalone)').matches
                                   || window.navigator.standalone === true;
                if (isStandalone) return;

                // 2) Usuário fechou explicitamente clicando no X? Respeitar 30 dias.
                var dismissed = localStorage.getItem('pwa-dismissed');
                if (dismissed && (Date.now() - parseInt(dismissed)) < 30 * 24 * 60 * 60 * 1000) return;

                // 3) Já mostrou nesta SESSÃO do navegador? Não mostrar de novo nesta aba.
                //    (Evita reaparição a cada navegação entre páginas non-SPA.)
                if (sessionStorage.getItem('pwa-shown-session')) return;

                // 4) Cooldown leve: já apareceu nas últimas 24h? Pular.
                //    (Evita reaparição quando o usuário ignora o banner sem fechar.)
                var lastShown = localStorage.getItem('pwa-last-shown');
                if (lastShown && (Date.now() - parseInt(lastShown)) < 24 * 60 * 60 * 1000) return;

                // Marca como exibido nesta sessão e registra timestamp global
                var markShown = function () {
                    sessionStorage.setItem('pwa-shown-session', '1');
                    localStorage.setItem('pwa-last-shown', Date.now().toString());
                };

                window.addEventListener('beforeinstallprompt', function (e) {
                    e.preventDefault();
                    self._prompt = e;
                    self.visible = true;
                    markShown();
                });
                window.addEventListener('appinstalled', function () {
                    self.visible = false; self._prompt = null;
                });

                // iOS Safari não dispara beforeinstallprompt — mostrar manualmente
                var isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent);
                if (isIOS) {
                    self.visible = true;
                    markShown();
                }
            },

            install() {
                if (this._prompt) {
                    var self = this;
                    this._prompt.prompt();
                    this._prompt.userChoice.then(function () { self._prompt = null; self.visible = false; });
                } else {
                    alert('Para instalar no iPhone:\nToque em compartilhar (⬆) → "Adicionar à Tela de Início"');
                    this.visible = false;
                }
            },

            close() {
                localStorage.setItem('pwa-dismissed', Date.now().toString());
                this.visible = false;
            }
        };
    };

    // ════════════════════════════════════════════════════════════════════
    //  2. Service Worker registration + background sync listener
    // ════════════════════════════════════════════════════════════════════
    var _swReg = null;

    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/sw.js', { scope: '/' })
                .then(function (reg) {
                    _swReg = reg;
                    // Pre-fetch dashboard snapshot into DATA_CACHE
                    if (navigator.onLine) alfaFetchSnapshot();
                })
                .catch(function (err) {
                    console.warn('[PWA] SW registration failed:', err);
                });

            // Listen for sync-success messages from SW
            navigator.serviceWorker.addEventListener('message', function (event) {
                if (event.data && event.data.type === 'sync-success') {
                    alfaShowToast('✅ Lançamento sincronizado com sucesso!', 'success');
                }
            });
        });
    }

    // Pre-fetch API snapshot so offline.html always has fresh data
    function alfaFetchSnapshot() {
        fetch('/api/dashboard/snapshot', { credentials: 'include' }).catch(function () {});
    }

    // Refresh snapshot when user comes back online
    window.addEventListener('online', function () {
        alfaFetchSnapshot();
        alfaShowToast('🟢 Conexão restaurada', 'success');
    });
    window.addEventListener('offline', function () {
        alfaShowToast('🔴 Você está offline. Dados serão sincronizados ao reconectar.', 'warning');
    });

    // ════════════════════════════════════════════════════════════════════
    //  3. Push Notifications
    // ════════════════════════════════════════════════════════════════════
    var VAPID_PUBLIC_KEY = document.querySelector('meta[name="vapid-public-key"]')?.content || '';

    function urlBase64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - base64String.length % 4) % 4);
        var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var raw = atob(base64);
        var arr = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; i++) arr[i] = raw.charCodeAt(i);
        return arr;
    }

    /**
     * Call this to request push permission and subscribe.
     * Exposed globally so UI buttons can call alfaSubscribePush().
     */
    window.alfaSubscribePush = async function () {
        if (!('PushManager' in window) || !_swReg) {
            alfaShowToast('Push não suportado neste navegador', 'warning');
            return false;
        }

        var permission = await Notification.requestPermission();
        if (permission !== 'granted') {
            alfaShowToast('Permissão de notificações negada', 'warning');
            return false;
        }

        try {
            var subscription = await _swReg.pushManager.subscribe({
                userVisibleOnly:      true,
                applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY),
            });

            await fetch('/api/push/subscribe', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'include',
                body: JSON.stringify(subscription.toJSON()),
            });

            alfaShowToast('🔔 Notificações ativadas!', 'success');
            localStorage.setItem('alfahome-push-enabled', '1');
            return true;
        } catch (err) {
            console.error('[PWA] Push subscribe failed:', err);
            alfaShowToast('Erro ao ativar notificações', 'error');
            return false;
        }
    };

    window.alfaUnsubscribePush = async function () {
        if (!_swReg) return;
        var sub = await _swReg.pushManager.getSubscription();
        if (!sub) return;

        await fetch('/api/push/unsubscribe', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'include',
            body: JSON.stringify({ endpoint: sub.endpoint }),
        });
        await sub.unsubscribe();
        localStorage.removeItem('alfahome-push-enabled');
        alfaShowToast('🔕 Notificações desativadas', 'info');
    };

    // ════════════════════════════════════════════════════════════════════
    //  4. Background Sync — IndexedDB queue helpers
    // ════════════════════════════════════════════════════════════════════
    var IDB_NAME    = 'alfahome-offline';
    var IDB_VERSION = 1;
    var _idb        = null;

    function openIDB() {
        if (_idb) return Promise.resolve(_idb);
        return new Promise(function (resolve, reject) {
            var req = indexedDB.open(IDB_NAME, IDB_VERSION);
            req.onupgradeneeded = function (e) {
                var db = e.target.result;
                if (!db.objectStoreNames.contains('despesas'))
                    db.createObjectStore('despesas', { keyPath: 'client_queue_id' });
                if (!db.objectStoreNames.contains('receitas'))
                    db.createObjectStore('receitas', { keyPath: 'client_queue_id' });
            };
            req.onsuccess = function (e) { _idb = e.target.result; resolve(_idb); };
            req.onerror   = function (e) { reject(e.target.error); };
        });
    }

    /**
     * Queue an offline item for background sync.
     * storeName: 'despesas' | 'receitas'
     * data: plain object with form fields
     */
    window.alfaQueueOffline = async function (storeName, data) {
        var item = Object.assign({}, data, {
            client_queue_id: storeName + '_' + Date.now() + '_' + Math.random().toString(36).slice(2),
        });

        var db = await openIDB();
        await new Promise(function (resolve, reject) {
            var tx  = db.transaction(storeName, 'readwrite');
            var req = tx.objectStore(storeName).put(item);
            req.onsuccess = resolve;
            req.onerror   = function (e) { reject(e.target.error); };
        });

        // Register background sync tag with SW
        if (_swReg && 'SyncManager' in window) {
            await _swReg.sync.register('sync-' + storeName);
        }

        alfaShowToast('📦 Salvo offline — será sincronizado ao reconectar', 'info');
        return item.client_queue_id;
    };

    // ════════════════════════════════════════════════════════════════════
    //  5. Toast utility (used by all PWA modules above)
    // ════════════════════════════════════════════════════════════════════
    window.alfaShowToast = function (message, type) {
        var colors = {
            success: 'rgba(34,197,94,.15)',
            warning: 'rgba(245,158,11,.15)',
            error:   'rgba(239,68,68,.15)',
            info:    'rgba(59,130,246,.15)',
        };
        var borders = {
            success: '#22c55e', warning: '#f59e0b', error: '#ef4444', info: '#3b82f6',
        };
        var t = type || 'info';

        var el = document.createElement('div');
        el.style.cssText = [
            'position:fixed;bottom:80px;left:50%;transform:translateX(-50%)',
            'z-index:99999;padding:10px 18px;border-radius:10px',
            'font-size:13px;font-weight:600;max-width:calc(100vw - 32px)',
            'color:#e5e7eb;text-align:center;white-space:nowrap',
            'background:' + (colors[t] || colors.info),
            'border:1px solid ' + (borders[t] || borders.info),
            'backdrop-filter:blur(8px)',
            'box-shadow:0 4px 16px rgba(0,0,0,.3)',
            'transition:opacity .3s',
        ].join(';');
        el.textContent = message;
        document.body.appendChild(el);
        setTimeout(function () { el.style.opacity = '0'; }, 3200);
        setTimeout(function () { el.remove(); }, 3600);
    };

    // ════════════════════════════════════════════════════════════════════
    //  5b. Push toggle button logic (updates bell icon state)
    // ════════════════════════════════════════════════════════════════════
    window.alfaPushToggle = async function (btn) {
        var enabled = localStorage.getItem('alfahome-push-enabled') === '1';
        if (enabled) {
            await window.alfaUnsubscribePush();
            btn.querySelector('i').className = 'fa-regular fa-bell';
            btn.style.color = '#9ca3af';
        } else {
            var ok = await window.alfaSubscribePush();
            if (ok) {
                btn.querySelector('i').className = 'fa-solid fa-bell';
                btn.style.color = 'var(--color-primary, #4f46e5)';
            }
        }
    };

    // Restore push button state on load
    document.addEventListener('DOMContentLoaded', function () {
        var btn = document.getElementById('push-toggle');
        if (btn && localStorage.getItem('alfahome-push-enabled') === '1') {
            btn.querySelector('i').className = 'fa-solid fa-bell';
            btn.style.color = 'var(--color-primary, #4f46e5)';
        }
    });

    // ════════════════════════════════════════════════════════════════════
    //  6. Theme-color meta sync with dark/light toggle
    // ════════════════════════════════════════════════════════════════════
    var themeColorMeta = document.getElementById('meta-theme-color');
    function syncThemeColor() {
        if (!themeColorMeta) return;
        themeColorMeta.setAttribute('content',
            document.body.classList.contains('dark-mode') ? '#0b1120' : '#ffffff');
    }
    document.addEventListener('DOMContentLoaded', syncThemeColor);
    if (typeof MutationObserver !== 'undefined') {
        new MutationObserver(function (m) {
            m.forEach(function (r) { if (r.attributeName === 'class') syncThemeColor(); });
        }).observe(document.body, { attributes: true, attributeFilter: ['class'] });
    }

})();
</script>
{{-- ─── End PWA ──────────────────────────────────────────────────────────── --}}

{{-- ─── Polling manutenção (apenas para não-super_admin) ─────────────────── --}}
@if(Auth::check() && Auth::user()->role !== 'super_admin')
<div id="mnt-overlay" style="
    display:none;position:fixed;inset:0;z-index:99999;
    background:rgba(15,23,42,.96);
    flex-direction:column;align-items:center;justify-content:center;
    text-align:center;padding:32px;
    font-family:system-ui,-apple-system,sans-serif;color:#e2e8f0;
">
    <span style="font-size:48px;margin-bottom:20px;">🔧</span>
    <h2 id="mnt-overlay-titulo" style="font-size:22px;font-weight:700;margin-bottom:12px;color:#f1f5f9;">Sistema em Manutenção</h2>
    <p id="mnt-overlay-msg" style="font-size:15px;color:#94a3b8;max-width:400px;line-height:1.6;margin-bottom:24px;">Estamos realizando melhorias. Voltaremos em breve!</p>
    <p style="font-size:13px;color:#64748b;">Você será redirecionado para o login automaticamente.</p>
</div>
<script>
(function() {
    var overlay = document.getElementById('mnt-overlay');
    var checked = false;

    function checkManutencao() {
        fetch('/status-manutencao')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.ativa && !checked) {
                    checked = true;
                    document.getElementById('mnt-overlay-titulo').textContent = data.titulo || 'Sistema em Manutenção';
                    document.getElementById('mnt-overlay-msg').textContent = data.mensagem || 'Estamos realizando melhorias. Voltaremos em breve!';
                    overlay.style.display = 'flex';

                    setTimeout(function() {
                        window.location.href = '/login';
                    }, 3000);
                }
            })
            .catch(function() {});
    }

    // Verifica a cada 30 segundos
    setInterval(checkManutencao, 30000);
    // Primeira verificação após 5 segundos de carregamento
    setTimeout(checkManutencao, 5000);
})();
</script>
@endif
{{-- ─────────────────────────────────────────────────────────────────────────── --}}
</body>
</html>
