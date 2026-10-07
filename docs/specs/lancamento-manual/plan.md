# Implementation Plan: Lançamento manual (famílias sem planilha)

**Feature**: `lancamento-manual` | **Date**: 2026-10-07 | **Spec**: [spec.md](spec.md)

## Summary

Um serviço único de lançamentos (`LancamentoService`) concentra criar, editar,
marcar pago, excluir e pagar fatura; a API v1 passa a usá-lo e o site ganha um
controller fino que reaproveita as mesmas FormRequests. O `FinanceiroService`
e o `PlanejamentoService` passam a enxergar transferências, pendências e
cartões do sistema. O Início novo vale para toda família. O app ganha "+ Lançar"
com Saída/Entrada/Transferência reaproveitando o formulário existente.

## Technical Context

| Campo | Valor |
|-------|-------|
| Language/Version | PHP 8.2; Dart 3 |
| Primary Dependencies | Laravel 12; Riverpod/go_router; sem dependência nova |
| Storage | MySQL 8 — coluna nova `despesas.pago_com_banco_id` (nullable, reversível) |
| Testing | PHPUnit (regras, telas, API); flutter test |
| Constraints | não quebrar famílias com planilha; sem fluxo paralelo à API |

NEEDS CLARIFICATION: nenhum.

## Constitution Check

| Principio | Status | Notas |
|-----------|--------|-------|
| I. Veracidade | PASS | nenhum número inventado; fatura = soma das compras lançadas |
| II. Planilha é a fonte | PASS | dados da planilha continuam só leitura; o manual é outra origem |
| III. Números batem | PASS | testes de saldo/mês/fatura sem contagem dupla |
| IV. Uma definição por número | PASS | saldo, mês e fatura seguem no FinanceiroService |
| V. Saldos auditáveis | PASS | saldo = ajuste + movimentos; pagamento de fatura só marca as compras |
| VI. Isolamento por tenant | PASS | FormRequests com exists por tenant; testes de outra família |
| VII. Português | PASS | mensagens curtas |
| VIII. Prova executável | PASS | testes por FR |
| IX. Segredos | PASS | nenhum |

## Regras

| Caso | Regra |
|------|-------|
| Saída/entrada paga | move o saldo da conta na data de pagamento/recebimento |
| Pendente | não move saldo; aparece em Próximos/a receber |
| Transferência | origem −, destino +; fora de entrou/saiu; aparece no Extrato |
| Compra no cartão | `data_compra` = vencimento da fatura (regra já existente); parcelas em meses seguintes |
| Pagar fatura | `data_pagamento` = data escolhida e `pago_com_banco_id` = conta escolhida nas compras pendentes daquela fatura; saldo debita a conta escolhida; a compra continua sendo a única saída |
| Cartão do sistema | limite = `limite_cartao`; usado = compras de crédito pendentes; fatura = pendentes do próximo vencimento |

## Project Structure

```
app/Services/Lancamentos/LancamentoService.php            (novo)
app/Http/Controllers/LancamentoManualController.php       (novo, web)
app/Http/Controllers/Api/V1/{Despesa,Receita,Transferencia}Controller.php (passam a usar o serviço)
app/Http/Controllers/Api/V1/FaturaApiController.php       (novo: pagar fatura)
database/migrations/2026_10_07_000001_add_pago_com_banco_id_to_despesas.php
app/Services/Financeiro/FinanceiroService.php, Planejamento/PlanejamentoService.php
resources/views/lancamentos/_form.blade.php (modal "+ Lançar"), planejamento/lancamentos.blade.php, cartoes.blade.php, _inicio.blade.php
tests/Feature/Lancamentos/*
alfahome_app: "+ Lançar" (sheet com Saída/Entrada/Transferência + Mais opções), ações no Extrato, Início sempre novo, Pagar fatura
```

## Convencoes de Borda

| Camada | Case style | Validacao | Fonte da verdade |
|--------|------------|-----------|------------------|
| API/Form | snake_case | FormRequests v1 existentes | `app/Http/Requests/Api/V1/*` |
| DTO Dart | camelCase | `fromJson` | `features/despesas`, `features/financeiro` |

## Complexity Tracking

Sem violações de constitution.
