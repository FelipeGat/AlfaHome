# Feature Specification: Planejamento financeiro a partir da planilha

**Feature**: `planejamento-planilha`
**Created**: 2026-10-03
**Status**: Draft

Fonte de todos os valores citados: leitura do arquivo
`Planejamento_Financeiro_Domestico - v2.xlsx` em 03/10/2026 (dados de
agosto/2026) e do código do AlfaHome na mesma data.

Esta spec cobre o sistema web e a API. O app mobile recebe as mesmas telas em
spec própria no repositório `AlfaHomeApp`, consumindo a API definida aqui.

## Clarifications

### Session 2026-10-03

Nenhuma pergunta precisou ir ao Felipe: as cinco ambiguidades abaixo foram
resolvidas pelas fontes (respostas dele no briefing, constitution, planilha e
código).

- Q: Registro vindo da planilha pode ser editado no sistema? → A: Não; é somente leitura, edita-se na planilha (briefing: "ela segue na planilha, eu importo"; constitution II).
- Q: Lançamento criado à mão no sistema entra nos totais do mês? → A: Sim, soma junto e fica identificado pela origem (FR-006 preserva; o módulo de despesas e receitas já existe).
- Q: Os números dos cartões vêm da planilha ou são calculados pelo sistema? → A: Da planilha, como informados (constitution II; a planilha digita limite utilizado e fatura, não deriva).
- Q: Importar lançamentos altera o saldo das contas bancárias? → A: Não; a planilha não informa saldo de conta (constitution I).
- Q: Como a planilha chega ao sistema? → A: Envio manual do arquivo pela tela (FR-001); leitura automática de pasta fica fora desta feature.

## User Scenarios & Testing

### User Story 1 - Importar a planilha (Priority: P1)

A Ju mantém o planejamento da casa numa planilha Excel e vai continuar assim. O
Felipe envia o arquivo dela ao sistema e, em uma única ação, tudo o que está na
planilha passa a estar no sistema: lançamentos, contas fixas, cartões, compras
parceladas, dívidas e metas. Quando ela atualizar a planilha, ele envia de novo
e o sistema fica igual à planilha.

**Why this priority**: é a única porta de entrada dos dados. Sem ela nenhuma
outra tela tem o que mostrar.

**Independent Test**: enviar a planilha de referência num sistema vazio e
conferir que as contagens e os totais do relatório da importação batem com os
da planilha; enviar de novo e conferir que nada mudou.

**Acceptance Scenarios**:

1. **Given** uma família sem dados, **When** o Felipe importa a planilha de
   referência, **Then** o sistema passa a ter 52 lançamentos, 9 contas fixas,
   3 cartões, 6 compras parceladas, 2 dívidas e 2 metas, e mostra um relatório
   com a contagem por aba.
2. **Given** a planilha já importada, **When** o mesmo arquivo é importado de
   novo, **Then** nenhum registro é criado, alterado ou removido e o relatório
   informa "sem alterações".
3. **Given** a planilha já importada, **When** a Ju altera o valor realizado de
   uma linha, acrescenta uma linha e apaga outra, e o arquivo é importado,
   **Then** o sistema reflete as três mudanças e o relatório lista uma
   atualizada, uma incluída e uma removida.
4. **Given** um arquivo com uma linha inválida (ex.: data ilegível ou valor
   não numérico), **When** é importado, **Then** nada é gravado e o relatório
   aponta a aba, a linha e o motivo, em português.
5. **Given** um arquivo que não é a planilha de planejamento (abas ou
   cabeçalhos diferentes), **When** é enviado, **Then** o sistema recusa o
   arquivo dizendo quais abas ou colunas faltam.
6. **Given** lançamentos criados à mão no sistema, **When** a planilha é
   importada, **Then** esses lançamentos permanecem intocados.
7. **Given** contas bancárias com saldo cadastrado, **When** a planilha é
   importada, **Then** o saldo de nenhuma conta bancária é alterado pelos
   lançamentos importados.
8. **Given** um registro vindo da planilha, **When** o Felipe tenta editá-lo ou
   excluí-lo no sistema, **Then** o sistema não permite e informa que o
   registro é mantido na planilha.

---

### User Story 2 - Ver o mês: previsto x realizado (Priority: P1)

O Felipe abre o sistema e vê o mês como a planilha mostra: quanto estava
previsto e quanto foi realizado de receitas e despesas, o saldo, a economia e o
comprometimento da renda, e as despesas por categoria.

