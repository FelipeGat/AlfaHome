<?php

namespace App\Http\Controllers;

use App\Models\Banco;
use App\Services\Planejamento\PlanejamentoService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Telas do planejamento financeiro (dados da planilha da família). Somente
 * leitura: quem altera é a planilha, na próxima importação.
 */
class PlanejamentoController extends Controller
{
    public function __construct(private PlanejamentoService $planejamento)
    {
    }

    public function index(Request $request)
    {
        $tenantId = Auth::user()->tenant_id;
        $mes      = $this->mes($request) ?? $this->planejamento->mesPadrao($tenantId);

        return view('planejamento.index', [
            'mes'         => $mes,
            'resumo'      => $this->planejamento->resumoMes($tenantId, $mes),
            'lancamentos' => $this->planejamento->lancamentos($tenantId, $mes),
        ]);
    }

    /** Lançamentos da planilha do mês, com filtros; substitui as telas manuais de despesas e receitas no menu. */
    public function lancamentos(Request $request)
    {
        $tenantId = Auth::user()->tenant_id;
        $mes      = $this->mes($request) ?? $this->planejamento->mesPadrao($tenantId);
        $todos    = collect($this->planejamento->lancamentos($tenantId, $mes));

        $filtros = [
            'tipo'      => in_array($request->query('tipo'), ['receita', 'despesa'], true) ? $request->query('tipo') : null,
            'situacao'  => in_array($request->query('situacao'), ['pendente', 'concluido'], true) ? $request->query('situacao') : null,
            'q'         => trim((string) $request->query('q')),
            'categoria' => (string) $request->query('categoria') ?: null,
            'conta'     => (string) $request->query('conta') ?: null,
        ];
        $normal = fn (?string $t) => Str::lower(Str::ascii((string) $t));
        $busca  = $normal($filtros['q']);
        // "96,85", "96.85" ou "1.250,00" também acham pelo valor.
        $valorBuscado = $this->valorDigitado($filtros['q']);

        $lista = $todos
            ->when($filtros['tipo'], fn ($c, $v) => $c->where('tipo', $v))
            ->when($filtros['situacao'], fn ($c, $v) => $c->where('status', $v))
            ->when($filtros['categoria'], fn ($c, $v) => $c->where('categoria', $v))
            ->when($filtros['conta'], fn ($c, $v) => $c->where('conta', $v))
            ->when($busca !== '', fn ($c) => $c->filter(fn ($l) => str_contains($normal($l['descricao'] . ' ' . $l['categoria'] . ' ' . $l['conta']), $busca)
                || ($valorBuscado !== null && in_array($valorBuscado, [$l['valor_realizado'], $l['valor_previsto']], false))))
            ->values();

        $soma = fn ($c, string $campo) => round($c->sum(fn ($l) => (float) ($l[$campo] ?? 0)), 2);
        // Realizado = valor realizado preenchido: a mesma regra do FinanceiroService.
        $feitos    = $todos->whereNotNull('valor_realizado');
        $pendentes = $todos->whereNull('valor_realizado');
        $valor     = fn ($l) => $l['valor_realizado'] ?? $l['valor_previsto'];

        return view('planejamento.lancamentos', [
            'mes'     => $mes,
            'filtros' => $filtros,
            'dias'    => $lista->groupBy('data')->sortKeys(),
            'qtd'     => $lista->count(),
            'totalLista' => round($lista->sum(fn ($l) => ($l['tipo'] === 'receita' ? 1 : -1) * (float) $valor($l)), 2),
            'totais'  => [
                'entradas'  => $soma($feitos->where('tipo', 'receita'), 'valor_realizado'),
                'saidas'    => $soma($feitos->where('tipo', 'despesa'), 'valor_realizado'),
                'a_receber' => $soma($pendentes->where('tipo', 'receita'), 'valor_previsto'),
                'a_pagar'   => $soma($pendentes->where('tipo', 'despesa'), 'valor_previsto'),
            ],
            'categorias' => $todos->pluck('categoria')->filter()->unique()->sort()->values(),
            'contas'     => $todos->pluck('conta')->filter()->unique()->sort()->values(),
            'ultima'     => $this->planejamento->ultimaImportacao($tenantId),
        ]);
    }

