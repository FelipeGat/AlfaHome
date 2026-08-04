#!/usr/bin/env bash
# T-006 — JANELA DE CORTE. Tira a produção do ar por alguns minutos, move os
# dados finais e vira o endereço público da VPS para o LXC 114.
#
# ⛔ NÃO RODE SEM AUTORIZAÇÃO EXPLÍCITA DO DONO. Exige CONFIRMA=SIM.
#
# Ordem e porquês:
#  1. desligar o vigia de versões da VPS — dois vigias ativos aplicariam
#     deploys em servidores diferentes ao mesmo tempo;
#  2. `artisan down` na VPS — sem isso alguém escreve na VPS depois do dump e
#     esse dado morre no corte;
#  3. dump final + arquivos, com a origem já congelada;
#  4. só então virar o DNS.
#
# Rollback SEM painel: depois do corte o `home` é um CNAME para o túnel, e o
# `cloudflared` não sabe recriar o registro A da VPS. Mas não precisa — basta
# mandar o próprio túnel entregar na VPS em vez de no nginx local. Ver
# 08-rollback.sh: troca uma linha do ingress e reinicia. Volta em segundos,
# sem tocar em DNS e sem acesso ao painel do Cloudflare.
set -euo pipefail

LXC=${LXC:-114}
APP_DIR=${APP_DIR:-/var/www/alfahome}
TUNEL=${TUNEL:-alfahome-prod}
HOST_PROD=${HOST_PROD:-home.alfasolucoes.cloud}
HOST_VALIDACAO=${HOST_VALIDACAO:-home-novo.alfasolucoes.cloud}
ORIGEM=${ORIGEM:-http://localhost:8080}
DIR_COPIAS=${DIR_COPIAS:-/srv/dados/backups-vps/alfahome}
CARIMBO=$(date +%Y-%m-%d_%H%M)

[ "${CONFIRMA:-}" = "SIM" ] || { echo "recuse-se a rodar sem CONFIRMA=SIM (autorização do dono)"; exit 1; }
[ -n "${VPS_HOST:-}" ] && [ -n "${VPS_PASS:-}" ] || { echo "defina VPS_HOST e VPS_PASS"; exit 1; }

remoto() { ssh alfa-server "pct exec ${LXC} -- bash -lc \"$1\""; }
vps() { SSHPASS="${VPS_PASS}" sshpass -e ssh -o PreferredAuthentications=password -o StrictHostKeyChecking=no "root@${VPS_HOST}" "$1"; }

echo "==> [1/6] congelando a origem: vigia de versões da VPS"
vps "systemctl disable --now deploy-tag-watcher.timer; systemctl is-enabled deploy-tag-watcher.timer || true"

echo "==> [2/6] colocando a VPS em manutenção"
vps "cd /var/www/alfahome && docker exec alfa-home-app php artisan down --render=errors::503 --secret=migracao-${CARIMBO} || true"

echo "==> [3/6] dump final do banco (com a origem já parada)"
vps "S=\$(grep -m1 ^MYSQL_ROOT_PASSWORD= /var/www/alfahome/.env | cut -d= -f2-); docker exec alfa-home-db mysqldump -uroot -p\"\$S\" --single-transaction --routines --triggers alfahome 2>/dev/null | gzip" \
  | ssh alfa-server "cat > ${DIR_COPIAS}/alfahome-${CARIMBO}-final.sql.gz"
ssh alfa-server "gzip -dc ${DIR_COPIAS}/alfahome-${CARIMBO}-final.sql.gz | tail -2 | grep -q 'Dump completed' && echo 'dump final íntegro' || { echo 'DUMP FINAL INCOMPLETO — abortar'; exit 1; }"

echo "==> [4/6] arquivos de usuário finais (storage é volume docker na VPS)"
vps "docker exec alfa-home-app tar -C /var/www/storage -cf - ." \
  | ssh alfa-server "pct exec ${LXC} -- docker exec -i alfa-home-app tar -C /var/www/storage -xf -"
remoto "docker exec alfa-home-app chown -R www-data:www-data /var/www/storage"

echo "==> [5/6] importando os dados finais no servidor novo"
MODO=final DUMP="${DIR_COPIAS}/alfahome-${CARIMBO}-final.sql.gz" PULAR_STORAGE=sim \
  bash "$(dirname "$0")/04-restaurar-dados.sh"

echo "==> [6/6] virando o endereço público para o túnel"
# O ingress é REESCRITO inteiro (não editado no lugar): heredoc aninhado dentro
# de pct exec é frágil e este é o minuto mais caro da janela. O nome de
# validação sai do ingress aqui — ele era andaime e deixa de responder.
ID=$(ssh alfa-server "pct exec ${LXC} -- bash -lc \"cloudflared tunnel list --output json\"" \
  | python3 -c "import json,sys;print([t['id'] for t in json.load(sys.stdin) if t['name']=='${TUNEL}'][0])")
echo "  túnel ${TUNEL} = ${ID}"
remoto "cat > /etc/cloudflared/config.yml <<'EOF'
tunnel: ${ID}
credentials-file: /root/.cloudflared/${ID}.json
originRequest:
  connectTimeout: 30s
  noTLSVerify: false
ingress:
  - hostname: ${HOST_PROD}
    service: ${ORIGEM}
  - service: http_status:404
EOF
cat /etc/cloudflared/config.yml"
remoto "cloudflared tunnel ingress validate && systemctl restart cloudflared && sleep 5 && systemctl is-active cloudflared"
# --overwrite-dns troca o registro A (187.127.14.128) pelo CNAME do túnel.
remoto "cloudflared tunnel route dns --overwrite-dns ${TUNEL} ${HOST_PROD}"

echo "==> conferindo o canário no endereço público (pode levar alguns segundos)"
for i in $(seq 1 12); do
  quem=$(curl -sS --max-time 10 "https://${HOST_PROD}/whoami.txt" 2>/dev/null | tr -d '\r\n' || true)
  echo "  [$i] quem responde: '${quem}'"
  [ "${quem}" = "lxc-114" ] && break
  sleep 10
done

echo "==> corte concluído — a VPS segue em manutenção como plano B"
echo "    dump final: ${DIR_COPIAS}/alfahome-${CARIMBO}-final.sql.gz"
echo "    segredo de bypass da VPS: /migracao-${CARIMBO}"
