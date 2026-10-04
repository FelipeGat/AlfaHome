<?php

namespace App\Services\Planejamento;

use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Converte as abas lidas pelo XlsxReader em linhas validadas, prontas para
 * gravar. Colunas calculadas por fórmula na planilha (Mês, Limite Disponível,
 * Valor Parcela, Restantes, Saldo, Falta, % Concluído) não são lidas: o
 * sistema as recalcula a partir das colunas digitadas.
 */
class PlanilhaParser
{
    private const LINHA_CABECALHO = 3;

    /**
     * Abas importadas: coluna => [campo, tipo, cabeçalho esperado].
     * `identidade` são os campos que definem "a mesma linha" entre importações.
     */
    private const ABAS = [
        'lancamentos' => [
            'aba'        => 'Lançamentos',
            'colunas'    => [
                'A' => ['data', 'data', 'Data'],
                'C' => ['tipo', 'tipo', 'Tipo'],
                'D' => ['descricao', 'texto', 'Descrição'],
                'E' => ['categoria', 'texto', 'Categoria'],
                'F' => ['forma', 'forma', 'Forma'],
                'G' => ['conta', 'texto', 'Conta/Cartão'],
                'H' => ['valor_previsto', 'valor', 'Previsto'],
                'I' => ['valor_realizado', 'valor', 'Realizado'],
                'J' => ['status', 'status', 'Status'],
                'K' => ['observacao', 'texto', 'Observação'],
                'L' => ['id_planilha', 'texto', 'ID'],
            ],
            'obrigatorios' => ['data', 'tipo', 'descricao'],
            'identidade'   => ['data', 'tipo', 'descricao', 'valor_previsto'],
        ],
        'contas_fixas' => [
            'aba'        => 'Contas Fixas',
            'colunas'    => [
                'A' => ['conta', 'texto', 'Conta'],
                'B' => ['categoria', 'texto', 'Categoria'],
                'C' => ['dia_vencimento', 'dia', 'Vencimento'],
                'D' => ['valor_previsto', 'valor', 'Valor Previsto'],
                'E' => ['valor_realizado', 'valor', 'Valor Realizado'],
                'F' => ['forma', 'forma', 'Forma'],
                'G' => ['recorrente', 'simnao', 'Recorrente?'],
                'H' => ['status', 'status', 'Status'],
                'I' => ['mes_inicial', 'data', 'Mês inicial'],
                'J' => ['observacao', 'texto', 'Observação'],
            ],
            'obrigatorios' => ['conta'],
            'identidade'   => ['conta'],
        ],
        'cartoes' => [
            'aba'        => 'Cartões',
            'colunas'    => [
                'A' => ['nome', 'texto', 'Cartão'],
                'B' => ['banco', 'texto', 'Banco'],
                'C' => ['limite_total', 'valor', 'Limite Total'],
                'D' => ['limite_utilizado', 'valor', 'Limite Utilizado'],
                'F' => ['dia_fechamento', 'dia', 'Dia Fechamento'],
                'G' => ['dia_vencimento', 'dia', 'Dia Vencimento'],
                'H' => ['fatura_atual', 'valor', 'Fatura Atual'],
                'I' => ['status_fatura', 'texto', 'Status'],
                'J' => ['observacao', 'texto', 'Observação'],
            ],
            'obrigatorios' => ['nome'],
            'identidade'   => ['nome'],
        ],
        'parceladas' => [
            'aba'        => 'Compras Parceladas',
            'colunas'    => [
                'A' => ['compra', 'texto', 'Compra'],
                'B' => ['cartao', 'texto', 'Cartão'],
                'C' => ['data', 'data', 'Data'],
                'D' => ['valor_total', 'valor', 'Valor Total'],
                'E' => ['parcelas', 'inteiro', 'Nº Parcelas'],
                'F' => ['parcela_atual', 'inteiro', 'Parcela Atual'],
                'H' => ['parcelas_pagas', 'inteiro', 'Parcelas Pagas'],
                'K' => ['proximo_vencimento', 'data', 'Próx. Vencimento'],
                'L' => ['observacao', 'texto', 'Observação'],
            ],
            'obrigatorios' => ['compra'],
            'identidade'   => ['compra', 'cartao', 'data'],
        ],
        'dividas' => [
            'aba'        => 'Dívidas',
            'colunas'    => [
                'A' => ['nome', 'texto', 'Dívida'],
                'B' => ['credor', 'texto', 'Credor'],
                'C' => ['saldo_inicial', 'valor', 'Saldo Inicial'],
                'D' => ['saldo_atual', 'valor', 'Saldo Atual'],
                'E' => ['taxa_mensal', 'fracao', 'Taxa mensal'],
                'F' => ['parcela_mensal', 'valor', 'Parcela Mensal'],
                'G' => ['dia_vencimento', 'dia', 'Vencimento'],
                'H' => ['status', 'texto', 'Status'],
                'I' => ['prioridade', 'prioridade', 'Prioridade'],
                'J' => ['previsao_quitacao', 'data', 'Previsão Quitação'],
                'K' => ['observacao', 'texto', 'Observação'],
            ],
            'obrigatorios' => ['nome'],
            'identidade'   => ['nome', 'credor'],
        ],
        'metas' => [
            'aba'        => 'Metas',
            'colunas'    => [
                'A' => ['nome', 'texto', 'Meta'],
                'B' => ['objetivo', 'texto', 'Objetivo'],
                'C' => ['valor_alvo', 'valor', 'Valor Alvo'],
                'D' => ['valor_atual', 'valor', 'Valor Atual'],
                'G' => ['prazo', 'data', 'Prazo'],
                'H' => ['prioridade', 'prioridade', 'Prioridade'],
                'I' => ['aporte_mensal', 'valor', 'Aporte Mensal'],
                'J' => ['observacao', 'texto', 'Observação'],
            ],
            'obrigatorios' => ['nome'],
            'identidade'   => ['nome'],
        ],
    ];

