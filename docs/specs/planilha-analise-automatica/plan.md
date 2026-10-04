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

**Decision**: ler o link como visitante anônimo, do mesmo jeito que o navegador
faz ao abrir um link compartilhado do OneDrive pessoal:

1. `POST https://api-badgerp.svc.ms/v1.0/token` com `{"appId": "5cbed6ac-a083-4e14-b191-b4ba07653de2"}` → `token`;
2. `GET https://my.microsoftpersonalcontent.com/_api/v2.0/shares/u!{base64url(link)}/driveitem`
   com `Authorization: Badger {token}` e `Prefer: autoredeem` → item com
   `@content.downloadUrl`, `name`, `size`;
3. `GET` no `@content.downloadUrl`, seguido só se o host for do OneDrive.

`{base64url(link)}` é o link em Base64 sem `=` no fim, com `/`→`_` e `+`→`-`.

**Rationale**: validado em 04/10/2026 com o link real da família (resposta 200,
arquivo de 65.343 bytes idêntico ao do OneDrive). A primeira tentativa,
`https://api.onedrive.com/v1.0/shares/u!{…}/root/content`, respondeu **401
unauthenticated**: as contas pessoais migraram e a API antiga deixou de aceitar
acesso anônimo. Acrescentar `download=1` ao link responde 403.

**Risco**: o fluxo de visitante não é uma API pública documentada pela
Microsoft; se mudar, a análise falha com mensagem clara (FR-008) e o envio
manual continua disponível.

**Alternatives considered**: Microsoft Graph com login — exige registrar
aplicativo e guardar token com validade.

### Decision 2 — Endereços aceitos

**Decision**: aceitar apenas `https` nos hosts `1drv.ms`, `onedrive.live.com` e
`*.sharepoint.com`. O sistema nunca acessa o link colado diretamente: ele só vai
codificado na consulta do item, e o endereço de download devolvido só é seguido
se for de `*.microsoftpersonalcontent.com`, `*.sharepoint.com`, `*.1drv.com` ou
`*.onedrive.live.com`.

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
| I. Veracidade | PASS | endpoints do OneDrive conferidos com o link real em 04/10/2026 |
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
