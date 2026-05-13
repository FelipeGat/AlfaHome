# AlfaHome — API V1 para o App Mobile

Documento de integração consumido pelo app Flutter.

- **Base URL (produção)**: `https://home.alfasolucoes.cloud/api/v1`
- **Autenticação**: Bearer Token (Laravel Sanctum personal access tokens)
- **Headers obrigatórios**: `Authorization: Bearer <token>`, `Accept: application/json`, `Content-Type: application/json`
- **Encoding**: JSON em request e response
- **Datas**: `Y-m-d` para datas, ISO-8601 para timestamps
- **Multi-tenant**: todo registro é filtrado automaticamente pelo `tenant_id` do usuário autenticado

---

## Sumário

1. [Autenticação](#1-autenticação)
2. [Dashboard](#2-dashboard)
3. [Catálogos — listagens read-only](#3-catálogos--listagens-read-only)
4. [Categorias — CRUD](#4-categorias--crud)
5. [Bancos — CRUD](#5-bancos--crud)
6. [Fornecedores — CRUD](#6-fornecedores--crud)
7. [Familiares — CRUD](#7-familiares--crud)
8. [Despesas — CRUD](#8-despesas--crud)
9. [Receitas — CRUD](#9-receitas--crud)
10. [Investimentos — CRUD + rendimentos](#10-investimentos--crud--rendimentos)
11. [Transferências — CRUD](#11-transferências--crud)
12. [Códigos de erro e formato](#12-códigos-de-erro-e-formato)
13. [Pendências de deploy](#13-pendências-de-deploy)

---

## 1. Autenticação

### POST `/auth/login` (público)

**Body**:
```json
{ "email": "user@example.com", "password": "...", "device_name": "ios-iphone-12" }
```

**Response 200**:
```json
{
  "token": "1|abc...",
  "user": {
    "id": 1, "nome": "...", "email": "...", "foto_url": null,
    "role": "master", "tenant_id": 1, "familiar_id": 2,
    "permissoes": {}, "ativo": true, "is_master": true
  }
}
```

**Erros**: `422` credenciais inválidas, `422` usuário ou tenant inativo

### GET `/auth/me`
Retorna o `UserResource` do usuário atual.

### PUT `/auth/me`

Atualiza nome e/ou email do usuário logado. Campos parciais são aceitos (envie só o que mudou).

**Body**:
```json
{ "name": "Maria Silva", "email": "maria@exemplo.com" }
```

**Validação**:
- `name`: opcional; se presente, `string`, `max:255`
- `email`: opcional; se presente, `string`, `lowercase`, `email`, `max:255`, único entre usuários (ignora o próprio)

**Comportamento**:
- Se o email mudar, `email_verified_at` é zerado.

**Response 200**: `UserResource` atualizado (mesmo shape de `GET /auth/me`).

**Erros**: `422` validação (email duplicado, formato inválido, etc.).

### POST `/auth/me/password`

Troca a senha do usuário. Exige a senha atual.

**Body**:
```json
{
  "current_password": "antiga",
  "password": "NovaSenh@123",
  "password_confirmation": "NovaSenh@123"
}
```

**Validação**:
- `current_password`: obrigatório; verificado contra hash atual do usuário
- `password`: obrigatório; segue `Password::defaults()` do Laravel (mínimo 8 chars por padrão); requer `password_confirmation`

**Comportamento**:
- Senha é atualizada.
- **Todos os outros tokens do usuário são revogados** — apenas o token usado nesta requisição sobrevive. Outros dispositivos são forçados a re-autenticar.

**Response 200**:
```json
{ "message": "Senha atualizada com sucesso. Outras sessões foram encerradas." }
```

**Erros**:
- `422 current_password`: senha atual incorreta
- `422 password`: nova senha fraca ou `password_confirmation` não bate

### POST `/auth/logout`
Revoga o token usado nesta requisição.

### POST `/auth/logout-all`
Revoga todos os tokens do usuário (logout em todos os dispositivos).

---

## 2. Dashboard

### GET `/dashboard`

**Query**: `inicio` (Y-m-d, default início do mês), `fim` (Y-m-d, default fim do mês), `familiar_id` (int, opcional)

**Response 200**:
```json
{
  "cached_at": "2026-05-12T...",
  "periodo": { "inicio": "2026-05-01", "fim": "2026-05-31", "mes": "Maio 2026" },
  "kpis": { "receitas": 5000.0, "despesas": 3200.0, "saldo": 1800.0 },
  "bancos":  [{ "nome": "...", "saldo": 0.0, "cor": "#...", "logo": "..." }],
  "cartoes": [{ "nome": "...", "cor": "#...", "fatura": 0.0, "limite": 0.0, "percentual": 0.0 }],
  "lancamentos": [
    {
      "id": 1, "tipo": "despesa", "valor": 150.0,
      "data": "2026-05-10", "categoria": "Mercado",
      "tipo_pagamento": "pix"
    }
  ],
  "totais": { "saldo_contas": 0.0, "fatura_total": 0.0, "limite_total": 0.0 }
}
```

> `tipo_pagamento` é o campo necessário para o donut "Por meio de pagamento".

---

## 3. Catálogos — listagens read-only

Usadas para popular dropdowns. Todas retornam array (sem paginação).

| Método | Endpoint | Query | Notas |
|---|---|---|---|
| GET | `/categorias` | `tipo=DESPESA\|RECEITA` (opcional) | |
| GET | `/familiares` | — | |
| GET | `/fornecedores` | — | |
| GET | `/bancos` | — | |

---

## 4. Categorias — CRUD

### POST `/categorias`
```json
{ "nome": "Mercado", "tipo": "DESPESA", "icone": "shopping-cart" }
```
**Response 201**: `CategoriaResource`

### GET `/categorias/{id}` · PUT `/categorias/{id}` · DELETE `/categorias/{id}`

**DELETE 409** quando em uso:
```json
{ "message": "Categoria em uso e não pode ser excluída.",
  "em_uso": { "despesas": true, "receitas": false } }
```

### Schema `CategoriaResource`
```json
{ "id": 1, "nome": "Mercado", "tipo": "DESPESA", "icone": "shopping-cart" }
```

---

## 5. Bancos — CRUD

### POST `/bancos`
```json
{
  "nome": "Nubank",
  "cor": "#7C3AED",
  "logo": null,
  "titular_id": 1,
  "codigo_banco": "260",
  "agencia": "0001",
  "conta": "12345-6",
  "tem_conta_corrente": true,
  "tem_poupanca": false,
  "tem_cartao_credito": true,
  "eh_dinheiro": false,
  "saldo": 1000.0,
  "saldo_poupanca": 0,
  "cheque_especial": 0,
  "saldo_cheque": 0,
  "limite_cartao": 5000.0,
  "saldo_cartao": 0,
  "dia_fechamento_cartao": 5,
  "dia_vencimento_cartao": 15
}
```

### GET `/bancos/{id}` · PUT `/bancos/{id}` · DELETE `/bancos/{id}`

**DELETE 409**: `em_uso { despesas, receitas, investimentos, transferencias }`

### Schema `BancoResource`
```json
{
  "id": 1, "nome": "Nubank", "cor": "#7C3AED", "logo": null,
  "titular_id": 1, "codigo_banco": "260", "agencia": "0001", "conta": "12345-6",
  "tem_conta_corrente": true, "tem_poupanca": false,
  "tem_cartao_credito": true, "eh_dinheiro": false,
  "saldo": 1000.0, "saldo_poupanca": 0.0,
  "cheque_especial": 0.0, "saldo_cheque": 0.0,
  "limite_cartao": 5000.0, "saldo_cartao": 0.0,
  "dia_fechamento_cartao": 5, "dia_vencimento_cartao": 15
}
```

---

## 6. Fornecedores — CRUD

### POST `/fornecedores`
```json
{
  "nome": "Mercado Bom Preço",
  "grupo": "Mercados",
  "icone": "store",
  "telefone": "(27) 99999-9999",
  "cnpj": "00.000.000/0001-00",
  "contato": "João",
  "observacoes": null
}
```

### GET / PUT / DELETE `/fornecedores/{id}`
**DELETE 409**: `em_uso { despesas }`

### Schema `FornecedorResource`
```json
{
  "id": 1, "nome": "...", "icone": "store", "grupo": "Mercados",
  "contato": "João", "cnpj": "...", "telefone": "...", "observacoes": null
}
```

---

## 7. Familiares — CRUD

### POST `/familiares`
```json
{
  "nome": "Maria",
  "foto": "familiares/abc.jpg",
  "salario": 5000.0,
  "limite_cartao": 3000.0,
  "limite_cheque": 1000.0
}
```

> O campo `acesso_sistema` (vincular usuário do sistema ao familiar) **não está no V1** — fica em feature separada.

### GET / PUT / DELETE `/familiares/{id}`
**DELETE 409**: `em_uso { despesas, receitas, bancos, usuario }`

### Schema `FamiliarResource`
```json
{
  "id": 1, "nome": "Maria",
  "foto": "familiares/abc.jpg",
  "foto_url": "https://.../storage/familiares/abc.jpg",
  "salario": 5000.0, "limite_cartao": 3000.0, "limite_cheque": 1000.0,
  "is_master": false
}
```

> `is_master = true` quando existe um `User` com este `familiar_id` e `role = master`. App usa para badge "titular" e bloqueio do botão excluir no titular.

---

## 8. Despesas — CRUD

### GET `/despesas`

**Query**: `inicio`, `fim`, `familiar_id`, `fornecedor_id`, `banco_id`, `categoria_id`, `tipo_pagamento`, `status` (`pago|a_pagar|vencido`), `pending_only` (`1` = `a_pagar` OU `vencido`, excluindo crédito), `per_page` (1–100, default 30)

**Response**: paginação Laravel padrão + `meta.total_valor`, `meta.periodo`

### POST `/despesas`
```json
{
  "valor": 150.0,
  "data_compra": "2026-05-10",
  "data_pagamento": "2026-05-10",
  "categoria_id": 1,
  "quem_comprou": 1,
  "onde_comprou": 2,
  "forma_pagamento": 3,
  "tipo_pagamento": "pix",
  "parcelas": 1,
  "frequencia": null,
  "recorrente": false,
  "observacoes": null,
  "numero_documento": null
}
```

**`tipo_pagamento`**: `dinheiro|pix|debito|credito|transferencia|boleto`
**`frequencia`**: `diaria|semanal|quinzenal|mensal|trimestral|semestral|anual`

> Para `tipo_pagamento=credito`, datas das parcelas são calculadas server-side pelo dia de fechamento/vencimento do banco.

### GET `/despesas/grupo/{grupoId}`
Lista todas as parcelas/recorrências do mesmo grupo, em ordem cronológica.

### GET / PUT / DELETE `/despesas/{id}`

- `PUT` aceita `escopo` no body: `apenas_esta` (default) ou `esta_e_futuras`
- `DELETE` aceita `escopo` na query: `apenas_esta` (default) ou `esta_e_futuras`

### Schema `DespesaResource`
```json
{
  "id": 1, "valor": 150.0,
  "data_compra": "2026-05-10", "data_pagamento": "2026-05-10",
  "tipo_pagamento": "pix", "status": "pago",
  "observacoes": null, "numero_documento": null,
  "parcelas": 1, "frequencia": null, "recorrente": false,
  "grupo_recorrencia_id": null, "origem": null,
  "categoria_id": 1, "quem_comprou": 1, "onde_comprou": 2, "forma_pagamento": 3,
  "categoria":  { "id": 1, "nome": "Mercado", "icone": "..." },
  "familiar":   { "id": 1, "nome": "Maria" },
  "fornecedor": { "id": 2, "nome": "..." },
  "banco":      { "id": 3, "nome": "Nubank", "cor": "#7C3AED" },
  "created_at": "...", "updated_at": "..."
}
```

---

## 9. Receitas — CRUD

Espelho de despesas com nomes próprios. `status`: `recebido|a_receber|vencido`.

### POST `/receitas`
```json
{
  "valor": 5000.0,
  "data_prevista_recebimento": "2026-05-05",
  "data_recebimento": null,
  "categoria_id": 1,
  "quem_recebeu": 1,
  "forma_recebimento": 2,
  "tipo_pagamento": "pix",
  "parcelas": 1,
  "frequencia": null,
  "recorrente": false,
  "observacoes": null
}
```

### GET `/receitas`
Mesmos filtros de `/despesas` exceto `fornecedor_id`. `status`: `recebido|a_receber|vencido`. `pending_only=1` retorna apenas pendentes (sem `data_recebimento`).

### Demais rotas
`GET/PUT/DELETE /receitas/{id}` — mesma semântica de `despesas`.

### Schema `ReceitaResource`
Igual ao de despesa, sem `fornecedor`, com `data_prevista_recebimento`/`data_recebimento` e `quem_recebeu`/`forma_recebimento`.

---

## 10. Investimentos — CRUD + rendimentos

### GET `/investimentos`
Lista todos do tenant (sem paginação) com `banco` e `rendimentos[]` carregados, e stats calculadas.

### POST `/investimentos`
```json
{
  "nome_ativo": "CDB Inter 110% CDI",
  "tipo_investimento": "rendaFixa",
  "data_aporte": "2026-03-15",
  "valor_aportado": 1000.0,
  "quantidade_cotas": 0,
  "percentual_mensal": null,
  "percentual_anual": null,
  "banco_id": 1,
  "observacoes": null
}
```

> `tipo_investimento` aceita qualquer string. App envia: `rendaFixa|rendaVariavel|cripto|fundos`.

### GET / PUT / DELETE `/investimentos/{id}`

### POST `/investimentos/{id}/rendimentos`
```json
{ "data": "2026-04-15", "valor_atual": 1014.5, "observacoes": null }
```

### DELETE `/investimentos/{id}/rendimentos/{rid}`

### Schema `InvestimentoResource`
```json
{
  "id": 1, "nome_ativo": "CDB Inter 110% CDI", "tipo_investimento": "rendaFixa",
  "data_aporte": "2026-03-15", "valor_aportado": 1000.0,
  "quantidade_cotas": 0.0, "percentual_mensal": null, "percentual_anual": null,
  "banco_id": 1, "observacoes": null,
  "banco": { "id": 1, "nome": "Inter", "cor": "#FF7A00" },
  "rendimentos": [
    { "id": 100, "investimento_id": 1, "data": "2026-04-15",
      "valor_atual": 1014.5, "observacoes": null, "created_at": "..." }
  ],
  "valor_atual": 1014.5, "ganho_reais": 14.5, "ganho_percentual": 1.45,
  "created_at": "...", "updated_at": "..."
}
```

> `valor_atual`, `ganho_reais` e `ganho_percentual` calculados server-side a partir do último rendimento.

---

## 11. Transferências — CRUD

> Entidade própria — substitui o workaround antigo de registrar transferência como despesa com `tipo_pagamento='transferencia'`. **Não infla KPIs.**

### GET `/transferencias`

**Query**: `inicio`, `fim`, `banco_id` (filtra onde `origem_id = banco_id` OR `destino_id = banco_id`), `per_page`

**Response**: paginação Laravel + `meta.periodo`

### POST `/transferencias`
```json
{
  "valor": 500.0,
  "data": "2026-05-10",
  "origem_id": 2,
  "destino_id": 3,
  "observacao": null
}
```

**Validação**: `origem_id ≠ destino_id`, ambos pertencem ao mesmo tenant.

> Server-side: o observer **automaticamente** debita `origem.saldo` e credita `destino.saldo`. Em update reverte o estado antigo e aplica o novo. Em delete (soft) estorna.

### GET / PUT / DELETE `/transferencias/{id}`

### Schema `TransferenciaResource`
```json
{
  "id": 42, "valor": 500.0, "data": "2026-05-10",
  "origem_id": 2, "destino_id": 3, "observacao": null,
  "origem":  { "id": 2, "nome": "Nubank", "cor": "#7C3AED" },
  "destino": { "id": 3, "nome": "Itaú",   "cor": "#F97316" },
  "created_at": "...", "updated_at": "..."
}
```

---

## 12. Códigos de erro e formato

Toda rota sob `/api/*` retorna **JSON**, nunca HTML.

| Status | Quando |
|---|---|
| `200` / `201` | Sucesso |
| `401` | Token ausente ou inválido |
| `403` | Sem permissão para o módulo/ação |
| `403` `code:tenant_inactive` | Usuário ou tenant inativo (**o token é revogado** — app deve forçar logout local) |
| `404` | Registro não existe ou pertence a outro tenant |
| `409` | Catálogo em uso (delete bloqueado) — payload tem `em_uso{}` |
| `422` | Validação |
| `5xx` | Erro de servidor |

### Formatos

```json
// 422
{ "message": "The given data was invalid.",
  "errors": { "valor": ["O valor deve ser maior que zero."] } }

// 403 sem permissão
{ "message": "Sem permissão para excluir bancos." }

// 403 tenant inativo
{ "message": "Seu tenant está desativado.", "code": "tenant_inactive" }

// 409 catálogo em uso
{ "message": "Banco em uso e não pode ser excluído.",
  "em_uso": { "despesas": true, "receitas": false, "investimentos": false, "transferencias": false } }
```

---

## 13. Pendências de deploy

Antes do app virar as flags `USE_REMOTE_*` para `true`:

1. **`php artisan migrate`** no servidor — cria a tabela `transferencias`
2. Garantir que as permissões existem para o role do usuário:
   `bancos`, `categorias`, `fornecedores`, `familiares`, `investimentos`, `transferencias`
   × `criar`, `editar`, `excluir`
   (usuários `master`, `super_admin` e `admin_revenda` bypassam — vide `User::temPermissao`)
3. Cache de rotas e config: `php artisan config:cache && php artisan route:cache`

### Não entregue (proposital)

- Migração de pares legados (despesa + receita com `tipo_pagamento='transferencia'`) → `Transferencia`. Deixar para PR separado após contagem no banco.
- Remoção de `'transferencia'` de `TIPOS_PAGAMENTO` nos `StoreDespesaRequest`/`StoreReceitaRequest`. PR separado depois da migração de dados.
