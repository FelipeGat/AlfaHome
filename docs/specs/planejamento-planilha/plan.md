# Implementation Plan: Planejamento financeiro a partir da planilha

**Feature**: `planejamento-planilha` | **Date**: 2026-10-03 | **Spec**: [spec.md](spec.md)

## Summary

Importar o `.xlsx` de planejamento da família para tabelas próprias (`plan_*`),
de forma atômica e idempotente, e expor os mesmos números da planilha em telas
web e na API v1 (somente leitura) para o app. Um único serviço calcula todos os
números. Detalhes das decisões em [research.md](research.md).

## Technical Context

| Campo | Valor |
|-------|-------|
| Language/Version | PHP 8.2 |
| Primary Dependencies | Laravel 12, Sanctum 4; sem dependência nova |
| Storage | MySQL 8 |
| Testing | PHPUnit 11 (`php artisan test`), banco `alfahome_test` |
| Target Platform | Docker (php-fpm + nginx), produção no LXC 114 |
| Project Type | Web (Blade + Tailwind/Vite) + API REST |
| Performance Goals | importação da planilha de referência < 10 s (SC-002) |
| Constraints | upload até 5 MB; extensão `zip` do PHP (já na imagem) |
| Scale/Scope | 1 família; dezenas a poucas centenas de linhas por mês |

NEEDS CLARIFICATION: nenhum.

## Constitution Check

*GATE: passou antes do Phase 0; re-checado após o Phase 1.*

| Principio | Status | Notas |
|-----------|--------|-------|
| I. Veracidade — zero fabricação | PASS | campos vazios ficam `null`; valores do contrato marcados como proposta |
| II. Planilha é a fonte; importação idempotente | PASS | diff por `chave` + `conteudo_hash`, transação única, relatório por linha |
| III. Números batem com a planilha | PASS | testes com a planilha de referência como fixture (T de conferência) |
| IV. Uma definição por número | PASS | `PlanejamentoService` único para web, API e painel |
| V. Saldos só por caminho auditável | PASS | `plan_*` não tem observer nem toca `bancos`; nenhum update/delete em massa em despesas/receitas |
| VI. Isolamento por tenant | PASS | `tenant_id` + `BelongsToTenant` em todas as tabelas; teste de isolamento |
| VII. Português | PASS | mensagens de importação e validação em pt-BR |
| VIII. Prova executável | PASS | cada FR com teste; migrations com `down()` |
| IX. Segredos fora do código | PASS | nenhum segredo novo |

Re-check pós-design: a remoção de linhas `plan_*` ausentes do arquivo é feita
registro a registro dentro da transação (não é despesa/receita, mas mantém o
espírito do princípio V e permite contar as removidas).

## Project Structure

### Documentation

```
docs/specs/planejamento-planilha/
├── spec.md
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/api-v1-planejamento.md
└── tasks.md            (gerado por /create-tasks)
```

### Source Code

```
app/
├── Console/Commands/PlanilhaImportar.php                      (novo)
├── Http/Controllers/
│   ├── PlanejamentoController.php                              (novo — telas)
│   ├── PlanilhaImportacaoController.php                        (novo — upload + histórico)
│   ├── DashboardController.php                                 (alterado — bloco da planilha)
│   ├── FluxoCaixaController.php                                (alterado — preserva previsto)
│   └── Api/V1/PlanejamentoApiController.php                    (novo)
├── Models/
│   ├── PlanilhaImportacao.php, PlanLancamento.php, PlanContaFixa.php,
│   │   PlanCartao.php, PlanCompraParcelada.php, PlanDivida.php, PlanMeta.php   (novos)
│   └── Despesa.php, Receita.php                                (alterados — valor_previsto)
├── Services/Planejamento/
│   ├── XlsxReader.php                                          (novo — leitura do arquivo)
│   ├── PlanilhaParser.php                                      (novo — abas → linhas validadas)
│   ├── PlanilhaImportService.php                               (novo — diff + transação + relatório)
│   └── PlanejamentoService.php                                 (novo — todos os números)
database/migrations/
│   ├── 2026_10_03_000001_create_planilha_importacoes_table.php
│   ├── 2026_10_03_000002_create_plan_tables.php
│   └── 2026_10_03_000003_add_valor_previsto_to_despesas_receitas.php
resources/views/planejamento/
│   ├── index.blade.php (mês), anual.blade.php, cartoes.blade.php,
│   │   dividas.blade.php, metas.blade.php, contas-fixas.blade.php,
│   │   importar.blade.php
resources/views/layouts/main.blade.php                          (alterado — menu)
resources/views/dashboard.blade.php                             (alterado — bloco)
routes/web.php, routes/api.php                                  (alterados)
tests/
├── Fixtures/planilha/planejamento_v2.xlsx                      (cópia da planilha de referência)
├── Unit/Planejamento/XlsxReaderTest.php, PlanilhaParserTest.php
└── Feature/Planejamento/
    ├── ImportacaoTest.php            (idempotência, atomicidade, diff, permissões)
    ├── NumerosAgosto2026Test.php     (conferência com a planilha)
    ├── TelasTest.php
    ├── IsolamentoTenantTest.php
    └── Api/PlanejamentoApiTest.php   (contrato + roundtrip)
```

## Fluxo da importação

1. Recebe o arquivo (tela, API ou comando) → `XlsxReader` devolve, por aba, as
   linhas com valores em cache.
2. `PlanilhaParser` valida abas e cabeçalhos (linha 3; aba Listas não é
   importada), descarta linhas vazias, normaliza e valida cada linha. Qualquer
   erro → importação `rejeitada`, registrada, nada gravado.
3. `PlanilhaImportService` calcula `chave`/`conteudo_hash`, compara com o banco
   do tenant e aplica incluir/atualizar/remover numa transação; grava
   `planilha_importacoes` com resumo e avisos.
4. Categoria inexistente (mesmo nome e tipo, no tenant) é criada e vira aviso.

## Telas (web)

Grupo "Planejamento" no menu lateral, antes de "Lançamentos": Resumo do mês,
Visão anual, Cartões e parceladas, Dívidas, Metas, Contas fixas, Importar
planilha. Seguem o visual das telas existentes (`layouts/main.blade.php`).
Registros da planilha não têm botão de editar/excluir; cada tela mostra "Dados
da planilha — última importação em …". O painel inicial ganha um bloco com os
números do Dashboard da planilha (FR-024).

## Convencoes de Borda

| Camada | Case style | Validacao | Fonte da verdade |
|--------|------------|-----------|------------------|
| DB columns (MySQL) | snake_case | migrations | `database/migrations/*.php` |
| Models (Eloquent) | snake_case (atributos = colunas) | `$casts` | `app/Models/*.php` |
| API payload | snake_case | testes de contrato | `contracts/api-v1-planejamento.md` |
| URL path/query | kebab-case no path, snake_case na query | `Request::validate` | `routes/api.php` |
| App Flutter (DTO) | camelCase em Dart, mapeado de snake_case no `fromJson` | models do app | spec do AlfaHomeApp |

**Mapper layer**: Eloquent mapeia coluna ↔ atributo automaticamente; a API monta
o payload a partir do `PlanejamentoService` (arrays) e de Resources para as
listas. Valores monetários saem como número (`float` com 2 casas), percentuais
em pontos percentuais com 2 casas, datas `Y-m-d`.

## Complexity Tracking

Sem violações de constitution.
