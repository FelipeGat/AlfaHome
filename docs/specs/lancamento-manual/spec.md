# Feature Specification: Lançamento manual (famílias sem planilha)

**Feature**: `lancamento-manual`
**Created**: 2026-10-07
**Status**: Draft

O Alexandre quer usar o AlfaHome sem planilha: cadastrar contas, cartões e
lançar entradas, saídas e transferências pelo próprio sistema, no site e no
app. Hoje o site não tem onde lançar (as telas antigas foram removidas em
06/10/2026), o Início novo só aparece para quem importou planilha e os
vencimentos, cartões e faturas leem só a planilha.

## Clarifications

### Session 2026-10-07

- Q: Conta? → A: Família separada; o Alexandre vê só os dados dele (Felipe).
- Q: Onde lançar? → A: Site e app (Felipe).
- Q: Cartão? → A: Sim, com fatura: compras no cartão entram na fatura do mês; ao pagar, sai da conta sem contar duas vezes (Felipe).
- Q: Convive com a planilha? → A: Sim. Uma família pode usar planilha, lançamento manual ou os dois; lançamentos da planilha continuam só de leitura.
- Q: Regra da compra no cartão? → A: A compra é a saída, na fatura em que cai (mesma regra da planilha); "Pagar fatura" marca as compras da fatura como pagas na data do pagamento e debita a conta escolhida. O pagamento não é uma saída a mais.

## User Scenarios & Testing

### User Story 1 - Lançar saída, entrada e transferência (Priority: P1)

**Acceptance Scenarios**:

1. **Given** o Início, **When** o Alexandre toca em "+ Lançar" e escolhe Saída, **Then** vê só valor, descrição, categoria, conta e data (hoje), e "Mais opções" com pago/pendente, parcelas, recorrência e observação.
2. **Given** uma saída paga de R$ 96,85 na conta Itaú, **When** salva, **Then** aparece no Extrato, sai do saldo do Itaú e soma em "Saiu" do mês, com a mensagem "Saída registrada."
3. **Given** uma entrada pendente, **When** salva, **Then** aparece em Próximos/a receber e não mexe no saldo até ser marcada como recebida.
4. **Given** uma transferência de R$ 500 do Itaú para o Sicoob, **When** salva, **Then** um saldo cai, o outro sobe, o total não muda e ela não entra em entrou/saiu.

### User Story 2 - Editar, marcar pago e excluir pelo Extrato (Priority: P1)

**Acceptance Scenarios**:

1. **Given** um lançamento do sistema no Extrato, **When** o Alexandre o abre, **Then** pode editar, marcar como pago/recebido e excluir (com "esta" ou "esta e as próximas" se for série).
2. **Given** um lançamento da planilha, **When** é aberto, **Then** não há editar nem excluir (continua só leitura).
3. **Given** uma exclusão, **When** confirmada, **Then** saldo, mês e Extrato se atualizam.

### User Story 3 - Cartão com fatura (Priority: P1)

**Acceptance Scenarios**:

1. **Given** uma conta com cartão (limite, fechamento e vencimento), **When** o Alexandre lança uma saída no cartão em 3 parcelas, **Then** cada parcela entra na fatura do seu mês de vencimento.
2. **Given** a fatura de outubro com R$ 800 em compras, **When** ele vê o Início e a tela de Cartões, **Then** vê usado, livre, fatura e vencimento do cartão, como acontece com a planilha.
3. **Given** a fatura de outubro, **When** ele toca em "Pagar fatura" e escolhe a conta Itaú, **Then** as compras da fatura ficam pagas na data, o Itaú cai R$ 800 e "Saiu" do mês não conta R$ 800 a mais.
4. **Given** a fatura paga, **When** vê Próximos pagamentos, **Then** a fatura aparece paga (verde) até o vencimento.

### User Story 4 - Início e vencimentos para quem não usa planilha (Priority: P1)

**Acceptance Scenarios**:

1. **Given** uma família sem planilha, **When** abre o Início (site ou app), **Then** vê o Início novo (saldo, mês, movimentações, pagamentos, cartões, gastos).
2. **Given** saídas e entradas pendentes do sistema, **When** vê Próximos pagamentos e Alertas, **Then** elas aparecem com data e valor, junto com as da planilha (se houver).
3. **Given** uma família sem contas, **When** abre o Início, **Then** vê "Vamos começar?" com "Adicionar conta".

### Edge Cases

- Família só com planilha: nada muda para ela; "+ Lançar" aparece mas os dados da planilha seguem só leitura.
- Lançamento em conta de outra família: recusado (403/422) — isolamento por família.
- Compra no cartão sem fechamento/vencimento cadastrados: entra na data da compra e o sistema avisa para completar o cadastro do cartão.
- Pagar fatura sem compras: botão não aparece.
- Valor com vírgula ("96,85") aceito no formulário.
- Erro ao salvar (conta inexistente, valor vazio): mensagem curta em português no campo, sem detalhes técnicos; sucesso mostra "Saída registrada." (FR-009).
- Outra família tentando abrir, editar ou pagar lançamento/fatura desta: recusado; tudo isolado por família (FR-010).

## Requirements

### Functional Requirements

- **FR-001**: Site e app MUST ter "+ Lançar" com Saída, Entrada e Transferência usando as regras e validações já existentes da API v1 (sem fluxo paralelo).
- **FR-002**: O formulário MUST pedir só valor, descrição, categoria, conta e data (padrão hoje); pago/pendente, parcelas, recorrência e observação MUST ficar em "Mais opções".
- **FR-003**: O Extrato MUST permitir editar, marcar pago/recebido e excluir lançamentos do sistema (com escopo de série) e MUST manter os da planilha só leitura.
- **FR-004**: Transferências entre contas próprias MUST aparecer no Extrato e no saldo, sem entrar em entrou/saiu.
- **FR-005**: Compras no cartão MUST cair na fatura do mês de vencimento (parcelas em meses seguintes).
- **FR-006**: "Pagar fatura" MUST marcar todas as compras pendentes da fatura como pagas na data e conta escolhidas, sem gerar saída adicional.
- **FR-007**: Início, Cartões, Próximos pagamentos, Alertas e avisos MUST considerar cartões cadastrados no sistema e lançamentos pendentes do sistema, além da planilha.
- **FR-008**: O Início novo MUST aparecer para qualquer família (com ou sem planilha); sem contas, MUST mostrar o primeiro passo "Adicionar conta".
- **FR-009**: Mensagens após salvar MUST ser curtas ("Saída registrada.") e erros MUST ser em português, sem detalhes técnicos.
- **FR-010**: Tudo MUST ser isolado por família.

## Success Criteria

- **SC-001**: Registrar uma saída comum em até 10 segundos no celular.
- **SC-002**: Pagar uma fatura de cartão não altera "Saiu" do mês (sem contagem dupla), comprovado por teste.

## Delta Requirements

**Skip**: sem corpus canônico em `docs/specs/current/` — Felipe/Claude, 2026-10-07
