{{-- Vencimentos da planilha: atrasado, a pagar e a receber --}}
@php
    $vBrl = fn ($v) => brl($v);
    $vGrupos = [
        'atrasado'  => ['Atrasado', 'fa-triangle-exclamation', 'var(--color-danger)', 'Nada atrasado.'],
        'a_pagar'   => ['A pagar', 'fa-arrow-trend-down', 'var(--color-warning)', 'Nada a pagar.'],
        'a_receber' => ['A receber', 'fa-arrow-trend-up', 'var(--color-success)', 'Nada a receber.'],
    ];
@endphp

<style>
.venc-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:20px; }
@media (max-width:1000px) { .venc-grid { grid-template-columns:1fr; } }
</style>

<div class="venc-grid">
    @foreach($vGrupos as $chave => [$titulo, $icone, $cor, $vazio])
        <div class="card" style="padding:16px 18px;border-top:3px solid {{ $cor }};">
            <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:10px;">
                <div style="font-size:13px;font-weight:700;color:{{ $cor }};"><i class="fa-solid {{ $icone }}"></i> {{ $titulo }} <span style="font-weight:400;color:var(--color-text-muted);">({{ count($vencimentos[$chave]) }})</span></div>
                <div style="font-size:18px;font-weight:700;color:{{ $cor }};">{{ $vBrl($vencimentos['totais'][$chave]) }}</div>
            </div>

            @forelse($vencimentos[$chave] as $v)
                <div style="display:flex;justify-content:space-between;gap:10px;padding:8px 0;border-top:1px solid var(--color-border);font-size:13px;">
                    <div style="min-width:0;">
                        <div style="font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                            @if($chave === 'atrasado')
                                <i class="fa-solid {{ $v['tipo'] === 'receita' ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' }}" style="color:var(--color-text-muted);" title="{{ $v['tipo'] === 'receita' ? 'A receber' : 'A pagar' }}"></i>
                            @endif
                            {{ $v['descricao'] }}
                        </div>
                        <div style="font-size:11px;color:var(--color-text-muted);">
                            {{ $v['data'] ? \Carbon\Carbon::parse($v['data'])->format('d/m/Y') : 'sem data' }}@if($v['detalhe']) · {{ $v['detalhe'] }}@endif
                        </div>
                    </div>
                    <div style="font-weight:700;white-space:nowrap;">{{ $vBrl($v['valor']) }}</div>
                </div>
            @empty
                <div style="font-size:13px;color:var(--color-text-muted);padding:8px 0;border-top:1px solid var(--color-border);">{{ $vazio }}</div>
            @endforelse
        </div>
    @endforeach
</div>
