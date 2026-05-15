<?php

use App\Models\Banco;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * php artisan bancos:resync-saldo-cartao [--tenant=ID] [--dry-run]
 *
 * Resincroniza `bancos.saldo_cartao` a partir das despesas de crédito
 * em aberto. Útil para corrigir dados legados criados antes do
 * DespesaObserver (PR #5) — para operações novas o observer mantém
 * o saldo sincronizado automaticamente, então este comando não
 * precisa ser executado em rotina.
 *
 * Executar no servidor:
 *   docker exec alfa-home-app php artisan bancos:resync-saldo-cartao
 *   docker exec alfa-home-app php artisan bancos:resync-saldo-cartao --dry-run
 *   docker exec alfa-home-app php artisan bancos:resync-saldo-cartao --tenant=1
 */
Artisan::command('bancos:resync-saldo-cartao {--tenant= : ID do tenant (opcional, default: todos)} {--dry-run : Mostra o que seria atualizado sem persistir}', function () {
    $tenantId = $this->option('tenant');
    $dryRun   = (bool) $this->option('dry-run');

    $query = Banco::query()
        ->where('tem_cartao_credito', true)
        ->when($tenantId, fn($q, $id) => $q->where('tenant_id', $id));

    $total   = $query->count();
    $changed = 0;

    $this->info("Verificando {$total} cartões..." . ($dryRun ? ' (DRY RUN)' : ''));

    $query->each(function (Banco $banco) use (&$changed, $dryRun) {
        $faturaAtual = (float) DB::table('despesas')
            ->where('tenant_id', $banco->tenant_id)
            ->whereNull('deleted_at')
            ->where('forma_pagamento', $banco->id)
            ->where(function ($q) {
                $q->where('tipo_pagamento', 'credito')
                  ->orWhereNull('tipo_pagamento');
            })
            ->whereNull('data_pagamento')
            ->sum('valor');

        $atual = (float) $banco->saldo_cartao;

        if ($atual === $faturaAtual) {
            return;
        }

        $changed++;
        $this->line(sprintf(
            '  Banco #%d (tenant %d) "%s": %.2f -> %.2f (delta %+.2f)',
            $banco->id, $banco->tenant_id, $banco->nome,
            $atual, $faturaAtual, $faturaAtual - $atual
        ));

        if (! $dryRun) {
            // saveQuietly() para nao disparar observers (loop e custo).
            $banco->saldo_cartao = $faturaAtual;
            $banco->saveQuietly();
        }
    });

    if ($dryRun) {
        $this->info("DRY RUN: {$changed} cartao(oes) ficariam atualizados. Rode sem --dry-run para persistir.");
    } else {
        $this->info("Sync concluido: {$changed} cartao(oes) atualizados.");
    }
})->purpose('Resincroniza bancos.saldo_cartao a partir das despesas em aberto (dados legados)');
