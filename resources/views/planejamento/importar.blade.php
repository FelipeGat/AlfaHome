@extends('layouts.main')
@section('title', 'Importar planilha')
@section('page-title', 'Planejamento — importar planilha')

@section('content')
@php
    $situacoes = [
        'sucesso'        => ['Importada', 'badge-success'],
        'sem_alteracoes' => ['Sem alterações', 'badge-gray'],
        'rejeitada'      => ['Rejeitada', 'badge-danger'],
    ];
    $ultima = $importacoes->first();
@endphp

<style>.plan-num { text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums; }</style>

<div class="card" style="padding:20px;margin-bottom:20px;">
    <div class="card-title" style="margin-bottom:6px;">Enviar a planilha de planejamento</div>
    <p style="font-size:13px;color:var(--color-text-muted);margin-bottom:14px;">
        Escolha o arquivo Excel (.xlsx) do planejamento da casa. O sistema fica igual à planilha: inclui o que é novo,
        atualiza o que mudou e remove o que saiu. Enviar o mesmo arquivo de novo não muda nada, e se houver qualquer
        erro nada é alterado.
    </p>
    <form method="POST" action="{{ route('planejamento.importar.store') }}" enctype="multipart/form-data" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
        @csrf
        <input type="file" name="arquivo" accept=".xlsx" required class="form-control" style="max-width:420px;">
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-file-arrow-up"></i> Importar</button>
    </form>
</div>

@if($ultima)
    <div class="card" style="padding:20px;margin-bottom:20px;">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:12px;">
            <div class="card-title">Última importação — {{ $ultima->created_at->format('d/m/Y H:i') }}</div>
            <span class="badge {{ $situacoes[$ultima->status][1] }}">{{ $situacoes[$ultima->status][0] }}</span>
        </div>
        <div style="font-size:12px;color:var(--color-text-muted);margin-bottom:12px;">{{ $ultima->arquivo_nome }}@if($ultima->user) · por {{ $ultima->user->name }}@endif</div>

        @if($ultima->erros)
            <div class="alert alert-danger" style="flex-direction:column;align-items:flex-start;">
                <strong><i class="fa-solid fa-triangle-exclamation"></i> Nada foi alterado. Corrija na planilha e envie de novo:</strong>
                <ul style="margin-top:6px;padding-left:16px;font-size:13px;">
                    @foreach($ultima->erros as $erro)
                        <li>@if($erro['aba'])<strong>{{ $erro['aba'] }}@if($erro['linha']), linha {{ $erro['linha'] }}@endif:</strong> @endif{{ $erro['mensagem'] }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if($ultima->resumo)
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr><th>Aba</th><th class="plan-num">Incluídas</th><th class="plan-num">Atualizadas</th><th class="plan-num">Removidas</th><th class="plan-num">Mantidas</th></tr>
                    </thead>
                    <tbody>
                        @foreach($ultima->resumo as $aba => $r)
                            <tr>
                                <td><strong>{{ $abas[$aba] ?? $aba }}</strong></td>
                                <td class="plan-num">{{ $r['incluidas'] }}</td>
                                <td class="plan-num">{{ $r['atualizadas'] }}</td>
                                <td class="plan-num">{{ $r['removidas'] }}</td>
                                <td class="plan-num">{{ $r['mantidas'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if($ultima->avisos)
            <details style="margin-top:12px;">
                <summary style="cursor:pointer;font-size:13px;font-weight:600;">{{ count($ultima->avisos) }} {{ count($ultima->avisos) === 1 ? 'aviso' : 'avisos' }}</summary>
                <ul style="margin-top:6px;padding-left:16px;font-size:13px;color:var(--color-text-muted);">
                    @foreach($ultima->avisos as $aviso)
                        <li><strong>{{ $aviso['aba'] }}, linha {{ $aviso['linha'] }}:</strong> {{ $aviso['mensagem'] }}</li>
                    @endforeach
                </ul>
            </details>
        @endif
    </div>
@endif

<div class="card" style="padding:20px;">
    <div class="card-title" style="margin-bottom:12px;">Histórico</div>
    @if($importacoes->isEmpty())
        <div class="empty-state"><i class="fa-solid fa-file-excel"></i><p>A planilha ainda não foi importada.</p></div>
    @else
        <div class="table-wrapper">
            <table class="table">
                <thead><tr><th>Quando</th><th>Arquivo</th><th>Quem</th><th>Resultado</th><th class="plan-num">Linhas alteradas</th></tr></thead>
                <tbody>
                    @foreach($importacoes as $i)
                        <tr>
                            <td style="white-space:nowrap;">{{ $i->created_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $i->arquivo_nome }}</td>
                            <td>{{ $i->user?->name ?? 'Sistema' }}</td>
                            <td><span class="badge {{ $situacoes[$i->status][1] }}">{{ $situacoes[$i->status][0] }}</span></td>
                            <td class="plan-num">{{ $i->resumo ? collect($i->resumo)->sum(fn ($r) => $r['incluidas'] + $r['atualizadas'] + $r['removidas']) : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
