#!/usr/bin/env bash
# T-007 — Liga o vigia de versões (deploy-tag-watcher) no LXC 114.
#
# Só rode DEPOIS do corte: o vigia consulta HEALTH_URL=https://home.alfasolucoes.cloud/
# e, antes do corte, esse endereço ainda é a VPS — ele aprovaria um deploy
# olhando para o servidor errado.
#
# Regra de ouro: nunca dois vigias ligados. O da VPS é desligado no 05-cutover.sh.
set -euo pipefail

LXC=${LXC:-114}

remoto() { ssh alfa-server "pct exec ${LXC} -- bash -lc \"$1\""; }

echo "==> conferindo que o vigia da VPS está desligado"
if [ -n "${VPS_HOST:-}" ] && [ -n "${VPS_PASS:-}" ]; then
  estado=$(SSHPASS="${VPS_PASS}" sshpass -e ssh -o PreferredAuthentications=password -o StrictHostKeyChecking=no \
    "root@${VPS_HOST}" 'systemctl is-enabled deploy-tag-watcher.timer 2>&1 || true')
  echo "  vigia da VPS: ${estado}"
  [ "${estado}" = "disabled" ] || { echo "ABORTAR: o vigia da VPS ainda está ${estado}"; exit 1; }
else
  echo "  (sem VPS_HOST/VPS_PASS — confira à mão antes de seguir)"
fi

echo "==> conferindo que o endereço público já aponta para o servidor novo"
quem=$(curl -sS --max-time 10 https://home.alfasolucoes.cloud/whoami.txt | tr -d '\r\n')
[ "${quem}" = "lxc-114" ] || { echo "ABORTAR: quem responde é '${quem}', não lxc-114"; exit 1; }

echo "==> ligando o vigia no 114"
remoto "mkdir -p /opt/alfahome/logs && systemctl enable --now deploy-tag-watcher.timer && systemctl is-enabled deploy-tag-watcher.timer"
remoto "systemctl list-timers deploy-tag-watcher.timer --no-pager | head -3"

echo "==> rodando uma passada à mão para gravar o primeiro estado"
remoto "systemctl start deploy-tag-watcher.service && sleep 20 && journalctl -u deploy-tag-watcher.service -n 20 --no-pager"
remoto "cat /opt/alfahome/logs/deploy-status.json 2>/dev/null || echo 'sem deploy-status.json ainda'"
