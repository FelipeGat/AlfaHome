<?php

namespace App\Http\Controllers;

use App\Models\Banco;
use App\Models\Familiar;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BancoController extends Controller
{
    public function index()
    {
        $tenantId = Auth::user()->tenant_id;

        $bancos = Banco::with('titular')
            ->orderBy('nome')
            ->get()
            ->each(function ($banco) use ($tenantId) {
                if (! $banco->tem_cartao_credito) {
                    return;
                }

                // Recalcula a fatura aberta a partir das despesas em aberto
                // (não pagas) que apontam para este cartão.
                // Inclui tipo_pagamento = 'credito' OU NULL (registros sem tipo definido)
                // — exclui pix/débito/dinheiro/transferencia/boleto.
                $faturaAtual = (float) DB::table('despesas')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->where('forma_pagamento', $banco->id)
                    ->where(function ($q) {
                        $q->where('tipo_pagamento', 'credito')
                          ->orWhereNull('tipo_pagamento');
                    })
                    ->whereNull('data_pagamento')
                    ->sum('valor');

                // Persiste no DB quando estiver fora de sincronia. Sem isso,
                // o valor exibido (recalculado em memória) diverge do valor
                // gravado, e qualquer caminho que leia `saldo_cartao` do DB
                // (ex.: API mobile, relatórios) vê um valor antigo.
                if ((float) $banco->saldo_cartao !== $faturaAtual) {
                    $banco->saldo_cartao = $faturaAtual;
                    $banco->saveQuietly(); // sem disparar observers (loop e custo)
                }
            });

        $familiares = Familiar::orderBy('nome')->get();

        return view('bancos.index', compact('bancos', 'familiares'));
    }

    public function store(Request $request)
    {
        $userId   = Auth::id();
        $tenantId = Auth::user()->tenant_id;

        // Verificar limite de contas bancárias do plano
        $tenant = Tenant::with('plano')->find($tenantId);
        if ($tenant && $tenant->limiteBancosAtingido()) {
            return back()->withErrors([
                'nome' => 'Limite de contas bancárias do plano atingido. Faça upgrade do plano para adicionar mais contas.',
            ]);
        }

        $request->validate([
            'nome'               => 'required|string|max:150',
            'tem_conta_corrente' => 'nullable|boolean',
            'tem_poupanca'       => 'nullable|boolean',
            'tem_cartao_credito' => 'nullable|boolean',
            'eh_dinheiro'        => 'nullable|boolean',
            'saldo'              => 'nullable|numeric',
            'saldo_poupanca'     => 'nullable|numeric',
            'cheque_especial'    => 'nullable|numeric|min:0',
            'limite_cartao'      => 'nullable|numeric|min:0',
            'dia_vencimento_cartao' => 'nullable|integer|min:1|max:31',
            'dia_fechamento_cartao' => 'nullable|integer|min:1|max:31',
            'titular_id'         => ['nullable', Rule::exists('familiares', 'id')->where('tenant_id', $tenantId)],
            'codigo_banco'       => 'nullable|string|max:10',
            'agencia'            => 'nullable|string|max:20',
            'conta'              => 'nullable|string|max:30',
            'logo'               => 'nullable|string|max:100',
            'cor'                => 'nullable|string|max:7',
        ]);

        Banco::create([
            'user_id'            => $userId,
            'titular_id'         => $request->titular_id,
            'nome'               => $request->nome,
            'tem_conta_corrente' => $request->boolean('tem_conta_corrente'),
            'tem_poupanca'       => $request->boolean('tem_poupanca'),
            'tem_cartao_credito' => $request->boolean('tem_cartao_credito'),
            'eh_dinheiro'        => $request->boolean('eh_dinheiro'),
            'codigo_banco'       => $request->codigo_banco,
            'agencia'            => $request->agencia,
            'conta'              => $request->conta,
            'saldo'              => $request->saldo ?? 0,
            'saldo_poupanca'     => $request->saldo_poupanca ?? 0,
            'cheque_especial'    => $request->cheque_especial ?? 0,
            'saldo_cheque'       => 0,
            'limite_cartao'      => $request->limite_cartao ?? 0,
            'saldo_cartao'       => 0,
            'dia_vencimento_cartao' => $request->dia_vencimento_cartao,
            'dia_fechamento_cartao' => $request->dia_fechamento_cartao,
            'logo'               => $request->logo,
            'cor'                => $request->cor,
        ]);

        return back()->with('success', 'Conta bancária criada com sucesso!');
    }

    public function update(Request $request, Banco $banco)
    {
        $this->authorize('update', $banco);

        $tenantId = Auth::user()->tenant_id;

        // NOTA: saldo e saldo_poupanca NÃO são editáveis por este endpoint.
        // Eles são gerenciados automaticamente pelos observers de despesa/
        // receita/transferência. Para ajuste manual, use os endpoints
        // dedicados POST /bancos/{id}/ajustar-saldo e /ajustar-saldo-poupanca.
        // (Aceitar `saldo` aqui causava perda de ajustes feitos pelos
        // observers entre o GET da tela e o POST do form — race condition.)
        $request->validate([
            'nome'               => 'required|string|max:150',
            'tem_conta_corrente' => 'nullable|boolean',
            'tem_poupanca'       => 'nullable|boolean',
            'tem_cartao_credito' => 'nullable|boolean',
            'eh_dinheiro'        => 'nullable|boolean',
            'cheque_especial'    => 'nullable|numeric|min:0',
            'limite_cartao'      => 'nullable|numeric|min:0',
            'dia_vencimento_cartao' => 'nullable|integer|min:1|max:31',
            'dia_fechamento_cartao' => 'nullable|integer|min:1|max:31',
            'titular_id'         => ['nullable', Rule::exists('familiares', 'id')->where('tenant_id', $tenantId)],
            'codigo_banco'       => 'nullable|string|max:10',
            'agencia'            => 'nullable|string|max:20',
            'conta'              => 'nullable|string|max:30',
            'logo'               => 'nullable|string|max:100',
            'cor'                => 'nullable|string|max:7',
        ]);

        $banco->update([
            'titular_id'         => $request->titular_id,
            'nome'               => $request->nome,
            'tem_conta_corrente' => $request->boolean('tem_conta_corrente'),
            'tem_poupanca'       => $request->boolean('tem_poupanca'),
            'tem_cartao_credito' => $request->boolean('tem_cartao_credito'),
            'eh_dinheiro'        => $request->boolean('eh_dinheiro'),
            'codigo_banco'       => $request->codigo_banco,
            'agencia'            => $request->agencia,
            'conta'              => $request->conta,
            'cheque_especial'    => $request->cheque_especial ?? 0,
            'limite_cartao'      => $request->limite_cartao ?? 0,
            'dia_vencimento_cartao' => $request->dia_vencimento_cartao,
            'dia_fechamento_cartao' => $request->dia_fechamento_cartao,
            'logo'               => $request->logo,
            'cor'                => $request->cor,
        ]);

        return back()->with('success', 'Conta atualizada com sucesso!');
    }

    public function ajustarSaldo(Request $request, Banco $banco)
    {
        $this->authorize('update', $banco);

        $request->validate(['saldo' => 'required|numeric']);
        $banco->update(['saldo' => $request->saldo]);

        return back()->with('success', 'Saldo ajustado com sucesso!');
    }

    public function ajustarSaldoPoupanca(Request $request, Banco $banco)
    {
        $this->authorize('update', $banco);

        $request->validate(['saldo_poupanca' => 'required|numeric']);
        $banco->update(['saldo_poupanca' => $request->saldo_poupanca]);

        return back()->with('success', 'Saldo da poupança ajustado com sucesso!');
    }

    // O método `ajustarSaldoCartao` foi removido propositalmente.
    // `bancos.saldo_cartao` representa a fatura aberta — é totalmente
    // derivado das despesas de crédito em aberto e é mantido em sincronia
    // pelo DespesaObserver (criar/editar/pagar/excluir despesa de crédito)
    // e pelo `index()` (que persiste o recálculo). Ajustar manualmente
    // levaria a valor inconsistente (sobrescrito na próxima navegação) —
    // melhor não oferecer esse caminho.

    public function destroy(Banco $banco)
    {
        $this->authorize('delete', $banco);

        $tenantId = Auth::user()->tenant_id;

        // Mapeamento das FKs (migrations 2024_01_01_050/060/070):
        //   - despesas.forma_pagamento, receitas.forma_recebimento e
        //     investimentos.banco_id usam onDelete('set null') — o DB
        //     apenas zera a referência, sem FK violation. Checagem só
        //     pelo uso ATIVO (sem withTrashed) para permitir limpeza
        //     do catálogo quando só restam despesas soft-deletadas.
        //   - transferencias.origem_id/destino_id são RESTRICT (default):
        //     soft-deletado ainda tem FK viva apontando para bancos.id
        //     e gera FK violation real. Por isso só transferencias usa
        //     withTrashed().
        $emUso = [
            'despesas'       => \App\Models\Despesa::where('tenant_id', $tenantId)
                ->where('forma_pagamento', $banco->id)->exists(),
            'receitas'       => \App\Models\Receita::where('tenant_id', $tenantId)
                ->where('forma_recebimento', $banco->id)->exists(),
            'investimentos'  => \App\Models\Investimento::where('tenant_id', $tenantId)
                ->where('banco_id', $banco->id)->exists(),
            'transferencias' => \App\Models\Transferencia::withTrashed()
                ->where('tenant_id', $tenantId)
                ->where(function ($q) use ($banco) {
                    $q->where('origem_id', $banco->id)
                      ->orWhere('destino_id', $banco->id);
                })->exists(),
        ];

        $bloqueios = array_keys(array_filter($emUso));
        if (! empty($bloqueios)) {
            return back()->withErrors([
                'banco' => 'Conta em uso e não pode ser excluída. Remova primeiro: '
                    . implode(', ', $bloqueios) . '.',
            ]);
        }

        $banco->delete();

        return back()->with('success', 'Conta excluída com sucesso!');
    }
}
