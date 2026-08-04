# Tasks: Migração da produção AlfaHome para o alfa-server (LXC 114)

> feature: migracao-prod-lxc

## T-001 — Subir a stack do AlfaHome no LXC 114 [concluida]
- Refs: AC-002
- Arquivos: infra/migracao-lxc/01-subir-stack.sh
- Notas: `docker compose up -d` em `/var/www/alfahome` do 114 (app, nginx, db, redis). O MySQL leva minutos para inicializar no HDD — o healthcheck sem `start_period` marca "unhealthy" antes da hora; esperar e subir o resto. Nada aqui toca a produção.

## T-002 — Publicar o túnel Cloudflare e o endereço de validação [concluida]
- Refs: AC-001, AC-003
- Arquivos: infra/migracao-lxc/02-tunnel.sh
- Notas: `cloudflared tunnel create alfahome-prod`, ingress para `http://localhost:8080`, serviço systemd, e `tunnel route dns` só para `home-novo.alfasolucoes.cloud`. O nome de produção (`home`) NÃO entra aqui — entra na T-006. Zona já autorizada (cert do cloudflared no 114).

## T-003 — Canário de origem, IP real e sessão segura atrás do túnel [concluida]
- Refs: AC-002, AC-007, AC-008
- Arquivos: infra/migracao-lxc/03-canario-e-nginx.sh
- Notas: `public/whoami.txt` com `lxc-114`; nginx do 114 com `set_real_ip_from 172.16.0.0/12` + `real_ip_header CF-Connecting-IP` e `fastcgi_param HTTPS on` (necessário porque `SESSION_SECURE_COOKIE=true`). Depende da T-001.

## T-004 — Ensaio de dados: importar dump e arquivos no 114 [concluida]
- Refs: AC-004, AC-005, AC-006
- Arquivos: infra/migracao-lxc/04-restaurar-dados.sh
- Notas: importa a cópia diária mais recente de `/srv/dados/backups-vps/alfahome/` e joga `/root/staging-storage` no volume `storage-data`. É ensaio — na janela (T-006) o mesmo script roda com o dump final. Depende da T-001.

## T-005 — Escrever os testes que provam os critérios [concluida]
- Refs: AC-001, AC-002, AC-003, AC-004, AC-005, AC-006, AC-007, AC-008, AC-010, AC-011, AC-012, AC-013
- Arquivos: tests/migracao/migracao.test.mjs
- Notas: testes em `node --test` que consultam de verdade — HTTP no endereço público, canário de origem, contagens de tabela nos dois bancos, contagem/bytes do storage, `migrate:status`, log do nginx, frescor do backup. Antes do corte eles falham (é o esperado); o gate roda depois da janela.

## T-006 — Janela de corte: congelar a VPS, dados finais e virar o DNS [concluida]
- Refs: AC-001, AC-002, AC-004, AC-006, AC-013
- Arquivos: infra/migracao-lxc/05-cutover.sh
- Notas: **exige autorização explícita do dono e roda de madrugada (Q-001)**. Desliga o vigia da VPS, `artisan down`, dump final + delta do storage, importa no 114, remove o registro A `home` no Cloudflare e publica `home` no túnel. Depende de T-002, T-003, T-004.

## T-007 — Ligar o vigia de versões no servidor novo [concluida]
- Refs: AC-010
- Arquivos: infra/migracao-lxc/06-watcher.sh
- Notas: habilita `deploy-tag-watcher.timer` no 114 (nunca com o da VPS ligado) e valida com uma publicação de teste ponta a ponta. Depende da T-006.

## T-008 — Cópias de segurança da nova produção [em-andamento]
- Refs: AC-011, AC-012
- Arquivos: infra/migracao-lxc/07-backups.sh
- Notas: cron do rclone para o Google Drive no 114 (e desliga o da VPS depois do primeiro verde), mais `alfahome-prod` nas rotinas `backup-dados.sh` e `backup-check.sh` do host. Depende da T-006.

## T-009 — Provar que os demais endereços da zona não foram afetados [concluida]
- Refs: AC-009
- Arquivos: tests/migracao/subdominios.test.mjs
- Notas: captura o comportamento de `gym`, `control` e `jornada` antes do corte e compara depois. Independente das demais tarefas.

## T-010 — Documentar o novo acesso e o caminho de volta [concluida]
- Refs: AC-013
- Arquivos: infra/migracao-lxc/README.md, infra/migracao-lxc/08-rollback.sh
- Notas: runbook do LXC 114, procedimento de rollback para a VPS e o que muda no `CLAUDE.md`. A VPS fica congelada até o fim do período pago (Q-002).
