<?php

namespace Tests\Feature;

use App\Models\Banco;
use App\Models\Receita;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Regressão: ReceitaController::update com escopo=esta_e_futuras antes usava
 * query-builder update() em massa, pulando model events. O ReceitaObserver
 * não rodava em batch e o saldo de banco ficava fora de sincronia ao mudar
 * `data_recebimento` em recorrências.
 *
 * Fix: iterar os modelos e chamar ->update() em cada, mantendo observer.
 */
class ReceitaBatchUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_esta_e_futuras_triggers_observer_for_all(): void
    {
        $user  = User::factory()->create();
        $banco = Banco::factory()->create([
            'tenant_id' => $user->tenant_id,
            'user_id'   => $user->id,
            'saldo'     => 0,
        ]);
        $grupo = (string) Str::uuid();

        Sanctum::actingAs($user);

        // 2 receitas recorrentes em aberto (sem data_recebimento)
        $parcelas = collect(['2026-05-15', '2026-06-15'])
            ->map(fn ($data) => Receita::create([
                'tenant_id'                 => $user->tenant_id,
                'user_id'                   => $user->id,
                'valor'                     => 1000.0,
                'data_prevista_recebimento' => $data,
                'forma_recebimento'         => $banco->id,
                'grupo_recorrencia_id'      => $grupo,
                'origem'                    => 'recorrencia',
                'recorrente'                => true,
                'frequencia'                => 'mensal',
            ]));

        // Como nenhuma está recebida, saldo do banco continua 0
        $this->assertEquals(0.0, (float) $banco->fresh()->saldo);

        // Marca todas como recebidas via esta_e_futuras
        $primeira = $parcelas->first();
        $this->putJson("/api/v1/receitas/{$primeira->id}", [
            'valor'                     => 1000.0,
            'data_prevista_recebimento' => $primeira->data_prevista_recebimento->format('Y-m-d'),
            'data_recebimento'          => $primeira->data_prevista_recebimento->format('Y-m-d'),
            'forma_recebimento'         => $banco->id,
            'escopo'                    => 'esta_e_futuras',
        ])->assertOk();

        // Esperado: as 2 receitas marcadas como recebidas → +1000 cada = +2000
        $this->assertEquals(2000.0, (float) $banco->fresh()->saldo);
    }
}
