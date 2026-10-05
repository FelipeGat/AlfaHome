<?php

namespace App\Services\Notificacoes;

use App\Models\Banco;
use App\Models\NotificacaoConfig;
use App\Models\NotificacaoEnvio;
use App\Models\NotificacaoEstado;
use App\Models\PlanCartao;
use App\Models\PlanilhaFonte;
use App\Models\PlanLancamento;
use App\Models\TelegramDestinatario;
use App\Services\Planejamento\PlanejamentoService;
use App\Services\Planejamento\PlanilhaFonteService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Avisos da família pelo Telegram, no molde do AlfaControl: cada ocorrência
 * vira uma linha por destinatário em `notificacao_envios` (a chave única
 * impede duplicata), e o envio toma a linha de forma atômica antes de chamar
 * o Telegram. Não há worker nem agendador em produção: quem chama
 * `processar()` é o relógio externo, o comando agendado e o fim de cada
 * análise da planilha.
 */
class NotificacaoService
{
    /** Resumo da manhã: sai a partir das 7h e só até o meio-dia. */
    private const RESUMO_DAS = 7;
    private const RESUMO_ATE = 12;

    private const LIMITE_PCT = 80.0;

    public function __construct(
        private TelegramClient $telegram,
        private PlanejamentoService $planejamento,
    ) {}

    /** O que o relógio faz: atualiza a planilha se a última verificação passou de 1 h e processa os avisos. */
    public function relogio(int $tenantId): array
    {
        if (! NotificacaoConfig::liberado($tenantId)) {
            return ['enfileirados' => 0, 'enviados' => 0, 'falhas' => 0];
        }

        $fonte = PlanilhaFonte::withoutGlobalScopes()->where('tenant_id', $tenantId)->first();
        if ($fonte && ($fonte->verificada_em === null || $fonte->verificada_em->lt(now()->subHour()))) {
            try {
                app(PlanilhaFonteService::class)->analisar($fonte, null, automatica: true);
            } catch (\Throwable $e) {
                Log::warning('Relógio: análise da planilha falhou', ['tenant' => $tenantId, 'erro' => $e->getMessage()]);
            }
        }

        return $this->processar($tenantId);
    }

    /** Detecta o que está na hora, enfileira e envia. Nunca lança: aviso não pode derrubar quem chamou. */
    public function processar(int $tenantId, ?CarbonInterface $agora = null): array
    {
        $config = $this->config($tenantId);
        if (! $config?->telegram_token || ! NotificacaoConfig::liberado($tenantId)) {
            return ['enfileirados' => 0, 'enviados' => 0, 'falhas' => 0];
        }

        // Sem ninguém vinculado não se detecta nada: um alerta contínuo (saldo,
        // limite) marcado como dado sem destinatário nunca mais seria enviado.
        $temQuemRecebe = TelegramDestinatario::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('ativo', true)->exists();
        if (! $temQuemRecebe) {
            return ['enfileirados' => 0, 'enviados' => 0, 'falhas' => 0];
        }

        try {
            $enfileirados = $this->enfileirar($config, $this->detectar($config, $agora ?? now()));
            $config->forceFill(['processado_em' => now()])->save();

            return ['enfileirados' => $enfileirados] + $this->enviarPendentes($config);
        } catch (\Throwable $e) {
            Log::error('Notificações: processamento falhou', ['tenant' => $tenantId, 'erro' => $e->getMessage()]);

            return ['enfileirados' => 0, 'enviados' => 0, 'falhas' => 0, 'erro' => $e->getMessage()];
        }
    }

    /** Mensagem de teste para cada pessoa vinculada, na hora. */
    public function enviarTeste(int $tenantId): array
    {
        $config = $this->config($tenantId);
        if (! $config?->telegram_token || ! NotificacaoConfig::liberado($tenantId)) {
            return ['enviados' => 0, 'falhas' => 0];
        }
        $this->enfileirar($config, [[
            'tipo'  => 'teste',
            'chave' => 'teste:' . now()->format('YmdHisv'),
            'texto' => "✅ <b>Teste do AlfaHome</b>\nOs avisos da família estão chegando aqui.",
        ]], respeitarTipos: false);

        return $this->enviarPendentes($config);
    }

    // ─── Detecção ────────────────────────────────────────────────────────────

