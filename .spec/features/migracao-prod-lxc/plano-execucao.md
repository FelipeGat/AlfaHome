# Plano de execução — migracao-prod-lxc

> gerado por `onp-spec plano` em 2026-08-04 15:07 — NÃO edite à mão;
> mudou tasks.md ou a config? Regenere: `onp-spec plano migracao-prod-lxc --sequencial`

## Resumo — o que vai acontecer

- **modo SEQUENCIAL (escolha do usuário)**: 10 tarefa(s) pendente(s), UMA APÓS A OUTRA, na árvore principal
- sem worktrees e sem paralelismo — cada tarefa roda numa janela de contexto limpa, na ordem do tasks.md
- tudo acontece na branch de trabalho `spec/migracao-prod-lxc`; levar para a main é decisão sua

## Ordem de execução (uma tarefa após a outra)

| tarefa | título | modelo | esforço |
|---|---|---|---|
| T-001 | Subir a stack do AlfaHome no LXC 114 | `claude-sonnet-5` | medium |
| T-002 | Publicar o túnel Cloudflare e o endereço de validação | `claude-sonnet-5` | medium |
| T-003 | Canário de origem, IP real e sessão segura atrás do túnel | `claude-sonnet-5` | medium |
| T-004 | Ensaio de dados: importar dump e arquivos no 114 | `claude-sonnet-5` | medium |
| T-005 | Escrever os testes que provam os critérios | `claude-sonnet-5` | medium |
| T-006 | Janela de corte: congelar a VPS, dados finais e virar o DNS | `claude-sonnet-5` | medium |
| T-007 | Ligar o vigia de versões no servidor novo | `claude-sonnet-5` | medium |
| T-008 | Cópias de segurança da nova produção | `claude-sonnet-5` | medium |
| T-009 | Provar que os demais endereços da zona não foram afetados | `claude-sonnet-5` | medium |
| T-010 | Documentar o novo acesso e o caminho de volta | `claude-sonnet-5` | medium |

## Gestão de branches e commits

1. branch de trabalho `spec/migracao-prod-lxc` criada do ponto atual (se ainda não existir)
2. as tarefas rodam nela mesma, na ordem — **1 tarefa = 1 commit** (`T-xxx feature: título`), marcada `[concluida]` só com trabalho feito
3. gate final na branch de trabalho: `onp-spec verify migracao-prod-lxc` + `onp-spec audit --ci` — **exit 0 ou não está pronto**

## Como executar

### ▶ Execução — Claude Code headless

```bash
bash .spec/features/migracao-prod-lxc/executar-tarefas.sh
```

Cada tarefa roda `claude -p` com **janela de contexto limpa**, na árvore principal,
uma após a outra, com `--model` e `--effort` já definidos por tarefa e permissões `acceptEdits`.
Os prompts exatos estão embutidos no script.
Logs: `../onp-worktrees/AlfaHome-migracao-prod-lxc-logs/`.

### 📣 Acompanhamento — tabela + resumo no chat (a cada 1 min)

O script roda em **background**: o agente AVISA o usuário antes de iniciar e,
enquanto roda, posta no chat a cada ~1 minuto a **tabela de andamento** (qual
tarefa está rodando, qual não está, o que concluiu/falhou) junto com o
**resumo geral de andamento** (escrito por IA; sem IA, o motor resume). Ao
final, o usuário recebe o resumo completo da execução. A qualquer momento:

```bash
onp-spec resumo migracao-prod-lxc --tabela   # a tabela de andamento
onp-spec resumo migracao-prod-lxc            # o resumo em texto
```

