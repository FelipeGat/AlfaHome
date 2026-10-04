<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PlanilhaFonte;
use App\Models\PlanilhaImportacao;
use App\Services\Planejamento\PlanilhaFonteService;
use App\Services\Planejamento\PlanejamentoService;
use App\Services\Planejamento\PlanilhaImportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Planejamento financeiro vindo da planilha — somente leitura, mais o envio
 * do arquivo. Todos os números saem do PlanejamentoService, o mesmo das telas.
 */
class PlanejamentoApiController extends Controller
{
    public function __construct(private PlanejamentoService $planejamento)
    {
    }

    /** GET /api/v1/planejamento/resumo?mes=YYYY-MM */
    public function resumo(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->planejamento->resumoMes($request->user()->tenant_id, $this->mes($request))]);
    }

    /** GET /api/v1/planejamento/anual?ano=YYYY */
    public function anual(Request $request): JsonResponse
    {
        $request->validate(['ano' => ['nullable', 'integer', 'min:2000', 'max:2100']]);

        return response()->json(['data' => $this->planejamento->anual($request->user()->tenant_id, (int) $request->query('ano', now()->year))]);
    }

    /** GET /api/v1/planejamento/lancamentos?mes=YYYY-MM&tipo=receita|despesa */
    public function lancamentos(Request $request): JsonResponse
    {
        $request->validate(['tipo' => ['nullable', 'in:receita,despesa']]);

        return response()->json(['data' => $this->planejamento->lancamentos($request->user()->tenant_id, $this->mes($request), $request->query('tipo'))]);
    }

    /** GET /api/v1/planejamento/cartoes */
    public function cartoes(Request $request): JsonResponse
    {
        $r = $this->planejamento->cartoes($request->user()->tenant_id);

        return response()->json([
            'data' => $r['itens']->map(fn ($c) => [
                'id'                => $c->id,
                'nome'              => $c->nome,
                'banco'             => $c->banco,
                'limite_total'      => $this->valor($c->limite_total),
                'limite_utilizado'  => $this->valor($c->limite_utilizado),
                'limite_disponivel' => $c->limite_disponivel,
                'utilizado_pct'     => $c->utilizado_pct,
                'dia_fechamento'    => $c->dia_fechamento,
                'dia_vencimento'    => $c->dia_vencimento,
                'fatura_atual'      => $this->valor($c->fatura_atual),
                'status_fatura'     => $c->status_fatura,
                'observacao'        => $c->observacao,
            ])->values(),
            'resumo' => $r['resumo'],
        ]);
    }

    /** GET /api/v1/planejamento/parceladas */
    public function parceladas(Request $request): JsonResponse
    {
        $r = $this->planejamento->parceladas($request->user()->tenant_id);

        return response()->json([
            'data' => $r['itens']->map(fn ($p) => [
                'id'                 => $p->id,
                'compra'             => $p->compra,
                'cartao'             => $p->cartao,
                'cartao_cadastrado'  => $p->plan_cartao_id !== null,
                'data'               => $p->data?->format('Y-m-d'),
                'valor_total'        => $this->valor($p->valor_total),
                'parcelas'           => $p->parcelas,
                'parcela_atual'      => $p->parcela_atual,
                'valor_parcela'      => $p->valor_parcela,
                'parcelas_pagas'     => $p->parcelas_pagas,
                'parcelas_restantes' => $p->parcelas_restantes,
                'saldo'              => $p->saldo,
                'proximo_vencimento' => $p->proximo_vencimento?->format('Y-m-d'),
                'observacao'         => $p->observacao,
            ])->values(),
            'resumo' => $r['resumo'],
        ]);
    }

    /** GET /api/v1/planejamento/dividas */
    public function dividas(Request $request): JsonResponse
    {
        $r = $this->planejamento->dividas($request->user()->tenant_id);

        return response()->json([
            'data' => $r['itens']->map(fn ($d) => [
                'id'                => $d->id,
                'nome'              => $d->nome,
                'credor'            => $d->credor,
                'saldo_inicial'     => $this->valor($d->saldo_inicial),
                'saldo_atual'       => $this->valor($d->saldo_atual),
                'amortizado'        => $d->amortizado,
                'amortizado_pct'    => $d->amortizado_pct,
                'taxa_mensal_pct'   => $d->taxa_mensal !== null ? round((float) $d->taxa_mensal * 100, 4) : null,
                'parcela_mensal'    => $this->valor($d->parcela_mensal),
                'dia_vencimento'    => $d->dia_vencimento,
                'status'            => $d->status,
                'prioridade'        => $d->prioridade,
                'previsao_quitacao' => $d->previsao_quitacao?->format('Y-m-d'),
                'observacao'        => $d->observacao,
            ])->values(),
            'resumo' => $r['resumo'],
        ]);
    }

    /** GET /api/v1/planejamento/metas */
    public function metas(Request $request): JsonResponse
    {
        $r = $this->planejamento->metas($request->user()->tenant_id);

        return response()->json([
            'data' => $r['itens']->map(fn ($m) => [
                'id'            => $m->id,
                'nome'          => $m->nome,
                'objetivo'      => $m->objetivo,
                'valor_alvo'    => $this->valor($m->valor_alvo),
                'valor_atual'   => $this->valor($m->valor_atual),
                'falta'         => $m->falta,
                'concluido_pct' => $m->concluido_pct,
                'prazo'         => $m->prazo?->format('Y-m-d'),
                'prioridade'    => $m->prioridade,
                'aporte_mensal' => $this->valor($m->aporte_mensal),
                'observacao'    => $m->observacao,
            ])->values(),
            'resumo' => $r['resumo'],
        ]);
    }

    /** GET /api/v1/planejamento/contas-fixas */
    public function contasFixas(Request $request): JsonResponse
    {
        $r = $this->planejamento->contasFixas($request->user()->tenant_id);

        return response()->json([
            'data' => $r['itens']->map(fn ($c) => [
                'id'              => $c->id,
                'conta'           => $c->conta,
                'categoria'       => $c->categoria,
                'dia_vencimento'  => $c->dia_vencimento,
                'valor_previsto'  => $this->valor($c->valor_previsto),
                'valor_realizado' => $this->valor($c->valor_realizado),
                'forma'           => $c->forma,
                'recorrente'      => $c->recorrente,
                'status'          => $c->status,
                'mes_inicial'     => $c->mes_inicial?->format('Y-m-d'),
                'observacao'      => $c->observacao,
            ])->values(),
            'resumo' => $r['resumo'],
        ]);
    }

    /** GET /api/v1/planejamento/importacoes */
    public function importacoes(Request $request): JsonResponse
    {
        $importacoes = PlanilhaImportacao::where('tenant_id', $request->user()->tenant_id)
            ->with('user:id,name')->latest('id')->limit(20)->get();

        return response()->json(['data' => $importacoes->map(fn ($i) => $this->importacao($i))->values()]);
    }

    /** POST /api/v1/planejamento/importar (multipart: arquivo) */
    public function importar(Request $request, PlanilhaImportService $servico): JsonResponse
    {
        if ($request->user()->role !== 'master') {
            return response()->json(['message' => 'Somente o dono da conta pode importar a planilha.'], Response::HTTP_FORBIDDEN);
        }

        $request->validate(
            ['arquivo' => ['required', 'file', 'max:5120']],
            [
                'arquivo.required' => 'Envie o arquivo da planilha.',
                'arquivo.file'     => 'Envie o arquivo da planilha.',
                'arquivo.max'      => 'A planilha deve ter no máximo 5 MB.',
            ]
        );

        $arquivo    = $request->file('arquivo');
        $importacao = $servico->importar($request->user()->tenant_id, $request->user()->id, $arquivo->getRealPath(), $arquivo->getClientOriginalName());

        if ($importacao->status === PlanilhaImportacao::REJEITADA) {
            return response()->json([
                'message' => 'A planilha foi rejeitada e nada foi alterado.',
                'data'    => $this->importacao($importacao),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json(['data' => $this->importacao($importacao)]);
    }

    /** GET /api/v1/planejamento/fonte */
    public function fonte(Request $request): JsonResponse
    {
        $fonte = PlanilhaFonte::where('tenant_id', $request->user()->tenant_id)->first();

        return response()->json(['data' => [
            'configurada'   => $fonte !== null,
            'verificada_em' => $fonte?->verificada_em?->toIso8601String(),
            'status'        => $fonte?->status,
            'erro'          => $fonte?->erro,
        ]]);
    }

    /** POST /api/v1/planejamento/analisar */
    public function analisar(Request $request, PlanilhaFonteService $servico): JsonResponse
    {
        if ($request->user()->role !== 'master') {
            return response()->json(['message' => 'Somente o dono da conta pode analisar a planilha.'], Response::HTTP_FORBIDDEN);
        }

        $fonte = PlanilhaFonte::where('tenant_id', $request->user()->tenant_id)->first();
        if (! $fonte) {
            return response()->json(['message' => 'O link da planilha no OneDrive ainda não foi configurado.'], Response::HTTP_CONFLICT);
        }

        $r = $servico->analisar($fonte, $request->user()->id);

        if ($r['erro']) {
            return response()->json(['message' => $r['erro']], Response::HTTP_BAD_GATEWAY);
        }

        if ($r['importacao']->status === PlanilhaImportacao::REJEITADA) {
            return response()->json([
                'message' => 'A planilha foi rejeitada e nada foi alterado.',
                'data'    => $this->importacao($r['importacao']),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json(['data' => $this->importacao($r['importacao'])]);
    }

    private function importacao(PlanilhaImportacao $i): array
    {
        return [
            'id'      => $i->id,
            'em'      => $i->created_at->toIso8601String(),
            'arquivo' => $i->arquivo_nome,
            'status'  => $i->status,
            'usuario' => $i->user?->name,
            'resumo'  => $i->resumo,
            'avisos'  => $i->avisos ?? [],
            'erros'   => $i->erros ?? [],
        ];
    }

    private function mes(Request $request): Carbon
    {
        $request->validate(['mes' => ['nullable', 'date_format:Y-m']], ['mes.date_format' => 'Informe o mês no formato AAAA-MM.']);

        // Sem mês pedido, o mesmo padrão das telas web: o corrente se já tem
        // lançamento da planilha, senão o último que tiver.
        return $request->filled('mes')
            ? Carbon::createFromFormat('!Y-m', $request->query('mes'))
            : $this->planejamento->mesPadrao($request->user()->tenant_id);
    }

    private function valor(?string $decimal): ?float
    {
        return $decimal !== null ? (float) $decimal : null;
    }
}