**Why this priority**: é a pergunta que a planilha responde todo mês e a razão
de ele não precisar mais abri-la.

**Independent Test**: com a planilha de referência importada, abrir agosto/2026
e comparar cada número com a aba Planejamento Mensal.

**Acceptance Scenarios**:

1. **Given** a planilha de referência importada, **When** o Felipe abre o
   painel em agosto/2026, **Then** vê receitas previstas R$ 17.261,86 e
   realizadas R$ 17.632,86; despesas previstas R$ 12.217,44 e realizadas
   R$ 12.225,10; saldo realizado R$ 5.407,76; economia 30,67%;
   comprometimento 69,33%.
2. **Given** a planilha de referência importada, **When** o Felipe vê as
   despesas por categoria de agosto/2026, **Then** Moradia mostra R$ 3.000,00,
   Alimentação R$ 549,46, Investimentos R$ 4.683,05 e Pensão R$ 1.134,70, e a
   soma de todas as categorias é igual ao total de despesas realizadas.
3. **Given** um mês sem lançamentos, **When** é aberto, **Then** todos os
   valores aparecem zerados e os percentuais como 0%, sem erro.
4. **Given** o painel aberto, **When** o Felipe troca o mês de análise,
   **Then** todos os números da tela passam a ser os do mês escolhido.
5. **Given** a planilha de referência importada, **When** o Felipe abre a visão
   anual de 2026, **Then** cada mês mostra previsto x realizado e o total do
   ano é a soma dos meses.
6. **Given** a planilha importada e uma despesa criada à mão no sistema no
   mesmo mês, **When** o Felipe abre o mês, **Then** a despesa manual soma nos
   totais e aparece identificada pela origem, distinta dos lançamentos da
   planilha.
7. **Given** um lançamento com previsto R$ 1.200,00 e realizado R$ 1.571,00,
   **When** aparece na lista do mês, **Then** os dois valores são exibidos e a
   diferença é destacada.

---

### User Story 3 - Cartões e compras parceladas (Priority: P2)

O Felipe vê, por cartão, limite total, utilizado, disponível, fechamento,
vencimento e fatura atual; e a lista de compras parceladas com o quanto falta
de cada uma.

**Why this priority**: os cartões estão com 92,64% do limite utilizado; é a
informação que mais muda decisão de compra.

**Independent Test**: com a planilha importada, comparar a tela de cartões e a
de parceladas com as abas correspondentes.

**Acceptance Scenarios**:

1. **Given** a planilha de referência importada, **When** o Felipe abre os
   cartões, **Then** o Cartão Sicoob mostra limite R$ 23.593,00, utilizado
   R$ 21.737,85, disponível R$ 1.855,15, fechamento dia 12, vencimento dia 22 e
   fatura R$ 3.921,44.
2. **Given** a planilha de referência importada, **When** o Felipe vê o resumo
   de cartões, **Then** limite disponível total é R$ 1.855,15, faturas em
   aberto R$ 5.549,77 e limite utilizado 92,64%.
3. **Given** um cartão sem valores na planilha (Cartão Celebre), **When** é
   exibido, **Then** aparece com os campos vazios, sem zeros inventados e sem
   erro.
4. **Given** a planilha de referência importada, **When** o Felipe abre as
   compras parceladas, **Then** "Celular Iphone" mostra 10 parcelas de
   R$ 519,90, 6 pagas, 4 restantes e saldo R$ 2.079,60.
5. **Given** uma compra parcelada cujo cartão não está na aba Cartões
   ("Cartão Mercado Pago"), **When** a planilha é importada, **Then** a compra
   é importada com o nome do cartão como está na planilha e o relatório avisa
   que o cartão não está cadastrado.

---

### User Story 4 - Dívidas (Priority: P2)

O Felipe acompanha cada dívida: credor, saldo inicial e atual, taxa, parcela,
vencimento, prioridade e previsão de quitação, e o total devido.

**Why this priority**: não existe hoje no sistema e a planilha controla dois
empréstimos ativos.

**Independent Test**: comparar a tela de dívidas com a aba Dívidas.

**Acceptance Scenarios**:

