# Implementation Plan: Lançamentos da planilha numa tela só

**Feature**: `lancamentos-da-planilha` | **Date**: 2026-10-05 | **Spec**: [spec.md](spec.md)

## Summary

Nova tela web `planejamento.lancamentos` e nova página do app na aba
Lançamentos, ambas lendo `PlanejamentoService::lancamentos()` (web direto, app
por `GET /api/v1/planejamento/lancamentos`, já existente). Filtros aplicados em
memória sobre o mês (dezenas a ~200 linhas). Menu web reorganizado.

## Technical Context

| Campo | Valor |
|-------|-------|
| Language/Version | PHP 8.2; Dart 3 (Flutter) |
| Primary Dependencies | Laravel 12; Riverpod + go_router no app; sem dependência nova |
| Storage | sem mudança de esquema |
| Testing | PHPUnit (tela e filtros); flutter test (página com fixture) |
| Constraints | somente leitura (constitution II) |

NEEDS CLARIFICATION: nenhum.

## Constitution Check

| Principio | Status | Notas |
|-----------|--------|-------|
| I. Veracidade | PASS | valor ausente aparece como "—" e não soma |
| II. Planilha é a fonte | PASS | tela sem criar/editar |
| III. Números batem com a planilha | PASS | mesma função de Previsto x Realizado |
| IV. Uma definição por número | PASS | `lancamentos()` + totais calculados sobre a mesma lista |
| V. Saldos auditáveis | PASS | nada é gravado |
| VI. Isolamento por tenant | PASS | consulta por `tenant_id` do usuário |
| VII. Português | PASS | |
| VIII. Prova executável | PASS | testes de filtro, totais, vazio e menu |
| IX. Segredos | PASS | nenhum |

## Project Structure

```
app/Http/Controllers/PlanejamentoController.php      (+ lancamentos)
resources/views/planejamento/lancamentos.blade.php   (novo)
resources/views/layouts/main.blade.php                (menu)
routes/web.php                                        (rota)
tests/Feature/Planejamento/LancamentosTelaTest.php    (novo)
alfahome_app: lib/features/planejamento/presentation/pages/lancamentos_page.dart (novo),
              lib/core/router/app_router.dart (aba Lançamentos), teste com fixture
```

## Convencoes de Borda

| Camada | Case style | Validacao | Fonte da verdade |
|--------|------------|-----------|------------------|
| Query string web | snake_case (`mes`, `tipo`, `situacao`, `q`, `categoria`, `conta`) | `Request` | `routes/web.php` |
| API payload | snake_case | testes de contrato existentes | `docs/API_V1_MOBILE.md` §14 |
| DTO Dart | camelCase | `fromJson` | `LancamentoPlanejadoDto` |

## Complexity Tracking

Sem violações de constitution.
