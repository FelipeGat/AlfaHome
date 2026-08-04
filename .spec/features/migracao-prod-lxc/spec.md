# Spec: Migração da produção AlfaHome para o alfa-server (LXC 114)

> feature: migracao-prod-lxc
> status: rascunho

## Contexto

A produção do AlfaHome roda hoje numa VPS alugada (187.127.14.128) que será cancelada.
O sistema precisa passar a rodar no alfa-server (Proxmox local, LXC 114), **sem perder
nenhum dado**, continuando acessível no mesmo endereço público que os clientes já usam
(`home.alfasolucoes.cloud`) e **sem afetar os demais subdomínios** da zona
`alfasolucoes.cloud`.

## Histórias

### US-001 — O cliente continua entrando no mesmo endereço

Como cliente do AlfaHome, quero continuar acessando o sistema pelo mesmo endereço de
sempre, para que a mudança de servidor seja invisível para mim.

#### AC-001 — O endereço de sempre abre a tela de entrada

- **Dado** que a migração foi concluída
- **Quando** alguém abre o endereço público do sistema (`https://home.alfasolucoes.cloud/login`)
- **Então** a tela de entrada carrega normalmente (resposta HTTP 200)

#### AC-002 — Quem responde é o servidor novo, não a VPS

- **Dado** que o endereço público foi apontado para o servidor novo
- **Quando** se consulta a marca de identificação do servidor que atendeu a requisição (arquivo canário `/whoami.txt`)
- **Então** a resposta identifica o servidor novo (`lxc-114`), provando que a VPS não está mais atendendo

#### AC-003 — O acesso continua seguro (HTTPS)

- **Dado** que o sistema está publicado pelo túnel
- **Quando** alguém acessa o endereço público sem HTTPS (`http://`)
- **Então** o acesso é redirecionado para HTTPS e o certificado é válido

### US-002 — Nenhum dado é perdido na mudança

Como dono do produto, quero que todos os cadastros, movimentações e arquivos continuem
lá depois da mudança, para que ninguém precise refazer trabalho.

#### AC-004 — Os cadastros conferem dos dois lados

- **Dado** o banco da VPS congelado e o banco do servidor novo já importado
- **Quando** se compara a quantidade de registros das tabelas principais nos dois servidores
- **Então** os números são idênticos, tabela a tabela

#### AC-005 — O banco está na versão certa do sistema

- **Dado** o banco importado no servidor novo
- **Quando** se verifica a lista de atualizações de banco pendentes (`migrate:status`)
- **Então** não há nenhuma pendente

#### AC-006 — Os arquivos enviados pelos usuários continuam lá

- **Dado** a pasta de arquivos dos usuários (`storage/app`) da VPS congelada
- **Quando** se compara a quantidade de arquivos e o total de bytes com o servidor novo
- **Então** os dois números batem exatamente (o resto de `storage/` é cache e sessão: diverge por definição depois do corte e não é dado do usuário)

### US-003 — Entrar no sistema funciona atrás do túnel

Como usuário do sistema, quero conseguir entrar normalmente depois da mudança, para
que a sessão não expire nem dê erro de página expirada.

#### AC-007 — A entrada no sistema não dá erro de página expirada

- **Dado** o sistema publicado pelo túnel
- **Quando** alguém carrega a tela de entrada e envia o formulário de login
- **Então** o servidor aceita o envio (não retorna erro 419 de página expirada) e o cookie de sessão vem marcado como seguro

#### AC-008 — Os registros mostram o endereço real de quem acessou

- **Dado** um acesso vindo da internet pelo túnel
- **Quando** se olha o registro de acesso do servidor web
- **Então** aparece o endereço de internet real do visitante, e não o endereço interno do túnel (`172.x`)

### US-004 — Os outros sistemas da mesma zona não são afetados

Como dono da infraestrutura, quero que os demais endereços da zona continuem
funcionando igual, para que a migração de um sistema não derrube os outros.

#### AC-009 — Os demais endereços da zona seguem respondendo

- **Dado** os demais subdomínios da zona (`gym`, `control`, `jornada`)
- **Quando** se consulta cada um deles depois do corte
- **Então** todos continuam respondendo exatamente como antes do corte