    /** Valor em reais digitado na busca ("1.250,00", "96,85", "96.85"), ou null se não for valor. */
    private function valorDigitado(string $texto): ?float
    {
        $t = trim(str_replace('R$', '', $texto));
        if (preg_match('/^\d{1,3}(\.\d{3})+(,\d{1,2})?$/', $t) || preg_match('/^\d+,\d{1,2}$/', $t)) {
            return (float) str_replace(',', '.', str_replace('.', '', $t));
        }

        return preg_match('/^\d+(\.\d{1,2})?$/', $t) ? (float) $t : null;
    }

    public function anual(Request $request)
    {
        $ano = (int) $request->query('ano');
        if ($ano < 2000 || $ano > 2100) {
            $ano = $this->planejamento->mesPadrao(Auth::user()->tenant_id)->year;
        }

        return view('planejamento.anual', [
            'anual'  => $this->planejamento->anual(Auth::user()->tenant_id, $ano),
            'ultima' => $this->planejamento->ultimaImportacao(Auth::user()->tenant_id),
        ]);
    }

    /** Cartões e parceladas; `?cartao=<chave>` filtra um cartão e mostra as faturas dele. */
    public function cartoes(Request $request)
    {
        $tenantId   = Auth::user()->tenant_id;
        $cartoes    = $this->planejamento->cartoes($tenantId);
        $parceladas = $this->planejamento->parceladas($tenantId);

        // Logo e cor do banco de cada cartão (cadastro de Contas), pelo nome.
        $bancos = Banco::where('tenant_id', $tenantId)->get()->keyBy(fn ($b) => $this->planejamento->chaveConta($b->nome));
        $marcas = $cartoes['itens']->mapWithKeys(function ($c) use ($bancos) {
            $b = $bancos->get($this->planejamento->chaveConta($c->banco ?: $c->nome));
            $logo = $b?->logo && is_file(public_path('img/bancos/' . basename($b->logo))) ? 'img/bancos/' . basename($b->logo) : null;

            return [$c->chave => ['logo' => $logo, 'cor' => $b?->cor]];
        });

        $selecionado = $cartoes['itens']->firstWhere('chave', (string) $request->query('cartao'));
        if ($selecionado) {
            $parceladas['itens'] = $parceladas['itens']->where('plan_cartao_id', $selecionado->id)->values();
        }

        return view('planejamento.cartoes', [
            'cartoes'     => $cartoes,
            'parceladas'  => $parceladas,
            'marcas'      => $marcas,
            'selecionado' => $selecionado,
            'faturas'     => $selecionado ? $this->planejamento->faturasDoCartao($tenantId, $selecionado) : [],
            'ultima'      => $this->planejamento->ultimaImportacao($tenantId),
        ]);
    }

    public function dividas()
    {
        $tenantId = Auth::user()->tenant_id;

        return view('planejamento.dividas', [
            'dividas' => $this->planejamento->dividas($tenantId),
            'ultima'  => $this->planejamento->ultimaImportacao($tenantId),
        ]);
    }

    public function metas()
    {
        $tenantId = Auth::user()->tenant_id;

        return view('planejamento.metas', [
            'metas'  => $this->planejamento->metas($tenantId),
            'ultima' => $this->planejamento->ultimaImportacao($tenantId),
        ]);
    }

    public function contasFixas()
    {
        $tenantId = Auth::user()->tenant_id;

        return view('planejamento.contas-fixas', [
            'contas' => $this->planejamento->contasFixas($tenantId),
            'ultima' => $this->planejamento->ultimaImportacao($tenantId),
        ]);
    }

    /** Mês pedido em ?mes=AAAA-MM; nulo quando ausente ou inválido. */
    private function mes(Request $request): ?Carbon
    {
        $mes = (string) $request->query('mes');

        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes)) {
            return Carbon::createFromFormat('!Y-m', $mes);
        }

        return null;
    }
}
