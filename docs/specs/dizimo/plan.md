# Implementation Plan: Dízimo semanal calculado

**Feature**: `dizimo` | **Date**: 2026-10-12 | **Spec**: [spec.md](spec.md)

## Summary

`PlanejamentoService::dizimo()` lê os movimentos do mês (planilha + sistema),
distribui 10% das receitas da base pelos domingos e compara com as despesas
"Devolução Dízimo" pagas. A tela do mês ganha o card "Dízimo de {mês}" e a API
expõe o mesmo resultado.

## Technical Context

| Campo | Valor |
|-------|-------|
| Language/Version | PHP 8.2 |
| Primary Dependencies | Laravel 12 (sem dependência nova) |
| Storage | nenhuma mudança de banco |
| Testing | PHPUnit (`tests/Feature/Planejamento/DizimoTest.php`) |

NEEDS CLARIFICATION: nenhum.

## Constitution Check

| Principio | Status | Notas |
|-----------|--------|-------|
| I. Veracidade | PASS | só soma o que está lançado; nada inventado |
| II. Planilha é a fonte | PASS | só leitura; os dízimos continuam lançados na planilha |
| III. Números batem | PASS | somas em centavos; teste com os valores de outubro |
| IV. Uma definição por número | PASS | uma função no PlanejamentoService para web e API |
| VI. Isolamento por tenant | PASS | tenant por parâmetro; teste de outra família |
| VII. Português | PASS | textos curtos |
| VIII. Prova executável | PASS | testes por FR |

## Project Structure

```
app/Services/Planejamento/PlanejamentoService.php   (dizimo())
app/Models/PlanContaFixa.php                        (Venda de imóvel é receita)
app/Http/Controllers/PlanejamentoController.php     (card no mês)
app/Http/Controllers/Api/V1/PlanejamentoApiController.php + routes/api.php
resources/views/planejamento/index.blade.php
tests/Feature/Planejamento/DizimoTest.php
```

## Convencoes de Borda

| Camada | Case style | Validacao | Fonte da verdade |
|--------|------------|-----------|------------------|
| API | snake_case | `mes=AAAA-MM` | `PlanejamentoApiController::mes()` |

## Complexity Tracking

Sem violações de constitution.
