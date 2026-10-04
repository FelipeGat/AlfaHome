<!--
Sync Impact Report
- Version: (nenhuma) → 1.0.0
- Principios criados: I a IX
- Secoes adicionadas: Quality Gates, Processo e Autorizacao, Governance
- Secoes removidas: nenhuma
- Artefatos que precisam atualizacao:
  - CLAUDE.md do projeto: nao existe — nada a alinhar
  - .spec/constituicao.md (v1.1.0, formato onpspec): mantida como esta; rege so a
    feature de infraestrutura `migracao-prod-lxc`. Seus dois principios (P-001 prova
    executavel, P-002 segredos fora do codigo) estao absorvidos aqui em VIII e IX
  - docs/specs/: ainda nao ha specs
- TODOs pendentes: nenhum
-->

# AlfaHome Constitution

## Core Principles

### I. Veracidade de Dados — Zero Fabricacao (NON-NEGOTIABLE)

O AlfaHome guarda o dinheiro real de uma família. Um número plausível e errado
é pior que um número ausente.

**MUST:**

- Nenhum artefato pode conter dado factual inventado. Assinaturas de
  request/response, URLs/endpoints/querystrings e valores concretos (financeiros,
  quantidades, status, IDs, datas, resultados de API) só podem ser escritos se
  vierem de fonte rastreável (código, planilha, documentação do repositório,
  resposta real observada). Esgotadas as fontes, a ação correta é **bloqueio
  humano**, nunca suposição plausível. Plausibilidade não é veracidade.
- A carga inicial e toda importação gravam somente o que está na planilha. Campo
  vazio na planilha permanece vazio no sistema.

### II. A planilha é a fonte; a importação é idempotente

Quem mantém os dados continua na planilha Excel. O sistema lê, não disputa.

**MUST:**

- Reimportar o mesmo arquivo não cria, duplica nem apaga registro.
- Reimportar um arquivo alterado atualiza as linhas correspondentes, inclui as
  novas e trata as que sumiram de forma explícita e relatada ao usuário.
- Toda linha lida termina em um de três estados visíveis no relatório da
  importação: importada, atualizada ou rejeitada com o motivo. Nenhuma linha some
  em silêncio.
- A importação é atômica: ou o arquivo inteiro entra, ou nada muda.

**MUST NOT:**

- Sobrescrever o valor previsto ao registrar o valor realizado.

### III. Os números do sistema batem com os da planilha

O critério de aceite de qualquer tela que reproduza a planilha é aritmético.

**MUST:**

- Cada total exibido (receitas, despesas, saldo, economia %, comprometimento %,
  limite disponível, faturas em aberto, saldo de dívidas, progresso de metas) tem
  teste automatizado que o compara com o valor da planilha de referência
  (`Planejamento_Financeiro_Domestico - v2.xlsx`, agosto/2026).
- Divergência entre sistema e planilha é tratada como defeito do sistema até
  prova em contrário; quando o defeito for da planilha, ele é relatado, não
  corrigido em silêncio.

### IV. Uma definição para cada número

Hoje a fatura do cartão é calculada de três jeitos em três telas. Isso acaba.

**MUST:**

- Cada grandeza de negócio (fatura aberta, limite disponível, previsto,
  realizado, saldo de conta) tem uma única implementação, reutilizada por web,
  API e app.
- Web e API v1 aplicam as mesmas regras e os mesmos valores aceitos para o mesmo
  campo. O app Flutter só consome a API; nenhuma regra de cálculo vive só no app.

### V. Saldos só mudam por caminho auditável

Saldo de conta e fatura de cartão são derivados dos lançamentos.

**MUST:**

- Toda criação, alteração, baixa, estorno e exclusão de lançamento passa pelo
  caminho que dispara os observers do modelo.

**MUST NOT:**

- Usar `update()` ou `delete()` em massa sobre despesas, receitas ou
  transferências.

### VI. Isolamento por família (tenant)

**MUST:**

- Toda tabela de negócio tem `tenant_id` e usa a trait `BelongsToTenant`.
- Toda validação de chave estrangeira vinda do usuário restringe ao tenant do
  usuário autenticado.
- Toda funcionalidade nova tem teste que prova que um tenant não lê nem altera
  dado de outro.

### VII. O usuário lê português

**MUST:**

- Toda mensagem exibida ao usuário (validação, autenticação, erro, confirmação)
  está em português do Brasil.

**MUST NOT:**

- Exibir chave de tradução crua (ex.: `auth.failed`), rastro de pilha ou
  mensagem técnica de banco de dados.

### VIII. Todo requisito tem prova executável

Absorve o P-001 de `.spec/constituicao.md`.

**MUST:**

- Nenhuma tarefa é declarada pronta sem teste automatizado que a exercite e sem
  a suíte PHPUnit inteira verde.
- Correção de defeito vem acompanhada do teste que falhava antes dela.
- Migrations têm `down()` funcional.

### IX. Segredos fora do código

Absorve o P-002 de `.spec/constituicao.md`.

**MUST NOT:**

- Gravar senha, token ou chave em arquivo versionado. Vêm de variável de
  ambiente.

## Quality Gates

- `php artisan test` verde é condição para qualquer entrega.
- Deploy de produção só por tag `v*`, com o CI verde (regra já vigente no
  repositório).
- Funcionalidade vinda da planilha só é aceita com o teste de conferência do
  Princípio III.

## Processo e Autorizacao

- Commit, criação de branch, push, tag, abertura de PR e deploy de produção só
  com autorização explícita do Felipe. A autorização vale para a etapa
  autorizada, não para a seguinte.
- Frente nova passa por `specify → clarify → plan → create-tasks → execute-task`,
  com artefatos em `docs/specs/<feature>/`. Correção pontual usa `/cstk:bugfix`.
- Fora do escopo desta fase: parte comercial (super admin, revenda, planos,
  landing, indicação), salvo quando impedir o uso da família.

## Governance

Esta constitution prevalece sobre qualquer `CLAUDE.md` do projeto: onde
divergirem, o `CLAUDE.md` é que deve ser corrigido. Alterações seguem SemVer —
MAJOR para remoção ou redefinição incompatível de princípio, MINOR para
princípio novo ou expansão material, PATCH para clarificação — e levam Sync
Impact Report no topo do arquivo. Exceção a um princípio exige registro escrito
do motivo no plano da feature e aprovação do Felipe.

**Version**: 1.0.0 | **Ratified**: 2026-10-03 | **Last Amended**: 2026-10-03
