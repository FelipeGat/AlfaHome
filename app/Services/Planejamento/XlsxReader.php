<?php

namespace App\Services\Planejamento;

use SimpleXMLElement;
use ZipArchive;

/**
 * Leitor mínimo de .xlsx: devolve, por aba, os valores em cache das células.
 * Não recalcula fórmulas nem lê estilos — por isso datas chegam como número
 * serial do Excel e quem consome decide o que é data.
 */
class XlsxReader
{
    private const NS_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    /**
     * @return array<string, array<int, array<string, string>>> aba => linha => coluna => valor
     */
    public function ler(string $caminho): array
    {
        $zip = new ZipArchive();
        if (! is_file($caminho) || $zip->open($caminho, ZipArchive::RDONLY) !== true) {
            throw new PlanilhaInvalidaException('O arquivo não é uma planilha Excel (.xlsx) válida.');
        }

        try {
            $workbook = $this->xml($zip, 'xl/workbook.xml');
            $rels     = $this->xml($zip, 'xl/_rels/workbook.xml.rels');
            if (! $workbook || ! $rels) {
                throw new PlanilhaInvalidaException('O arquivo não é uma planilha Excel (.xlsx) válida.');
            }

            $alvos = [];
            foreach ($rels->Relationship as $rel) {
                $alvos[(string) $rel['Id']] = (string) $rel['Target'];
            }

            $textos = $this->textosCompartilhados($zip);

            $abas = [];
            foreach ($workbook->sheets->sheet as $sheet) {
                $rid   = (string) $sheet->attributes(self::NS_REL)['id'];
                $alvo  = $alvos[$rid] ?? null;
                if ($alvo === null) {
                    continue;
                }
                $path  = str_starts_with($alvo, '/') ? ltrim($alvo, '/') : 'xl/' . $alvo;
                $folha = $this->xml($zip, $path);
                if ($folha) {
                    $abas[(string) $sheet['name']] = $this->linhas($folha, $textos);
                }
            }

            return $abas;
        } finally {
            $zip->close();
        }
    }

    private function xml(ZipArchive $zip, string $path): ?SimpleXMLElement
    {
        $conteudo = $zip->getFromName($path);
        if ($conteudo === false) {
            return null;
        }

        // Sem o namespace padrão os elementos são acessíveis direto por nome;
        // os prefixados (r:id) continuam com o namespace deles.
        $xml = @simplexml_load_string(preg_replace('/\sxmlns="[^"]+"/', '', $conteudo));

        return $xml === false ? null : $xml;
    }

    /** @return string[] */
    private function textosCompartilhados(ZipArchive $zip): array
    {
        $xml = $this->xml($zip, 'xl/sharedStrings.xml');
        if (! $xml) {
            return [];
        }

        $textos = [];
        foreach ($xml->si as $si) {
            $textos[] = $this->texto($si);
        }

        return $textos;
    }

    /** Texto simples (<t>) ou formatado em trechos (<r><t>). */
    private function texto(SimpleXMLElement $no): string
    {
        if (isset($no->t)) {
            return (string) $no->t;
        }

        $texto = '';
        foreach ($no->r as $trecho) {
            $texto .= (string) $trecho->t;
        }

        return $texto;
    }

    /**
     * @param  string[]  $textos
     * @return array<int, array<string, string>>
     */
    private function linhas(SimpleXMLElement $folha, array $textos): array
    {
        $linhas = [];
        $dados  = $folha->sheetData;
        if (! $dados) {
            return $linhas;
        }

        foreach ($dados->row as $row) {
            foreach ($row->c as $c) {
                $ref = (string) $c['r'];
                if (! preg_match('/^([A-Z]+)(\d+)$/', $ref, $m)) {
                    continue;
                }

                $filhos = $c;
                $tipo   = (string) $c['t'];
                $valor  = null;

                if ($tipo === 's' && isset($filhos->v)) {
                    $valor = $textos[(int) $filhos->v] ?? null;
                } elseif ($tipo === 'inlineStr' && isset($filhos->is)) {
                    $valor = $this->texto($filhos->is);
                } elseif (isset($filhos->v)) {
                    $valor = (string) $filhos->v;
                }

                if ($valor !== null && $valor !== '') {
                    $linhas[(int) $m[2]][$m[1]] = $valor;
                }
            }
        }

        return $linhas;
    }
}