1. **Given** a planilha de referência importada, **When** o Felipe abre as
   dívidas, **Then** vê "Empréstimo empresa" (Sicoob, saldo R$ 6.744,29 de
   R$ 15.000,00, taxa 5,2% ao mês, parcela R$ 720,21, prioridade Alta) e
   "Empréstimo pedreiro" (saldo R$ 3.430,37, parcela R$ 877,97), e saldo total
   de dívidas R$ 10.174,66.
2. **Given** uma dívida, **When** é exibida, **Then** mostra quanto já foi
   amortizado em valor e em percentual do saldo inicial.
3. **Given** nenhuma dívida, **When** a tela é aberta, **Then** mostra uma
   mensagem de lista vazia e total R$ 0,00.

---

### User Story 5 - Metas (Priority: P2)

O Felipe acompanha cada meta: objetivo, valor alvo, valor atual, quanto falta,
percentual concluído, prazo, prioridade e aporte mensal.

**Why this priority**: não existe hoje no sistema; é o lado "para onde vai a
sobra" do planejamento.

**Independent Test**: comparar a tela de metas com a aba Metas.

**Acceptance Scenarios**:

1. **Given** a planilha de referência importada, **When** o Felipe abre as
   metas, **Then** vê "Reserva de emergência" (alvo R$ 30.000,00, atual R$ 0,00,
   falta R$ 30.000,00, 0%, aporte R$ 1.000,00, sem prazo) e "Viagem" (alvo
   R$ 7.000,00, 0%, com prazo), e progresso médio 0%.
2. **Given** uma meta com valor atual maior que o alvo, **When** é exibida,
   **Then** falta mostra R$ 0,00 e o percentual não passa de 100% na barra.
3. **Given** uma meta sem prazo na planilha, **When** é exibida, **Then** o
   prazo aparece em branco.

---

### User Story 6 - Contas fixas (Priority: P3)

O Felipe vê a lista das contas que se repetem todo mês com dia de vencimento,
valor previsto, valor realizado, forma de pagamento e situação.

**Why this priority**: é uma lista de referência; na planilha ela não entra nos
totais do mês.

**Independent Test**: comparar a tela com a aba Contas Fixas.

**Acceptance Scenarios**:

1. **Given** a planilha de referência importada, **When** o Felipe abre as
   contas fixas, **Then** vê 9 contas ordenadas por dia de vencimento, entre
   elas Energia (dia 14, previsto R$ 340,00, realizado R$ 303,36, Pago).
2. **Given** as contas fixas importadas, **When** o Felipe vê o painel do mês,
   **Then** os totais do mês continuam sendo os dos lançamentos, sem somar as
   contas fixas em duplicidade.

---

### User Story 7 - Os mesmos dados no celular (Priority: P3)

O app mobile consegue ler tudo o que as telas acima mostram.

**Why this priority**: o app é entregue na mesma rodada, mas depende de as
telas web estarem certas primeiro.

**Independent Test**: consultar cada recurso pela API com um usuário
autenticado e comparar com a tela web.

**Acceptance Scenarios**:

1. **Given** a planilha importada e um usuário autenticado no app, **When** o
   app pede o resumo do mês, cartões, parceladas, dívidas, metas e contas
   fixas, **Then** recebe exatamente os mesmos valores exibidos na web.
2. **Given** um usuário de outra família, **When** pede qualquer um desses
   recursos, **Then** não recebe nenhum dado da família do Felipe.
3. **Given** um pedido sem autenticação, **When** chega à API, **Then** é
   recusado.

---

### Edge Cases

- Linha de lançamento sem ID (41 das 52 linhas): a importação identifica a
  linha pelo conteúdo e continua sem duplicar em reimportações.
- Duas linhas idênticas no mesmo dia (ex.: dois "Auto serviço marina"): as duas
  são mantidas como registros distintos.
- Receita com status "Pago" e despesa com status "Realizado": ambos são
  entendidos como concluído.
- Categoria usada num lançamento e ausente da aba Listas, ou com erro de
  digitação ("Tarifa báncaria"): é aceita como está escrita; o relatório lista
  as categorias novas.
- Descrição com espaço sobrando ("Padaria "): espaços das pontas são ignorados.
- Linhas vazias no fim de cada aba (a planilha tem 200 linhas preparadas com
  fórmula): são ignoradas.
- Aba ausente ou coluna renomeada: o arquivo é recusado inteiro com a lista do
  que falta.
- Arquivo que não é Excel, corrompido ou maior que o limite: recusado com
  mensagem clara.
