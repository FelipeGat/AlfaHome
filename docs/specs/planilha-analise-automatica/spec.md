# Feature Specification: Análise automática da planilha

**Feature**: `planilha-analise-automatica`
**Created**: 2026-10-04
**Status**: Draft

Continuação de `planejamento-planilha`: hoje a planilha só entra no sistema
quando alguém envia o arquivo. O Felipe não quer importar à mão: o sistema deve
buscar a planilha sozinho todos os dias e também quando ele apertar "Analisar".

## Clarifications

### Session 2026-10-04

- Q: Como o servidor alcança a planilha, que vive no OneDrive da família? → A: Por um link de compartilhamento do OneDrive colado uma vez no sistema (resposta do Felipe).
- Q: O envio manual do arquivo continua existindo? → A: Sim, como alternativa (a tela já existe).
- Q: O que acontece todo dia quando a planilha não mudou? → A: Nada é gravado além da data da última verificação; não entra linha nova no histórico.
- Q: Quem pode configurar o link e apertar Analisar? → A: O dono da conta configura; Analisar fica disponível para o dono da conta.
- Q: O servidor de produção tem agendador ligado? → A: Não há evidência no repositório; por isso a verificação diária também acontece no primeiro acesso do dia, sem depender do agendador.

## User Scenarios & Testing

### User Story 1 - Configurar o link da planilha (Priority: P1)

O Felipe cola no sistema o link de compartilhamento da planilha no OneDrive. O
sistema testa o link na hora e diz se conseguiu ler a planilha.

**Why this priority**: sem o link nada mais funciona.

**Independent Test**: salvar um link válido e ver a primeira análise acontecer;
salvar um link inválido e ver a recusa com o motivo.

**Acceptance Scenarios**:

1. **Given** o dono da conta na tela de importação, **When** salva um link de
   compartilhamento que aponta para a planilha, **Then** o sistema baixa o
   arquivo, importa e mostra o relatório da importação e a data da verificação.
2. **Given** um link que não é do OneDrive, **When** tenta salvar, **Then** o
   sistema recusa dizendo que só aceita link de compartilhamento do OneDrive.
3. **Given** um link do OneDrive que não devolve uma planilha Excel, **When**
   salva, **Then** o sistema não grava o link e explica que não conseguiu ler
   uma planilha nesse endereço.
4. **Given** um link configurado, **When** o dono da conta o remove, **Then** a
   análise automática para e os dados já importados permanecem.
5. **Given** um usuário que não é o dono da conta, **When** tenta configurar ou
   remover o link, **Then** é recusado.
6. **Given** o link salvo, **When** qualquer tela é exibida, **Then** o endereço
   completo do link não aparece, só a indicação de que está configurado.

---

### User Story 2 - Botão Analisar (Priority: P1)

Com o link configurado, o Felipe aperta "Analisar" e o sistema busca a versão
atual da planilha e mostra o que mudou.

**Why this priority**: é o pedido direto dele — ver na hora o que ela lançou.

**Independent Test**: alterar a planilha de origem, apertar Analisar e conferir
o relatório.

**Acceptance Scenarios**:

1. **Given** o link configurado e a planilha alterada desde a última análise,
   **When** o Felipe aperta Analisar, **Then** o sistema importa a versão atual
   e mostra quantas linhas foram incluídas, atualizadas e removidas por aba.
2. **Given** a planilha igual à da última análise, **When** aperta Analisar,
   **Then** o sistema informa que não houve alterações.
3. **Given** a planilha com uma linha inválida, **When** aperta Analisar,
   **Then** nada é alterado e o sistema mostra aba, linha e motivo.
4. **Given** o OneDrive fora do ar ou o link revogado, **When** aperta
   Analisar, **Then** o sistema informa que não conseguiu baixar a planilha e
   mantém os dados anteriores.
5. **Given** nenhum link configurado, **When** a tela é aberta, **Then** o
   botão Analisar não aparece e a tela convida a configurar o link.

---

### User Story 3 - Análise diária sem ninguém pedir (Priority: P2)

Todo dia o sistema confere a planilha sozinho, para que o Felipe abra o sistema
ou o app e já veja os lançamentos do dia anterior.

**Why this priority**: tira dele a obrigação de lembrar.

**Independent Test**: com o link configurado e a última verificação de ontem,
acessar o sistema e ver a verificação de hoje registrada.

**Acceptance Scenarios**:

1. **Given** o link configurado e nenhuma verificação hoje, **When** alguém da
   família acessa o sistema ou o app, **Then** a planilha é verificada uma vez
   e a tela mostra a data da última verificação.
2. **Given** uma verificação já feita hoje, **When** há novos acessos, **Then**
   nenhuma nova verificação automática acontece.
