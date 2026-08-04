#!/usr/bin/env bash
# Rollback do corte — devolve a produção para a VPS em segundos, SEM painel
# do Cloudflare e SEM token de API.
#
# A sacada: depois do corte, `home.alfasolucoes.cloud` é um CNAME para o túnel.
# O `cloudflared` não sabe recriar o registro A da VPS, mas o DNS não precisa
# mudar — basta o túnel parar de entregar no nginx local e passar a entregar na
# VPS. O visitante continua chegando pelo mesmo nome; muda só o outro lado.
#
# A VPS responde HTTPS com certificado Let's Encrypt válido para
# home.alfasolucoes.cloud, por isso `originServerName` — sem ele o cloudflared
# recusaria o certificado ao falar com um IP.
set -euo pipefail

LXC=${LXC:-114}
HOST_PROD=${HOST_PROD:-home.alfasolucoes.cloud}
VPS_IP=${VPS_IP:-187.127.14.128}
LOCAL=${LOCAL:-http://localhost:8080}
ALVO=${ALVO:-vps}   # vps = volta para a VPS · lxc = volta para o servidor novo

remoto() { ssh alfa-server "pct exec ${LXC} -- bash -lc \"$1\""; }

[ "${CONFIRMA:-}" = "SIM" ] || { echo "recuse-se a rodar sem CONFIRMA=SIM"; exit 1; }

if [ "${ALVO}" = "vps" ]; then
  echo "==> [1/4] desligando o vigia de versões do servidor novo"
  remoto "systemctl disable --now deploy-tag-watcher.timer 2>&1 || true"

  echo "==> [2/4] apontando o túnel para a VPS"
  remoto "python3 - <<'PY'
p='/etc/cloudflared/config.yml'
s=open(p).read()
novo='''  - hostname: ${HOST_PROD}
    service: https://${VPS_IP}
    originRequest:
      originServerName: ${HOST_PROD}
'''
import re
s=re.sub(r'  - hostname: ${HOST_PROD}\n    service: [^\n]*\n(    originRequest:\n(      [^\n]*\n)*)?', novo, s)
open(p,'w').write(s)
print(s)
PY"

  echo "==> [3/4] tirando a VPS da manutenção"
  if [ -n "${VPS_HOST:-}" ] && [ -n "${VPS_PASS:-}" ]; then
    SSHPASS="${VPS_PASS}" sshpass -e ssh -o PreferredAuthentications=password -o StrictHostKeyChecking=no \
      "root@${VPS_HOST}" 'docker exec alfa-home-app php artisan up; systemctl enable --now deploy-tag-watcher.timer; systemctl is-enabled deploy-tag-watcher.timer'
  else
    echo "  defina VPS_HOST e VPS_PASS, ou rode à mão na VPS: artisan up + enable do timer"
  fi
else
  echo "==> apontando o túnel de volta para o servidor novo"
  remoto "python3 - <<'PY'
import re
p='/etc/cloudflared/config.yml'
s=open(p).read()
novo='''  - hostname: ${HOST_PROD}
    service: ${LOCAL}
'''
s=re.sub(r'  - hostname: ${HOST_PROD}\n    service: [^\n]*\n(    originRequest:\n(      [^\n]*\n)*)?', novo, s)
open(p,'w').write(s)
print(s)
PY"
fi

echo "==> [4/4] validando e reiniciando o túnel"
remoto "cloudflared tunnel ingress validate && systemctl restart cloudflared && sleep 5 && systemctl is-active cloudflared"

echo "==> quem responde agora (esperado: canário vazio/404 se voltou para a VPS)"
for i in $(seq 1 6); do
  quem=$(curl -sS --max-time 10 "https://${HOST_PROD}/whoami.txt" 2>/dev/null | tr -d '\r\n' || true)
  echo "  [$i] '${quem}'  (lxc-114 = servidor novo · vazio = VPS)"
  sleep 5
done