- Percentuais com divisor zero (economia % e comprometimento % num mês sem
  receita realizada; progresso de meta com valor alvo zero): resultado 0%, sem
  erro.
- A planilha tem fórmulas com defeito (saldo do mês e economia do Dashboard
  dão 0; o Planejamento Anual dá 0 para 2026): o sistema mostra o valor correto
  calculado a partir dos lançamentos, e a divergência fica registrada como
  defeito da planilha.
- As categorias listadas no Dashboard da planilha não somam o total de despesas
  (faltam Restaurante, Padaria, Empréstimo, entre outras): o sistema mostra
  todas as categorias com movimento.
- Usuário sem permissão de dono da conta tenta importar: recusado.
- Importação interrompida no meio: nada fica gravado pela metade.

## Requirements

### Functional Requirements

- **FR-001**: O sistema MUST permitir ao dono da conta enviar o arquivo Excel
  da planilha de planejamento e importar, numa única ação, as abas Lançamentos,
  Contas Fixas, Cartões, Compras Parceladas, Dívidas e Metas.
- **FR-002**: O sistema MUST validar a estrutura do arquivo antes de gravar
  (abas e cabeçalhos esperados) e recusar o arquivo inteiro indicando o que
  falta.
- **FR-003**: A importação MUST ser atômica: havendo qualquer linha inválida,
  nada é gravado e o relatório indica aba, linha e motivo.
- **FR-004**: A importação MUST ser idempotente: importar o mesmo arquivo duas
  vezes não cria, altera nem remove registro.
- **FR-005**: Após a importação, os registros de origem planilha MUST refletir
  exatamente o arquivo: linhas novas incluídas, linhas alteradas atualizadas e
  linhas que deixaram de existir removidas.
- **FR-006**: A importação MUST NOT alterar nem remover registros criados fora
  da planilha.
- **FR-007**: O sistema MUST apresentar, ao fim de cada importação, um
  relatório por aba com quantas linhas foram incluídas, atualizadas, removidas
  e mantidas, e os avisos (categoria nova, cartão não cadastrado).
- **FR-008**: O sistema MUST guardar o histórico das importações (quando, quem,
  nome do arquivo e resultado).
- **FR-009**: Cada lançamento MUST guardar separadamente o valor previsto e o
  valor realizado; registrar o realizado MUST NOT alterar o previsto.
- **FR-010**: Cada lançamento importado MUST guardar data, tipo, descrição,
  categoria, forma de pagamento, conta ou cartão, situação e observação, como
  na planilha.
- **FR-011**: O sistema MUST ignorar linhas vazias, aparar espaços das pontas
  dos textos e manter linhas idênticas como registros distintos.
- **FR-012**: O sistema MUST aceitar categorias que não constam da lista de
  apoio, criando-as com o nome escrito na planilha.
- **FR-013**: O sistema MUST mostrar, para o mês escolhido, receitas previstas
  e realizadas, despesas previstas e realizadas, saldo previsto e realizado,
  economia % (saldo realizado sobre receita realizada) e comprometimento %
  (despesa realizada sobre receita realizada).
- **FR-014**: O sistema MUST mostrar as despesas realizadas do mês por
  categoria, incluindo todas as categorias com movimento.
- **FR-015**: O sistema MUST mostrar a visão anual: previsto x realizado mês a
  mês e o total do ano como soma dos meses.
- **FR-016**: O sistema MUST mostrar os lançamentos do mês com previsto,
  realizado e a diferença entre eles.
- **FR-017**: O sistema MUST mostrar, por cartão, limite total, limite
  utilizado, limite disponível, dia de fechamento, dia de vencimento, fatura
  atual e situação da fatura; e, no resumo, limite disponível total, total de
  faturas em aberto e percentual do limite utilizado.
- **FR-018**: O sistema MUST mostrar cada compra parcelada com cartão, data,
  valor total, número de parcelas, valor da parcela, parcelas pagas, parcelas
  restantes, saldo e próximo vencimento.
- **FR-019**: O sistema MUST mostrar cada dívida com credor, saldo inicial,
  saldo atual, valor e percentual amortizados, taxa mensal, parcela, dia de
  vencimento, situação, prioridade, previsão de quitação e observação; e o
  saldo total de dívidas.
