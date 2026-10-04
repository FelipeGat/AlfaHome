# Project Briefing: AlfaHome

**Data**: 2026-10-03
**Status**: Draft
**Versao**: 1.0

Marcações: `[fornecido]` = dito pelo Felipe; `[inferido]` = derivado do código,
da documentação do repositório ou da planilha.

---

## 1. Visao e Proposito

**O que e**: sistema de gestão financeira pessoal e familiar — web (Laravel +
Blade) e app mobile (Flutter, `AlfaHomeApp`), no ar em
`home.alfasolucoes.cloud`. `[inferido]`

**Problema que resolve**: o planejamento financeiro da casa hoje vive numa
planilha Excel (`Planejamento_Financeiro_Domestico - v2.xlsx`) feita pela
esposa do Felipe. Ela não gosta de app nem de sistema online; ele não gosta de
planilha. O resultado é que o sistema que já existe não é usado por ninguém e
está "cheio de erros". `[fornecido]`

**Proposta de valor**: ela continua preenchendo a planilha do jeito dela; o
sistema **importa a planilha** e mostra para os dois, na web e no app, tudo o
que a planilha mostra — e o Felipe nunca mais precisa abrir o Excel.
`[fornecido]`

## 2. Usuarios e Stakeholders

| Ator | Papel | Acoes Principais |
|------|-------|-----------------|
| Felipe | Dono da conta (master do tenant) e dono do produto | Consulta dashboard, planejamento, dívidas e metas na web e no app; importa a planilha |
| Esposa do Felipe (Ju) | Quem mantém os dados | Preenche a planilha Excel; consulta o sistema se quiser |
| Rossini | Infraestrutura | Dá acesso ao servidor de produção (LXC 114 do alfa-server) |

**Stakeholders de decisao**: Felipe.

O sistema também tem papéis comerciais (super admin, revenda, clientes de
revenda) `[inferido]` — fora do foco desta rodada (ver §3).

## 3. Escopo

### MVP (Essencial)

1. **Importação da planilha** — enviar o `.xlsx` da Ju e o sistema atualizar
   tudo de uma vez; reimportar o mesmo arquivo não pode duplicar nada.
   `[fornecido]`
2. **Lançamentos com previsto x realizado** — cada receita/despesa guarda o
   valor previsto e o realizado (hoje há um único `valor`, e a baixa sobrescreve
   o previsto). `[inferido da planilha]`
3. **Contas fixas** — contas recorrentes com dia de vencimento, valor previsto
   x realizado e status do mês. `[inferido da planilha]`
4. **Cartões** — limite total, utilizado, disponível, fechamento, vencimento,
   fatura atual e status da fatura. `[inferido da planilha]`
5. **Compras parceladas** — compra, cartão, total, nº de parcelas, parcela
   atual, pagas, restantes, saldo e próximo vencimento. `[inferido da planilha]`
6. **Dívidas** — credor, saldo inicial e atual, taxa mensal, parcela,
   vencimento, status, prioridade e previsão de quitação. Não existe no sistema.
   `[inferido]`
7. **Metas** — objetivo, valor alvo, valor atual, falta, % concluído, prazo,
   prioridade e aporte mensal. Não existe no sistema. `[inferido]`
8. **Planejamento mensal e anual** — receitas e despesas previstas x realizadas,
   saldo, economia % e comprometimento % da renda. `[inferido da planilha]`
9. **Dashboard no formato da planilha** — receitas, despesas e saldo do mês,
   economia %, limite disponível, faturas em aberto, saldo de dívidas, progresso
   médio das metas, despesas por categoria e % do limite de cartão utilizado.
   `[inferido da planilha]`
10. **Correção completa do que a família usa** — login, dashboard, lançamentos,
    despesas, receitas, bancos e cartões, investimentos, fluxo de caixa,
    alertas, cadastros e perfil. `[fornecido]`
11. **Web e app juntos** — as telas novas saem também no app Flutter nesta
    rodada. `[fornecido]`
12. **Carga inicial** — banco zerado, família criada e as 10 abas da planilha
    importadas (dados de agosto/2026). `[fornecido]`

