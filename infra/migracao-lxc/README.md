# Migração da produção AlfaHome — VPS → alfa-server (LXC 114)

Runbook da mudança da produção da VPS alugada (`187.127.14.128`) para o
alfa-server local, mantendo o endereço público `home.alfasolucoes.cloud`.

A especificação, os critérios de aceite e o estado de cada tarefa vivem em
`.spec/features/migracao-prod-lxc/`. As provas mecânicas ficam em
`tests/migracao/` (`node --test --test-reporter=tap tests/migracao/*.test.mjs`).

## Onde a produção passa a morar

| | antes | depois |
|---|---|---|
| máquina | VPS 187.127.14.128 | alfa-server, LXC **114** (`10.0.3.114`) |
| acesso | `ssh root@187.127.14.128` | `ssh alfa-server` + `pct exec 114` |
| projeto | `/var/www/alfahome` | `/var/www/alfahome` (mesmo layout, de propósito) |
| containers | `alfa-home-{app,nginx,db,redis}` | idem |
| entrada da internet | Cloudflare → IP da VPS (443) | Cloudflare Tunnel → `localhost:8080` |
| TLS | Let's Encrypt no nginx | termina na borda do Cloudflare |

O layout foi espelhado justamente para o `deploy-tag-watcher.sh` continuar
valendo sem uma linha de edição.

## Ordem de execução

```bash
export VPS_HOST=187.127.14.128 VPS_PASS='<senha da VPS>'

bash infra/migracao-lxc/01-subir-stack.sh        # stack de pé no 114
bash infra/migracao-lxc/02-tunnel.sh             # túnel + home-novo (validação)
bash infra/migracao-lxc/03-canario-e-nginx.sh    # canário + IP real + cookie seguro
bash infra/migracao-lxc/04-restaurar-dados.sh    # ensaio com a cópia diária

node --test --test-reporter=tap tests/migracao/*.test.mjs   # o que já dá para provar

# ⛔ daqui em diante só com autorização explícita do dono, de madrugada
CONFIRMA=SIM bash infra/migracao-lxc/05-cutover.sh
bash infra/migracao-lxc/06-watcher.sh
bash infra/migracao-lxc/07-backups.sh
```

## Armadilhas já encontradas (não repita)

- **`docker compose` sem `-f docker-compose.yml`**: o `docker-compose.override.yml`
  do repositório é de desenvolvimento e troca o nginx de produção pelo de dev —
  o que apaga o IP real e o `fastcgi_param HTTPS on`, e derruba o login em laço
  de 419. A VPS sobe com o arquivo explícito; o 114 também precisa.
- **`bootstrap/cache` como root**: o clone no LXC é feito por root, o PHP roda
  como `www-data`. O sintoma é um 500 que se disfarça de
  `Target class [view] does not exist`.
- **`public/build` não está no git**: os assets compilados do Vite existem só na
  VPS. Sem copiá-los, toda página morre com `Vite manifest not found`.
- **`docker exec -i` engolindo o dump**: se o `drop/create` for na mesma chamada
  que recebe o dump por stdin, a importação começa no meio do arquivo.
- **Healthcheck do MySQL sem `start_period`**: no HDD do host o banco demora e o
  compose desiste antes. Esperar o `healthy` e subir o resto.
- **WAF da zona bloqueia hostname novo**: `home-novo.alfasolucoes.cloud` levou 403
  do Cloudflare antes de chegar no servidor. `home` passa. Validar pelo endereço
  novo exige liberar o hostname no WAF.

## Voltar atrás (rollback)

```bash
CONFIRMA=SIM VPS_HOST=187.127.14.128 VPS_PASS='<senha>' bash infra/migracao-lxc/08-rollback.sh
```

O DNS **não** muda. Depois do corte `home` é um CNAME para o túnel, e o rollback
apenas manda o túnel entregar na VPS (`https://187.127.14.128` com
`originServerName`) em vez de no nginx local. Volta em segundos, sem painel do
Cloudflare e sem token de API — que é justamente o que o `cloudflared` sozinho
não permitiria se dependêssemos de recriar o registro A.

O script já desliga o vigia de versões do 114 antes de religar o da VPS (nunca
dois) e tira a VPS da manutenção. Se houve escrita depois do corte, leve o dump
do 114 e os arquivos de volta para a VPS antes de liberar o acesso.

Enquanto a VPS estiver paga ela fica ligada, em manutenção, como plano B.

## Pendências herdadas (não são regressão da migração)

- E-mail transacional nunca funcionou: `mail.alfasolucoes.cloud` não tem A nem MX
  e `MAIL_USERNAME` é placeholder.
- Não há worker de fila nem `schedule:run` — igual à VPS.