    private const FORMAS = [
        'dinheiro'          => 'dinheiro',
        'pix'               => 'pix',
        'debito'            => 'debito',
        'cartao'            => 'cartao',
        'cartao de credito' => 'cartao',
        'credito'           => 'cartao',
        'transferencia'     => 'transferencia',
        'boleto'            => 'boleto',
    ];

    private const STATUS = [
        'pago'      => 'concluido',
        'realizado' => 'concluido',
        'recebido'  => 'concluido',
        'previsto'  => 'pendente',
        'pendente'  => 'pendente',
    ];

    private const PRIORIDADES = ['alta' => 'alta', 'media' => 'media', 'baixa' => 'baixa'];

    /** @var array<int, array{aba: string, linha: int|null, mensagem: string}> */
    private array $erros = [];

    /** @var array<int, array{aba: string, linha: int|null, mensagem: string}> */
    private array $avisos = [];

    /** Nomes legíveis das abas, na ordem do relatório. */
    public static function abas(): array
    {
        return array_map(fn ($def) => $def['aba'], self::ABAS);
    }

    /**
     * @param  array<string, array<int, array<string, string>>>  $planilha  saída do XlsxReader
     * @return array{dados: array<string, array<int, array<string, mixed>>>, erros: array, avisos: array}
     */
    public function interpretar(array $planilha): array
    {
        $this->erros  = [];
        $this->avisos = [];

        $porNome = [];
        foreach ($planilha as $nome => $linhas) {
            $porNome[$this->normalizar($nome)] = $linhas;
        }

        // Estrutura primeiro: se falta aba ou coluna, nem olha as linhas.
        foreach (self::ABAS as $def) {
            $linhas = $porNome[$this->normalizar($def['aba'])] ?? null;
            if ($linhas === null) {
                $this->erro($def['aba'], null, "A aba \"{$def['aba']}\" não foi encontrada na planilha.");
                continue;
            }

            $cabecalho = $linhas[self::LINHA_CABECALHO] ?? [];
            foreach ($def['colunas'] as $coluna => [, , $esperado]) {
                if ($this->normalizar($cabecalho[$coluna] ?? '') !== $this->normalizar($esperado)) {
                    $this->erro($def['aba'], self::LINHA_CABECALHO, "A coluna {$coluna} deveria ser \"{$esperado}\".");
                }
            }
        }

        if ($this->erros) {
            return ['dados' => [], 'erros' => $this->erros, 'avisos' => []];
        }

        $dados = [];
        foreach (self::ABAS as $chave => $def) {
            $dados[$chave] = $this->linhasDaAba($def, $porNome[$this->normalizar($def['aba'])]);
        }

        if ($this->erros) {
            return ['dados' => [], 'erros' => $this->erros, 'avisos' => []];
        }

        return ['dados' => $dados, 'erros' => [], 'avisos' => $this->avisos];
    }

