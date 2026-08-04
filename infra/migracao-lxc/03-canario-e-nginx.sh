#!/usr/bin/env bash
# T-003 — Canário de origem + conferência do nginx atrás do túnel.
#
# Canário: um arquivo estático que diz QUEM respondeu. É a única prova barata
# de que o endereço público parou de bater na VPS e passou a bater no LXC 114
# (o conteúdo do site é idêntico nos dois — sem canário não dá para distinguir).
#
# Conferências do nginx (a config já veio adaptada da VPS, aqui só provamos):
#  - set_real_ip_from 172.16.0.0/12 + real_ip_header CF-Connecting-IP: o túnel
#    entrega a conexão de um IP interno 172.x; sem isso todo log e todo
#    rate-limit enxergariam o túnel, não o visitante.
#  - fastcgi_param HTTPS on: o .env tem SESSION_SECURE_COOKIE=true; sem esse
#    parâmetro o PHP acha que a conexão é http, não marca o cookie como seguro
#    e o login entra em laço de página expirada (419).
set -euo pipefail

LXC=${LXC:-114}
APP_DIR=${APP_DIR:-/var/www/alfahome}
MARCA=${MARCA:-lxc-114}

remoto() { ssh alfa-server "pct exec ${LXC} -- bash -lc \"$1\""; }

echo "==> publicando o canário de origem"
remoto "printf '%s\\n' '${MARCA}' > ${APP_DIR}/public/whoami.txt && chown 33:33 ${APP_DIR}/public/whoami.txt && cat ${APP_DIR}/public/whoami.txt"

echo "==> conferindo o nginx"
for regra in 'set_real_ip_from 172.16.0.0/12' 'real_ip_header CF-Connecting-IP' 'fastcgi_param HTTPS on'; do
  if remoto "grep -q '${regra}' ${APP_DIR}/nginx.prod.conf"; then
    echo "  ok   ${regra}"
  else
    echo "  FALTA ${regra}"; exit 1
  fi
done

echo "==> conferindo que a app confia no proxy do túnel"
remoto "grep -n 'trustProxies\\|172\\.' ${APP_DIR}/bootstrap/app.php | head -5"

echo "==> resposta local (canário e login)"
remoto "curl -sS -H 'Host: home.alfasolucoes.cloud' http://localhost:8080/whoami.txt"
remoto "curl -sS -o /dev/null -w 'login http %{http_code}\\n' -H 'Host: home.alfasolucoes.cloud' http://localhost:8080/login"

echo "==> prova de IP real: requisição simulando o túnel"
remoto "curl -sS -o /dev/null -H 'Host: home.alfasolucoes.cloud' -H 'CF-Connecting-IP: 203.0.113.77' http://localhost:8080/login && docker logs alfa-home-nginx 2>&1 | tail -1"
