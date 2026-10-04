# Data Model: planejamento-planilha

Convenções comuns a todas as tabelas `plan_*`:

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | FK tenants, cascade | obrigatório; trait `BelongsToTenant` |
| importacao_id | FK planilha_importacoes, set null | última importação que incluiu ou alterou a linha |
| chave | char(40) | identidade da linha (research, Decision 3); único por `tenant_id` |
| conteudo_hash | char(40) | detecta alteração |
| linha | smallint | número da linha na aba, para mensagens |
| timestamps | | |

Sem soft delete: linha removida da planilha é removida do sistema (FR-005); o
histórico fica no relatório da importação.

Valores monetários: `decimal(12,2)`, nullable quando a planilha pode deixar
vazio (FR-022). Percentuais informados (taxa): `decimal(8,6)` como fração
(5,2% = 0.052000).

## Entity: PlanilhaImportacao (`planilha_importacoes`)

| Campo | Tipo | Notas |
|-------|------|-------|
| id, tenant_id, timestamps | | |
| user_id | FK users, set null | quem importou |
| arquivo_nome | string | nome original |
| arquivo_hash | char(64) | SHA-256 do arquivo |
| status | string(20) | `sucesso`, `sem_alteracoes`, `rejeitada` |
| resumo | json | por aba: incluidas, atualizadas, removidas, mantidas |
| avisos | json | lista de `{aba, linha, mensagem}` |
| erros | json | lista de `{aba, linha, mensagem}` (quando rejeitada) |

Importação rejeitada também é registrada (fora da transação dos dados).

## Entity: PlanLancamento (`plan_lancamentos`)

| Campo | Tipo | Planilha (aba Lançamentos) |
|-------|------|----------------------------|
| data | date | A Data |
| tipo | string(10) | C Tipo → `receita` / `despesa` |
| descricao | string | D Descrição |
| categoria | string | E Categoria (texto como escrito) |
| categoria_id | FK categorias, set null | categoria do sistema com o mesmo nome e tipo; criada se não existir (FR-012) |
| forma | string(20) nullable | F Forma (normalizada) |
| conta | string nullable | G Conta/Cartão |
| valor_previsto | decimal nullable | H |
| valor_realizado | decimal nullable | I |
| status | string(20) | J → `concluido` / `pendente` |
| status_planilha | string | J como escrito |
| observacao | text nullable | K |
| id_planilha | string nullable | L |

Índice: (`tenant_id`, `data`), (`tenant_id`, `tipo`, `data`). Coluna B (Mês) é
fórmula — não importada.

## Entity: PlanContaFixa (`plan_contas_fixas`)

conta (A), categoria (B), dia_vencimento tinyint (C), valor_previsto (D),
valor_realizado (E), forma (F), recorrente bool (G), status / status_planilha
(H), mes_inicial date nullable (I), observacao (J).

## Entity: PlanCartao (`plan_cartoes`)

nome (A), banco (B), limite_total (C), limite_utilizado (D), dia_fechamento
tinyint (F), dia_vencimento tinyint (G), fatura_atual (H), status_fatura string
nullable (I), observacao (J). Todos os valores nullable.
`limite_disponivel` é calculado (C − D) quando ambos informados — coluna E é
fórmula.

## Entity: PlanCompraParcelada (`plan_compras_parceladas`)

compra (A), cartao string (B, texto como escrito), plan_cartao_id FK nullable
(cartão de mesmo nome, se existir), data (C), valor_total (D), parcelas (E),
parcela_atual (F), parcelas_pagas (H), proximo_vencimento date nullable (K),
observacao (L). Calculados: valor_parcela = total ÷ parcelas; restantes =
max(parcelas − pagas, 0); saldo = valor_parcela × restantes (colunas G, I, J
são fórmula).

## Entity: PlanDivida (`plan_dividas`)

nome (A), credor (B), saldo_inicial (C), saldo_atual (D), taxa_mensal fração
(E), parcela_mensal (F), dia_vencimento tinyint (G), status string (H),
prioridade string(10) → `alta`/`media`/`baixa` (I), previsao_quitacao date
nullable (J), observacao (K). Calculados: amortizado = inicial − atual;
% amortizado = amortizado ÷ inicial.

## Entity: PlanMeta (`plan_metas`)

nome (A), objetivo (B), valor_alvo (C), valor_atual (D), prazo date nullable
(G), prioridade (H), aporte_mensal (I), observacao (J). Calculados: falta =
max(alvo − atual, 0); % concluído = atual ÷ alvo (colunas E e F são fórmula).

## Alterações em tabelas existentes

| Tabela | Coluna | Notas |
|--------|--------|-------|
| despesas | valor_previsto decimal(10,2) nullable | preenchida na baixa com valor diferente |
| receitas | valor_previsto decimal(10,2) nullable | idem |

## Estados

`PlanLancamento.status`, `PlanContaFixa.status`: `pendente` ↔ `concluido`,
definidos só pela planilha. Não há transição iniciada pelo sistema (FR-030).