    /** @return list<array{tipo: string, chave: string, texto: string, desde?: CarbonInterface}> */
    public function detectar(NotificacaoConfig $config, CarbonInterface $agora): array
    {
        $tenantId = $config->tenant_id;
        $hoje     = $agora->copy()->startOfDay();
        $ocorrencias = [];

        if ($agora->hour >= self::RESUMO_DAS && $agora->hour < self::RESUMO_ATE) {
            $ocorrencias[] = ['tipo' => 'resumo', 'chave' => 'resumo:' . $hoje->format('Y-m-d'), 'texto' => $this->textoResumo($tenantId, $hoje)];
        }

        $v = $this->planejamento->vencimentos($tenantId, $hoje);

        foreach ($v['a_pagar'] as $i) {
            $dias = $i['data'] ? (int) $hoje->diffInDays($i['data'], false) : null;
            if ($dias === null || $dias < 0 || $dias > 3) {
                continue;
            }
            $marco = $dias === 0 ? 'd0' : 'd3';
            $quando = $dias === 0 ? 'vence <b>hoje</b>' : ($dias === 1 ? 'vence <b>amanhã</b>' : "vence em {$dias} dias ({$this->data($i['data'])})");
            $ocorrencias[] = [
                'tipo'  => 'pagar',
                'chave' => "pagar:{$i['origem']}:" . md5($i['descricao']) . ":{$i['data']}:{$marco}",
                'texto' => "💸 <b>Conta a pagar</b>\n{$this->e($i['descricao'])} — {$this->valor($i['valor'])}\n{$quando}",
            ];
        }

        foreach ($v['a_receber'] as $i) {
            if ($i['data'] === $hoje->format('Y-m-d')) {
                $ocorrencias[] = [
                    'tipo'  => 'receber',
                    'chave' => 'receber:' . md5($i['descricao']) . ":{$i['data']}:d0",
                    'texto' => "💰 <b>A receber hoje</b>\n{$this->e($i['descricao'])} — {$this->valor($i['valor'])}",
                ];
            }
        }

        // Atrasos anteriores aos avisos já aparecem no resumo; aqui só o que
        // atrasar daqui em diante, para não despejar o histórico de uma vez.
        $desdeAtraso = $config->ligado_em?->copy()->startOfDay()->subDay();
        foreach ($v['atrasado'] as $i) {
            if ($i['tipo'] !== 'receita' || ! $i['data'] || ($desdeAtraso && $i['data'] < $desdeAtraso->format('Y-m-d'))) {
                continue;
            }
            $ocorrencias[] = [
                'tipo'  => 'receber',
                'chave' => 'receber:' . md5($i['descricao']) . ":{$i['data']}:atraso",
                'texto' => "⏰ <b>Recebimento atrasado</b>\n{$this->e($i['descricao'])} — {$this->valor($i['valor'])}\nera para {$this->data($i['data'])}",
            ];
        }

        $contas = Banco::withoutGlobalScopes()->where('tenant_id', $tenantId)
            ->where(fn ($q) => $q->where('tem_conta_corrente', true)->orWhere('tem_poupanca', true)->orWhere('eh_dinheiro', true))
            ->orderBy('nome')->get();
        foreach ($contas as $b) {
            $saldo = round($b->saldo_total, 2);
            if ($n = $this->transicao($tenantId, "saldo:{$b->id}", $saldo < 0)) {
                $ocorrencias[] = [
                    'tipo'  => 'saldo',
                    'chave' => "saldo:{$b->id}:{$n}",
                    'texto' => "🔴 <b>Saldo negativo</b>\n{$this->e($b->nome)}: {$this->valor($saldo)}",
                ];
            }
        }

        $cartoes = PlanCartao::withoutGlobalScopes()->where('tenant_id', $tenantId)->orderBy('linha')->get();
        foreach ($cartoes as $c) {
            $pct = $c->utilizado_pct;
            if ($n = $this->transicao($tenantId, "limite:{$c->chave}", $pct !== null && $pct > self::LIMITE_PCT)) {
                $ocorrencias[] = [
                    'tipo'  => 'limite',
                    'chave' => "limite:{$c->chave}:{$n}",
                    'texto' => sprintf("💳 <b>Limite do cartão acima de 80%%</b>\n%s: %s%% usado — disponível %s",
                        $this->e($c->nome), number_format($pct, 1, ',', '.'), $this->valor($c->limite_disponivel)),
                ];
            }
        }

        if ($config->ligado_em) {
            $compras = PlanLancamento::withoutGlobalScopes()->where('tenant_id', $tenantId)
                ->where('forma', 'cartao')->where('tipo', 'despesa')
                ->where('created_at', '>=', $config->ligado_em)
                ->orderBy('data')->orderBy('linha')->get();
            foreach ($compras as $l) {
                $cartao = $l->conta ? ' no ' . $this->e($l->conta) : '';
                $ocorrencias[] = [
                    'tipo'  => 'compra',
                    'chave' => "compra:{$l->chave}",
                    'desde' => $l->created_at,
                    'texto' => "🛒 <b>Compra no cartão</b>{$cartao}\n{$this->e($l->descricao)} — {$this->valor($l->valor_realizado ?? $l->valor_previsto)}\n{$this->data($l->data->format('Y-m-d'))}",
                ];
            }
        }

        return $ocorrencias;
    }

