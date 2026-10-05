# Implementation Plan: Notificações pelo Telegram

**Feature**: `notificacoes-telegram` | **Date**: 2026-10-04 | **Spec**: [spec.md](spec.md)

## Summary

Um bot por família (token cifrado no banco), vínculo por link `t.me/<bot>?start=<código>`
recebido por webhook, fila de envios no banco no molde do AlfaControl (tomada
atômica, nova tentativa, deduplicação por destinatário + ocorrência) e um
"relógio" que, a cada chamada, analisa a planilha (se passou 1 h), detecta as
ocorrências e envia o que estiver pendente.

## Technical Context

| Campo | Valor |
|-------|-------|
| Language/Version | PHP 8.2 |
| Primary Dependencies | Laravel 12 (`Http` facade); sem dependência nova |
| Storage | MySQL 8 |
| Testing | PHPUnit 11 com `Http::fake()` para a API do Telegram |
| Target Platform | Docker (produção no LXC 114, sem agendador nem worker) |
| Constraints | sem SSH no servidor; repositório público (nenhum segredo no git) |
| Scale/Scope | 1 família, 2 destinatários, dezenas de mensagens por dia |

NEEDS CLARIFICATION: nenhum.

## Constitution Check

| Principio | Status | Notas |
|-----------|--------|-------|
| I. Veracidade | PASS | mensagens usam só números do `PlanejamentoService`; valor ausente sai como "valor não informado" |
| II. Planilha é a fonte | PASS | avisos só leem; nada é gravado em `plan_*` |
| III. Números batem com a planilha | PASS | resumo usa `hoje()`, o mesmo do painel do dia |
| IV. Uma definição por número | PASS | `hoje()`, `vencimentos()` e `cartoes()` reaproveitados |
| V. Saldos só por caminho auditável | PASS | nenhum saldo é alterado |
| VI. Isolamento por tenant | PASS | todas as tabelas com `tenant_id`; webhook e relógio identificam a família pela rota e chave |
| VII. Português | PASS | mensagens e telas em pt-BR |
| VIII. Prova executável | PASS | testes de vínculo, resumo, cada alerta, dedupe, retry, bloqueio e isolamento |
| IX. Segredos fora do código | PASS | token do bot cifrado (`encrypted` cast); chave do relógio e segredo do webhook = HMAC da `APP_KEY`, nunca gravados no git |

## Decisões

1. **Webhook** `POST /telegram/webhook/{tenant}` com header `X-Telegram-Bot-Api-Secret-Token`
   = HMAC(`APP_KEY`, "telegram:{tenant}"). `setWebhook` é chamado ao salvar o token.
2. **Relógio** `POST /relogio/notificacoes/{tenant}/{chave}` (chave = HMAC(`APP_KEY`, "relogio:{tenant}"),
   comparada com `hash_equals`). Mesmo trabalho no comando `notificacoes:processar`
   (`Schedule` a cada 15 min). GitHub Actions (`.github/workflows/relogio.yml`,
   cron `*/15`) chama a URL guardada no segredo `ALFAHOME_RELOGIO_URL`.
3. **Detecção** (`NotificacaoDetector`) gera ocorrências com chave estável:
   - `resumo:{data}` às 7h (após 07:00 de Brasília);
   - `pagar:{origem}:{descrição}:{data}:{marco}` — marco `d3` ou `d0`;
   - `receber:{descrição}:{data}:{d0|atraso}`;
   - `saldo:{banco}:{n}` e `limite:{cartão}:{n}` — `n` incrementa quando o estado volta ao normal (tabela de estados);
   - `compra:{chave do lançamento}` para lançamento com forma cartão criado depois de `ligado_em`.
4. **Fila** `notificacao_envios` com único (`destinatario_id`, `chave`); envio
   toma a linha com `UPDATE ... WHERE status IN (pendente, falhou) AND tentativas < 5`;
   expira em 24 h; 403/400 "chat not found" desativa o destinatário.
5. **Na hora**: o relógio roda a cada 15 min e a tela Analisar dispara o
   processamento ao fim; nenhum worker.

## Project Structure

```
app/Models/NotificacaoConfig.php, TelegramDestinatario.php, NotificacaoEnvio.php, NotificacaoEstado.php
app/Services/Notificacoes/TelegramClient.php, NotificacaoDetector.php, NotificacaoService.php
app/Http/Controllers/NotificacaoController.php, TelegramWebhookController.php, RelogioController.php
database/migrations/2026_10_05_000001_create_notificacoes_tables.php
resources/views/notificacoes/index.blade.php
routes/web.php, routes/console.php, bootstrap/app.php (CSRF except)
.github/workflows/relogio.yml
tests/Feature/Notificacoes/{VinculoTest, ResumoTest, AlertasTest, EnvioTest, RelogioTest}.php
```

## Convencoes de Borda

| Camada | Case style | Validacao | Fonte da verdade |
|--------|------------|-----------|------------------|
| DB columns | snake_case | migrations | `database/migrations/*.php` |
| Telegram Bot API | snake_case (`chat_id`, `parse_mode`) | resposta `ok` | core.telegram.org/bots/api |
| URL path | kebab/segmentos | rota + `hash_equals` | `routes/web.php` |

## Complexity Tracking

Sem violações de constitution.