    private function linhasDaAba(array $def, array $linhas): array
    {
        ksort($linhas);

        $resultado  = [];
        $repeticoes = [];

        foreach ($linhas as $numero => $celulas) {
            if ($numero <= self::LINHA_CABECALHO) {
                continue;
            }

            $brutos = [];
            foreach ($def['colunas'] as $coluna => [$campo]) {
                $brutos[$campo] = isset($celulas[$coluna]) ? trim($celulas[$coluna]) : '';
            }

            // Linha só com fórmulas pré-preenchidas (ou totalmente em branco).
            if (implode('', $brutos) === '') {
                continue;
            }

            $errosAntes = count($this->erros);
            $registro   = [];
            foreach ($def['colunas'] as $coluna => [$campo, $tipo, $titulo]) {
                $registro += $this->converter($def['aba'], $numero, $campo, $tipo, $titulo, $brutos[$campo]);
            }

            foreach ($def['obrigatorios'] as $campo) {
                if (($registro[$campo] ?? null) === null) {
                    $titulo = $this->tituloDoCampo($def, $campo);
                    $this->erro($def['aba'], $numero, "A coluna \"{$titulo}\" é obrigatória.");
                }
            }

            if (count($this->erros) > $errosAntes) {
                continue;
            }

            $identidade = [];
            foreach ($def['identidade'] as $campo) {
                $identidade[] = $this->paraIdentidade($registro[$campo] ?? null);
            }
            $base    = $def['aba'] . '|' . implode('|', $identidade);
            $ordinal = $repeticoes[$base] = ($repeticoes[$base] ?? 0) + 1;

            $registro['chave']         = sha1($base . '|' . $ordinal);
            $registro['conteudo_hash'] = sha1(json_encode($registro, JSON_UNESCAPED_UNICODE));
            $registro['linha']         = $numero;

            $resultado[] = $registro;
        }

        return $resultado;
    }

