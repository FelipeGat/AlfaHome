<?php

namespace Tests\Unit\Planejamento;

use App\Services\Planejamento\PlanilhaInvalidaException;
use App\Services\Planejamento\PlanilhaParser;
use App\Services\Planejamento\XlsxReader;
use Tests\Support\PlanilhaFixture;
use Tests\TestCase;

class PlanilhaParserTest extends TestCase
{
    use PlanilhaFixture;

    private function interpretar(string $caminho): array
    {
        return (new PlanilhaParser())->interpretar((new XlsxReader())->ler($caminho));
    }

    public function test_leitor_devolve_as_dez_abas_com_texto_numero_e_data(): void
    {
        $abas = (new XlsxReader())->ler($this->planilha());

        $this->assertSame(
            ['Dashboard', 'Lançamentos', 'Contas Fixas', 'Cartões', 'Compras Parceladas', 'Dívidas', 'Metas', 'Planejamento Mensal', 'Planejamento Anual', 'Listas'],
            array_keys($abas)
        );
        $this->assertSame('Aluguel', $abas['Lançamentos'][4]['D']);
        $this->assertSame('1500', $abas['Lançamentos'][4]['H']);
        $this->assertSame('46240', $abas['Lançamentos'][4]['A']);
        $this->assertArrayNotHasKey('K', $abas['Lançamentos'][4], 'célula vazia não aparece');
    }

    public function test_leitor_recusa_arquivo_que_nao_e_xlsx(): void
    {
        $arquivo = tempnam(sys_get_temp_dir(), 'nao-xlsx');
        file_put_contents($arquivo, 'isto não é uma planilha');

        $this->expectException(PlanilhaInvalidaException::class);
        (new XlsxReader())->ler($arquivo);
    }

    public function test_contagens_da_planilha_de_referencia(): void
    {
        $r = $this->interpretar($this->planilha());

        $this->assertSame([], $r['erros']);
        $this->assertSame(
            ['lancamentos' => 52, 'contas_fixas' => 9, 'cartoes' => 3, 'parceladas' => 6, 'dividas' => 2, 'metas' => 2],
            array_map('count', $r['dados'])
        );
    }

    public function test_linha_de_lancamento_e_normalizada(): void
    {
        $linha = $this->interpretar($this->planilha())['dados']['lancamentos'][1];

        $this->assertSame('2026-08-28', $linha['data']);
        $this->assertSame('receita', $linha['tipo']);
        $this->assertSame('Salário (Gdl)', $linha['descricao']);
        $this->assertSame('transferencia', $linha['forma']);
        $this->assertSame('1200.00', $linha['valor_previsto']);
        $this->assertSame('1571.00', $linha['valor_realizado']);
        $this->assertSame('concluido', $linha['status']);
        $this->assertSame('Realizado', $linha['status_planilha']);
        $this->assertNull($linha['observacao']);
        $this->assertSame(5, $linha['linha']);
    }

    public function test_receita_com_status_pago_e_concluida_e_espacos_sao_aparados(): void
    {
        $lancamentos = collect($this->interpretar($this->planilha())['dados']['lancamentos']);

        $trimestral = $lancamentos->firstWhere('descricao', 'Trimestral (Grupo soluções)');
        $this->assertSame('receita', $trimestral['tipo']);
        $this->assertSame('concluido', $trimestral['status']);

        // Na planilha está "Padaria " (com espaço) na linha 45.
        $this->assertSame('Padaria', $lancamentos->firstWhere('linha', 45)['descricao']);
    }

    public function test_cada_linha_tem_chave_propria_mesmo_quando_o_texto_se_repete(): void
    {
        $lancamentos = $this->interpretar($this->planilha())['dados']['lancamentos'];

        $this->assertCount(52, array_unique(array_column($lancamentos, 'chave')));
    }

    public function test_cartao_sem_valores_fica_com_campos_nulos(): void
    {
        $celebre = collect($this->interpretar($this->planilha())['dados']['cartoes'])->firstWhere('nome', 'Cartão celebre');

        $this->assertNull($celebre['limite_total']);
        $this->assertNull($celebre['limite_utilizado']);
        $this->assertNull($celebre['fatura_atual']);
        $this->assertNull($celebre['dia_vencimento']);
    }

    public function test_aba_ausente_e_apontada_pelo_nome(): void
    {
        $r = $this->interpretar($this->planilhaSemAba('Dívidas'));

        $this->assertSame([], $r['dados']);
        $this->assertSame('A aba "Dívidas" não foi encontrada na planilha.', $r['erros'][0]['mensagem']);
    }

    public function test_valor_nao_numerico_e_apontado_com_aba_linha_e_motivo(): void
    {
        $r = $this->interpretar($this->planilhaComTexto('Lançamentos', 'H4', 'mil e quinhentos'));

        $this->assertSame([], $r['dados']);
        $this->assertSame(
            ['aba' => 'Lançamentos', 'linha' => 4, 'mensagem' => 'A coluna "Previsto" deveria ser um número, mas contém "mil e quinhentos".'],
            $r['erros'][0]
        );
    }

    public function test_data_ilegivel_e_rejeitada(): void
    {
        $r = $this->interpretar($this->planilhaComTexto('Lançamentos', 'A4', 'ontem'));

        $this->assertSame('A coluna "Data" deveria ser uma data, mas contém "ontem".', $r['erros'][0]['mensagem']);
    }
}