- **FR-020**: O sistema MUST mostrar cada meta com objetivo, valor alvo, valor
  atual, quanto falta, percentual concluído, prazo, prioridade e aporte mensal;
  e o progresso médio das metas.
- **FR-021**: O sistema MUST mostrar as contas fixas com categoria, dia de
  vencimento, valor previsto, valor realizado, forma, situação e mês inicial,
  sem somá-las aos totais do mês.
- **FR-022**: Campo vazio na planilha MUST permanecer vazio no sistema; o
  sistema MUST NOT preencher valor que a planilha não informa.
- **FR-023**: Percentuais com divisor zero MUST resultar em 0%.
- **FR-024**: O painel inicial MUST reunir, para o mês de análise, os números
  do Dashboard da planilha: receitas, despesas e saldo realizados, economia %,
  limite disponível, faturas em aberto, saldo de dívidas, progresso médio das
  metas, série mensal e despesas por categoria.
- **FR-025**: Todos os dados desta feature MUST estar disponíveis ao app mobile
  pela API autenticada, com os mesmos valores exibidos na web.
- **FR-026**: Todos os dados desta feature MUST ser isolados por família: um
  usuário nunca lê nem altera dado de outra família.
- **FR-027**: Somente o dono da conta MUST poder importar a planilha.
- **FR-028**: Todas as mensagens da importação e das telas MUST estar em
  português.
- **FR-029**: O sistema MUST permitir a carga inicial com a planilha de
  referência num banco vazio, produzindo os números de agosto/2026 citados
  nesta spec.

- **FR-030**: Registros de origem planilha MUST ser somente leitura no sistema
  (web e app): não podem ser editados nem excluídos, e a tentativa informa que
  o registro é mantido na planilha.
- **FR-031**: Lançamentos importados da planilha MUST NOT alterar o saldo das
  contas bancárias.
- **FR-032**: Lançamentos criados à mão no sistema MUST somar nos totais do mês
  junto com os da planilha, e cada lançamento MUST exibir a sua origem.
- **FR-033**: Os valores de cartão (limite utilizado, fatura atual, situação)
  vindos da planilha MUST ser exibidos como informados, sem recálculo.

> Decisoes de infraestrutura: idempotência coberta por FR-004/FR-005 (a
> identidade de cada linha é definida no plano). Sem scheduling, rotação de
> chave, token externo ou trava entre réplicas — a importação é acionada
> manualmente por um usuário e a produção roda em instância única.

### Key Entities

- **Lançamento**: receita ou despesa de um dia, com descrição, categoria, forma
  de pagamento, conta ou cartão, valor previsto, valor realizado, situação e
  observação. Sabe se veio da planilha ou foi criado no sistema.
- **Conta fixa**: conta que se repete todo mês — nome, categoria, dia de
  vencimento, previsto, realizado, forma, situação e mês inicial.
- **Cartão**: limite total, utilizado, disponível, dias de fechamento e
  vencimento, fatura atual e situação.
- **Compra parcelada**: compra feita num cartão, com valor total, número de
  parcelas, parcelas pagas e próximo vencimento.
- **Dívida**: empréstimo com credor, saldo inicial e atual, taxa mensal,
  parcela, vencimento, situação, prioridade e previsão de quitação.
- **Meta**: objetivo com valor alvo, valor atual, prazo, prioridade e aporte
  mensal.
- **Importação**: cada envio da planilha, com data, autor, arquivo e resultado
  por aba.

## Success Criteria

### Measurable Outcomes

- **SC-001**: 100% dos números de agosto/2026 citados nesta spec aparecem
  idênticos no sistema após a importação da planilha de referência.
- **SC-002**: Importar a planilha de referência leva menos de 10 segundos do
  envio ao relatório.
- **SC-003**: Reimportar o mesmo arquivo resulta em zero registros alterados.
- **SC-004**: O Felipe responde "quanto sobrou este mês, quanto devo e quanto
  tenho de limite" em até 3 cliques a partir do login, sem abrir o Excel.
- **SC-005**: 100% das linhas da planilha terminam contabilizadas no relatório
  da importação (incluída, atualizada, mantida, removida ou rejeitada).
- **SC-006**: Nenhuma consulta de um usuário de outra família retorna dado
  desta feature.

## Delta Requirements

**Skip**: não existe corpus canônico em `docs/specs/current/`; esta é a primeira spec cstk do projeto — Felipe/Claude, 2026-10-03
