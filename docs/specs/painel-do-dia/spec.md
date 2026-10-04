# Feature Specification: Painel do dia

**Feature**: `painel-do-dia`
**Created**: 2026-10-04
**Status**: Draft

O Felipe quer abrir o app (ou o site) de manhã e saber, sem procurar: quanto
tem nas contas, o que está atrasado, o que tem para pagar e receber nos
próximos dias e como o mês vai fechar.

Referência de mercado (pesquisa de 04/10/2026): Mobills e Organizze abrem com
saldo consolidado de todas as contas, receitas x despesas do mês e contas a
pagar e receber; Monarch Money e Copilot destacam contas a vencer e projeção
de caixa ("cash flow forecast") logo na primeira tela.

## Clarifications

### Session 2026-10-04

- Q: De onde vem "quanto tenho"? → A: Do saldo das contas cadastradas no sistema (Sicoob, Itaú, Mercado Pago); a planilha não informa saldo de conta.
- Q: O que entra em "a pagar/receber"? → A: Os vencimentos que a tela de Alertas já usa (planilha): lançamentos e contas fixas pendentes, faturas abertas e parcelas de dívidas.
- Q: Substitui a tela inicial atual? → A: Sim, quando a planilha já foi importada o painel do dia é o topo da tela inicial na web e no app; os blocos antigos, que leem só lançamentos manuais e hoje mostram zero, saem do topo.

## User Scenarios & Testing

### User Story 1 - Saber o que tenho e o que vence (Priority: P1)

Ao abrir a tela inicial, o Felipe vê o saldo somado das contas (e de cada
conta), o total atrasado, o que vence hoje e nos próximos 7 dias (a pagar e a
receber, com data e valor) e a projeção do saldo no fim do mês.

**Why this priority**: é o pedido literal.

**Independent Test**: com as contas e a planilha de produção, conferir cada
número com a tela de Alertas e com os saldos das contas.

**Acceptance Scenarios**:

1. **Given** contas Sicoob (-189,02), Itaú (35,42) e Mercado Pago (55,45), **When** o painel abre, **Then** mostra saldo em contas -98,15 e cada conta com o seu saldo.
2. **Given** faturas e parcelas vencendo em 05/10, 07/10 e 13/10, **When** o painel abre em 04/10, **Then** elas aparecem em "Próximos 7 dias" com data e valor, e as de 22/10 e 30/10 não aparecem ali.
3. **Given** um lançamento pendente com data vencida, **When** o painel abre, **Then** o total atrasado aparece em destaque.
4. **Given** saldo em contas, a pagar e a receber até o fim do mês (inclusive o atrasado), **When** o painel abre, **Then** mostra a projeção: saldo + a receber − a pagar.
5. **Given** nada para vencer em 7 dias, **When** o painel abre, **Then** mostra "Nada vence nos próximos 7 dias".

### User Story 2 - Como está o mês (Priority: P2)

O painel mostra receitas e despesas realizadas no mês até hoje, o saldo do mês
e o limite disponível nos cartões.

**Acceptance Scenarios**:

1. **Given** o mês corrente, **When** o painel abre, **Then** mostra receitas, despesas e saldo realizados iguais aos do Planejamento do mês.
2. **Given** os cartões da planilha, **When** o painel abre, **Then** mostra o limite disponível somado e o percentual utilizado, iguais aos da tela de Cartões.

### User Story 3 - No celular (Priority: P1)

O app abre com o mesmo painel, com os mesmos números da web.

**Acceptance Scenarios**:

1. **Given** a API do painel, **When** o app e a web abrem, **Then** web e app mostram os mesmos números, vindos de uma única definição no servidor.
2. **Given** a planilha não importada, **When** o app abre, **Then** a tela inicial antiga continua aparecendo.

### Edge Cases

- Conta só de cartão (Celebre) não entra no saldo em contas.
- Saldo negativo aparece em vermelho, com sinal.
- Fatura e parcela de dívida sempre na próxima ocorrência do dia de vencimento (mesma regra de Alertas).
- Item sem valor na planilha entra na lista sem somar.
- Família sem contas: saldo em contas R$ 0,00 e convite para cadastrar.

## Requirements

### Functional Requirements

- **FR-001**: O sistema MUST mostrar o saldo somado das contas que não são só de cartão e o saldo de cada uma.
- **FR-002**: O sistema MUST mostrar o total atrasado (a pagar e a receber) e a lista dos itens atrasados.
- **FR-003**: O sistema MUST mostrar o que vence de hoje até 7 dias à frente, separando a pagar e a receber, com data e valor.
- **FR-004**: O sistema MUST mostrar a pagar e a receber até o fim do mês e a projeção do saldo no fim do mês (saldo em contas + a receber − a pagar, incluindo atrasados).
- **FR-005**: O sistema MUST mostrar receitas, despesas e saldo realizados do mês corrente com os mesmos valores do Planejamento.
- **FR-006**: O sistema MUST mostrar o limite disponível somado dos cartões e o percentual utilizado.
- **FR-007**: Web e app MUST mostrar os mesmos números, vindos de uma única definição no servidor.
- **FR-008**: A tela inicial MUST abrir com o painel do dia quando a planilha já foi importada.
- **FR-009**: Item sem valor MUST aparecer na lista sem entrar nas somas; saldo negativo MUST aparecer com sinal e em vermelho.

> Decisoes de infraestrutura: N/A (leitura; reusa vencimentos e resumo do mês).

### Key Entities

- **Painel do dia**: contas com saldo, atrasados, próximos 7 dias, totais do mês e projeção, resumo do mês e dos cartões.

## Success Criteria

- **SC-001**: Em até 5 segundos depois de abrir o app, o Felipe sabe quanto tem, o que vence na semana e como o mês fecha, sem tocar em nada.
- **SC-002**: 100% dos números do painel iguais aos das telas de Alertas, Planejamento e Contas.

## Delta Requirements

**Skip**: sem corpus canônico em `docs/specs/current/` — Felipe/Claude, 2026-10-04
