# Feature Specification: Notificações pelo Telegram

**Feature**: `notificacoes-telegram`
**Created**: 2026-10-04
**Status**: Draft

O Felipe quer receber no Telegram, sem abrir o sistema: o resumo do dia de
manhã e avisos na hora sobre contas a pagar e a receber, saldo negativo e
gastos no cartão. E-mail fica para depois (decisão dele em 04/10/2026).

Referência interna: o AlfaControl envia notificações com uma fila de envios no
banco (pendente → enviando → enviado/falhou), tomada atômica antes de enviar,
nova tentativa periódica com teto e deduplicação por destinatário. No
AlfaControl o Telegram é só um alerta fixo para o administrador; o vínculo por
usuário é novo aqui.

## Clarifications

### Session 2026-10-04

- Q: Canais? → A: Só Telegram por enquanto (Felipe).
- Q: Quando? → A: Resumo às 7h e alerta na hora (Felipe).
- Q: Cartão? → A: Cada compra nova, limite acima de 80% e fatura vencendo (Felipe).
- Q: Quem recebe? → A: Quem vincular o próprio Telegram à família — Felipe e a Ju.
- Q: Onde fica o token do bot? → A: Na configuração da família, cifrado, preenchido pela tela (sem acesso ao servidor para variável de ambiente).
- Q: Como acontece o envio às 7h sem agendador no servidor? → A: Um "relógio" externo (agendamento do GitHub Actions) chama, a cada 15 minutos, um endereço do sistema protegido por chave; o sistema decide o que está na hora de enviar. Se o servidor ganhar agendador, o mesmo comando roda por ele.

## User Scenarios & Testing

### User Story 1 - Vincular o Telegram (Priority: P1)

O Felipe abre Notificações no sistema, salva o token do bot e recebe um link.
Quem abrir o link no Telegram e tocar em "Iniciar" passa a receber os avisos
da família.

**Acceptance Scenarios**:

1. **Given** o token salvo, **When** o Felipe gera o link de vínculo, **Then** o sistema mostra `t.me/<bot>?start=<código>` válido por 7 dias.
2. **Given** o link, **When** alguém toca em Iniciar, **Then** o bot responde "Pronto! Você vai receber os avisos da família" e a pessoa aparece na lista de quem recebe.
3. **Given** um código inválido ou vencido, **When** alguém toca em Iniciar, **Then** o bot responde que o link não vale e nada é vinculado.
4. **Given** uma pessoa vinculada, **When** o Felipe a remove ou ela bloqueia o bot, **Then** ela deixa de receber avisos.
5. **Given** um token inválido, **When** o Felipe tenta salvar, **Then** o sistema recusa dizendo que o Telegram não reconheceu o token.

### User Story 2 - Resumo do dia às 7h (Priority: P1)

Todo dia às 7h cada pessoa vinculada recebe: saldo em contas, atrasados, o que
vence hoje, os próximos 7 dias e a previsão do fim do mês.

**Acceptance Scenarios**:

1. **Given** a família com pessoas vinculadas, **When** o relógio passa das 7h, **Then** cada uma recebe um único resumo no dia, com os números do painel do dia.
2. **Given** o relógio chamando várias vezes depois das 7h, **When** o resumo do dia já foi enviado, **Then** não envia de novo.
3. **Given** nada atrasado e nada vencendo, **When** o resumo sai, **Then** ele diz isso em vez de listas vazias.

### User Story 3 - Alertas na hora (Priority: P1)

**Acceptance Scenarios**:

1. **Given** uma conta a pagar (lançamento ou conta fixa pendente, fatura aberta, parcela de dívida), **When** faltam 3 dias e no dia do vencimento, **Then** chega um aviso com descrição, valor e data — uma vez por marco.
2. **Given** uma conta a receber prevista, **When** é o dia, **Then** chega um aviso; **When** passa do dia sem ser realizada, **Then** chega um aviso de atraso.
3. **Given** uma conta com saldo que fica negativo, **When** o sistema percebe, **Then** chega um aviso com o saldo; não repete enquanto continuar negativo, e avisa de novo se voltar a positivo e cair outra vez.
4. **Given** uma compra nova no cartão (vinda da planilha), **When** a planilha é analisada, **Then** chega um aviso com descrição, valor e cartão.
5. **Given** um cartão cujo uso do limite passa de 80%, **When** o sistema percebe, **Then** chega um aviso; não repete enquanto continuar acima.
6. **Given** a primeira análise depois de ligar os avisos, **When** a planilha já tinha dezenas de compras, **Then** não dispara um aviso por compra antiga (só a partir daí).

### User Story 4 - Ligar e desligar (Priority: P2)

**Acceptance Scenarios**:

