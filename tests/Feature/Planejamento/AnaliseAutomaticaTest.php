<?php

namespace Tests\Feature\Planejamento;

use App\Models\PlanilhaFonte;
use App\Models\PlanilhaImportacao;
use App\Models\PlanLancamento;
use App\Models\User;
use App\Services\Planejamento\PlanilhaFonteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\PlanilhaFixture;
use Tests\TestCase;

class AnaliseAutomaticaTest extends TestCase
{
    use RefreshDatabase, PlanilhaFixture;

    private const LINK = 'https://1drv.ms/x/s!planilha-da-familia';

    /** O que o OneDrive devolve agora; trocado ao longo de cada teste. */
    private \Closure $onedrive;

    protected function setUp(): void
    {
        parent::setUp();

        $this->onedrive = fn () => Http::response('', 404);
        Http::fake(['api.onedrive.com/*' => fn ($request) => ($this->onedrive)($request)]);
    }

    private function onedriveDevolve(string $caminho): void
    {
        $this->onedriveResponde(Http::response(file_get_contents($caminho)));
    }

    private function onedriveResponde($resposta): void
    {
        $this->onedrive = fn () => $resposta;
    }

    private function comLink(?User $user = null): User
    {
        $user ??= User::factory()->create();
        $this->onedriveDevolve($this->planilha());
        app(PlanilhaFonteService::class)->configurar($user->tenant_id, $user->id, self::LINK);

        return $user;
    }

    // ─── Configurar o link ───────────────────────────────────────────────────

    public function test_salvar_link_valido_importa_na_hora_e_nao_exibe_o_endereco(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->onedriveDevolve($this->planilha());

        $this->post(route('planejamento.fonte.salvar'), ['url' => self::LINK])
            ->assertRedirect(route('planejamento.importar'))
            ->assertSessionHas('success', 'Link salvo. Planilha analisada: veja abaixo o que mudou.');

        $this->assertSame(52, PlanLancamento::count());
        $fonte = PlanilhaFonte::first();
        $this->assertSame(self::LINK, $fonte->url);
        $this->assertNotNull($fonte->verificada_em);
        $this->assertSame(PlanilhaImportacao::SUCESSO, $fonte->status);

        // Cifrado no banco e fora da tela.
        $this->assertStringNotContainsString('1drv.ms', DB::table('planilha_fontes')->value('url'));
        $this->get(route('planejamento.importar'))->assertOk()
            ->assertSee('Analisar agora')->assertSee('Última verificação')->assertDontSee('s!planilha-da-familia');

        // O servidor só fala com a API do OneDrive, com o link codificado.
        Http::assertSent(fn ($r) => str_starts_with($r->url(), 'https://api.onedrive.com/v1.0/shares/u!')
            && str_ends_with($r->url(), '/root/content')
            && str_contains($r->url(), rtrim(strtr(base64_encode(self::LINK), '+/', '-_'), '=')));
    }

    public function test_link_de_outro_site_e_recusado_sem_nenhum_acesso(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (['https://exemplo.com/planilha.xlsx', 'http://1drv.ms/x/abc', 'https://169.254.169.254/latest', 'https://1drv.ms.exemplo.com/x'] as $url) {
            $this->post(route('planejamento.fonte.salvar'), ['url' => $url])
                ->assertSessionHasErrors(['url' => 'Cole o link de compartilhamento do OneDrive (começa com https://1drv.ms ou https://onedrive.live.com).']);
        }

        Http::assertNothingSent();
        $this->assertSame(0, PlanilhaFonte::count());
    }

    public function test_link_que_nao_devolve_planilha_nao_e_gravado(): void
    {
        $this->actingAs(User::factory()->create());

        $this->onedriveResponde(Http::response('<html>Entrar no OneDrive</html>'));
        $this->post(route('planejamento.fonte.salvar'), ['url' => self::LINK])
            ->assertSessionHasErrors(['url' => 'O link não devolveu uma planilha Excel. Compartilhe o arquivo da planilha, não a pasta.']);

        $this->onedriveResponde(Http::response('PK' . str_repeat('x', 5 * 1024 * 1024)));
        $this->post(route('planejamento.fonte.salvar'), ['url' => self::LINK])
            ->assertSessionHasErrors(['url' => 'O arquivo do link tem mais de 5 MB.']);

        $this->onedriveResponde(Http::response('', 403));
        $this->post(route('planejamento.fonte.salvar'), ['url' => self::LINK])->assertSessionHasErrors('url');

        $this->assertSame(0, PlanilhaFonte::count());
        $this->assertSame(0, PlanLancamento::count());
    }

