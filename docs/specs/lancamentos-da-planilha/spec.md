# Feature Specification: Lançamentos da planilha numa tela só

**Feature**: `lancamentos-da-planilha`
**Created**: 2026-10-05
**Status**: Draft

As telas Lançamentos, Despesas e Receitas aparecem zeradas: são do lançamento
manual do sistema antigo, e tudo o que a família lança vive na planilha. O
Felipe não entende o que está vendo. Decisão dele (05/10/2026): trocar as três
por **uma tela só, com os lançamentos da planilha**, no site e no app; menu
atual reorganizado, com Planilha e Avisos dentro de Configurações.

## Clarifications

### Session 2026-10-05

- Q: O que fazer com as telas zeradas? → A: Uma tela só, com a planilha (Felipe).
- Q: Menu? → A: Atual reorganizado; Planilha e Avisos em Configurações (Felipe).
- Q: Dá para lançar ou editar nessa tela? → A: Não. Quem edita é a Ju no Excel; a tela é de consulta (constitution II).

## User Scenarios & Testing

### User Story 1 - Ver os lançamentos do mês (Priority: P1)

O Felipe abre Lançamentos e vê o mês atual: quanto entrou, quanto saiu, o que
falta pagar e receber, e a lista dia a dia.

**Acceptance Scenarios**:

1. **Given** a planilha importada, **When** ele abre Lançamentos, **Then** vê o mês padrão com totais de entradas, saídas e saldo realizados, e totais pendentes a pagar e a receber.
2. **Given** a lista, **When** ele olha um lançamento, **Then** vê data, descrição, categoria, conta/cartão, valor (realizado ou previsto) e se está pago ou pendente.
3. **Given** um mês sem lançamentos, **When** ele escolhe esse mês, **Then** a tela diz que não há lançamentos no mês, sem erro.

### User Story 2 - Filtrar (Priority: P1)

**Acceptance Scenarios**:

1. **Given** a lista do mês, **When** ele escolhe Saídas ou Entradas, **Then** só aparecem despesas ou receitas.
2. **Given** a lista, **When** ele escolhe Pendentes ou Pagos, **Then** a lista e os totais da lista respeitam o filtro.
3. **Given** a lista, **When** ele digita "mercado" na busca, **Then** aparecem só os lançamentos com o termo na descrição, categoria ou conta, sem diferença de acento ou maiúscula.
4. **Given** a lista, **When** ele escolhe uma categoria ou uma conta/cartão, **Then** a lista se restringe a ela.

### User Story 3 - Menu sem telas vazias (Priority: P1)

**Acceptance Scenarios**:

1. **Given** o menu do site, **When** a família entra, **Then** a seção Lançamentos tem Lançamentos (planilha) e Investimentos; Despesas e Receitas manuais não aparecem; Planilha e Avisos ficam em Configurações.
2. **Given** o app, **When** ele toca na aba Lançamentos, **Then** vê os lançamentos da planilha, com os mesmos filtros.

### Edge Cases

- Lançamento sem valor na planilha: aparece com "—" e não soma.
- Endereço antigo `/despesas` ou `/receitas` digitado: continua abrindo (não quebra link salvo), só sai do menu.
- Outra família nunca vê lançamentos desta.
- Mês sem lançamentos: estado vazio explicado ("Nenhum lançamento na planilha neste mês"), com o seletor de mês ativo (FR-008).

## Requirements

### Functional Requirements

- **FR-001**: A tela Lançamentos MUST listar os lançamentos da planilha do mês escolhido, agrupados por dia.
- **FR-002**: A tela MUST mostrar totais realizados de entradas, saídas e saldo, e pendentes a pagar e a receber, do mês.
- **FR-003**: A tela MUST filtrar por tipo (entradas/saídas), situação (pagos/pendentes), busca de texto sem acento, categoria e conta/cartão.
- **FR-004**: A tela MUST ser somente leitura, com aviso de que os lançamentos são mantidos na planilha.
- **FR-005**: O menu do site MUST trocar Lançamentos/Despesas/Receitas por um item Lançamentos que abre esta tela; as rotas antigas MUST continuar respondendo.
- **FR-006**: A aba Lançamentos do app MUST mostrar esta mesma lista, com os mesmos filtros, lendo `GET /api/v1/planejamento/lancamentos`.
- **FR-007**: Os números MUST vir do `PlanejamentoService` (mesma definição de Previsto x Realizado).
- **FR-008**: Mês sem lançamentos MUST mostrar estado vazio explicado.

## Success Criteria

- **SC-001**: Nenhuma tela do menu da família abre zerada quando a planilha tem dados do mês.
- **SC-002**: O Felipe acha um lançamento pela busca em menos de 10 segundos.

## Delta Requirements

**Skip**: sem corpus canônico em `docs/specs/current/` — Felipe/Claude, 2026-10-05
