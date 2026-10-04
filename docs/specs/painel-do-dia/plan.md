# Implementation Plan: Painel do dia

**Feature**: `painel-do-dia` | **Date**: 2026-10-04 | **Spec**: [spec.md](spec.md)

## Summary

`PlanejamentoService::hoje()` monta o painel a partir do que já existe:
`vencimentos()` (Alertas), `resumoMes()` e `cartoes()`, mais os saldos de
`bancos`. Exposto em `GET /api/v1/planejamento/hoje` e num bloco no topo do
dashboard web; no app, um card "Hoje" no topo da tela inicial substitui o card
de saldo antigo quando a planilha já foi importada.

## Constitution Check

| Principio | Status | Notas |
|---|---|---|
| IV. Uma definição por número | PASS | painel compõe métodos existentes; app só exibe |
| VI. Tenant | PASS | tudo por `tenant_id`; teste de isolamento |
| VIII. Prova executável | PASS | testes do serviço, da API e da tela |

## Contrato

`GET /api/v1/planejamento/hoje` →
`{ data: { data, contas: { total, itens: [{id, nome, saldo, cor, logo}] }, atrasado: { total_pagar, total_receber, itens }, proximos_7_dias: { a_pagar, a_receber, total_pagar, total_receber }, ate_fim_do_mes: { a_pagar, a_receber, projecao_saldo }, mes: { receitas, despesas, saldo }, cartoes: { limite_disponivel, utilizado_pct, proxima_fatura: {descricao, valor, data}|null }, planilha_importada } }`

## Project Structure

- `app/Services/Planejamento/PlanejamentoService.php` — `hoje()`
- `app/Http/Controllers/Api/V1/PlanejamentoApiController.php`, `routes/api.php`
- `resources/views/planejamento/_hoje.blade.php` + `dashboard.blade.php`
- `tests/Feature/Planejamento/PainelDoDiaTest.php`
- App: `lib/features/planejamento/.../hoje_*` e `dashboard_page.dart`

## Convencoes de Borda

snake_case no JSON (contrato acima); DTO Dart converte para camelCase.
