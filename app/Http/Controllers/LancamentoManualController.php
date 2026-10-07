<?php

namespace App\Http\Controllers;

use App\Http\Requests\Api\V1\PagarFaturaRequest;
use App\Http\Requests\Api\V1\StoreDespesaRequest;
use App\Http\Requests\Api\V1\StoreReceitaRequest;
use App\Http\Requests\Api\V1\StoreTransferenciaRequest;
use App\Http\Requests\Api\V1\UpdateDespesaRequest;
use App\Http\Requests\Api\V1\UpdateReceitaRequest;
use App\Http\Requests\Api\V1\UpdateTransferenciaRequest;
use App\Models\Banco;
use App\Models\Despesa;
use App\Models\Receita;
use App\Models\Transferencia;
use App\Services\Lancamentos\LancamentoService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "+ Lançar" do site: as mesmas validações (FormRequests da API v1) e a mesma
 * regra (LancamentoService) do app. Responde JSON para o formulário; a
 * mensagem de sucesso também fica na sessão para a tela recarregada mostrar.
 */
class LancamentoManualController extends Controller
{
    public function __construct(private LancamentoService $lancamentos) {}

    public function criarSaida(StoreDespesaRequest $request): JsonResponse
    {
        $r = $this->lancamentos->criarSaida($request->validated(), $request->user());

        return $this->ok($r['total'] > 1 ? "{$r['total']} saídas registradas." : 'Saída registrada.', $r['aviso']);
    }

    public function criarEntrada(StoreReceitaRequest $request): JsonResponse
    {
        $total = $this->lancamentos->criarEntrada($request->validated(), $request->user());

        return $this->ok($total > 1 ? "{$total} entradas registradas." : 'Entrada registrada.');
    }

    public function criarTransferencia(StoreTransferenciaRequest $request): JsonResponse
    {
        $this->lancamentos->criarTransferencia($request->validated(), $request->user());

        return $this->ok('Transferência registrada.');
    }

    /** Dados de um lançamento do sistema para o formulário de edição. */
    public function mostrar(Request $request, string $tipo, int $id): JsonResponse
    {
        $l = $this->achar($request, $tipo, $id);
        $serie = $l->grupo_recorrencia_id ?? null;

        return response()->json(['tipo' => $tipo, 'id' => $l->id, 'serie' => $serie !== null] + match ($tipo) {
            'saida' => [
                'valor' => (float) $l->valor, 'data' => $l->data_compra->format('Y-m-d'), 'pago_em' => $l->data_pagamento?->format('Y-m-d'),
                'categoria_id' => $l->categoria_id, 'conta_id' => $l->forma_pagamento, 'forma' => $l->tipo_pagamento, 'descricao' => $l->observacoes,
            ],
            'entrada' => [
                'valor' => (float) $l->valor, 'data' => $l->data_prevista_recebimento->format('Y-m-d'), 'pago_em' => $l->data_recebimento?->format('Y-m-d'),
                'categoria_id' => $l->categoria_id, 'conta_id' => $l->forma_recebimento, 'forma' => $l->tipo_pagamento, 'descricao' => $l->observacoes,
            ],
            'transferencia' => [
                'valor' => (float) $l->valor, 'data' => $l->data->format('Y-m-d'), 'origem_id' => $l->origem_id, 'destino_id' => $l->destino_id, 'descricao' => $l->observacao,
            ],
        });
    }

    public function atualizarSaida(UpdateDespesaRequest $request, int $id): JsonResponse
    {
        $this->lancamentos->atualizarSaida($this->achar($request, 'saida', $id), $request->validated());

        return $this->ok('Saída atualizada.');
    }

    public function atualizarEntrada(UpdateReceitaRequest $request, int $id): JsonResponse
    {
        $this->lancamentos->atualizarEntrada($this->achar($request, 'entrada', $id), $request->validated());

        return $this->ok('Entrada atualizada.');
    }

    public function atualizarTransferencia(UpdateTransferenciaRequest $request, int $id): JsonResponse
    {
        $this->achar($request, 'transferencia', $id)->update($request->validated());

        return $this->ok('Transferência atualizada.');
    }

    /** Marca pago/recebido hoje (ou na `data` enviada). */
    public function pagar(Request $request, string $tipo, int $id): JsonResponse
    {
        abort_unless(in_array($tipo, ['saida', 'entrada'], true), 404);
        $request->validate(['data' => ['nullable', 'date']]);
        $this->permitir($request, $tipo, 'editar');
        $this->lancamentos->marcarPago($this->achar($request, $tipo, $id), $request->filled('data') ? Carbon::parse($request->input('data')) : now());

        return $this->ok($tipo === 'saida' ? 'Marcada como paga.' : 'Marcada como recebida.');
    }

    public function excluir(Request $request, string $tipo, int $id): JsonResponse
    {
        $request->validate(['escopo' => ['nullable', 'in:apenas_esta,esta_e_futuras']]);
        $this->permitir($request, $tipo, 'excluir');
        $l = $this->achar($request, $tipo, $id);

        if ($l instanceof Transferencia) {
            $l->delete();

            return $this->ok('Transferência excluída.');
        }
        $n = $this->lancamentos->excluir($l, (string) $request->input('escopo', 'apenas_esta'));

        return $this->ok($n > 1 ? "{$n} lançamentos excluídos." : 'Lançamento excluído.');
    }

    public function pagarFatura(PagarFaturaRequest $request, int $banco): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $cartao   = Banco::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('tem_cartao_credito', true)->findOrFail($banco);
        $conta    = Banco::withoutGlobalScopes()->where('tenant_id', $tenantId)->findOrFail($request->validated('conta_id'));
        $data     = $request->validated('data') ? Carbon::parse($request->validated('data')) : now();

        $n = $this->lancamentos->pagarFatura($cartao, Carbon::parse($request->validated('vencimento')), $conta, $data);
        if ($n === 0) {
            return response()->json(['message' => 'Esta fatura não tem compras pendentes.'], 422);
        }

        return $this->ok('Fatura paga.');
    }

    // ─── Apoio ───────────────────────────────────────────────────────────────

    /** Lançamento do sistema desta família; de outra família ou inexistente = 404. */
    private function achar(Request $request, string $tipo, int $id): Despesa|Receita|Transferencia
    {
        $modelo = match ($tipo) {
            'saida'         => Despesa::class,
            'entrada'       => Receita::class,
            'transferencia' => Transferencia::class,
            default         => abort(404),
        };

        return $modelo::withoutGlobalScope('tenant')->where('tenant_id', $request->user()->tenant_id)->findOrFail($id);
    }

    private function permitir(Request $request, string $tipo, string $acao): void
    {
        $modulo = ['saida' => 'despesas', 'entrada' => 'receitas', 'transferencia' => 'transferencias'][$tipo] ?? abort(404);
        abort_unless($request->user()->temPermissao($modulo, $acao), 403, 'Sem permissão.');
    }

    private function ok(string $mensagem, ?string $aviso = null): JsonResponse
    {
        session()->flash('success', $aviso ? "{$mensagem} {$aviso}" : $mensagem);

        return response()->json(['message' => $mensagem, 'aviso' => $aviso]);
    }
}