    /**
     * Alerta contínuo: devolve o número da ocorrência quando o estado entra em
     * alerta (e só nessa hora); sair do alerta rearma para a próxima vez.
     */
    private function transicao(int $tenantId, string $chave, bool $emAlerta): ?int
    {
        $estado = NotificacaoEstado::withoutGlobalScopes()->firstOrNew(['tenant_id' => $tenantId, 'chave' => $chave]);

        if ($emAlerta && ! $estado->em_alerta) {
            $estado->fill(['em_alerta' => true, 'vezes' => $estado->vezes + 1])->save();

            return $estado->vezes;
        }
        if (! $emAlerta && $estado->em_alerta) {
            $estado->fill(['em_alerta' => false])->save();
        }

        return null;
    }

    private function textoResumo(int $tenantId, CarbonInterface $hoje): string
    {
        $h = $this->planejamento->hoje($tenantId, $hoje);
        $dias = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];
        $l = ['☀️ <b>Bom dia! Resumo de ' . $dias[$hoje->dayOfWeek] . ', ' . $hoje->format('d/m') . '</b>', ''];

        $l[] = '<b>Em contas: ' . $this->valor($h['contas']['total']) . '</b>';
        foreach ($h['contas']['itens'] as $c) {
            $l[] = '  ' . $this->e($c['nome']) . ': ' . $this->valor($c['saldo']);
        }

        $atrasados = $h['atrasado']['itens'];
        if ($atrasados) {
            $l[] = '';
            $l[] = '⚠️ <b>Atrasado</b> — pagar ' . $this->valor($h['atrasado']['total_pagar']) . ' · receber ' . $this->valor($h['atrasado']['total_receber']);
            array_push($l, ...$this->linhas($atrasados));
        }

        $data = $hoje->format('Y-m-d');
        $venceHoje = array_values(array_filter(
            array_merge($h['proximos_7_dias']['a_pagar'], $h['proximos_7_dias']['a_receber']),
            fn ($i) => $i['data'] === $data
        ));
        if ($venceHoje) {
            $l[] = '';
            $l[] = '📅 <b>Hoje</b>';
            array_push($l, ...$this->linhas($venceHoje, comData: false));
        }

        $p = $h['proximos_7_dias'];
        if ($p['a_pagar'] || $p['a_receber']) {
            $l[] = '';
            $l[] = '🗓 <b>Próximos 7 dias</b> — pagar ' . $this->valor($p['total_pagar']) . ' · receber ' . $this->valor($p['total_receber']);
            array_push($l, ...$this->linhas(array_values(array_filter(
                array_merge($p['a_pagar'], $p['a_receber']),
                fn ($i) => $i['data'] !== $data
            ))));
        }

        if (! $atrasados && ! $p['a_pagar'] && ! $p['a_receber']) {
            $l[] = '';
            $l[] = 'Nada atrasado e nada vencendo nos próximos 7 dias.';
        }

        $l[] = '';
        $l[] = '📈 Fim do mês: previsão de ' . $this->valor($h['ate_fim_do_mes']['projecao_saldo']) . ' em contas';

