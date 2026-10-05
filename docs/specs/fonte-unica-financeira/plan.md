# Implementation Plan: Fonte única dos números e app simples de usar

**Feature**: `fonte-unica-financeira` | **Date**: 2026-10-05 | **Spec**: [spec.md](spec.md)

## Summary

`App\Services\Financeiro\FinanceiroService` concentra as regras (saldo por
conta com ajuste, mês, últimas movimentações, próximos pagamentos), reusando
`PlanejamentoService::movimentos()` (planilha + sistema) e `resumoMes()`.
Somas em centavos inteiros. `bancos.saldo` vira cache regravado após importar
ou ajustar. Novos endpoints `GET /api/v1/financeiro/inicio`, `/financeiro/resumo`,
`/financeiro/contas/{banco}` e `POST /api/v1/bancos/{banco}/ajustar-saldo`.
App: abas Início | Extrato | Contas | Mais; web: Início, Extrato, Contas no
topo do menu.

## Technical Context

| Campo | Valor |
|-------|-------|
| Language/Version | PHP 8.2; Dart 3 |
| Primary Dependencies | Laravel 12; Riverpod/go_router; sem dependência nova |
| Storage | MySQL 8 — tabela nova `saldo_ajustes` |
| Testing | PHPUnit (regras financeiras e contrato); flutter test |
| Constraints | não apagar dados; migration reversível; planilha somente leitura |

NEEDS CLARIFICATION: nenhum.

## Constitution Check

| Principio | Status | Notas |
|-----------|--------|-------|
| I. Veracidade | PASS | sem conclusões sem dado ("economizou" removido); valor ausente não soma |
| II. Planilha é a fonte | PASS | app só visualiza; ajuste de saldo é dado do cadastro de conta, não da planilha |
| III. Números batem com a planilha | PASS | mês = `resumoMes()` realizado |
| IV. Uma definição por número | PASS | FinanceiroService único; snapshot/relatórios passam a usá-lo |
| V. Saldos só por caminho auditável | PASS | saldo = ajuste registrado + movimentos; cada ajuste fica gravado |
| VI. Isolamento por tenant | PASS | tudo por `tenant_id`; testes de isolamento |
| VII. Português | PASS | |
| VIII. Prova executável | PASS | testes de cada regra da spec |
| IX. Segredos | PASS | nenhum |

## Regras (fonte única)

| Número | Definição |
|--------|-----------|
| Saldo da conta | último ajuste + Σ realizados (receita +, despesa −) com data > data do ajuste e ≤ hoje, casados por conta (planilha: nome normalizado; sistema: `banco_id`) ± transferências próprias |
| Saldo total | Σ saldo das contas corrente/poupança/dinheiro |
| Entrou / Saiu no mês | `resumoMes()` realizado |
| Resultado do mês | entrou − saiu |
| Pendente | previsto ainda não realizado |
| Previsão do fim do mês | saldo total + a receber − a pagar até o fim do mês (`hoje()`) |
| Cartão | compra = saída na data da planilha; limite fora do saldo |

## Project Structure

```
app/Services/Financeiro/FinanceiroService.php           (novo)
app/Models/SaldoAjuste.php                              (novo)
database/migrations/2026_10_05_000003_create_saldo_ajustes_table.php
app/Http/Controllers/Api/V1/FinanceiroApiController.php (novo)
app/Http/Controllers/DashboardController.php, BancoController.php (ajuste)
app/Services/Planejamento/PlanejamentoService.php       (movimentos público; hoje() usa saldos)
app/Services/Planejamento/PlanilhaFonteService.php, PlanilhaImportService.php (recalcular após importar)
app/Support/Dinheiro.php + helper brl()                 (formatação única no web)
resources/views/dashboard.blade.php, planejamento/_hoje, bancos/*
tests/Feature/Financeiro/*                              (novos)
alfahome_app: app_shell (4 abas), dashboard_page, lancamentos_page→Extrato, accounts_page, account_detail, reports→Resumo, todos→Mais, formatters
```

## Convencoes de Borda

| Camada | Case style | Validacao | Fonte da verdade |
|--------|------------|-----------|------------------|
| API payload | snake_case, valores em reais com 2 casas | testes de contrato | `docs/API_V1_MOBILE.md` §15 |
| DTO Dart | camelCase | `fromJson` | `features/financeiro` |
| Moeda exibida | "R$ 1.250,00" / "-R$ 98,15" | testes de formatação | `Dinheiro::brl` e `Formatters.brl` |

## Complexity Tracking

Sem violações de constitution.