    /** @return array<string, mixed> campo(s) => valor */
    private function converter(string $aba, int $linha, string $campo, string $tipo, string $titulo, string $bruto): array
    {
        $vazio = $bruto === '';

        switch ($tipo) {
            case 'texto':
                return [$campo => $vazio ? null : preg_replace('/\s+/u', ' ', $bruto)];

            case 'valor':
            case 'fracao':
                if ($vazio) {
                    return [$campo => null];
                }
                if (! is_numeric($bruto)) {
                    $this->erro($aba, $linha, "A coluna \"{$titulo}\" deveria ser um número, mas contém \"{$bruto}\".");

                    return [$campo => null];
                }

                return [$campo => $tipo === 'valor'
                    ? number_format((float) $bruto, 2, '.', '')
                    : number_format((float) $bruto, 6, '.', '')];

            case 'inteiro':
            case 'dia':
                if ($vazio) {
                    return [$campo => null];
                }
                $max = $tipo === 'dia' ? 31 : 65535;
                $min = $tipo === 'dia' ? 1 : 0;
                if (! is_numeric($bruto) || (float) $bruto != (int) $bruto || (int) $bruto < $min || (int) $bruto > $max) {
                    $esperado = $tipo === 'dia' ? 'um dia do mês (1 a 31)' : 'um número inteiro';
                    $this->erro($aba, $linha, "A coluna \"{$titulo}\" deveria ser {$esperado}, mas contém \"{$bruto}\".");

                    return [$campo => null];
                }

                return [$campo => (int) $bruto];

            case 'data':
                if ($vazio) {
                    return [$campo => null];
                }
                $data = $this->data($bruto);
                if ($data === null) {
                    $this->erro($aba, $linha, "A coluna \"{$titulo}\" deveria ser uma data, mas contém \"{$bruto}\".");
                }

                return [$campo => $data];

            case 'tipo':
                $norm = $this->normalizar($bruto);
                if (! in_array($norm, ['receita', 'despesa'], true)) {
                    if (! $vazio) {
                        $this->erro($aba, $linha, "A coluna \"{$titulo}\" deveria ser Receita ou Despesa, mas contém \"{$bruto}\".");
                    }

                    return [$campo => null];
                }

                return [$campo => $norm];

            case 'forma':
                if ($vazio) {
                    return [$campo => null];
                }
                $norm = $this->normalizar($bruto);
                if (! isset(self::FORMAS[$norm])) {
                    $this->aviso($aba, $linha, "Forma de pagamento \"{$bruto}\" não está na lista; foi mantida como escrita.");

                    return [$campo => Str::limit($norm, 20, '')];
                }

                return [$campo => self::FORMAS[$norm]];

            case 'status':
                if ($vazio) {
                    return [$campo => 'pendente', 'status_planilha' => null];
                }
                $norm = $this->normalizar($bruto);
                if (! isset(self::STATUS[$norm])) {
                    $this->aviso($aba, $linha, "Status \"{$bruto}\" não está na lista; foi mantido como escrito.");

                    return [$campo => 'outro', 'status_planilha' => $bruto];
                }

                return [$campo => self::STATUS[$norm], 'status_planilha' => $bruto];

            case 'prioridade':
                if ($vazio) {
                    return [$campo => null];
                }
                $norm = $this->normalizar($bruto);
                if (! isset(self::PRIORIDADES[$norm])) {
                    $this->erro($aba, $linha, "A coluna \"{$titulo}\" deveria ser Alta, Média ou Baixa, mas contém \"{$bruto}\".");

                    return [$campo => null];
                }

                return [$campo => self::PRIORIDADES[$norm]];

            case 'simnao':
                if ($vazio) {
                    return [$campo => null];
                }
                $norm = $this->normalizar($bruto);
                if (! in_array($norm, ['sim', 'nao'], true)) {
                    $this->erro($aba, $linha, "A coluna \"{$titulo}\" deveria ser Sim ou Não, mas contém \"{$bruto}\".");

                    return [$campo => null];
                }

                return [$campo => $norm === 'sim'];
        }

        return [$campo => null];
    }

    /** Número serial do Excel (base 1899-12-30) ou texto dd/mm/aaaa. */
    private function data(string $bruto): ?string
    {
        if (is_numeric($bruto)) {
            $serial = (int) floor((float) $bruto);
            if ($serial < 1 || $serial > 2958465) {
                return null;
            }

            return Carbon::create(1899, 12, 30)->addDays($serial)->format('Y-m-d');
        }

        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $bruto, $m) && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }

        return null;
    }

    private function tituloDoCampo(array $def, string $campo): string
    {
        foreach ($def['colunas'] as [$nome, , $titulo]) {
            if ($nome === $campo) {
                return $titulo;
            }
        }

        return $campo;
    }

    private function paraIdentidade(mixed $valor): string
    {
        return $valor === null ? '' : $this->normalizar((string) $valor);
    }

    /** Minúsculas, sem acento, espaços colapsados — só para comparar. */
    private function normalizar(string $texto): string
    {
        return trim(preg_replace('/\s+/', ' ', Str::lower(Str::ascii($texto))));
    }

    private function erro(string $aba, ?int $linha, string $mensagem): void
    {
        $this->erros[] = ['aba' => $aba, 'linha' => $linha, 'mensagem' => $mensagem];
    }

    private function aviso(string $aba, ?int $linha, string $mensagem): void
    {
        $this->avisos[] = ['aba' => $aba, 'linha' => $linha, 'mensagem' => $mensagem];
    }
}
