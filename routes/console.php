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

/**
 * php artisan planilha:importar {arquivo} {--tenant=}
 *
 * Importa a planilha de planejamento (.xlsx) para o tenant informado — mesmo
 * serviço usado pela tela Planejamento > Importar planilha. Serve para a carga
 * inicial e para reimportar sem passar pelo navegador.
 */
Artisan::command(
    'planilha:importar {arquivo : Caminho do .xlsx} {--tenant= : ID do tenant (família)}',
    function (\App\Services\Planejamento\PlanilhaImportService $servico) {
        $arquivo = $this->argument('arquivo');
        if (! is_file($arquivo)) {
            $this->error("Arquivo não encontrado: {$arquivo}");

            return 1;
        }

        $tenant = \App\Models\Tenant::find($this->option('tenant'));
        if (! $tenant) {
            $this->error('Informe um tenant existente com --tenant=<id>.');

            return 1;
        }

        $importacao = $servico->importar($tenant->id, null, $arquivo, basename($arquivo));

        if ($importacao->status === \App\Models\PlanilhaImportacao::REJEITADA) {
            $this->error('Planilha rejeitada — nada foi gravado.');
            foreach ($importacao->erros as $erro) {
                $this->line(sprintf('  %s%s: %s', $erro['aba'] ?? 'Arquivo', $erro['linha'] ? " linha {$erro['linha']}" : '', $erro['mensagem']));
            }

            return 1;
        }

        $abas = \App\Services\Planejamento\PlanilhaParser::abas();
        $this->table(
            ['Aba', 'Incluídas', 'Atualizadas', 'Removidas', 'Mantidas'],
            collect($importacao->resumo)->map(fn ($r, $aba) => [$abas[$aba], $r['incluidas'], $r['atualizadas'], $r['removidas'], $r['mantidas']])->values()->all()
        );
        foreach ($importacao->avisos ?? [] as $aviso) {
            $this->warn(sprintf('  %s linha %s: %s', $aviso['aba'], $aviso['linha'], $aviso['mensagem']));
        }
        $this->info($importacao->status === \App\Models\PlanilhaImportacao::SEM_ALTERACOES ? 'Sem alterações.' : 'Planilha importada.');

        return 0;
    }
)->purpose('Importa a planilha de planejamento financeiro para um tenant');

/**
 * php artisan planilha:analisar
 *
 * Verifica a planilha de todas as famílias que configuraram o link do
 * OneDrive. É a mesma verificação que o primeiro acesso do dia dispara; com o
 * cron do servidor chamando `schedule:run`, ela acontece às 06:00 sem depender
 * de ninguém entrar no sistema.
 */
Artisan::command('planilha:analisar', function (\App\Services\Planejamento\PlanilhaFonteService $servico) {
    $fontes = \App\Models\PlanilhaFonte::withoutGlobalScopes()->get();

    foreach ($fontes as $fonte) {
        try {
            $r = $servico->analisar($fonte, null, automatica: true);
            $this->line(sprintf('tenant %d: %s', $fonte->tenant_id, $r['erro'] ?? $fonte->fresh()->status));
        } catch (\Throwable $e) {
            // Uma família com problema não impede as demais.
            $this->error(sprintf('tenant %d: %s', $fonte->tenant_id, $e->getMessage()));
        }
    }

    $this->info($fontes->count() . ' planilha(s) verificada(s).');
})->purpose('Verifica a planilha do OneDrive de cada família');

\Illuminate\Support\Facades\Schedule::command('planilha:analisar')->dailyAt('06:00');
