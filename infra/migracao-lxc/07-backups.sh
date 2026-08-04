#!/usr/bin/env bash
# T-008 — Cópias de segurança da produção nova.
#
# São duas rotinas independentes, de propósito:
#  · local  — o host alfa-server puxa dump de cada LXC (/usr/local/sbin/backup-dados.sh)
#             e o backup-check.sh alarma se algum ficar velho;
#  · externa — o próprio LXC manda para o Google Drive via rclone (a única cópia
#             que sobrevive à perda do servidor inteiro).
#
# Enquanto a VPS for plano B, a entrada `alfahome` do backup-vps continua —
# ela só sai no dia em que a VPS for desligada.
set -euo pipefail

LXC=${LXC:-114}

remoto() { ssh alfa-server "pct exec ${LXC} -- bash -lc \"$1\""; }

echo "==> [1/4] rclone no 114 (a cópia externa depende dele)"
if remoto "command -v rclone >/dev/null"; then
  echo "  rclone já instalado"
else
  echo "  instalando rclone"
  remoto "apt-get update -qq && apt-get install -y -qq rclone && rclone version | head -1"
fi
remoto "test -f /root/.config/rclone/rclone.conf && echo '  configuração do rclone presente' || echo '  FALTA /root/.config/rclone/rclone.conf'"
remoto "test -f /opt/alfahome/backup/backup.env && echo '  backup.env presente' || echo '  FALTA /opt/alfahome/backup/backup.env'"

echo "==> [2/4] cópia externa: uma rodada à mão antes de agendar"
remoto "test -x /opt/alfahome/backup/backup.sh && /opt/alfahome/backup/backup.sh || echo '  (backup.sh ainda não está no 114)'"

echo "==> [3/4] agendando a cópia externa no 114 (06:00, como era na VPS)"
remoto "( crontab -l 2>/dev/null | grep -v 'alfahome/backup'; \
  echo '0 6 * * * /opt/alfahome/backup/backup.sh >> /opt/alfahome/backup/logs/cron.log 2>&1' ) | crontab - && crontab -l"

echo "==> [4/4] incluindo a produção nova nas rotinas do host"
ssh alfa-server "grep -q '114:alfa-home-db:alfahome-prod' /usr/local/sbin/backup-dados.sh \
  || sed -i 's|\"104:alfa-home-db:alfahome\"|\"104:alfa-home-db:alfahome\"\n  \"114:alfa-home-db:alfahome-prod\"|' /usr/local/sbin/backup-dados.sh; \
  grep -n 'alfahome' /usr/local/sbin/backup-dados.sh"
ssh alfa-server "sed -i 's|for a in alfagym alfamed alfahome gestoralfa; do|for a in alfagym alfamed alfahome alfahome-prod gestoralfa; do|' /usr/local/sbin/backup-check.sh; \
  grep -n 'alfahome-prod' /usr/local/sbin/backup-check.sh || echo '  conferir backup-check.sh à mão'"

echo "==> rodando a cópia local uma vez e conferindo"
ssh alfa-server "/usr/local/sbin/backup-dados.sh >/dev/null 2>&1 || true; ls -la /srv/backup/db/ | grep alfahome-prod | tail -3"

echo
echo "LEMBRETE: desligar o cron de backup da VPS assim que a cópia externa do 114"
echo "rodar verde (senão os dois mandam para o mesmo destino no Drive)."
