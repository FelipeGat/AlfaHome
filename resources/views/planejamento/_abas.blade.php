{{-- Navegação entre as telas do planejamento + de onde vêm os dados --}}
@php
    $abasPlanejamento = [
        'planejamento.index'        => ['Mês', 'fa-calendar-day'],
        'planejamento.anual'        => ['Ano', 'fa-calendar'],
        'planejamento.cartoes'      => ['Cartões', 'fa-credit-card'],
        'planejamento.dividas'      => ['Dívidas', 'fa-hand-holding-dollar'],
        'planejamento.metas'        => ['Metas', 'fa-bullseye'],
        'planejamento.contas-fixas' => ['Contas fixas', 'fa-calendar-check'],
    ];
@endphp
<div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:space-between;margin-bottom:18px;">
    <div style="display:flex;flex-wrap:wrap;gap:6px;">
        @foreach($abasPlanejamento as $rota => [$rotulo, $icone])
            <a href="{{ route($rota) }}" class="btn btn-sm {{ request()->routeIs($rota) ? 'btn-primary' : 'btn-ghost' }}">
                <i class="fa-solid {{ $icone }}"></i> {{ $rotulo }}
            </a>
        @endforeach
    </div>
    <div style="font-size:12px;color:var(--color-text-muted);">
        <i class="fa-solid fa-file-excel"></i>
        @if($ultima)
            Dados da planilha — última importação em {{ \Carbon\Carbon::parse($ultima['em'])->format('d/m/Y H:i') }}
        @else
            A planilha ainda não foi importada
        @endif
        @if(Auth::user()->role === 'master')
            · <a href="{{ route('planejamento.importar') }}" style="color:var(--color-primary);font-weight:600;">Importar</a>
        @endif
    </div>
</div>
