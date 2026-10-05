@extends('layouts.main')
@section('title', 'Alertas Financeiros')
@section('page-title', 'Alertas Financeiros')

@section('content')

@include('alertas._vencimentos')

@php
$config = [
    'critico'      => ['cor' => 'var(--color-danger)',  'bg' => 'var(--color-danger-soft)',  'borda' => 'var(--color-danger)',  'label' => 'Crítico'],
    'atencao'      => ['cor' => 'var(--color-amber)',   'bg' => 'var(--color-warning-soft)', 'borda' => 'var(--color-amber)',   'label' => 'Atenção'],
    'info'         => ['cor' => 'var(--color-info)',    'bg' => 'var(--color-info-soft)',    'borda' => 'var(--color-info)',    'label' => 'Informativo'],
    'oportunidade' => ['cor' => 'var(--color-success)', 'bg' => 'var(--color-success-soft)', 'borda' => 'var(--color-success)', 'label' => 'Oportunidade'],
];
@endphp

{{-- ═══════════════════════════════════════════════════════════════════ --}}
{{-- 2. ALERTA PRINCIPAL (DESTAQUE) --}}
{{-- ═══════════════════════════════════════════════════════════════════ --}}

{{-- CSS do Alerta Principal --}}
<style>
    .alerta-principal {
        background: linear-gradient(135deg, var(--cor-10), var(--cor-08));
        border: 2px solid var(--cor);
        border-radius: 14px;
        padding: 24px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 16px;
        flex-wrap: wrap;
        justify-content: center;
    }

    .alerta-principal-icone {
        width: 56px;
        height: 56px;
        border-radius: 14px;
        background: var(--cor);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .alerta-principal-icone i {
        font-size: 22px;
        color: #fff;
    }

    .alerta-principal-conteudo {
        flex: 1;
        min-width: 0;
    }

    .alerta-principal-badge {
        background: var(--cor);
        color: #fff;
        font-size: 10px;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 99px;
        text-transform: uppercase;
        letter-spacing: .4px;
        display: inline-block;
    }

    .alerta-principal-badge-wrap {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 6px;
        flex-wrap: wrap;
    }

    .alerta-principal-titulo {
        margin: 0 0 6px;
        font-size: 18px;
        font-weight: 800;
        color: var(--color-text);
    }

    .alerta-principal-descricao {
        margin: 0 0 4px;
        font-size: 14px;
        color: var(--color-text-muted);
        line-height: 1.5;
    }

    .alerta-principal-impacto {
        margin: 0;
        font-size: 13px;
        color: var(--cor);
        font-weight: 700;
    }

    .alerta-principal-previsao {
        margin: 4px 0 0;
        font-size: 12px;
        color: var(--color-text-muted);
        font-style: italic;
    }

    .alerta-principal-acao {
        background: var(--cor);
        color: #fff;
        padding: 10px 22px;
        border-radius: 9999px;
        font-size: 13px;
        font-weight: 700;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        flex-shrink: 0;
        white-space: nowrap;
        transition: opacity .2s;
    }

    .alerta-principal-acao:hover {
        opacity: 0.9;
    }

    /* Mobile: < 480px */
    @media (max-width: 480px) {
        .alerta-principal {
            padding: 16px;
            text-align: center;
            justify-content: center;
        }

        .alerta-principal-icone {
            width: 48px;
            height: 48px;
            margin: 0 auto;
        }

        .alerta-principal-icone i {
            font-size: 18px;
        }

        .alerta-principal-conteudo {
            width: 100%;
            margin-top: 8px;
        }

        .alerta-principal-badge-wrap {
            justify-content: center;
        }

        .alerta-principal-titulo {
            font-size: 16px !important;
        }

        .alerta-principal-descricao {
            font-size: 13px !important;
        }

        .alerta-principal-impacto {
            font-size: 12px !important;
        }

        .alerta-principal-previsao {
            font-size: 11px !important;
        }

        .alerta-principal-acao {
            width: 100%;
            justify-content: center;
            margin-top: 12px;
            padding: 12px 16px;
            font-size: 14px;
        }
    }
</style>

@if($alertaPrincipal)
@php $cp = $config[$alertaPrincipal['tipo']] ?? $config['critico']; @endphp
<div class="alerta-principal" style="--cor: {{ $cp['cor'] }}; --cor-10: {{ $cp['bg'] }}; --cor-08: {{ $cp['bg'] }};">
    <div class="alerta-principal-icone">
        <i class="fa-solid {{ $alertaPrincipal['icone'] }}"></i>
    </div>
    <div class="alerta-principal-conteudo">
        <div class="alerta-principal-badge-wrap">
            <span class="alerta-principal-badge">{{ $cp['label'] }}</span>
        </div>
        <h3 class="alerta-principal-titulo">{{ $alertaPrincipal['titulo'] }}</h3>
        <p class="alerta-principal-descricao">{!! $alertaPrincipal['descricao'] !!}</p>
        <p class="alerta-principal-impacto">{{ $alertaPrincipal['impacto'] }}</p>
        @if($alertaPrincipal['previsao'])
        <p class="alerta-principal-previsao">
            <i class="fa-solid fa-forward" style="font-size:10px; margin-right:4px;"></i>{{ $alertaPrincipal['previsao'] }}
        </p>
        @endif
    </div>
    <a href="{{ $alertaPrincipal['acao_url'] }}" class="alerta-principal-acao">
        {{ $alertaPrincipal['acao_label'] }} <i class="fa-solid fa-arrow-right" style="font-size:11px;"></i>
    </a>
</div>
@endif

{{-- ═══════════════════════════════════════════════════════════════════ --}}
{{-- 4. LISTA DE ALERTAS (CARDS) --}}
{{-- ═══════════════════════════════════════════════════════════════════ --}}
@php $semVencimentos = empty($vencimentos['atrasado']) && empty($vencimentos['a_pagar']) && empty($vencimentos['a_receber']); @endphp
@if($alertas->isEmpty() && ! $semVencimentos)
{{-- Sem alertas de análise, mas há vencimentos listados acima: não dizer "tudo em ordem". --}}
@elseif($alertas->isEmpty())
<div class="card text-center" style="padding: 40px 20px;">
    <div style="font-size: 48px; margin-bottom: 12px;">🎉</div>
    <h3 style="color:var(--color-success); font-size:18px; margin-bottom:6px;">Tudo em ordem!</h3>
    <p style="color:var(--color-text-muted); font-size:14px;">Nenhum alerta financeiro identificado para o período atual.</p>
</div>
@else

{{-- CSS dos cards de alerta --}}
<style>
    .alertas-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(min(360px, 100%), 1fr));
        gap: 12px;
    }

    .alerta-card {
        background: var(--color-bg-card);
        border-radius: 12px;
        overflow: hidden;
        transition: all .2s;
        display: flex;
        flex-direction: column;
        height: 100%;
        min-height: 180px;
    }

    .alerta-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }

    .alerta-header {
        padding: 14px 16px;
        cursor: pointer;
        display: flex;
        align-items: flex-start;
        gap: 12px;
        flex: 1;
    }

    .alerta-icone {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        margin-top: 2px;
    }

    .alerta-conteudo {
        flex: 1;
        min-width: 0;
    }

    .alerta-badge {
        display: inline-block;
        font-size: 9px;
        font-weight: 700;
        padding: 2px 7px;
        border-radius: 99px;
        text-transform: uppercase;
        letter-spacing: .3px;
        color: #fff;
    }

    .alerta-titulo {
        font-size: 13px;
        font-weight: 700;
        color: var(--color-text);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .alerta-descricao {
        font-size: 12px;
        color: var(--color-text-muted);
        margin: 0 0 6px;
        line-height: 1.5;
    }

    .alerta-impacto {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
    }

    .alerta-impacto-texto {
        font-size: 11px;
        font-weight: 700;
    }

    .alerta-previsao {
        font-size: 11px;
        color: var(--color-text-subtle);
        font-style: italic;
    }

    .alerta-chevron {
        font-size: 10px;
        color: var(--color-text-subtle);
        flex-shrink: 0;
        margin-top: 4px;
        transition: .2s;
    }

    .alerta-chevron.rotacionado {
        transform: rotate(180deg);
    }

    .alerta-detalhes {
        display: none;
        padding: 0 16px 10px 64px;
        border-top: 1px solid;
        padding-top: 10px;
    }

    .alerta-detalhes ul {
        margin: 0;
        padding-left: 14px;
        color: var(--color-text-muted);
        font-size: 11px;
        line-height: 1.9;
    }

    .alerta-footer {
        padding: 8px 16px 14px;
        display: flex;
        justify-content: flex-end;
        margin-top: auto;
    }

    .alerta-acao {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        color: #fff;
        padding: 6px 16px;
        border-radius: 9999px;
        font-size: 11px;
        font-weight: 700;
        text-decoration: none;
        transition: opacity .2s;
    }

    .alerta-acao:hover {
        opacity: 0.85;
    }

    .alerta-oculto {
        display: none;
    }
</style>

<div id="alertas-grid" class="alertas-grid">
    @foreach($alertas->slice(1) as $i => $alerta)
    @php
        $c = $config[$alerta['tipo']] ?? $config['info'];
        $temContas = !empty($alerta['contas_afetadas']);
        $hidden = $i >= 7 ? 'alerta-oculto' : '';
    @endphp

    <div class="alerta-card {{ $hidden }}" data-tipo="{{ $alerta['tipo'] }}" data-intencao="{{ $alerta['intencao'] }}" data-indice="{{ $i }}" style="border: 1px solid {{ $c['borda'] }}; border-left: 4px solid {{ $c['cor'] }};">

        {{-- Header --}}
        <div class="alerta-header" onclick="toggleAlerta('det-{{ $alerta['id'] }}')">
            <div class="alerta-icone" style="background:{{ $c['bg'] }};">
                <i class="fa-solid {{ $alerta['icone'] }}" style="font-size:14px; color:{{ $c['cor'] }};"></i>
            </div>
            <div class="alerta-conteudo">
                <div style="display:flex; align-items:center; gap:6px; margin-bottom:4px;">
                    <span class="alerta-badge" style="background:{{ $c['cor'] }};">{{ $c['label'] }}</span>
                    <span class="alerta-titulo">{{ $alerta['titulo'] }}</span>
                </div>
                <p class="alerta-descricao">{!! $alerta['descricao'] !!}</p>
                <div class="alerta-impacto">
                    <span class="alerta-impacto-texto" style="color:{{ $c['cor'] }};">
                        <i class="fa-solid fa-circle-dollar-to-slot" style="font-size:10px; margin-right:2px;"></i>{{ $alerta['impacto'] }}
                    </span>
                    @if($alerta['previsao'])
                    <span class="alerta-previsao">
                        <i class="fa-solid fa-forward" style="font-size:9px; margin-right:2px;"></i>{{ $alerta['previsao'] }}
                    </span>
                    @endif
                </div>
            </div>
            <i id="chev-{{ $alerta['id'] }}" class="alerta-chevron fa-solid {{ $temContas ? 'fa-chevron-down' : 'fa-circle' }}" style="{{ !$temContas ? 'visibility:hidden;' : '' }}"></i>
        </div>

        {{-- Contas afetadas (expansível) --}}
        @if($temContas)
        <div id="det-{{ $alerta['id'] }}" class="alerta-detalhes" style="border-top-color: {{ $c['borda'] }};">
            <ul>
                @foreach($alerta['contas_afetadas'] as $item)
                <li>{{ $item }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        {{-- Ação --}}
        <div class="alerta-footer">
            <a href="{{ $alerta['acao_url'] }}" class="alerta-acao" style="background:{{ $c['cor'] }};">
                {{ $alerta['acao_label'] }} <i class="fa-solid fa-arrow-right" style="font-size:9px;"></i>
            </a>
        </div>
    </div>
    @endforeach
</div>

{{-- Botão ver todos --}}
@if($alertas->count() > 8)
<div id="ver-todos-wrap" style="text-align:center; margin-top:16px;">
    <button onclick="mostrarTodos()" id="btn-ver-todos"
            style="background:var(--color-bg-container); border:1px solid var(--color-border); color:var(--color-text-muted); padding:10px 24px; border-radius:9999px; font-size:13px; font-weight:600; cursor:pointer; transition:all .2s;">
        <i class="fa-solid fa-chevron-down" style="margin-right:6px;"></i>
        Ver todos os alertas ({{ $alertas->count() - 1 }} restantes)
    </button>
</div>
@endif

@endif

{{-- Rodapé --}}
<div style="margin-top:20px; padding:10px 16px; background:var(--color-bg-container); border-radius:8px; border:1px solid var(--color-border); display:flex; align-items:center; gap:10px;">
    <i class="fa-solid fa-robot" style="color:var(--color-text-subtle); font-size:13px;"></i>
    <span style="font-size:12px; color:var(--color-text-muted); flex:1;">Consultor financeiro automático — alertas calculados em tempo real com base nos seus lançamentos.</span>
    <a href="{{ route('alertas.index') }}" class="btn btn-secondary btn-sm" style="font-size:11px; padding:4px 10px;">
        <i class="fa-solid fa-rotate-right me-1"></i> Atualizar
    </a>
</div>

<script>
let filtroAtual = 'todos';
let tipoAtual = null;

const filtrosBtns = {
    todos:      { bg: 'var(--color-text)',    cor: 'var(--color-bg-card)', border: 'var(--color-text)' },
    resolver:   { bg: 'var(--color-danger)',  cor: '#fff', border: 'var(--color-danger)' },
    economizar: { bg: 'var(--color-success)', cor: '#fff', border: 'var(--color-success)' },
    entender:   { bg: 'var(--color-info)',    cor: '#fff', border: 'var(--color-info)' },
};

const filtrosDefault = {
    todos:      { bg: 'var(--color-text)',         cor: 'var(--color-bg-card)', border: 'var(--color-border)' },
    resolver:   { bg: 'var(--color-danger-soft)',  cor: 'var(--color-danger)',  border: 'var(--color-danger)' },
    economizar: { bg: 'var(--color-success-soft)', cor: 'var(--color-success)', border: 'var(--color-success)' },
    entender:   { bg: 'var(--color-info-soft)',    cor: 'var(--color-info)',    border: 'var(--color-info)' },
};

const configCores = @json($config);

function aplicarEstiloBotao(id, bg, cor, border) {
    const el = document.getElementById(id);
    if (!el) return;
    el.style.background = bg;
    el.style.color = cor;
    el.style.borderColor = border;
}

function filtrar(intencao) {
    tipoAtual = null;
    filtroAtual = intencao;
    const cards = document.querySelectorAll('.alerta-card');

    cards.forEach(c => {
        const match = intencao === 'todos' || c.getAttribute('data-intencao') === intencao;
        c.classList.toggle('alerta-oculto', !match);
    });

    // Reset tipo pills
    ['critico','atencao','info','oportunidade'].forEach(t => {
        const el = document.getElementById('tipo-' + t);
        if (!el) return;
        const cfg = configCores[t];
        el.style.background = cfg.bg;
        el.style.color = cfg.cor;
        el.style.borderColor = cfg.borda;
        el.style.opacity = '1';
    });

    // Estilo filtros intenção
    ['todos','resolver','economizar','entender'].forEach(f => {
        if (f === intencao) {
            aplicarEstiloBotao('filtro-' + f, filtrosBtns[f].bg, filtrosBtns[f].cor, filtrosBtns[f].border);
        } else {
            aplicarEstiloBotao('filtro-' + f, filtrosDefault[f].bg, filtrosDefault[f].cor, filtrosDefault[f].border);
        }
    });

    const wrap = document.getElementById('ver-todos-wrap');
    if (wrap) wrap.style.display = intencao === 'todos' ? '' : 'none';
}

function filtrarTipo(tipo) {
    if (tipoAtual === tipo) {
        filtrar('todos');
        return;
    }

    tipoAtual = tipo;
    filtroAtual = null;

    const cards = document.querySelectorAll('.alerta-card');
    cards.forEach(c => {
        const match = c.getAttribute('data-tipo') === tipo;
        c.classList.toggle('alerta-oculto', !match);
    });

    // Reset intenção
    ['todos','resolver','economizar','entender'].forEach(f => {
        aplicarEstiloBotao('filtro-' + f, filtrosDefault[f].bg, filtrosDefault[f].cor, filtrosDefault[f].border);
    });

    // Estilo tipo pills
    ['critico','atencao','info','oportunidade'].forEach(t => {
        const el = document.getElementById('tipo-' + t);
        if (!el) return;
        const cfg = configCores[t];
        if (t === tipo) {
            el.style.background = cfg.cor;
            el.style.color = '#fff';
            el.style.opacity = '1';
        } else {
            el.style.background = cfg.bg;
            el.style.color = cfg.cor;
            el.style.opacity = '0.4';
        }
    });

    const wrap = document.getElementById('ver-todos-wrap');
    if (wrap) wrap.style.display = 'none';
}

function toggleAlerta(detId) {
    const det = document.getElementById(detId);
    const chevId = detId.replace('det-', 'chev-');
    const chev = document.getElementById(chevId);
    if (!det) return;
    const aberto = det.style.display !== 'none';
    det.style.display = aberto ? 'none' : 'block';
    if (chev) {
        chev.classList.toggle('rotacionado', !aberto);
    }
}

function mostrarTodos() {
    document.querySelectorAll('.alerta-card').forEach(c => {
        if (filtroAtual === 'todos' || !filtroAtual || c.getAttribute('data-intencao') === filtroAtual) {
            c.classList.remove('alerta-oculto');
        }
    });
    const wrap = document.getElementById('ver-todos-wrap');
    if (wrap) wrap.style.display = 'none';
}
</script>

<style>
@media (max-width: 768px) {
    [style*="grid-template-columns: repeat(4"] {
        grid-template-columns: repeat(2, 1fr) !important;
    }
}
@media (max-width: 480px) {
    [style*="grid-template-columns: repeat(4"] {
        grid-template-columns: 1fr !important;
    }
}
</style>

@endsection
