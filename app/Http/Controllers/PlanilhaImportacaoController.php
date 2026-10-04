<?php

namespace App\Http\Controllers;

use App\Models\PlanilhaFonte;
use App\Models\PlanilhaImportacao;
use App\Services\Planejamento\PlanilhaFonteService;
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
            'fonte'       => PlanilhaFonte::where('tenant_id', Auth::user()->tenant_id)->first(),
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

    /** Guarda o link do OneDrive — só se a planilha puder ser lida por ele. */
    public function salvarFonte(Request $request, PlanilhaFonteService $servico)
    {
        $this->somenteDono();

        $request->validate(
            ['url' => ['required', 'string', 'max:2000']],
            ['url.required' => 'Cole o link de compartilhamento da planilha.', 'url.max' => 'O link é longo demais.']
        );

        $r = $servico->configurar(Auth::user()->tenant_id, Auth::id(), $request->input('url'));

        if ($r['erro']) {
            return redirect()->route('planejamento.importar')->withErrors(['url' => $r['erro']]);
        }

        return redirect()->route('planejamento.importar')->with(...$this->mensagem($r['importacao'], 'Link salvo. '));
    }

    public function removerFonte()
    {
        $this->somenteDono();

        PlanilhaFonte::where('tenant_id', Auth::user()->tenant_id)->get()->each->delete();

        return redirect()->route('planejamento.importar')->with('success', 'Link removido. A planilha deixa de ser analisada automaticamente; os dados já importados continuam.');
    }

    /** Botão Analisar: busca a versão atual da planilha no OneDrive. */
    public function analisar(PlanilhaFonteService $servico)
    {
        $this->somenteDono();

        $fonte = PlanilhaFonte::where('tenant_id', Auth::user()->tenant_id)->first();
        if (! $fonte) {
            return redirect()->route('planejamento.importar')->with('error', 'Configure primeiro o link da planilha no OneDrive.');
        }

        $r = $servico->analisar($fonte, Auth::id());

        return $r['erro']
            ? redirect()->route('planejamento.importar')->with('error', $r['erro'])
            : redirect()->route('planejamento.importar')->with(...$this->mensagem($r['importacao']));
    }

    /** @return array{0: string, 1: string} chave e texto do aviso */
    private function mensagem(PlanilhaImportacao $importacao, string $prefixo = ''): array
    {
        return match ($importacao->status) {
            PlanilhaImportacao::REJEITADA      => ['error', $prefixo . 'A planilha foi rejeitada e nada foi alterado. Veja os motivos abaixo.'],
            PlanilhaImportacao::SEM_ALTERACOES => ['success', $prefixo . 'A planilha já estava em dia: nenhuma alteração.'],
            default                            => ['success', $prefixo . 'Planilha analisada: veja abaixo o que mudou.'],
        };
    }

    private function somenteDono(): void
    {
        abort_unless(Auth::user()->role === 'master', 403, 'Somente o dono da conta pode importar a planilha.');
    }
}