1. **Given** a tela de Notificações, **When** o Felipe desliga um tipo de aviso, **Then** esse tipo deixa de ser enviado para a família.
2. **Given** a tela, **When** ele pede "Enviar teste", **Then** cada pessoa vinculada recebe uma mensagem de teste na hora.

### Edge Cases

- Telegram fora do ar ou erro temporário: o envio fica pendente e é tentado de novo até 5 vezes em 24h.
- Bot bloqueado pela pessoa (403) ou chat inexistente (400): o vínculo é desativado.
- Mesma ocorrência gerada duas vezes (relógio e análise juntos): só um envio por pessoa.
- Valor ausente na planilha: o aviso sai sem valor, com "valor não informado".
- Família de outra conta nunca recebe avisos desta.
- Link de vínculo gerado há mais de 7 dias: o código de uso da família vence e o bot recusa (FR-002).
- Conta a receber prevista para hoje: aviso no dia; se continuar sem ser realizada depois do dia, aviso de atraso uma única vez (FR-007).
- Relógio chamando o endereço sem a chave da família ou com chave errada: recusado com 403 e nada processado; o comando agendável `notificacoes:processar` faz o mesmo processamento (FR-014).

## Requirements

### Functional Requirements

- **FR-001**: O dono da conta MUST poder salvar o token do bot; o sistema MUST validá-lo no Telegram antes de gravar e MUST guardá-lo cifrado, sem exibi-lo depois.
- **FR-002**: O sistema MUST gerar um link de vínculo com código de uso da família, válido por 7 dias.
- **FR-003**: O sistema MUST vincular o chat de quem tocar em Iniciar com código válido e responder confirmando; código inválido MUST ser recusado com mensagem.
- **FR-004**: O dono MUST poder ver e remover as pessoas vinculadas.
- **FR-005**: O sistema MUST enviar às 7h (horário de Brasília) o resumo do dia, uma única vez por dia por pessoa.
- **FR-006**: O sistema MUST avisar contas a pagar 3 dias antes e no dia do vencimento, uma vez por marco.
- **FR-007**: O sistema MUST avisar contas a receber no dia e quando atrasarem.
- **FR-008**: O sistema MUST avisar quando uma conta ficar com saldo negativo, sem repetir enquanto continuar negativa.
- **FR-009**: O sistema MUST avisar cada compra nova no cartão que entrar pela planilha, exceto as já existentes quando os avisos foram ligados.
- **FR-010**: O sistema MUST avisar quando o uso do limite de um cartão passar de 80%, sem repetir enquanto continuar acima.
- **FR-011**: Cada envio MUST ficar registrado (destinatário, tipo, texto, situação, tentativas, erro) e ocorrências repetidas MUST NOT gerar envio duplicado.
- **FR-012**: Falha temporária MUST ser tentada de novo até 5 vezes em 24h; bloqueio ou chat inexistente MUST desativar o vínculo.
- **FR-013**: O dono MUST poder ligar e desligar cada tipo de aviso e enviar um teste.
- **FR-014**: O sistema MUST expor um endereço de "relógio", protegido por chave da família, que processa o que estiver na hora; o mesmo processamento MUST existir como comando agendável.
- **FR-015**: Tudo MUST ser isolado por família.

> Decisoes de infraestrutura:
> - **FR-INFRA-SCHED**: `autoSchedule = auto` — relógio externo (GitHub Actions a cada 15 min chamando o endereço com a chave) e `Schedule::command('notificacoes:processar')->everyFifteenMinutes()` para quando houver agendador.
> - **FR-INFRA-IDEMP**: chave única (família, destinatário, ocorrência) na tabela de envios; tomada atômica do envio antes de chamar o Telegram.
> - **FR-INFRA-KEY**: token do bot e chave do relógio cifrados com `APP_KEY`.

### Key Entities

- **Configuração de notificações** (por família): token do bot (cifrado), nome do bot, tipos ligados, chave do relógio, data do último resumo, marca de "ligado em".
- **Destinatário Telegram**: chat, nome, ativo, vinculado em.
- **Envio**: destinatário, tipo, chave da ocorrência, texto, situação, tentativas, erro, enviado em.
- **Estado de alerta**: último estado conhecido de saldo negativo e de limite acima de 80%, por conta/cartão.

## Success Criteria

- **SC-001**: Às 7h15 o Felipe já tem o resumo do dia no Telegram.
- **SC-002**: Nenhuma ocorrência gera mais de um aviso por pessoa.
- **SC-003**: Um aviso de compra no cartão chega em até 1 hora depois de a Ju lançar na planilha.

## Delta Requirements

**Skip**: sem corpus canônico em `docs/specs/current/` — Felipe/Claude, 2026-10-04
