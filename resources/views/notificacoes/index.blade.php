@extends('layouts.main')
@section('title', 'Avisos no Telegram')
@section('page-title', 'Avisos no Telegram')

@section('content')
@php
    $situacoes = [
        'pendente' => ['Na fila', 'badge-gray'],
        'enviando' => ['Enviando', 'badge-gray'],
        'enviado'  => ['Enviado', 'badge-success'],
        'falhou'   => ['Falhou', 'badge-danger'],
        'expirado' => ['Não enviado', 'badge-danger'],
    ];
@endphp

<div class="card" style="padding:20px;margin-bottom:20px;">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:6px;">
        <div class="card-title">Bot do Telegram</div>
        @if($config?->telegram_bot)
            <span class="badge badge-success">{{ '@' . $config->telegram_bot }}</span>
        @endif
    </div>
    <p style="font-size:13px;color:var(--color-text-muted);margin-bottom:12px;">
        @if($config?->telegram_bot)
            O token fica guardado cifrado no servidor e não aparece mais aqui. Para trocar de bot, cole outro token.
        @else
            No Telegram, fale com o <strong>@BotFather</strong>, envie <code>/newbot</code>, escolha nome e usuário e cole aqui o token que ele devolver.
        @endif
    </p>
    <form method="POST" action="{{ route('notificacoes.token') }}" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
        @csrf
        <input type="password" name="token" required placeholder="123456789:AA..." class="form-control" style="flex:1;min-width:260px;" autocomplete="off">
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-key"></i> {{ $config?->telegram_bot ? 'Trocar token' : 'Salvar token' }}</button>
    </form>
</div>

@if($config?->telegram_bot)
<div class="card" style="padding:20px;margin-bottom:20px;">
    <div class="card-title" style="margin-bottom:6px;">Quem recebe</div>
    @if($link)
        <p style="font-size:13px;color:var(--color-text-muted);margin-bottom:10px;">
            Cada pessoa abre este link no celular (ou no Telegram Web) e toca em <strong>Iniciar</strong>.
            Vale até {{ $config->codigo_vinculo_ate->format('d/m/Y') }}.
        </p>
        <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:14px;">
            <input type="text" readonly value="{{ $link }}" class="form-control" style="flex:1;min-width:260px;" onclick="this.select()">
            <a href="{{ $link }}" target="_blank" rel="noopener" class="btn btn-primary"><i class="fa-brands fa-telegram"></i> Abrir no Telegram</a>
        </div>
    @else
        <form method="POST" action="{{ route('notificacoes.link') }}" style="margin-bottom:14px;">
            @csrf
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-link"></i> Gerar link de vínculo</button>
        </form>
    @endif

    @forelse($destinatarios as $d)
        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;padding:8px 0;border-top:1px solid var(--color-border);">
            <div>
                <strong>{{ $d->nome ?? 'Sem nome' }}</strong>
                <span style="font-size:12px;color:var(--color-text-muted);">desde {{ $d->created_at->format('d/m/Y') }}</span>
                @unless($d->ativo)
                    <span class="badge badge-danger" title="{{ $d->motivo_inativo }}">Não recebe (bloqueou o bot?)</span>
                @endunless
            </div>
            <form method="POST" action="{{ route('notificacoes.destinatarios.remover', $d) }}" onsubmit="return confirm('Parar de enviar avisos para {{ $d->nome }}?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-ghost btn-sm"><i class="fa-solid fa-user-minus"></i> Remover</button>
            </form>
        </div>
    @empty
        <p style="font-size:13px;color:var(--color-text-muted);">Ninguém vinculado ainda.</p>
    @endforelse

    @if($link)
        <form method="POST" action="{{ route('notificacoes.link') }}" style="margin-top:10px;">
            @csrf
            <button type="submit" class="btn btn-ghost btn-sm"><i class="fa-solid fa-rotate"></i> Gerar link novo (o atual deixa de valer)</button>
        </form>
    @endif
</div>

<div class="card" style="padding:20px;margin-bottom:20px;">
    <div class="card-title" style="margin-bottom:10px;">O que avisar</div>
    <form method="POST" action="{{ route('notificacoes.tipos') }}">
        @csrf
        @foreach(\App\Models\NotificacaoConfig::TIPOS as $tipo => $rotulo)
            <label style="display:flex;align-items:center;gap:8px;padding:5px 0;font-size:14px;">
                <input type="checkbox" name="tipos[{{ $tipo }}]" value="1" @checked($config->ligado($tipo))> {{ $rotulo }}
            </label>
        @endforeach
        <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:12px;">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Salvar</button>
        </div>
    </form>
    <form method="POST" action="{{ route('notificacoes.teste') }}" style="margin-top:10px;">
        @csrf
        <button type="submit" class="btn btn-ghost"><i class="fa-solid fa-paper-plane"></i> Enviar teste agora</button>
    </form>
</div>

<div class="card" style="padding:20px;margin-bottom:20px;">
    <div class="card-title" style="margin-bottom:6px;">Relógio dos avisos</div>
    <p style="font-size:13px;color:var(--color-text-muted);margin-bottom:10px;">
        O servidor não tem agendador: este endereço precisa ser chamado a cada 15 minutos (o GitHub do projeto faz isso com o segredo
        <code>ALFAHOME_RELOGIO_URL</code>). Não compartilhe.
        @if($config->processado_em) Última passagem: <strong>{{ $config->processado_em->format('d/m/Y H:i') }}</strong>.@endif
    </p>
    <input type="text" readonly value="{{ $relogio }}" class="form-control" onclick="this.select()">
</div>

@if($envios->isNotEmpty())
<div class="card" style="padding:20px;">
    <div class="card-title" style="margin-bottom:10px;">Últimos envios</div>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>Quando</th><th>Para</th><th>Aviso</th><th>Situação</th></tr></thead>
            <tbody>
            @foreach($envios as $e)
                <tr>
                    <td style="white-space:nowrap;">{{ ($e->enviado_em ?? $e->created_at)->format('d/m H:i') }}</td>
                    <td>{{ $e->destinatario?->nome }}</td>
                    <td>{{ \Illuminate\Support\Str::limit(strip_tags(str_replace("\n", ' · ', $e->texto)), 90) }}</td>
                    <td><span class="badge {{ $situacoes[$e->status][1] ?? 'badge-gray' }}" title="{{ $e->erro }}">{{ $situacoes[$e->status][0] ?? $e->status }}</span></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endif
@endsection
