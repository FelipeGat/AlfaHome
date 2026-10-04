# Implementation Plan: Análise automática da planilha

**Feature**: `planilha-analise-automatica` | **Date**: 2026-10-04 | **Spec**: [spec.md](spec.md)

## Summary

Guardar, por família, o link de compartilhamento do OneDrive (cifrado). Um
serviço baixa o arquivo pelo link e entrega ao `PlanilhaImportService` que já
existe. Três gatilhos chamam o mesmo serviço: salvar o link, o botão Analisar e
a verificação diária (no primeiro acesso do dia e por comando agendável).

## Technical Context

| Campo | Valor |
|-------|-------|
| Language/Version | PHP 8.2, Laravel 12 |
| Primary Dependencies | cliente HTTP do Laravel (`Http`); nenhuma dependência nova |
| Storage | MySQL 8 — tabela `planilha_fontes` |
| Testing | PHPUnit com `Http::fake` devolvendo a planilha de referência |
| Constraints | servidor de produção sem evidência de `schedule:run`; sem acesso SSH para ligar |

NEEDS CLARIFICATION: nenhum.

## Research

### Decision 1 — Baixar a planilha a partir do link de compartilhamento

**Decision**: converter o link em chamada à API de compartilhamento do OneDrive:
`https://api.onedrive.com/v1.0/shares/u!{base64url(link)}/root/content`, seguindo
o redirecionamento até o arquivo. `{base64url(link)}` é o link em Base64 sem
`=` no fim, com `/`→`_` e `+`→`-`.

**Rationale**: é a forma documentada pela Microsoft para ler um item a partir de
um link de compartilhamento sem autenticação (links "qualquer pessoa com o
link"). Não depende do formato do link curto (`1drv.ms`).

**Validação pendente**: só há como confirmar com o link real do Felipe; os
testes usam `Http::fake`. Se a Microsoft recusar a chamada anônima para a conta
dele, a mensagem ao usuário é a de FR-008 e o envio manual continua disponível.

**Alternatives considered**: acrescentar `download=1` ao link — depende do
formato do link e devolve HTML em alguns casos; Microsoft Graph com login —
exige registrar aplicativo e guardar token com validade.

### Decision 2 — Endereços aceitos

**Decision**: aceitar apenas `https` nos hosts `1drv.ms`, `onedrive.live.com` e
`*.sharepoint.com`. O sistema nunca acessa o link colado diretamente: só chama
`api.onedrive.com` com o link codificado.

**Rationale**: impede que o campo seja usado para fazer o servidor acessar
endereços internos (FR-002).

### Decision 3 — Verificação diária sem agendador

**Decision**: middleware nas rotas da família (web e API v1): se a fonte existe
e a última verificação é anterior a hoje, grava a data de agora com uma
atualização condicional (quem conseguir gravar roda) e agenda a análise para
depois de a resposta ser enviada. O comando `planilha:analisar` faz o mesmo para
todas as famílias e fica registrado no agendador para as 06:00.

**Rationale**: funciona hoje, sem depender de cron no servidor; quando o cron
existir, o comando roda cedo e o middleware não encontra nada a fazer.

### Decision 4 — Histórico

**Decision**: a verificação automática compara o SHA-256 do arquivo baixado com
o da última importação bem-sucedida; se igual, só atualiza a fonte. O botão
Analisar sempre passa pela importação e mostra o relatório.

## Constitution Check

| Principio | Status | Notas |
|-----------|--------|-------|
| I. Veracidade | PASS | endpoint do OneDrive marcado como pendente de validação com link real |
| II. Planilha é a fonte; importação idempotente | PASS | reusa `PlanilhaImportService` |
| III. Números batem | PASS | nenhum cálculo novo |
| IV. Uma definição | PASS | um serviço para os três gatilhos |
| V. Saldos | N/A | não toca despesas/receitas |
| VI. Tenant | PASS | uma fonte por tenant; teste de isolamento |
| VII. Português | PASS | mensagens de falha em pt-BR |
| VIII. Prova executável | PASS | testes por FR; migration com `down()` |
| IX. Segredos | PASS | link cifrado (`encrypted` cast), nunca exibido |

## Project Structure

```
app/Models/PlanilhaFonte.php                                   (novo)
app/Services/Planejamento/PlanilhaFonteService.php             (novo — baixar + analisar)
app/Http/Middleware/VerificarPlanilhaDoDia.php                 (novo)
app/Http/Controllers/PlanilhaImportacaoController.php          (alterado — salvar/remover link, analisar)
app/Http/Controllers/Api/V1/PlanejamentoApiController.php      (alterado — analisar, fonte)
database/migrations/2026_10_04_000001_create_planilha_fontes_table.php
resources/views/planejamento/importar.blade.php, _abas.blade.php (alterados)
routes/web.php, routes/api.php, routes/console.php, bootstrap/app.php (alterados)
tests/Feature/Planejamento/AnaliseAutomaticaTest.php           (novo)
```

## Convencoes de Borda

| Camada | Case style | Fonte da verdade |
|--------|------------|------------------|
| DB | snake_case | migration |
| API payload | snake_case | `docs/API_V1_MOBILE.md` §14 |

`POST /api/v1/planejamento/analisar` → mesmo corpo de `POST /planejamento/importar`
(200/422), mais 409 quando não há link e 502 quando o download falha.
`GET /api/v1/planejamento/fonte` → `{ "data": { "configurada", "verificada_em", "status", "erro" } }`.

## Complexity Tracking

Sem violações.
