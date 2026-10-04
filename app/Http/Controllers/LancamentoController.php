<?php

namespace App\Http\Controllers;

use App\Models\Banco;
use App\Models\Categoria;
use App\Models\Despesa;
use App\Models\Familiar;
use App\Models\Fornecedor;
use App\Models\Receita;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

class LancamentoController extends Controller
{
    // ─── Index: Extrato ──────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $inicio       = $request->get('inicio', now()->startOfMonth()->format('Y-m-d'));
        $fim          = $request->get('fim', now()->endOfMonth()->format('Y-m-d'));
        $bancoId      = $request->get('banco_id');
        $tipo         = $request->get('tipo', 'todos'); // todos | debito | credito
        $familiarId   = $request->get('familiar_id') ? (int) $request->get('familiar_id') : null;
        $tipoPagamento = $request->get('tipo_pagamento') ?: null;

        // ── Despesas (débitos) ────────────────────────────────────────────
        $despesas = Despesa::with(['categoria', 'fornecedor', 'banco'])
            ->whereBetween('data_compra', [$inicio, $fim])
            ->when($bancoId,       fn($q) => $q->where('forma_pagamento', $bancoId))
            ->when($familiarId,    fn($q) => $q->where(function ($sub) use ($familiarId) {
                $sub->where('quem_comprou', $familiarId)->orWhereNull('quem_comprou');
            }))
            ->when($tipoPagamento, fn($q) => $q->where('tipo_pagamento', $tipoPagamento))
            ->when($tipo === 'debito', fn($q) => $q)
            ->orderBy('data_compra')->orderBy('id')
            ->get()
            ->map(fn($d) => [
                'id'          => $d->id,
                'model'       => 'despesa',
                'data'        => $d->data_compra,
                'data_fmt'    => $d->data_compra->format('d/m/Y'),
                'descricao'   => $d->observacoes ?? $d->fornecedor?->nome ?? '—',
                'categoria'   => $d->categoria?->nome,
                'categoria_icone' => $d->categoria?->icone ?? 'fa-tag',
                'conta'       => $d->banco?->nome ?? '—',
                'conta_cor'   => $d->banco?->cor ?? '#64748b',
                'valor'       => (float) $d->valor,
                'tipo'        => 'debito',
                'status'      => $d->status,
                'pago'        => !is_null($d->data_pagamento),
                'origem'      => $d->origem ?? 'manual',
                'numero_doc'  => $d->numero_documento,
                'recorrente'  => $d->recorrente,
            ]);

        // ── Receitas (créditos) ───────────────────────────────────────────
        $receitas = Receita::with(['categoria', 'banco'])
            ->whereBetween('data_prevista_recebimento', [$inicio, $fim])
            ->when($bancoId,       fn($q) => $q->where('forma_recebimento', $bancoId))
            ->when($familiarId,    fn($q) => $q->where(function ($sub) use ($familiarId) {
                $sub->where('quem_recebeu', $familiarId)->orWhereNull('quem_recebeu');
            }))
            ->when($tipoPagamento, fn($q) => $q->where('tipo_pagamento', $tipoPagamento))
            ->orderBy('data_prevista_recebimento')->orderBy('id')
            ->get()
            ->map(fn($r) => [
                'id'          => $r->id,
                'model'       => 'receita',
                'data'        => $r->data_prevista_recebimento,
                'data_fmt'    => $r->data_prevista_recebimento->format('d/m/Y'),
                'descricao'   => $r->observacoes ?? '—',
                'categoria'   => $r->categoria?->nome,
                'categoria_icone' => $r->categoria?->icone ?? 'fa-tag',
                'conta'       => $r->banco?->nome ?? '—',
                'conta_cor'   => $r->banco?->cor ?? '#64748b',
                'valor'       => (float) $r->valor,
                'tipo'        => 'credito',
                'status'      => $r->status,
                'pago'        => !is_null($r->data_recebimento),
                'origem'      => 'manual',
                'numero_doc'  => null,
                'recorrente'  => $r->recorrente,
            ]);

