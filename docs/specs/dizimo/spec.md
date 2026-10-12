# Feature Specification: Dízimo semanal calculado

**Feature**: `dizimo`
**Created**: 2026-10-12
**Status**: Draft

O Felipe devolve 10% de toda receita da casa como dízimo, todo domingo: a
receita que entra numa semana gera o dízimo do domingo daquela semana. Hoje os
dízimos pagos estão na planilha, mas ninguém confere se o devolvido bate com
10% do que entrou.

## Clarifications

### Session 2026-10-12

- Q: Base? → A: Salários, pró-labore, Vixcard, trimestral, adiantamentos e ticket (categorias Renda fixa e Renda variável). Fora: venda da casa (passa a "Venda de imóvel"), reembolsos e "Outros" (Felipe).
- Q: Onde fica? → A: Planilha (dízimos previstos por domingo) + cálculo automático no sistema (Felipe).
- Q: Qual domingo? → A: O da data da receita, ou o primeiro depois dela; o mês do domingo é o mês do dízimo.

## User Scenarios & Testing

### User Story 1 - Ver o dízimo do mês (Priority: P1)

**Acceptance Scenarios**:

1. **Given** R$ 7.500 de Vixcard recebidos numa quinta, **When** o Felipe abre o mês, **Then** vê R$ 750 previstos para o domingo seguinte.
2. **Given** R$ 914,92 de "Devolução Dízimo" pagos no mês e R$ 813,40 devidos, **When** abre o mês, **Then** vê "Devolvido R$ 914,92" e falta R$ 0,00.
3. **Given** receitas previstas do mês, **When** escolhe um mês futuro, **Then** vê o dízimo previsto de cada domingo.
4. **Given** o app, **When** chama a API `GET /api/v1/planejamento/dizimo?mes=AAAA-MM`, **Then** recebe os mesmos números da tela, só da família logada.

### Edge Cases

- Receita no próprio domingo: dízimo naquele domingo.
- Receita no fim do mês (ex.: 30/11, segunda): dízimo no domingo do mês seguinte.
- Venda da casa, reembolso e "Outros" não entram.
- Ticket alimentação (Renda fixa) entra.
- Outra família não vê os números desta (isolamento).

## Requirements

### Functional Requirements

- **FR-001**: O sistema MUST calcular 10% das receitas da base (Renda fixa e Renda variável) por domingo, pela data da receita (o próprio domingo ou o seguinte).
- **FR-002**: MUST mostrar no mês: base recebida, dízimo devido (10% do recebido), dízimo previsto (10% de toda a base), devolvido (despesas pagas "Devolução Dízimo" no mês) e falta (devido − devolvido, mínimo zero).
- **FR-003**: MUST listar os domingos do mês com o valor previsto e a situação (devolvido, pendente, atrasado).
- **FR-004**: MUST oferecer os mesmos dados na API (`GET /api/v1/planejamento/dizimo?mes=AAAA-MM`), isolados por família.

## Success Criteria

- **SC-001**: Em outubro/2026 o card mostra o devido de 10% de (7.500 + 634) e o devolvido real da planilha.

## Delta Requirements

**Skip**: sem corpus canônico em `docs/specs/current/` — Felipe/Claude, 2026-10-12
