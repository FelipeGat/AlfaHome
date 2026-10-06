<x-guest-layout>
    <x-slot name="titulo">Bem-vindo de volta</x-slot>
    <x-slot name="subtitulo">Acesse sua conta para continuar cuidando das suas finanças de forma simples e segura.</x-slot>

    <!-- Session Status -->
    @if(session('status') && str_starts_with(session('status'), '🔧'))
    @php
        $parts = explode('. ', session('status'), 2);
        $mntTitulo = ltrim($parts[0], '🔧 ');
        $mntMsg = $parts[1] ?? '';
    @endphp
    <div class="mb-6 rounded-xl overflow-hidden" style="border: 1px solid rgba(251,146,60,.35); background: rgba(120,53,15,.25);">
        <div style="background: rgba(234,88,12,.85); padding: 10px 16px; display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 16px;">🔧</span>
            <span style="font-weight: 700; font-size: 13px; color: #fff; letter-spacing: .3px;">{{ $mntTitulo }}</span>
        </div>
        @if($mntMsg)
        <p style="padding: 10px 16px; font-size: 13px; color: #fdba74; margin: 0; line-height: 1.5;">{{ $mntMsg }}</p>
        @endif
    </div>
    @elseif(session('status'))
    <div class="g-status">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="g-campo">
            <label for="email">E-mail</label>
            <div class="g-entrada">
                <svg class="g-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" inputmode="email" placeholder="seu@email.com">
            </div>
            @error('email')<div class="g-erro">{{ $message }}</div>@enderror
        </div>

        <div class="g-campo">
            <label for="password">Senha</label>
            <div class="g-entrada">
                <svg class="g-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                <input id="password" type="password" name="password" required autocomplete="current-password" placeholder="Sua senha">
                <button type="button" class="g-olho" aria-label="Mostrar senha" onclick="const c=document.getElementById('password');const v=c.type==='password';c.type=v?'text':'password';this.setAttribute('aria-label',v?'Esconder senha':'Mostrar senha');">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
            </div>
            @error('password')<div class="g-erro">{{ $message }}</div>@enderror
        </div>

        <div class="g-opcoes">
            <label for="remember_me"><input id="remember_me" type="checkbox" name="remember" checked> Lembrar de mim</label>
            @if (Route::has('password.request'))
                <a class="g-link" href="{{ route('password.request') }}">Esqueci minha senha</a>
            @endif
        </div>

        <button type="submit" class="g-botao">
            Entrar
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </button>
    </form>

    <div class="g-ou">ou</div>
    <div class="g-seguro">
        <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
        <span>Seus dados ficam protegidos e<br>visíveis só para a sua família.</span>
    </div>
</x-guest-layout>
