# Contrato: API v1 — Planejamento

Implementado em `app/Http/Controllers/Api/V1/PlanejamentoApiController.php` e
coberto por `tests/Feature/Planejamento/Api/PlanejamentoApiTest.php`; a
referência para o app é a seção 14 de `docs/API_V1_MOBILE.md`. Prefixo
`/api/v1`, middleware `auth:sanctum` + `tenant.ativo.api`, JSON em snake_case,
valores monetários como número, datas `Y-m-d`. Sem autenticação → 401. Tudo é
filtrado pelo tenant do token.

## GET /api/v1/planejamento/resumo?mes=YYYY-MM

`mes` opcional (padrão: mês corrente). Formato inválido → 422.

```json
{
  "data": {
    "mes": "2026-08",
    "receitas": { "previsto": 17261.86, "realizado": 17632.86 },
    "despesas": { "previsto": 12217.44, "realizado": 12225.10 },
    "saldo":    { "previsto": 5044.42,  "realizado": 5407.76 },
    "economia_pct": 30.67,
    "comprometimento_pct": 69.33,
    "por_categoria": [ { "categoria": "Investimentos", "realizado": 4683.05, "pct": 38.31 } ],
    "cartoes": { "limite_total": 25221.33, "limite_utilizado": 23366.18, "limite_disponivel": 1855.15, "faturas_abertas": 5549.77, "utilizado_pct": 92.64 },
    "dividas": { "saldo_total": 10174.66, "quantidade": 2 },
    "metas":   { "progresso_medio_pct": 0.0, "quantidade": 2 },
    "ultima_importacao": { "id": 1, "em": "2026-10-03T19:48:00-03:00", "arquivo": "Planejamento_Financeiro_Domestico - v2.xlsx" }
  }
}
```

Percentuais em pontos percentuais com 2 casas. `por_categoria` ordenado por
`realizado` decrescente. `ultima_importacao` é `null` se nunca houve.

## GET /api/v1/planejamento/anual?ano=YYYY

```json
{ "data": { "ano": 2026,
  "meses": [ { "mes": "2026-08", "receitas": {"previsto":0,"realizado":0}, "despesas": {"previsto":0,"realizado":0}, "saldo": {"previsto":0,"realizado":0}, "economia_pct": 0, "comprometimento_pct": 0 } ],
  "total": { "receitas": {"previsto":0,"realizado":0}, "despesas": {"previsto":0,"realizado":0}, "saldo": {"previsto":0,"realizado":0}, "economia_pct": 0 } } }
```

Sempre 12 itens em `meses`.

## GET /api/v1/planejamento/lancamentos?mes=YYYY-MM&tipo=receita|despesa

```json
{ "data": [ { "id": 1, "origem": "planilha", "data": "2026-08-27", "tipo": "receita", "descricao": "Salário (Gdl)", "categoria": "Renda fixa", "forma": "transferencia", "conta": "Itaú", "valor_previsto": 1200.00, "valor_realizado": 1571.00, "diferenca": 371.00, "status": "concluido", "observacao": null, "editavel": false } ] }
```

Inclui lançamentos manuais do mês (`origem: "manual"`, `editavel: true`, `id`
prefixado pelo tipo de origem em `ref`: `"ref": "despesa:123"`). Ordenado por
data e id.

## GET /api/v1/planejamento/cartoes

```json
{ "data": [ { "id": 1, "nome": "Cartão Sicoob", "banco": "Sicoob", "limite_total": 23593.00, "limite_utilizado": 21737.85, "limite_disponivel": 1855.15, "utilizado_pct": 92.14, "dia_fechamento": 12, "dia_vencimento": 22, "fatura_atual": 3921.44, "status_fatura": "Aberta", "observacao": null } ],
  "resumo": { "limite_total": 25221.33, "limite_utilizado": 23366.18, "limite_disponivel": 1855.15, "faturas_abertas": 5549.77, "utilizado_pct": 92.64 } }
```

Campo não informado na planilha → `null`.

## GET /api/v1/planejamento/parceladas

```json
{ "data": [ { "id": 1, "compra": "Celular Iphone", "cartao": "Cartão Sicoob", "cartao_cadastrado": true, "data": "2026-03-27", "valor_total": 5199.00, "parcelas": 10, "parcela_atual": 6, "valor_parcela": 519.90, "parcelas_pagas": 6, "parcelas_restantes": 4, "saldo": 2079.60, "proximo_vencimento": "2026-10-23", "observacao": null } ],
  "resumo": { "saldo_total": 0, "parcela_mensal_total": 0 } }
```

## GET /api/v1/planejamento/dividas

```json
{ "data": [ { "id": 1, "nome": "Empréstimo empresa", "credor": "Sicoob", "saldo_inicial": 15000.00, "saldo_atual": 6744.29, "amortizado": 8255.71, "amortizado_pct": 55.04, "taxa_mensal_pct": 5.2, "parcela_mensal": 720.21, "dia_vencimento": 5, "status": "Ativa", "prioridade": "alta", "previsao_quitacao": "2027-08-05", "observacao": "19 parcelas" } ],
  "resumo": { "saldo_total": 10174.66, "parcela_mensal_total": 1598.18 } }
```

## GET /api/v1/planejamento/metas

```json
{ "data": [ { "id": 1, "nome": "Reserva de emergência", "objetivo": "6 meses de despesas", "valor_alvo": 30000.00, "valor_atual": 0.00, "falta": 30000.00, "concluido_pct": 0.0, "prazo": null, "prioridade": "alta", "aporte_mensal": 1000.00, "observacao": null } ],
  "resumo": { "progresso_medio_pct": 0.0 } }
```

## GET /api/v1/planejamento/contas-fixas

```json
{ "data": [ { "id": 1, "conta": "Energia", "categoria": "Moradia", "dia_vencimento": 14, "valor_previsto": 340.00, "valor_realizado": 303.36, "forma": "boleto", "recorrente": true, "status": "concluido", "mes_inicial": "2026-08-14", "observacao": null } ],
  "resumo": { "previsto_total": 0, "realizado_total": 0 } }
```

Ordenado por `dia_vencimento`.

## GET /api/v1/planejamento/importacoes

Últimas 20: `{ "data": [ { "id", "em", "arquivo", "status", "usuario", "resumo", "avisos", "erros" } ] }`.

## POST /api/v1/planejamento/importar

`multipart/form-data`, campo `arquivo` (.xlsx, até 5 MB). Somente o dono da
conta (`role = master`); outro papel → 403.

- 200 — importada ou sem alterações: `{ "data": { "id", "status": "sucesso"|"sem_alteracoes", "resumo": { "lancamentos": {"incluidas":52,"atualizadas":0,"removidas":0,"mantidas":0}, "contas_fixas": {...}, "cartoes": {...}, "parceladas": {...}, "dividas": {...}, "metas": {...} }, "avisos": [ {"aba","linha","mensagem"} ] } }`
- 422 — rejeitada (estrutura ou linha inválida): `{ "message": "...", "data": { "id", "status": "rejeitada", "erros": [ {"aba","linha","mensagem"} ] } }`. Nada é gravado nos dados.

Os valores de exemplo marcados com números reais vêm da planilha de referência;
datas de exemplo derivadas de número serial e os zeros de `resumo` são
ilustrativos e serão fixados pelos testes de contrato.
