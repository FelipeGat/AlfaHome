<?php

namespace App\Http\Controllers;

use App\Models\Banco;
use App\Models\Investimento;
use App\Models\InvestimentoRendimento;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class InvestimentoController extends Controller
{
    public function index()
    {
        $tenantId = Auth::user()->tenant_id;

        $investimentos = Investimento::with(['banco', 'rendimentos'])
            ->orderByDesc('data_aporte')
            ->get();

        // Calcula stats e dados para gráficos
        foreach ($investimentos as $inv) {
            [$labels, $valores] = $this->buildChartData($inv);
            $inv->chart_labels  = $labels;
            $inv->chart_valores = $valores;
            $inv->valor_atual_calc  = count($valores) ? (float) end($valores) : (float) $inv->valor_aportado;
            $inv->ganho_reais       = $inv->valor_atual_calc - (float) $inv->valor_aportado;
            $inv->ganho_percentual  = (float) $inv->valor_aportado > 0
                ? ($inv->ganho_reais / (float) $inv->valor_aportado) * 100
                : 0;
        }

        $bancos        = Banco::orderBy('nome')->get();
        $totalAportado = $investimentos->sum(fn($i) => (float) $i->valor_aportado);
        $totalAtual    = $investimentos->sum('valor_atual_calc');
        $ganhoTotal    = $totalAtual - $totalAportado;
        $ganhoPercent  = $totalAportado > 0 ? ($ganhoTotal / $totalAportado) * 100 : 0;

        return view('investimentos.index', compact(
            'investimentos', 'bancos',
            'totalAportado', 'totalAtual', 'ganhoTotal', 'ganhoPercent'
        ));
    }

    public function store(Request $request)
    {
        $userId   = Auth::id();
        $tenantId = Auth::user()->tenant_id;

        $request->validate([
            'nome_ativo'        => 'required|string|max:150',
            'tipo_investimento' => 'required|string|max:100',
            'data_aporte'       => 'required|date',
            'valor_aportado'    => 'required|numeric|min:0.01',
            'quantidade_cotas'  => 'nullable|numeric|min:0',
            'percentual_mensal' => 'nullable|numeric|min:0|max:100',
            'percentual_anual'  => 'nullable|numeric|min:0|max:100',
            'banco_id'          => ['nullable', Rule::exists('bancos', 'id')->where('tenant_id', $tenantId)],
        ]);

        Investimento::create([
            'user_id'           => $userId,
            'banco_id'          => $request->banco_id,
            'nome_ativo'        => $request->nome_ativo,
            'tipo_investimento' => $request->tipo_investimento,
            'data_aporte'       => $request->data_aporte,
            'valor_aportado'    => $request->valor_aportado,
            'quantidade_cotas'  => $request->quantidade_cotas ?? 0,
            'percentual_mensal' => $request->percentual_mensal,
            'percentual_anual'  => $request->percentual_anual,
            'observacoes'       => $request->observacoes,
        ]);

        return back()->with('success', 'Investimento registrado com sucesso!');
    }

    public function update(Request $request, Investimento $investimento)
    {
        $this->authorize('update', $investimento);

        $tenantId = Auth::user()->tenant_id;

        $request->validate([
            'nome_ativo'        => 'required|string|max:150',
            'tipo_investimento' => 'required|string|max:100',
            'data_aporte'       => 'required|date',
            'valor_aportado'    => 'required|numeric|min:0.01',
            'quantidade_cotas'  => 'nullable|numeric|min:0',
            'percentual_mensal' => 'nullable|numeric|min:0|max:100',
            'percentual_anual'  => 'nullable|numeric|min:0|max:100',
            'banco_id'          => ['nullable', Rule::exists('bancos', 'id')->where('tenant_id', $tenantId)],
        ]);

        $investimento->update($request->only([
            'nome_ativo', 'tipo_investimento', 'data_aporte',
            'valor_aportado', 'quantidade_cotas', 'banco_id',
            'percentual_mensal', 'percentual_anual', 'observacoes',
        ]));

        return back()->with('success', 'Investimento atualizado com sucesso!');
    }

    public function destroy(Investimento $investimento)
    {
        $this->authorize('delete', $investimento);
        $investimento->delete();

        return back()->with('success', 'Investimento excluído com sucesso!');
    }

    /** Registra um novo ponto de rendimento */
    public function storeRendimento(Request $request, Investimento $investimento)
    {
        $this->authorize('update', $investimento);

        $tenantId = Auth::user()->tenant_id;

        $request->validate([
            'data'        => 'required|date',
            'tipo_entrada' => 'required|in:valor,percentual',
            'valor_atual'  => 'required_if:tipo_entrada,valor|nullable|numeric|min:0',
            'percentual'   => 'required_if:tipo_entrada,percentual|nullable|numeric|min:-100|max:1000',
            'observacoes'  => 'nullable|string|max:500',
        ]);

        // Calcula valor_atual a partir do percentual se necessário
        if ($request->tipo_entrada === 'percentual') {
            // Base: último rendimento ou valor aportado
            $base = (float) ($investimento->rendimentos()->latest('data')->value('valor_atual')
                ?? $investimento->valor_aportado);
            $valorAtual = $base * (1 + (float) $request->percentual / 100);
        } else {
            $valorAtual = (float) $request->valor_atual;
        }

        InvestimentoRendimento::create([
            'investimento_id' => $investimento->id,
            'tenant_id'       => $tenantId,
            'data'            => $request->data,
            'valor_atual'     => $valorAtual,
            'observacoes'     => $request->observacoes,
        ]);

        return back()->with('success', 'Rendimento registrado com sucesso!');
    }

    /** Exclui um ponto de rendimento */
    public function destroyRendimento(Investimento $investimento, InvestimentoRendimento $rendimento)
    {
        $this->authorize('update', $investimento);
        abort_unless($rendimento->investimento_id === $investimento->id, 404);
        $rendimento->delete();

        return back()->with('success', 'Registro excluído.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Monta arrays de labels e valores para o gráfico de um investimento.
     * Se não houver rendimentos mas houver taxa mensal, projeta até hoje.
     */
    private function buildChartData(Investimento $inv): array
    {
        $labels  = [$inv->data_aporte->format('d/m/Y')];
        $valores = [(float) $inv->valor_aportado];

        if ($inv->rendimentos->isNotEmpty()) {
            foreach ($inv->rendimentos as $rend) {
                $labels[]  = $rend->data->format('d/m/Y');
                $valores[] = (float) $rend->valor_atual;
            }
            return [$labels, $valores];
        }

        // Sem rendimentos: projeta com taxa mensal se configurada
        if ($inv->percentual_mensal && $inv->percentual_mensal > 0) {
            $taxa  = (float) $inv->percentual_mensal / 100;
            $atual = Carbon::now()->startOfMonth();
            $mes   = $inv->data_aporte->copy()->addMonthNoOverflow()->startOfMonth();
            $valor = (float) $inv->valor_aportado;

            while ($mes->lte($atual)) {
                $valor    *= (1 + $taxa);
                $labels[]  = $mes->format('m/Y');
                $valores[] = round($valor, 2);
                $mes->addMonthNoOverflow();
            }
        }

        return [$labels, $valores];
    }
}
