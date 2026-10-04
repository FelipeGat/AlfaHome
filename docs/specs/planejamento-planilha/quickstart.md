# Quickstart: planejamento-planilha

Ambiente local: `docker compose -p alfahome-dev -f docker-compose.local.yml up -d`
→ `http://localhost:8095`. Planilha de referência:
`tests/Fixtures/planilha/planejamento_v2.xlsx` (cópia do arquivo da Ju de
03/10/2026).

## Cenário 1 — Carga inicial (happy path)

1. `php artisan migrate:fresh --seed`
2. `php artisan planilha:importar tests/Fixtures/planilha/planejamento_v2.xlsx --tenant=<id>`
3. **Expected**: relatório com Lançamentos 52 incluídas, Contas Fixas 9,
   Cartões 3, Parceladas 6, Dívidas 2, Metas 2; avisos de categorias novas e de
   "Cartão Mercado Pago" não cadastrado.

## Cenário 2 — Reimportação idempotente

1. Repetir o passo 2 do cenário 1.
2. **Expected**: status `sem_alteracoes`; nenhuma linha `updated_at` muda.

## Cenário 3 — Números de agosto/2026

1. Entrar como dono da conta e abrir Planejamento em agosto/2026.
2. **Expected**: receitas 17.261,86 / 17.632,86; despesas 12.217,44 /
   12.225,10; saldo realizado 5.407,76; economia 30,67%; comprometimento
   69,33%; limite disponível 1.855,15; faturas em aberto 5.549,77; dívidas
   10.174,66; metas 0%.

## Cenário 4 — Arquivo inválido (erro)

1. Enviar na tela Importar um `.xlsx` sem a aba "Dívidas".
2. **Expected**: mensagem em português listando a aba ausente; nenhuma tabela
   `plan_*` muda; a tentativa aparece no histórico como rejeitada.

## Cenário 5 — Linha inválida (erro, atomicidade)

1. Importar cópia da planilha com texto no lugar de um valor em Lançamentos.
2. **Expected**: rejeitada com aba, linha e motivo; dados anteriores intactos.

## Cenário 6 — Roundtrip End-to-End (API real)

1. `POST /api/v1/auth/login` com o usuário dono → token.
2. `GET /api/v1/planejamento/resumo?mes=2026-08` com o token, contra o backend
   real (sem mock).
3. **Expected**: o JSON tem exatamente as chaves de
   `contracts/api-v1-planejamento.md` (snake_case) e os valores do cenário 3.
4. Repetir para `cartoes`, `parceladas`, `dividas`, `metas`, `contas-fixas`.
5. Com token de usuário de outro tenant: listas vazias e resumo zerado.

## Cenário 7 — Somente leitura

1. Tentar editar ou excluir um lançamento de origem planilha.
2. **Expected**: não há ação de edição na tela; a API não expõe rota de escrita
   para `plan_*`.