### US-005 — A publicação de novas versões continua funcionando

Como responsável pelo deploy, quero continuar publicando versões pelo painel como
antes, para que o processo de entrega não mude.

#### AC-010 — O servidor novo aplica sozinho a versão publicada

- **Dado** o vigia de versões (watcher) ativo no servidor novo
- **Quando** uma nova versão é publicada
- **Então** o servidor novo a aplica sozinho e registra o resultado como concluído com sucesso (`deploy-status.json` com estado `ok`)

### US-006 — A nova produção continua tendo cópia de segurança

Como dono do produto, quero que a produção no servidor novo continue sendo copiada
diariamente, para que um desastre não signifique perder tudo.

#### AC-011 — A cópia diária do banco novo existe e está fresca

- **Dado** a rotina de cópia de segurança do alfa-server
- **Quando** se verifica a cópia mais recente do banco da produção nova
- **Então** existe um arquivo com menos de 26 horas de idade

#### AC-012 — A cópia externa (nuvem) continua sendo enviada

- **Dado** a rotina que envia a cópia para o Google Drive
- **Quando** ela roda a partir do servidor novo
- **Então** a cópia do dia aparece no destino externo

### US-007 — Dá para voltar atrás se algo der errado

Como responsável pela operação, quero poder voltar para a VPS rapidamente, para que
uma falha na mudança não vire indisponibilidade longa.

#### AC-013 — A VPS fica intacta e pronta para reassumir

- **Dado** o corte concluído
- **Quando** se inspeciona a VPS
- **Então** os dados e containers continuam lá, o vigia de versões está desligado e existe um dump final guardado fora dela

## Fora de escopo

- Consertar o e-mail transacional (`mail.alfasolucoes.cloud` não existe no DNS) — falha
  pré-existente, anterior à migração, tratada separadamente.
- Criar worker de fila e agendador (`schedule:run`) — a VPS também não tem; não é regressão.
- Cancelar a assinatura da VPS (ação comercial do dono, depois do período de fallback).
- Trocar o disco do alfa-server (RAID1 degradado) — pendência de infraestrutura já conhecida.

## Suposições

| ID | Suposição | Status | Resolução |
|---|---|---|---|
| ASM-001 | Nenhum outro subdomínio da zona tem a VPS como origem — o nginx da VPS só atende `home.alfasolucoes.cloud` | confirmada | Verificado em 04/08/2026: `server_name` da VPS só tem `home.alfasolucoes.cloud`; `/var/www` só contém `alfahome` |
| ASM-002 | O link residencial do alfa-server aguenta o tráfego de produção, sem SLA | confirmada | Risco aceito pelo dono na sessão de 31/07 e reafirmado em 04/08; fallback é a VPS |
| ASM-003 | Dump final + cópia da pasta de arquivos bastam — não há replicação contínua, então a janela exige o sistema fora do ar | confirmada | Não existe replicação entre VPS e alfa-server; a janela com `artisan down` é o único jeito de garantir corte consistente |
| ASM-004 | O banco é pequeno (dump de ~59 KB comprimido, 27 tabelas), então a janela de importação é de minutos, não horas | confirmada | Verificado em 04/08: cópia diária de 04/08 01:00 tem 58 KB comprimidos |

## Perguntas em aberto

| ID | Pergunta | Status | Resposta |
|---|---|---|---|
| Q-001 | Quando executar a janela de corte (sistema fora do ar por ~1h)? | respondida | Preparar e validar hoje (04/08/2026) sem tocar em produção; executar o corte de madrugada, mediante autorização explícita |
| Q-002 | Por quanto tempo manter a VPS congelada como plano B antes de cancelar? | respondida | Até o fim do período já pago — VPS fica ligada e congelada, rollback disponível o tempo todo |
| Q-003 | O endereço público continua `home.alfasolucoes.cloud` ou passa a ser um endereço novo no padrão dos outros sistemas? | respondida | Continua `home.alfasolucoes.cloud` — o Cloudflare Tunnel publica o mesmo nome sem depender do IP da VPS, então não é preciso criar endereço novo |