3. **Given** a planilha sem mudança, **When** a verificação diária roda,
   **Then** só a data da última verificação muda; o histórico de importações
   não ganha linha.
4. **Given** a verificação diária falhando (link revogado, planilha inválida),
   **When** o Felipe abre o Planejamento, **Then** vê um aviso com o motivo e os
   dados anteriores continuam visíveis.
5. **Given** duas famílias com link e a planilha de uma delas inválida,
   **When** o comando agendável de verificação roda, **Then** ele verifica
   todas as famílias com link e a falha de uma fica isolada, sem impedir a
   outra.

---

### Edge Cases

- Arquivo baixado maior que 5 MB: recusado com mensagem clara.
- Resposta do OneDrive que é uma página HTML (link de pasta, link expirado): tratada como "não é uma planilha".
- Dois acessos simultâneos no primeiro acesso do dia: só uma verificação acontece.
- Link apontando para endereço interno ou outro site: recusado antes de qualquer acesso.
- Demora do OneDrive: a verificação diária não atrasa a tela de quem acessou.
- Família de outra conta: nunca usa nem enxerga o link desta.
- Envio manual do arquivo continua funcionando com ou sem link configurado.

## Requirements

### Functional Requirements

- **FR-001**: O dono da conta MUST poder salvar um link de compartilhamento do OneDrive que aponta para a planilha.
- **FR-002**: O sistema MUST aceitar somente link de compartilhamento do OneDrive e recusar qualquer outro endereço antes de acessá-lo.
- **FR-003**: Ao salvar o link, o sistema MUST baixar e importar a planilha na hora e só gravar o link se conseguir ler uma planilha Excel.
- **FR-004**: O dono da conta MUST poder remover o link; os dados já importados permanecem.
- **FR-005**: O sistema MUST NOT exibir o endereço completo do link depois de salvo, e MUST guardá-lo de forma que não seja legível no banco de dados.
- **FR-006**: Com link configurado, o dono da conta MUST poder acionar Analisar, que importa a versão atual da planilha e mostra o relatório.
- **FR-007**: A análise MUST seguir as mesmas regras da importação manual: atômica, idempotente, relatório por aba e rejeição com aba, linha e motivo.
- **FR-008**: Falha ao baixar a planilha MUST manter os dados anteriores e informar o motivo em português.
- **FR-009**: O sistema MUST verificar a planilha automaticamente no máximo uma vez por dia por família, no primeiro acesso do dia ao sistema ou ao app.
- **FR-010**: A verificação automática MUST NOT atrasar a resposta de quem acessou.
- **FR-011**: Verificação automática sem alteração na planilha MUST registrar apenas a data da verificação, sem linha nova no histórico.
- **FR-012**: O resultado da última verificação (data, sucesso ou motivo da falha) MUST aparecer nas telas de planejamento.
- **FR-013**: O sistema MUST oferecer um comando agendável que verifica todas as famílias com link, isolando falhas.
- **FR-014**: Arquivo baixado maior que 5 MB ou que não seja planilha Excel MUST ser recusado.
- **FR-015**: Somente o dono da conta MUST configurar o link, removê-lo e acionar Analisar; o link de uma família MUST NOT ser usado nem visto por outra.
- **FR-016**: O app MUST poder acionar Analisar e consultar a última verificação pela API.

> Decisoes de infraestrutura:
> - **FR-INFRA-SCHED**: `autoSchedule = auto` — comando `planilha:analisar` agendado para as 06:00 **e** verificação no primeiro acesso do dia; a segunda garante o funcionamento sem agendador no servidor.
> - **FR-INFRA-IDEMP**: a importação já é idempotente; a trava diária é a data da última verificação gravada antes de iniciar o download.
> - **FR-INFRA-KEY**: o link é cifrado com a chave da aplicação (`APP_KEY`); trocar a chave exige salvar o link de novo.
> - Sem token com validade: link de compartilhamento anônimo não expira por conta própria.

### Key Entities

- **Fonte da planilha**: uma por família — link (cifrado), data e resultado da última verificação, motivo da última falha e a identificação do último arquivo importado.

## Success Criteria

### Measurable Outcomes

- **SC-001**: Depois de configurar o link, o Felipe vê os lançamentos do dia anterior sem enviar arquivo nenhum.
- **SC-002**: Apertar Analisar devolve o relatório em menos de 15 segundos.
- **SC-003**: Um mês de verificações diárias sem mudança na planilha não acrescenta nenhuma linha ao histórico de importações.
- **SC-004**: 100% das falhas de download ou de leitura aparecem para o Felipe com o motivo, em português.

## Delta Requirements

**Skip**: não existe corpus canônico em `docs/specs/current/` — Felipe/Claude, 2026-10-04
