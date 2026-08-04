#!/usr/bin/env bash
# T-002 — Publica o AlfaHome do LXC 114 por um Cloudflare Tunnel.
#
# Por que túnel: o alfa-server está atrás de link residencial, sem IP fixo e
# sem porta 80/443 abertas. O túnel faz a saída ser de dentro para fora, então
# o mesmo nome de domínio continua valendo sem depender do IP da VPS.
#
# Este script cria SÓ o endereço de validação (home-novo). O nome de produção
# (home) entra no ingress apenas na janela de corte — ver 05-cutover.sh.
#
# Pré-requisito: /root/.cloudflared/cert.pem no 114 (login já feito na zona).
set -euo pipefail

LXC=${LXC:-114}
TUNEL=${TUNEL:-alfahome-prod}
HOST_VALIDACAO=${HOST_VALIDACAO:-home-novo.alfasolucoes.cloud}
ORIGEM=${ORIGEM:-http://localhost:8080}

remoto() { ssh alfa-server "pct exec ${LXC} -- bash -lc \"$1\""; }

echo "==> criando o túnel (idempotente)"
remoto "cloudflared tunnel list --output json | grep -q '\\\"name\\\":\\\"${TUNEL}\\\"' || cloudflared tunnel create ${TUNEL}"
remoto "cloudflared tunnel list"

ID=$(ssh alfa-server "pct exec ${LXC} -- bash -lc \"cloudflared tunnel list --output json\"" \
  | python3 -c "import json,sys;print([t['id'] for t in json.load(sys.stdin) if t['name']=='${TUNEL}'][0])")
echo "==> túnel ${TUNEL} = ${ID}"

echo "==> escrevendo o ingress"
# catch-all 404 no fim é obrigatório: sem ele o cloudflared recusa a config.
remoto "mkdir -p /etc/cloudflared && cat > /etc/cloudflared/config.yml <<'EOF'
tunnel: ${ID}
credentials-file: /root/.cloudflared/${ID}.json
originRequest:
  connectTimeout: 30s
  noTLSVerify: false
ingress:
  - hostname: ${HOST_VALIDACAO}
    service: ${ORIGEM}
  - service: http_status:404
EOF
cat /etc/cloudflared/config.yml"

echo "==> validando a config"
remoto "cloudflared tunnel ingress validate"

echo "==> instalando e ligando o serviço"
remoto "systemctl is-active --quiet cloudflared || cloudflared service install"
remoto "systemctl enable --now cloudflared && sleep 5 && systemctl is-active cloudflared"

echo "==> publicando o nome de validação no DNS"
remoto "cloudflared tunnel route dns --overwrite-dns ${TUNEL} ${HOST_VALIDACAO}"

echo "==> pronto — validar com: curl -sSI https://${HOST_VALIDACAO}/login"