        return implode("\n", $l);
    }

    /** Até 8 itens por bloco, com "e mais N" no fim. */
    private function linhas(array $itens, bool $comData = true): array
    {
        usort($itens, fn ($a, $b) => [$a['data'] ?? '9999', $a['descricao']] <=> [$b['data'] ?? '9999', $b['descricao']]);
        $linhas = array_map(fn ($i) => '  ' . ($comData && $i['data'] ? $this->data($i['data']) . ' ' : '')
            . ($i['tipo'] === 'receita' ? '↓ ' : '') . $this->e($i['descricao']) . ' — ' . $this->valor($i['valor']), array_slice($itens, 0, 8));
        if (count($itens) > 8) {
            $linhas[] = '  e mais ' . (count($itens) - 8);
        }

        return $linhas;
    }

    // ─── Fila e envio ────────────────────────────────────────────────────────

    /** Uma linha por destinatário ativo e ocorrência; repetidas são ignoradas pela chave única. */
    private function enfileirar(NotificacaoConfig $config, array $ocorrencias, bool $respeitarTipos = true): int
    {
        $destinatarios = TelegramDestinatario::withoutGlobalScopes()
            ->where('tenant_id', $config->tenant_id)->where('ativo', true)->get();
        $linhas = [];

        foreach ($ocorrencias as $o) {
            if ($respeitarTipos && ! $config->ligado($o['tipo'])) {
                continue;
            }
            foreach ($destinatarios as $d) {
                // Quem se vinculou depois não recebe compra antiga.
                if (isset($o['desde']) && $o['desde']->lt($d->created_at)) {
                    continue;
                }
                $linhas[] = [
                    'tenant_id'       => $config->tenant_id,
                    'destinatario_id' => $d->id,
                    'tipo'            => $o['tipo'],
                    'chave'           => $o['chave'],
                    'texto'           => $o['texto'],
                    'status'          => NotificacaoEnvio::PENDENTE,
                    'tentativas'      => 0,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ];
            }
        }

        return $linhas ? DB::table('notificacao_envios')->insertOrIgnore($linhas) : 0;
    }

    private function enviarPendentes(NotificacaoConfig $config): array
    {
        $tenantId = $config->tenant_id;
        $base = fn () => NotificacaoEnvio::withoutGlobalScopes()->where('tenant_id', $tenantId);

        // Envio que ficou "enviando" (processo interrompido) volta para nova tentativa.
        $base()->where('status', NotificacaoEnvio::ENVIANDO)->where('updated_at', '<', now()->subMinutes(10))
            ->update(['status' => NotificacaoEnvio::FALHOU, 'erro' => 'Envio interrompido']);
        $base()->whereIn('status', [NotificacaoEnvio::PENDENTE, NotificacaoEnvio::FALHOU])
            ->where(fn ($q) => $q->where('created_at', '<', now()->subDay())->orWhere('tentativas', '>=', NotificacaoEnvio::MAX_TENTATIVAS))
            ->update(['status' => NotificacaoEnvio::EXPIRADO]);

        $ids = $base()->whereIn('status', [NotificacaoEnvio::PENDENTE, NotificacaoEnvio::FALHOU])
            ->whereHas('destinatario', fn ($q) => $q->where('ativo', true))
            ->orderBy('id')->pluck('id');

        $enviados = $falhas = 0;
        foreach ($ids as $id) {
            // Tomada atômica: só um processo envia cada linha.
            $tomou = NotificacaoEnvio::withoutGlobalScopes()->whereKey($id)
                ->whereIn('status', [NotificacaoEnvio::PENDENTE, NotificacaoEnvio::FALHOU])
                ->update(['status' => NotificacaoEnvio::ENVIANDO, 'tentativas' => DB::raw('tentativas + 1'), 'updated_at' => now()]);
            if ($tomou !== 1) {
                continue;
            }

            $envio = NotificacaoEnvio::withoutGlobalScopes()->with(['destinatario' => fn ($q) => $q->withoutGlobalScopes()])->find($id);
            $r = $this->telegram->enviar($config->telegram_token, $envio->destinatario->chat_id, $envio->texto);

            if ($r['ok']) {
                $envio->update(['status' => NotificacaoEnvio::ENVIADO, 'enviado_em' => now(), 'erro' => null]);
                $enviados++;
                continue;
            }

            $falhas++;
            $envio->update(['status' => NotificacaoEnvio::FALHOU, 'erro' => $r['erro']]);
            if ($r['bloqueado']) {
                $envio->destinatario->update(['ativo' => false, 'motivo_inativo' => $r['erro']]);
            }
        }

        return ['enviados' => $enviados, 'falhas' => $falhas];
    }

    private function config(int $tenantId): ?NotificacaoConfig
    {
        return NotificacaoConfig::withoutGlobalScopes()->where('tenant_id', $tenantId)->first();
    }

    // ─── Formatação ──────────────────────────────────────────────────────────

    private function valor($v): string
    {
        return $v === null ? 'valor não informado' : 'R$ ' . number_format((float) $v, 2, ',', '.');
    }

    private function data(string $ymd): string
    {
        return substr($ymd, 8, 2) . '/' . substr($ymd, 5, 2);
    }

    private function e(?string $texto): string
    {
        return htmlspecialchars((string) $texto, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