        // ── Merge, filtro e ordenação ─────────────────────────────────────
        $movimentacoes = collect();
        if ($tipo !== 'credito') $movimentacoes = $movimentacoes->concat($despesas);
        if ($tipo !== 'debito')  $movimentacoes = $movimentacoes->concat($receitas);
        $movimentacoes = $movimentacoes->sortBy([['data', 'asc'], ['id', 'asc']])->values();

        // ── Totais ────────────────────────────────────────────────────────
        $totalEntradas = $receitas->sum('valor');
        $totalSaidas   = $despesas->sum('valor');
        $saldoPeriodo  = $totalEntradas - $totalSaidas;

        // ── Dados para selects ────────────────────────────────────────────
        $bancos       = Banco::orderBy('nome')->get();
        $categorias   = Categoria::where('tipo', 'DESPESA')->orderBy('nome')->get();
        $familiares   = Familiar::orderBy('nome')->get();
        $fornecedores = Fornecedor::orderBy('nome')->get();

        $meuFamiliarId = Auth::user()->familiar_id;
        $bancoBuscado  = $bancoId ? Banco::find($bancoId) : null;

        return view('lancamentos.index', compact(
            'movimentacoes', 'totalEntradas', 'totalSaidas', 'saldoPeriodo',
            'bancos', 'categorias', 'familiares', 'fornecedores',
            'inicio', 'fim', 'bancoId', 'tipo', 'meuFamiliarId', 'bancoBuscado',
            'familiarId', 'tipoPagamento'
        ));
    }

    // ─── Escanear Cupom / NF ────────────────────────────────────────────────

    public function escanear(Request $request)
    {
        if ($request->filled('qr_code')) {
            return response()->json($this->parseQRCode(trim($request->input('qr_code'))));
        }

        if ($request->filled('imagem')) {
            $apiKey = config('services.anthropic.key', env('ANTHROPIC_API_KEY'));
            if (empty($apiKey)) {
                return response()->json(['erro' => 'Chave da API Anthropic não configurada.'], 422);
            }
            return response()->json($this->processarImagemComClaude(
                $request->input('imagem'),
                $request->input('mime', 'image/jpeg'),
                $apiKey
            ));
        }

        return response()->json(['erro' => 'Nenhum dado enviado.'], 422);
    }

    // ─── Importar Extrato (OFX / CSV) ───────────────────────────────────────

    public function importarExtrato(Request $request)
    {
        $request->validate([
            'arquivo'  => 'required|file|mimes:ofx,ofc,qfx,csv,txt|max:5120',
            'banco_id' => ['required', Rule::exists('bancos', 'id')->where('tenant_id', Auth::user()->tenant_id)],
        ]);

        $conteudo  = file_get_contents($request->file('arquivo')->getRealPath());
        $extensao  = strtolower($request->file('arquivo')->getClientOriginalExtension());

        $transacoes = match (true) {
            in_array($extensao, ['ofx', 'ofc', 'qfx']) => $this->parseOFX($conteudo),
            default => $this->parseCSV($conteudo),
        };

        if (empty($transacoes)) {
            return response()->json(['erro' => 'Nenhuma transação encontrada no arquivo.'], 422);
        }

        return response()->json([
            'total'     => count($transacoes),
            'transacoes' => $transacoes,
            'banco_id'  => $request->banco_id,
        ]);
    }

    public function confirmarImportacao(Request $request)
    {
        $request->validate([
            'banco_id'     => ['required', Rule::exists('bancos', 'id')->where('tenant_id', Auth::user()->tenant_id)],
            'transacoes'   => 'required|array|min:1',
            'transacoes.*.data'      => 'required|date',
            'transacoes.*.valor'     => 'required|numeric|min:0.01',
            'transacoes.*.tipo'      => 'required|in:debito,credito',
            'transacoes.*.descricao' => 'nullable|string|max:255',
            'transacoes.*.importar'  => 'nullable|boolean',
        ]);

        $userId   = Auth::id();
        $bancoId  = $request->banco_id;
        $criados  = 0;

        foreach ($request->transacoes as $t) {
            if (empty($t['importar'])) continue;

            if ($t['tipo'] === 'debito') {
                Despesa::create([
                    'user_id'         => $userId,
                    'forma_pagamento' => $bancoId,
                    'valor'           => $t['valor'],
                    'data_compra'     => $t['data'],
                    'observacoes'     => $t['descricao'] ?? null,
                    'origem'          => 'importacao_extrato',
                    'numero_documento' => $t['fitid'] ?? null,
                ]);
            } else {
                Receita::create([
                    'user_id'                   => $userId,
                    'forma_recebimento'         => $bancoId,
                    'valor'                     => $t['valor'],
                    'data_prevista_recebimento' => $t['data'],
                    'observacoes'               => $t['descricao'] ?? null,
                ]);
            }
            $criados++;
        }

        return back()->with('success', "{$criados} lançamento(s) importado(s) com sucesso!");
    }

    // ─── OFX Parser ─────────────────────────────────────────────────────────

    private function parseOFX(string $content): array
    {
        $transacoes = [];

        // OFX 2.x → tenta como XML
        $xmlContent = preg_replace('/^.*?(<OFX>)/si', '$1', $content);
        $xml = @simplexml_load_string($xmlContent);
        if ($xml) {
            $lista = $xml->BANKMSGSRSV1->STMTTRNRS->STMTRS->BANKTRANLIST->STMTTRN
                  ?? $xml->CREDITCARDMSGSRSV1->CCSTMTTRNRS->CCSTMTRS->BANKTRANLIST->STMTTRN
                  ?? [];
            foreach ($lista as $trn) {
                $t = $this->normalizarTransacaoOFX((array) $trn);
                if ($t) $transacoes[] = $t;
            }
            return $transacoes;
        }

        // OFX 1.x → SGML com regex
        // Extrai blocos entre <STMTTRN> ... </STMTTRN> ou até o próximo <STMTTRN>
        $content = str_replace(["\r\n", "\r"], "\n", $content);

        // Com closing tags
        preg_match_all('/<STMTTRN>(.*?)<\/STMTTRN>/si', $content, $matches);

        if (empty($matches[1])) {
            // Sem closing tags (SGML puro) - divide por <STMTTRN>
            $partes = preg_split('/<STMTTRN>/i', $content);
            array_shift($partes); // remove cabeçalho
            foreach ($partes as $bloco) {
                $fim = stripos($bloco, '</BANKTRANLIST>');
                if ($fim !== false) $bloco = substr($bloco, 0, $fim);
                $matches[1][] = $bloco;
            }
        }

        foreach ($matches[1] as $bloco) {
            $t = $this->normalizarTransacaoOFX($this->extrairCamposOFX($bloco));
            if ($t) $transacoes[] = $t;
        }

        return $transacoes;
    }

    private function extrairCamposOFX(string $bloco): array
    {
        $campos = [];
        foreach (['TRNTYPE', 'DTPOSTED', 'TRNAMT', 'FITID', 'MEMO', 'NAME', 'CHECKNUM'] as $campo) {
            preg_match("/<{$campo}>([^<\n\r]+)/i", $bloco, $m);
            $campos[$campo] = trim($m[1] ?? '');
        }
        return $campos;
    }

    private function normalizarTransacaoOFX(array $t): ?array
    {
        $dtPosted = preg_replace('/\D/', '', $t['DTPOSTED'] ?? '');
        if (strlen($dtPosted) < 8) return null;

        $data  = substr($dtPosted, 0, 4) . '-' . substr($dtPosted, 4, 2) . '-' . substr($dtPosted, 6, 2);
        $valor = (float) str_replace(',', '.', $t['TRNAMT'] ?? '0');

        if ($valor == 0) return null;

        $tipo     = strtoupper($t['TRNTYPE'] ?? '');
        $ehCredito = $valor > 0 || in_array($tipo, ['CREDIT', 'INT', 'DIV', 'DIRECTDEP']);

        return [
            'fitid'     => $t['FITID'] ?? '',
            'data'      => $data,
            'valor'     => abs($valor),
            'tipo'      => $ehCredito ? 'credito' : 'debito',
            'descricao' => trim(($t['MEMO'] ?? '') . ' ' . ($t['NAME'] ?? '')),
            'tipo_ofx'  => $tipo,
        ];
    }

    // ─── CSV Parser ──────────────────────────────────────────────────────────

    private function parseCSV(string $content): array
    {
        $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8,ISO-8859-1,Windows-1252');
        $linhas  = array_values(array_filter(
            explode("\n", str_replace("\r", "", $content)),
            fn($l) => trim($l) !== ''
        ));

        if (count($linhas) < 2) return [];

        // Detecta delimitador
        $primLinha  = $linhas[0];
        $delimiters = [';', ',', "\t", '|'];
        $counts     = array_map(fn($d) => substr_count($primLinha, $d), $delimiters);
        $delimitador = $delimiters[array_search(max($counts), $counts)];

        $cabecalho = array_map(fn($h) => mb_strtolower(trim($h)), str_getcsv($primLinha, $delimitador));

        // Mapeia colunas por nome comum
        $cData    = $this->encontrarColuna($cabecalho, ['data', 'date', 'dt', 'data lancamento', 'data do lançamento']);
        $cDesc    = $this->encontrarColuna($cabecalho, ['descricao', 'historico', 'memo', 'description', 'lançamento', 'lancamento', 'histórico']);
        $cValor   = $this->encontrarColuna($cabecalho, ['valor', 'value', 'amount', 'vl', 'vlr']);
        $cTipo    = $this->encontrarColuna($cabecalho, ['tipo', 'type', 'natureza', 'd/c', 'dc']);
        $cDebito  = $this->encontrarColuna($cabecalho, ['debito', 'débito', 'debit', 'saída', 'saida']);
        $cCredito = $this->encontrarColuna($cabecalho, ['credito', 'crédito', 'credit', 'entrada']);

        $transacoes = [];

        foreach (array_slice($linhas, 1) as $linha) {
            $cols = str_getcsv($linha, $delimitador);
            if (count($cols) < 2) continue;

            $data = $this->parsarDataCSV($cols[$cData] ?? '');
            if (!$data) continue;

            $descricao = trim($cols[$cDesc] ?? '');

            if ($cDebito !== null && $cCredito !== null) {
                $vDebito  = $this->parsarValorCSV($cols[$cDebito] ?? '');
                $vCredito = $this->parsarValorCSV($cols[$cCredito] ?? '');
                if ($vCredito > 0) { $valor = $vCredito; $tipo = 'credito'; }
                else                { $valor = $vDebito;  $tipo = 'debito';  }
            } else {
                $valor    = $this->parsarValorCSV($cols[$cValor] ?? '');
                $tipoStr  = mb_strtolower(trim($cols[$cTipo] ?? ''));
                $ehCredito = in_array($tipoStr, ['c', 'cr', 'credito', 'crédito', 'credit', 'entrada', 'c ']);
                if ($valor < 0) { $valor = abs($valor); $ehCredito = false; }
                $tipo = $ehCredito ? 'credito' : 'debito';
            }

            if ($valor > 0) {
                $transacoes[] = [
                    'fitid'     => md5($data . $descricao . $valor),
                    'data'      => $data,
                    'valor'     => $valor,
                    'tipo'      => $tipo,
                    'descricao' => $descricao ?: '—',
                    'tipo_ofx'  => 'CSV',
                ];
            }
        }

        return $transacoes;
    }

    private function encontrarColuna(array $cabecalho, array $candidatos): ?int
    {
        foreach ($candidatos as $nome) {
            $idx = array_search($nome, $cabecalho);
            if ($idx !== false) return $idx;
        }
        // Busca parcial
        foreach ($cabecalho as $i => $col) {
            foreach ($candidatos as $nome) {
                if (str_contains($col, $nome)) return $i;
            }
        }
        return null;
    }

    private function parsarDataCSV(string $raw): ?string
    {
        $raw = trim($raw);
        if (empty($raw)) return null;

        // DD/MM/YYYY ou DD-MM-YYYY
        if (preg_match('/^(\d{2})[\/\-](\d{2})[\/\-](\d{4})$/', $raw, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        // YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) return $raw;
        // YYYYMMDD
        if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $raw, $m)) return "{$m[1]}-{$m[2]}-{$m[3]}";

        try { return (new \DateTime($raw))->format('Y-m-d'); } catch (\Throwable) { return null; }
    }

    private function parsarValorCSV(string $raw): float
    {
        $raw = trim($raw, " \t\n\r\0\x0B\"'R$");
        // Remove separador de milhar e normaliza decimal
        if (preg_match('/^-?[\d.,]+$/', $raw)) {
            // Formato BR: 1.234,56 → 1234.56
            if (str_contains($raw, ',') && str_contains($raw, '.')) {
                $raw = str_replace('.', '', $raw);
                $raw = str_replace(',', '.', $raw);
            } elseif (str_contains($raw, ',')) {
                $raw = str_replace(',', '.', $raw);
            }
        }
        return (float) $raw;
    }

    // ─── OCR / QR Code (legado do LancamentoDiario) ─────────────────────────

    private function processarImagemComClaude(string $base64, string $mime, string $apiKey): array
    {
        $prompt = <<<'PROMPT'
Você é um especialista em leitura de cupons fiscais brasileiros (ECF, NFC-e e CF-e SAT).
Analise a imagem do cupom fiscal e extraia as informações abaixo.
Retorne SOMENTE um JSON válido, sem markdown, sem texto fora do JSON.

Estrutura esperada:
{
  "data": "YYYY-MM-DD",
  "valor": 0.00,
  "forma_pagamento": "texto exato (ex: Dinheiro, Pix, Cartão Crédito, Cartão Débito)",
  "itens": ["ITEM 1 – qtd x descrição – R$0,00"],
  "descricao": "resumo dos itens principais separados por vírgula (máx 120 chars)",
  "cnpj": "XX.XXX.XXX/XXXX-XX",
  "estabelecimento": "razão social do emitente",
  "numero_cupom": "COO ou CCF ou número NF"
}
PROMPT;

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])->timeout(30)->post('https://api.anthropic.com/v1/messages', [
                'model'      => 'claude-haiku-4-5-20251001',
                'max_tokens' => 600,
                'messages'   => [[
                    'role'    => 'user',
                    'content' => [
                        ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mime, 'data' => $base64]],
                        ['type' => 'text',  'text'   => $prompt],
                    ],
                ]],
            ]);

            if (! $response->successful()) {
                return ['erro' => 'Erro na API de visão: ' . $response->status()];
            }

            $text = preg_replace('/^```(?:json)?\s*/i', '', trim($response->json('content.0.text', '')));
            $text = preg_replace('/\s*```$/', '', $text);
            $dados = json_decode(trim($text), true);

            return is_array($dados) ? $this->normalizarDadosClaude($dados)
                : ['erro' => 'Não foi possível interpretar o cupom. Tente o lançamento manual.'];
        } catch (\Throwable $e) {
            return ['erro' => 'Falha ao processar imagem: ' . $e->getMessage()];
        }
    }

    private function normalizarDadosClaude(array $d): array
    {
        $data = null;
        if (! empty($d['data'])) {
            try { $data = (new \DateTime($d['data']))->format('Y-m-d'); } catch (\Throwable) {}
        }

        $valor = null;
        if (! empty($d['valor'])) {
            $v = str_replace(',', '.', (string) $d['valor']);
            $valor = floatval($v) ?: null;
        }

        return [
            'origem'          => 'ocr',
            'data'            => $data ?? now()->format('Y-m-d'),
            'valor'           => $valor,
            'forma_pagamento' => $d['forma_pagamento'] ?? null,
            'itens'           => is_array($d['itens'] ?? null) ? array_values(array_filter($d['itens'])) : [],
            'descricao'       => $d['descricao'] ?? null,
            'cnpj'            => $d['cnpj'] ?? null,
            'estabelecimento' => $d['estabelecimento'] ?? null,
            'numero_cupom'    => $d['numero_cupom'] ?? null,
        ];
    }

    private function parseQRCode(string $qrCode): array
    {
        $chave = null; $valor = null; $data = null;

        if (str_contains($qrCode, '?')) {
            parse_str(parse_url($qrCode, PHP_URL_QUERY) ?? '', $params);
            if (! empty($params['p'])) {
                [$chave, $valor, $data] = $this->parsePipeFormat($params['p']);
            } elseif (! empty($params['chNFe'])) {
                $chave = preg_replace('/\D/', '', $params['chNFe']);
                $valor = isset($params['vNF'])   ? $this->parseValorOFX($params['vNF'])   : null;
                $data  = isset($params['dhEmi']) ? $this->parseDhEmi($params['dhEmi'])    : null;
            }
        } else {
            [$chave, $valor, $data] = $this->parsePipeFormat($qrCode);
        }

        $cnpj = null;
        if ($chave && strlen($chave) >= 22) {
            $cnpjRaw = substr($chave, 6, 14);
            if (preg_match('/^\d{14}$/', $cnpjRaw)) {
                $cnpj = preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $cnpjRaw);
            }
        }

        $estabelecimento = null;
        if ($cnpj) {
            $estabelecimento = $this->buscarNomeCNPJ(preg_replace('/\D/', '', $cnpj));
        }

        return [
            'origem'          => 'qrcode',
            'data'            => $data ?? now()->format('Y-m-d'),
            'valor'           => $valor,
            'forma_pagamento' => null,
            'itens'           => [],
            'descricao'       => null,
            'cnpj'            => $cnpj,
            'estabelecimento' => $estabelecimento,
            'numero_cupom'    => null,
            'chave'           => $chave,
        ];
    }

    private function parsePipeFormat(string $p): array
    {
        $parts = explode('|', $p);
        $chave = null; $valor = null; $data = null;
        if (count($parts) >= 1) { $c = preg_replace('/\D/', '', $parts[0]); if (strlen($c) === 44) $chave = $c; }
        if (count($parts) >= 6) $valor = $this->parseValorOFX($parts[5]);
        if (count($parts) >= 5) $data  = $this->parseDhEmi($parts[4]);
        return [$chave, $valor, $data];
    }

    private function parseValorOFX(string $v): ?float
    {
        $f = floatval(str_replace(',', '.', trim($v)));
        return $f > 0 ? $f : null;
    }

    private function parseDhEmi(string $d): ?string
    {
        $d = urldecode(trim($d));
        if (empty($d)) return null;
        if (preg_match('/^[0-9a-fA-F]{5,10}$/', $d)) { $ts = hexdec($d); if ($ts > 1_000_000_000 && $ts < 2_000_000_000) return date('Y-m-d', $ts); }
        if (preg_match('/^(\d{4})(\d{2})(\d{2})\d{6}$/', $d, $m)) return "{$m[1]}-{$m[2]}-{$m[3]}";
        try { return (new \DateTime($d))->format('Y-m-d'); } catch (\Throwable) {}
        return null;
    }

    private function buscarNomeCNPJ(string $cnpj): ?string
    {
        try {
            $resp = Http::timeout(4)->get("https://publica.cnpj.ws/cnpj/{$cnpj}");
            if ($resp->successful()) {
                $d = $resp->json();
                return trim($d['estabelecimento']['nome_fantasia'] ?? $d['razao_social'] ?? '') ?: null;
            }
        } catch (\Throwable) {}
        return null;
    }
}
