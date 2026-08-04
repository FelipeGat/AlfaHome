#!/usr/bin/env bash
# T-001 — Sobe a stack do AlfaHome no LXC 114 (produção nova, alfa-server).
#
# Roda a partir da máquina de trabalho: usa `ssh alfa-server` + `pct exec 114`.
# Idempotente: pode rodar quantas vezes precisar.
#
# Por que existe um laço de espera: o MySQL inicializa o datadir num HDD lento
# (RAID1 degradado) e o healthcheck do compose não tem `start_period` — ele
# marca "unhealthy" antes do mysqld aceitar conexão, e o `up` do app/nginx
# aborta com "dependency failed to start". Esperar o healthy e subir o resto
# resolve sem mexer no compose (que é espelho fiel do da VPS).
set -euo pipefail

LXC=${LXC:-114}
APP_DIR=${APP_DIR:-/var/www/alfahome}
ESPERA_MAX=${ESPERA_MAX:-40}   # tentativas de 15s = até 10 min

remoto() { ssh alfa-server "pct exec ${LXC} -- bash -lc \"$1\""; }

echo "==> subindo banco e cache"
remoto "cd ${APP_DIR} && docker compose -f docker-compose.yml up -d db redis"

echo "==> esperando o banco ficar saudável"
remoto "for i in \\\$(seq 1 ${ESPERA_MAX}); do
  s=\\\$(docker inspect alfa-home-db --format '{{.State.Health.Status}}')
  echo \\\"[\\\$i] banco: \\\$s\\\"
  [ \\\"\\\$s\\\" = healthy ] && exit 0
  sleep 15
done
echo 'banco não ficou saudável no tempo esperado'; exit 1"

echo "==> subindo aplicação e servidor web"
remoto "cd ${APP_DIR} && docker compose -f docker-compose.yml up -d"

# O clone no LXC foi feito como root; o PHP roda como www-data (uid 33) e
# precisa escrever o manifesto de pacotes em bootstrap/cache. Sem isto o
# Laravel derruba tudo com "The bootstrap/cache directory must be present and
# writable", que aparece como 500 e mascara-se depois como "Target class [view]
# does not exist". Na VPS esse diretório é www-data:www-data — espelhamos.
echo "==> corrigindo dono de bootstrap/cache (espelha a VPS)"
remoto "chown -R 33:33 ${APP_DIR}/bootstrap/cache && chmod 775 ${APP_DIR}/bootstrap/cache && ls -ld ${APP_DIR}/bootstrap/cache"

# Os assets compilados do Vite (public/build) estão no .gitignore: existem
# apenas na VPS, gerados no deploy inicial de 03/04. Sem eles o Laravel derruba
# toda página com "Vite manifest not found". A VPS não tem rsync — vai por tar
# sobre ssh. Exige VPS_HOST e VPS_PASS no ambiente (nunca no arquivo).
if [ -n "${VPS_HOST:-}" ] && [ -n "${VPS_PASS:-}" ]; then
  echo "==> copiando assets compilados (public/build) da VPS"
  SSHPASS="${VPS_PASS}" sshpass -e ssh -o PreferredAuthentications=password -o StrictHostKeyChecking=no \
    "root@${VPS_HOST}" 'tar -C /var/www/alfahome -cf - public/build' \
    | ssh alfa-server "pct exec ${LXC} -- tar -C ${APP_DIR} -xf -"
  remoto "chown -R 33:33 ${APP_DIR}/public/build && ls -l ${APP_DIR}/public/build/manifest.json"
else
  echo "==> pulando cópia de public/build (defina VPS_HOST e VPS_PASS para copiar)"
fi

echo "==> limpando caches do Laravel"
remoto "docker exec alfa-home-app php artisan optimize:clear || true"

echo "==> estado final"
remoto "docker ps --format '{{.Names}} | {{.Status}}'"

echo "==> resposta local do servidor web (esperado: cabeçalho HTTP)"
remoto "curl -sS -o /dev/null -w 'http %{http_code}\\n' -H 'Host: home.alfasolucoes.cloud' http://localhost:8080/login"
