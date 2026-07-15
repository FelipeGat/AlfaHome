<?php

use App\Models\Banco;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * php artisan app:publish-update {apk} {versao} {build} [--changelog=] [--obrigatorio]
 *
 * Publica uma nova versão do APK pro auto-update do app mobile: copia o
 * APK pro disco público (storage/app/public/updates) e grava o manifesto
 * (storage/app/app_update.json) que o AppUpdateController expõe em
 * GET /api/app/version. O app consulta esse endpoint no boot e, se
 * houver versão nova, baixa e abre o instalador sozinho — sem precisar
 * reenviar o APK manualmente pra cada aparelho.
 *
 * Executar no servidor (após copiar o APK pro container):
 *   docker exec alfa-home-app php artisan app:publish-update \
 *     storage/app/alfahome-1.2.0.apk 1.2.0 5 --changelog="Correções de saldo" --obrigatorio
 */
Artisan::command(
    'app:publish-update {apk : Caminho do arquivo .apk} {versao : Ex: 1.2.0} {build : Numero inteiro do build} {--changelog= : Texto de changelog} {--obrigatorio : Marca a atualizacao como obrigatoria} {--min-versao-ios= : Versao minima aceita no iOS (aviso, sem download)}',
    function () {
        $apkPath = $this->argument('apk');
        $versao  = $this->argument('versao');
        $build   = (int) $this->argument('build');

        if (! is_file($apkPath)) {
            $this->error("Arquivo não encontrado: {$apkPath}");
            return self::FAILURE;
        }

        $manifestPath = 'app_update.json';
        $anterior = Storage::disk('local')->exists($manifestPath)
            ? json_decode(Storage::disk('local')->get($manifestPath), true)
            : null;

        $fileName = "alfahome-{$versao}.apk";
        Storage::disk('public')->put("updates/{$fileName}", file_get_contents($apkPath));

        $manifest = [
            'versao'          => $versao,
            'build'           => $build,
            'url'             => rtrim(config('app.url'), '/') . '/storage/updates/' . $fileName,
            'changelog'       => $this->option('changelog'),
            'obrigatorio'     => (bool) $this->option('obrigatorio'),
            'min_versao_ios'  => $this->option('min-versao-ios'),
            'publicado_em'    => now()->toIso8601String(),
        ];

        Storage::disk('local')->put($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT));

        // Remove o APK antigo pra não acumular lixo no disco — só mantemos
        // a versão publicada mais recente.
        if ($anterior && ($anterior['url'] ?? null) && basename($anterior['url']) !== $fileName) {
            Storage::disk('public')->delete('updates/' . basename($anterior['url']));
        }

        $this->info("Publicado: versão {$versao} (build {$build}) em {$manifest['url']}");
        return self::SUCCESS;
    }
)->purpose('Publica uma nova versão do APK pro auto-update do app mobile');

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
