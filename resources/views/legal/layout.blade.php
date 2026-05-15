<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>@yield('title') — AlfaHome</title>
<link rel="icon" type="image/png" href="/favicon.png" />
<script>
  (function() {
    var saved = localStorage.getItem('lp-theme');
    document.documentElement.setAttribute('data-theme', saved || 'light');
  })();
</script>
<style>
  :root {
    --bg: #0a0e12;
    --bg-topbar: rgba(10,14,18,.85);
    --card: #1a2028;
    --text: #e5e9ef;
    --text-strong: #ffffff;
    --text-dim: #9aa4b2;
    --border: rgba(255,255,255,.08);
    --green: #56b988;
    --green-dark: #3fa06f;
  }
  [data-theme="light"] {
    --bg: #ffffff;
    --bg-topbar: rgba(255,255,255,.9);
    --card: #f8fafc;
    --text: #1f2937;
    --text-strong: #0f172a;
    --text-dim: #64748b;
    --border: rgba(15,23,42,.08);
    --green: #3fa06f;
    --green-dark: #2f8057;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  html { scroll-behavior: smooth; }
  body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    background: var(--bg);
    color: var(--text);
    line-height: 1.7;
    -webkit-font-smoothing: antialiased;
    transition: background-color .2s ease, color .2s ease;
  }
  .topbar {
    position: sticky; top: 0; z-index: 10;
    background: var(--bg-topbar);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border-bottom: 1px solid var(--border);
    padding: 16px 0;
  }
  .container {
    max-width: 820px;
    margin: 0 auto;
    padding: 0 24px;
  }
  .topbar-inner {
    display: flex; align-items: center; justify-content: space-between;
    gap: 16px;
  }
  .topbar a.brand {
    display: flex; align-items: center; gap: 10px;
    text-decoration: none; color: var(--text-strong);
    font-weight: 600;
  }
  .topbar a.brand img { height: 22px; }
  .topbar-actions { display: flex; align-items: center; gap: 16px; }
  .topbar a.back {
    color: var(--text-dim); text-decoration: none; font-size: 14px;
  }
  .topbar a.back:hover { color: var(--green); }
  .theme-toggle {
    background: transparent;
    border: 1px solid var(--border);
    color: var(--text-dim);
    width: 34px; height: 34px;
    border-radius: 8px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all .2s ease;
  }
  .theme-toggle:hover {
    color: var(--green);
    border-color: var(--green);
  }
  .theme-toggle svg { width: 16px; height: 16px; }
  .theme-toggle .icon-sun { display: none; }
  [data-theme="light"] .theme-toggle .icon-moon { display: none; }
  [data-theme="light"] .theme-toggle .icon-sun  { display: block; }

  main { padding: 56px 0 80px; }
  h1 {
    font-size: 32px;
    font-weight: 800;
    letter-spacing: -.02em;
    margin-bottom: 8px;
    color: var(--text-strong);
  }
  h1 span { color: var(--green); }
  .meta { color: var(--text-dim); font-size: 13px; margin-bottom: 40px; }
  h2 {
    font-size: 20px;
    font-weight: 700;
    margin: 36px 0 12px;
    color: var(--text-strong);
  }
  h2::before {
    content: "";
    display: inline-block;
    width: 4px; height: 18px;
    background: var(--green);
    border-radius: 2px;
    margin-right: 10px;
    vertical-align: -2px;
  }
  h3 { font-size: 16px; font-weight: 600; margin: 20px 0 6px; color: var(--text-strong); }
  p { margin-bottom: 12px; color: var(--text); }
  ul { margin: 8px 0 12px 24px; }
  li { margin-bottom: 6px; }
  a { color: var(--green); text-decoration: none; }
  a:hover { color: var(--green-dark); text-decoration: underline; }
  strong { color: var(--text-strong); }
  .callout {
    background: var(--card);
    border: 1px solid var(--border);
    border-left: 3px solid var(--green);
    border-radius: 8px;
    padding: 16px 20px;
    margin: 20px 0;
    font-size: 14px;
    color: var(--text-dim);
  }
  .callout strong { color: var(--text-strong); }
  footer {
    border-top: 1px solid var(--border);
    padding: 24px 0;
    text-align: center;
    color: var(--text-dim);
    font-size: 13px;
  }
  @media (max-width: 640px) {
    h1 { font-size: 26px; }
    main { padding: 32px 0 48px; }
    .topbar a.back { display: none; }
  }
</style>
</head>
<body>

<header class="topbar">
  <div class="container topbar-inner">
    <a href="/" class="brand">
      <img src="/alfa-home-logo/alfa-home-logo.png" alt="AlfaHome" />
    </a>
    <div class="topbar-actions">
      <a href="/" class="back">← Voltar ao site</a>
      <button type="button" class="theme-toggle" onclick="toggleTheme()" aria-label="Alternar tema">
        <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
      </button>
    </div>
  </div>
</header>

<main>
  <div class="container">
    @yield('content')
  </div>
</main>

<footer>
  <div class="container">
    AlfaHome • Alfa Soluções Tecnológicas
  </div>
</footer>

<script>
  function toggleTheme() {
    var cur = document.documentElement.getAttribute('data-theme') || 'dark';
    var next = cur === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    try { localStorage.setItem('lp-theme', next); } catch (e) {}
  }
</script>

</body>
</html>
