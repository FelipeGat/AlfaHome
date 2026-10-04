<?php

namespace Tests\Support;

use ZipArchive;

/**
 * Planilhas de teste. A de referência é a planilha real da família em
 * 03/10/2026 19:48; a "atualizada" é a mesma planilha salva às 20:01, com
 * dois lançamentos novos, uma descrição alterada, uma meta nova e os nomes
 * das dívidas reescritos.
 */
trait PlanilhaFixture
{
    protected function planilha(): string
    {
        return base_path('tests/Fixtures/planilha/planejamento_v2.xlsx');
    }

    protected function planilhaAtualizada(): string
    {
        return base_path('tests/Fixtures/planilha/planejamento_v2_atualizada.xlsx');
    }

    /** Cópia da planilha de referência com uma aba renomeada (some do ponto de vista do importador). */
    protected function planilhaSemAba(string $aba): string
    {
        return $this->planilhaAlterada(function (ZipArchive $zip) use ($aba) {
            $xml = $zip->getFromName('xl/workbook.xml');
            $zip->addFromString('xl/workbook.xml', str_replace('name="' . $aba . '"', 'name="' . $aba . ' (antiga)"', $xml));
        });
    }

    /** Cópia da planilha de referência com um texto digitado numa célula. */
    protected function planilhaComTexto(string $aba, string $celula, string $texto): string
    {
        return $this->planilhaAlterada(function (ZipArchive $zip) use ($aba, $celula, $texto) {
            $path = $this->arquivoDaAba($zip, $aba);
            $xml  = $zip->getFromName($path);
            $novo = '<c r="' . $celula . '" t="inlineStr"><is><t>' . htmlspecialchars($texto, ENT_XML1) . '</t></is></c>';

            $alterado = preg_replace('#<c r="' . $celula . '"[^>]*?(/>|>.*?</c>)#s', $novo, $xml, 1, $n);
            if ($n !== 1) {
                throw new \RuntimeException("Célula {$celula} não encontrada na aba {$aba}.");
            }
            $zip->addFromString($path, $alterado);
        });
    }

    private function planilhaAlterada(callable $alteracao): string
    {
        $destino = tempnam(sys_get_temp_dir(), 'planilha') . '.xlsx';
        copy($this->planilha(), $destino);

        $zip = new ZipArchive();
        $zip->open($destino);
        $alteracao($zip);
        $zip->close();

        return $destino;
    }

    private function arquivoDaAba(ZipArchive $zip, string $aba): string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        preg_match('#<sheet [^>]*name="' . preg_quote($aba, '#') . '"[^>]*r:id="([^"]+)"#', $workbook, $m);
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        preg_match('#<Relationship [^>]*Id="' . $m[1] . '"[^>]*Target="([^"]+)"|<Relationship [^>]*Target="([^"]+)"[^>]*Id="' . $m[1] . '"#', $rels, $r);

        return 'xl/' . ($r[1] ?: $r[2]);
    }
}
