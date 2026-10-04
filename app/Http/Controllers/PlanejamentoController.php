<?php

namespace App\Http\Controllers;

use App\Services\Planejamento\PlanejamentoService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

    public function cartoes()
    {
        $tenantId = Auth::user()->tenant_id;

        return view('planejamento.cartoes', [
            'cartoes'    => $this->planejamento->cartoes($tenantId),
            'parceladas' => $this->planejamento->parceladas($tenantId),
            'ultima'     => $this->planejamento->ultimaImportacao($tenantId),
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