    public function test_remover_o_link_mantem_os_dados(): void
    {
        $this->actingAs($this->comLink());

        $this->delete(route('planejamento.fonte.remover'))->assertSessionHas('success');

        $this->assertSame(0, PlanilhaFonte::count());
        $this->assertSame(52, PlanLancamento::count());
        $this->get(route('planejamento.index'))->assertOk()->assertDontSee('Analisar');
    }

    public function test_so_o_dono_da_conta_configura_remove_e_analisa(): void
    {
        $dono   = $this->comLink();
        $membro = User::factory()->create(['tenant_id' => $dono->tenant_id, 'role' => 'membro']);
        $this->actingAs($membro);

        $this->post(route('planejamento.fonte.salvar'), ['url' => self::LINK])->assertStatus(403);
        $this->delete(route('planejamento.fonte.remover'))->assertStatus(403);
        $this->post(route('planejamento.analisar'))->assertStatus(403);
        $this->get(route('planejamento.index'))->assertOk()->assertDontSee('Analisar');

        $this->assertSame(1, PlanilhaFonte::withoutGlobalScopes()->count());
    }

    // ─── Botão Analisar ──────────────────────────────────────────────────────

    public function test_analisar_traz_o_que_mudou_na_planilha(): void
    {
        $this->actingAs($this->comLink());
        $this->get(route('planejamento.index'))->assertSee('Analisar');

        $this->onedriveDevolve($this->planilhaAtualizada());
        $this->post(route('planejamento.analisar'))
            ->assertRedirect(route('planejamento.importar'))
            ->assertSessionHas('success', 'Planilha analisada: veja abaixo o que mudou.');

        $this->assertSame(54, PlanLancamento::count());
        $this->assertEquals(['incluidas' => 3, 'atualizadas' => 0, 'removidas' => 1, 'mantidas' => 51], PlanilhaImportacao::latest('id')->first()->resumo['lancamentos']);

        $this->post(route('planejamento.analisar'))->assertSessionHas('success', 'A planilha já estava em dia: nenhuma alteração.');
    }

    public function test_analisar_com_planilha_invalida_ou_onedrive_fora_do_ar_mantem_os_dados(): void
    {
        $this->actingAs($this->comLink());

        $this->onedriveDevolve($this->planilhaComTexto('Lançamentos', 'H4', 'mil'));
        $this->post(route('planejamento.analisar'))->assertSessionHas('error', 'A planilha foi rejeitada e nada foi alterado. Veja os motivos abaixo.');
        $this->assertSame(52, PlanLancamento::count());
        $this->get(route('planejamento.index', ['mes' => '2026-08']))
            ->assertSee('A última análise da planilha não foi aplicada')
            ->assertSee('Lançamentos, linha 4: A coluna "Previsto" deveria ser um número')
            ->assertSee('R$ 17.632,86');

        $this->onedriveResponde(Http::response('', 404));
        $this->post(route('planejamento.analisar'))->assertSessionHas('error');
        $this->assertSame(PlanilhaFonte::FALHA, PlanilhaFonte::first()->status);
        $this->assertStringContainsString('O OneDrive recusou o link (código 404)', PlanilhaFonte::first()->erro);
        $this->assertSame(52, PlanLancamento::count());
    }

