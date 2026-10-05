<?php

namespace Tests\Feature\Notificacoes;

use App\Models\Banco;
use App\Models\NotificacaoConfig;

class RelogioTest extends NotificacoesTestCase
{
    public function test_relogio_com_a_chave_processa_e_sem_ela_recusa(): void
    {
        $this->travelTo('2026-10-05 13:00:00');
        $this->configurar();
        $this->destinatario();
        Banco::withoutGlobalScopes()->create(['tenant_id' => $this->user->tenant_id, 'user_id' => $this->user->id, 'nome' => 'Sicoob', 'tem_conta_corrente' => true, 'saldo' => -1]);
        $tenant = $this->user->tenant_id;

        $this->post("/relogio/notificacoes/{$tenant}/chave-errada")->assertForbidden();
        $this->post('/relogio/notificacoes/' . ($tenant + 1) . '/' . NotificacaoConfig::chaveRelogio($tenant))->assertNotFound();
        $this->assertSame([], $this->enviadas());

        $this->post(route('notificacoes.relogio', ['tenant' => $tenant, 'chave' => NotificacaoConfig::chaveRelogio($tenant)]))
            ->assertOk()->assertJson(['enfileirados' => 1, 'enviados' => 1, 'falhas' => 0]);
        $this->assertNotNull(NotificacaoConfig::first()->processado_em);
    }

    public function test_comando_processa_cada_familia_com_bot(): void
    {
        $this->travelTo('2026-10-05 07:10:00');
        $this->configurar();
        $this->destinatario();

        $this->artisan('notificacoes:processar')->expectsOutputToContain('1 enviados')->assertSuccessful();
        $this->assertStringContainsString('Bom dia', $this->enviadas()[0]);
    }

    public function test_tela_mostra_o_endereco_do_relogio_so_para_o_dono(): void
    {
        $this->configurar();
        $url = route('notificacoes.relogio', ['tenant' => $this->user->tenant_id, 'chave' => NotificacaoConfig::chaveRelogio($this->user->tenant_id)]);

        $this->actingAs($this->user)->get(route('notificacoes.index'))->assertOk()->assertSee($url, false);
    }
}
