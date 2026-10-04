<?php

namespace App\Http\Controllers;

use App\Models\PlanilhaImportacao;
use App\Services\Planejamento\PlanilhaImportService;
use App\Services\Planejamento\PlanilhaParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PlanilhaImportacaoController extends Controller
{
    public function index()
    {
        $this->somenteDono();

        return view('planejamento.importar', [
            'importacoes' => PlanilhaImportacao::where('tenant_id', Auth::user()->tenant_id)
                ->with('user:id,name')->latest('id')->limit(20)->get(),
            'abas'        => PlanilhaParser::abas(),
        ]);
    }

    public function store(Request $request, PlanilhaImportService $servico)
    {
        $this->somenteDono();

        $request->validate(
            ['arquivo' => ['required', 'file', 'max:5120']],
            [
                'arquivo.required' => 'Escolha o arquivo da planilha.',
                'arquivo.file'     => 'Escolha o arquivo da planilha.',
                'arquivo.max'      => 'A planilha deve ter no máximo 5 MB.',
            ]
        );

        $arquivo    = $request->file('arquivo');
        $importacao = $servico->importar(Auth::user()->tenant_id, Auth::id(), $arquivo->getRealPath(), $arquivo->getClientOriginalName());

        $mensagem = match ($importacao->status) {
            PlanilhaImportacao::REJEITADA      => ['error', 'A planilha foi rejeitada e nada foi alterado. Veja os motivos abaixo.'],
            PlanilhaImportacao::SEM_ALTERACOES => ['success', 'A planilha já estava em dia: nenhuma alteração.'],
            default                            => ['success', 'Planilha importada.'],
        };

        return redirect()->route('planejamento.importar')->with($mensagem[0], $mensagem[1]);
    }

    private function somenteDono(): void
    {
        abort_unless(Auth::user()->role === 'master', 403, 'Somente o dono da conta pode importar a planilha.');
    }
}