### Pos-MVP (Desejavel)

1. Integração Sicoob (há trabalho iniciado e não commitado na cópia do Windows
   em `C:\xampp\htdocs\AlfaHome`). `[inferido]`
2. Tela em grade tipo planilha para lançar direto no sistema. `[inferido]`

### Fora de Escopo

- Parte comercial: painel de super admin, revendas, planos, landing e
  indicação — só é tocada se estiver quebrando o uso da família. `[fornecido]`
- Preservar os dados atuais do banco: ninguém usa ainda, pode zerar.
  `[fornecido]`

## 4. Prioridades e Trade-offs

**Ordem de prioridade**: números iguais aos da planilha > correção > cobertura
de telas > acabamento visual. `[inferido]`

**Decisoes explicitas**:
- A planilha é a fonte dos dados; o sistema é a visão. `[fornecido]`
- Pode zerar o banco, inclusive em produção. `[fornecido]`
- Trabalho local primeiro; produção quando houver acesso. `[fornecido]`

## 5. Restricoes

| Restricao | Valor | Notas |
|-----------|-------|-------|
| Prazo | flexível | não informado |
| Equipe | Felipe + Claude; Rossini para infraestrutura | |
| Tecnica | Laravel 12 / PHP 8.2 / MySQL 8 / Blade + Tailwind; Flutter no app | stack já existente `[inferido]` |
| Tecnica | Multi-tenant por `tenant_id` (trait `BelongsToTenant`) | toda tabela nova segue o padrão `[inferido]` |
| Tecnica | Produção no LXC 114 do alfa-server, deploy por tag `v*` com CI verde | sem acesso SSH no momento `[inferido]` |
| Processo | Commit, push, tag e deploy só com autorização explícita | regra global do Felipe |

## 6. Stack Tecnica

| Camada | Tecnologia | Justificativa |
|--------|-----------|---------------|
| Backend | Laravel 12, PHP 8.2, Sanctum | existente |
| Frontend web | Blade, Tailwind, Vite | existente |
| App | Flutter (Dart 3.11), consome API v1 | existente |
| Banco de dados | MySQL 8 | existente |
| Infraestrutura | Docker Compose; Cloudflare Tunnel; GHCR + watcher de tag | existente |
| Integracoes | Anthropic API (leitura de cupom), OFX/CSV, push | existente |

**Ambiente local (WSL)**: `docker compose -p alfahome-dev -f docker-compose.local.yml up -d`,
web em `http://localhost:8095` (arquivo não versionado).

## 7. Qualidade e Padroes

**Padroes adotados**:
- Suíte PHPUnit roda no CI e é condição para deploy. `[inferido]`
- Toda funcionalidade vinda da planilha é conferida contra os números da
  própria planilha (ex.: agosto/2026 — receitas realizadas R$ 17.632,86,
  despesas realizadas R$ 12.225,10). `[inferido]`
- Mensagens ao usuário em português (hoje aparecem chaves cruas como
  `auth.failed`). `[inferido]`

**Compliance**: LGPD — dados financeiros pessoais. `[inferido]`

## 8. Visao de Futuro

**Riscos conhecidos**:
- A planilha é livre: categorias novas, texto com erro de digitação
  ("Tarifa báncaria"), "Receita" marcada como "Pago", linha sem ID. O importador
  precisa tolerar isso sem duplicar nem perder linha.
- O login de produção com as credenciais informadas foi recusado em 03/10/2026;
  sem acesso SSH ao servidor, a publicação depende do Rossini.
- O app Flutter local está 25 commits atrás do remoto e com alterações não
  commitadas.

---

## Itens a Definir

| Item | Dimensao | Impacto |
|------|----------|---------|
| Acesso SSH ao LXC 114 e senha válida em produção | Infraestrutura | Alto |
| Destino do trabalho Sicoob não commitado na cópia do Windows | Escopo | Médio |
| Como a planilha chega ao sistema no dia a dia (upload manual x pasta do OneDrive) | Escopo | Médio |

---

**Proximo passo recomendado**: `/constitution` para definir principios de governanca