    public function test_analisar_sem_link_pede_para_configurar(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('planejamento.analisar'))->assertSessionHas('error', 'Configure primeiro o link da planilha no OneDrive.');
        $this->get(route('planejamento.importar'))->assertSee('Salvar link')->assertDontSee('Analisar agora');
    }

    // ─── Verificação diária ──────────────────────────────────────────────────

    public function test_primeiro_acesso_do_dia_verifica_a_planilha_uma_unica_vez(): void
    {
        $user = $this->comLink();
        $this->actingAs($user);

        $this->travelTo(now()->addDay()->setTime(8, 0));
        $this->onedriveDevolve($this->planilhaAtualizada());
        $antes = Http::recorded()->count();

        $this->get(route('dashboard'))->assertOk();
        $this->assertSame(54, PlanLancamento::count(), 'a planilha nova entrou no primeiro acesso do dia');
        $this->assertTrue(PlanilhaFonte::first()->verificada_em->isToday());

        $this->get(route('dashboard'))->assertOk();
        $this->get(route('planejamento.index'))->assertOk();
        $this->assertSame(1, Http::recorded()->count() - $antes, 'uma única verificação no dia');
    }

    public function test_acesso_pelo_app_tambem_dispara_a_verificacao_do_dia(): void
    {
        $user = $this->comLink();
        $this->travelTo(now()->addDay()->setTime(8, 0));
        $this->onedriveDevolve($this->planilhaAtualizada());

        $this->withHeader('Authorization', 'Bearer ' . $user->createToken('app')->plainTextToken)
            ->getJson('/api/v1/planejamento/metas')->assertOk();

        $this->assertSame(54, PlanLancamento::withoutGlobalScopes()->count());
    }

    public function test_verificacao_diaria_sem_mudanca_nao_cria_linha_no_historico(): void
    {
        $user = $this->comLink();
        $this->actingAs($user);
        $this->assertSame(1, PlanilhaImportacao::count());

        foreach ([1, 2, 3] as $dia) {
            $this->travelTo(now()->addDay());
            $this->get(route('dashboard'))->assertOk();
        }

        $this->assertSame(1, PlanilhaImportacao::count());
        $this->assertSame(PlanilhaImportacao::SEM_ALTERACOES, PlanilhaFonte::first()->status);
        $this->assertTrue(PlanilhaFonte::first()->verificada_em->isToday());
    }

    public function test_comando_verifica_todas_as_familias_e_isola_a_falha_de_uma(): void
    {
        $a = $this->comLink();
        $b = $this->comLink();
        // O link de B passa a devolver lixo; o de A, a planilha atualizada.
        $linkB = 'https://1drv.ms/x/s!outra-familia';
        PlanilhaFonte::withoutGlobalScopes()->where('tenant_id', $b->tenant_id)->first()->update(['url' => $linkB]);
        $codB = rtrim(strtr(base64_encode($linkB), '+/', '-_'), '=');
        $this->onedrive = fn ($request) => str_contains($request->url(), $codB)
            ? Http::response('<html>erro</html>')
            : Http::response(file_get_contents($this->planilhaAtualizada()));

        $this->artisan('planilha:analisar')->expectsOutputToContain('2 planilha(s) verificada(s).')->assertExitCode(0);

        $this->assertSame(54, PlanLancamento::withoutGlobalScopes()->where('tenant_id', $a->tenant_id)->count());
        $this->assertSame(52, PlanLancamento::withoutGlobalScopes()->where('tenant_id', $b->tenant_id)->count());
        $this->assertSame(PlanilhaFonte::FALHA, PlanilhaFonte::withoutGlobalScopes()->where('tenant_id', $b->tenant_id)->first()->status);
    }

    // ─── API ─────────────────────────────────────────────────────────────────

    public function test_api_informa_a_fonte_e_analisa(): void
    {
        $user  = $this->comLink();
        $token = $user->createToken('app')->plainTextToken;
        $api   = fn () => $this->withHeader('Authorization', "Bearer {$token}");

        $api()->getJson('/api/v1/planejamento/fonte')->assertOk()
            ->assertJsonPath('data.configurada', true)
            ->assertJsonPath('data.status', 'sucesso')
            ->assertJsonPath('data.erro', null)
            ->assertJsonMissingPath('data.url');

        $this->onedriveDevolve($this->planilhaAtualizada());
        $api()->postJson('/api/v1/planejamento/analisar')->assertOk()
            ->assertJsonPath('data.status', 'sucesso')
            ->assertJsonPath('data.resumo.lancamentos.incluidas', 3);

        $this->onedriveResponde(Http::response('', 500));
        $api()->postJson('/api/v1/planejamento/analisar')->assertStatus(502);
    }

    public function test_api_sem_link_e_de_outra_familia(): void
    {
        $this->comLink();
        $outro = User::factory()->create();
        $api   = fn () => $this->withHeader('Authorization', 'Bearer ' . $outro->createToken('app')->plainTextToken);

        $api()->getJson('/api/v1/planejamento/fonte')->assertOk()->assertJsonPath('data.configurada', false);
        $api()->postJson('/api/v1/planejamento/analisar')->assertStatus(409)
            ->assertJsonPath('message', 'O link da planilha no OneDrive ainda não foi configurado.');

        $this->assertSame(0, PlanLancamento::withoutGlobalScopes()->where('tenant_id', $outro->tenant_id)->count());
    }
}
