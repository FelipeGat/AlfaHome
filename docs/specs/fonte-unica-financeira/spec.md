# Feature Specification: Fonte única dos números e app simples de usar

**Feature**: `fonte-unica-financeira`
**Created**: 2026-10-05
**Status**: Draft

O Felipe pediu (05/10/2026) uma reestruturação: o AlfaHome deve responder
quanto tenho, quanto entrou, quanto saiu e onde gastei — com os mesmos números
em todas as telas. Hoje há dois mundos sem ligação (lançamentos manuais e
planilha), quatro definições de "despesas do mês", três de "saldo", saldo
negativo pintado de verde e moeda formatada de três jeitos.

## Clarifications

### Session 2026-10-05

- Q: Qual a fonte da verdade? → A: A planilha continua; o app apenas visualiza (Felipe). Sem botão de registrar.
- Q: Como se comporta o saldo da conta? → A: Calculado + ajuste (Felipe): último saldo informado + movimentos realizados depois dele; "Ajustar saldo" registra o saldo real.
- Q: Transferência na planilha? → A: Na planilha, "transferência" é forma de pagamento (TED/PIX de terceiros); continua entrada/saída. Transferência entre contas próprias só existe no sistema (tabela `transferencias`) e nunca é entrada/saída.
- Q: Cartão? → A: A compra é a saída, na data da fatura em que a planilha a registra; a planilha não registra o pagamento da fatura, então não há duplicação. Limite nunca é saldo.
- Q: O que é "realizado" na planilha? → A: A situação Pago/Realizado (06/10/2026: a Ju preenche o valor realizado de contas futuras, que seguem pendentes). Pago sem valor realizado vale o previsto.
- Q: Navegação do app? → A: Início | Extrato | Contas | Mais (sem "+", pois o app só visualiza).

## User Scenarios & Testing

### User Story 1 - Mesmos números em toda tela (Priority: P1)

**Acceptance Scenarios**:

1. **Given** saídas realizadas de R$ 389,13 em outubro na planilha, **When** o Felipe abre Início, Extrato, Resumo do mês e Previsto x Realizado, **Then** todas mostram saídas de R$ 389,13.
2. **Given** um mês sem nenhuma movimentação, **When** ele abre o Resumo, **Then** vê "Nenhuma movimentação neste período." e nenhuma conclusão como "você economizou".
3. **Given** uma compra no cartão registrada na planilha, **When** ele vê o mês, **Then** ela conta uma vez como saída e o limite do cartão não aparece somado ao saldo.

### User Story 2 - Saldo calculado com ajuste (Priority: P1)

**Acceptance Scenarios**:

1. **Given** o Sicoob ajustado em -189,02 em 05/10, **When** a planilha recebe uma saída paga de R$ 50 no Sicoob em 06/10, **Then** o saldo do Sicoob passa a -239,02 em todas as telas.
2. **Given** uma saída paga datada antes do ajuste, **When** a planilha é importada, **Then** o saldo não muda (o saldo informado já a incluía).
3. **Given** um lançamento pendente, **When** ele vence sem ser pago, **Then** o saldo não muda até ser marcado pago.
4. **Given** o saldo do app diferente do banco, **When** o Felipe usa "Ajustar saldo" e informa o real, **Then** o saldo passa a ser o informado e o ajuste fica registrado com data e diferença.
5. **Given** uma transferência entre contas próprias no sistema, **When** ela é feita, **Then** uma conta diminui, a outra aumenta, o saldo total não muda e ela não aparece em entradas nem saídas.
6. **Given** um lançamento excluído, **When** se recalcula, **Then** ele deixa de contar no saldo e nos totais.

### User Story 3 - Início, Extrato, Contas e Mais (Priority: P1)

**Acceptance Scenarios**:

1. **Given** o app aberto, **When** o Felipe está no Início, **Then** vê saldo total, entradas/saídas/saldo do mês, as últimas movimentações e no máximo 3 próximos pagamentos.
2. **Given** o Extrato, **When** ele busca "mercado" ou "96,85", **Then** encontra por descrição, categoria, conta ou valor; filtros rápidos Todos/Entradas/Saídas.
3. **Given** Contas, **When** uma conta está negativa, **Then** o valor aparece com sinal e em vermelho, nunca verde; tocar abre detalhe com movimentações e "Ajustar saldo".
4. **Given** Mais, **When** ele procura Cartões, Planejamento ou Resumo do mês, **Then** estão ali.

### Edge Cases

- Movimento da planilha numa conta sem cadastro (ex.: "Ticket"): não muda nenhum saldo e aparece em Contas como "sem conta cadastrada".
- Virada de mês: o Início mostra o mês novo; movimentos do mês anterior não entram em "este mês".
- Valor com centavos: somas em centavos inteiros, sem perda por arredondamento.
- Moeda: sempre "R$ 1.250,00" e "-R$ 98,15".

## Requirements

### Functional Requirements

- **FR-001**: Um serviço único MUST calcular saldo por conta, saldo total, entradas, saídas, resultado do mês, pendentes, categorias e últimas movimentações; web, API e avisos MUST usá-lo.
- **FR-002**: Saldo da conta MUST ser o último ajuste + entradas realizadas − saídas realizadas (planilha e sistema) com data posterior ao ajuste e até hoje, ± transferências entre contas próprias.
- **FR-003**: A primeira execução MUST criar, sem apagar nada, um ajuste por conta com o saldo atual na data de hoje.
- **FR-004**: "Ajustar saldo" MUST registrar saldo informado, data e diferença, e MUST atualizar o saldo em todas as telas.
- **FR-005**: Entradas e saídas do mês MUST ser o realizado do mês, idêntico ao Previsto x Realizado.
- **FR-006**: Transferência entre contas próprias MUST NOT contar como entrada ou saída.
- **FR-007**: Limite de cartão MUST NOT entrar em saldo; compra no cartão conta uma vez.
- **FR-008**: Mês sem movimentação MUST mostrar estado vazio sem conclusão financeira.
- **FR-009**: Valores negativos MUST aparecer com sinal "-R$" e cor de saída/negativo em web e app; formatação de moeda MUST ser centralizada.
- **FR-010**: O app MUST ter abas Início, Extrato, Contas e Mais, com Cartões, Planejamento e Resumo do mês em Mais.
- **FR-011**: A busca do Extrato MUST encontrar por descrição, categoria, conta e valor.
- **FR-012**: Movimento em conta sem cadastro MUST ser listado como sem conta, sem afetar saldos.

> Decisoes de infraestrutura:
> - **FR-INFRA-MIG**: migration aditiva `saldo_ajustes` (reversível); `bancos.saldo` passa a ser cache do saldo calculado, regravado após importação e ajuste.

## Success Criteria

- **SC-001**: Nenhuma tela mostra entrada/saída/saldo diferente de outra para o mesmo período.
- **SC-002**: O Felipe vê saldo, entrou e saiu do mês sem rolar a tela do Início.

## Delta Requirements

**Skip**: sem corpus canônico em `docs/specs/current/` — Felipe/Claude, 2026-10-05
