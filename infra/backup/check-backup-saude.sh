#!/bin/bash
# =============================================================================
# AlfaHome — Sonda de saúde do backup
# Roda no cron (05:30) e responde à única pergunta que importa:
#   "Existe hoje uma cópia dos dados fora deste servidor?"
#
# POR QUE ESTE SCRIPT EXISTE
# O antecessor (check-gdrive-quota.sh) avisava "Drive acima de 70%" todos os
# dias. Entre 08 e 18/08/2026 ele disparou 11 vezes seguidas e ninguém agiu —
# alerta que toca todo dia deixa de ser alerta. Enquanto isso o sinal que
# realmente importava (7 dias sem snapshot íntegro na nuvem) não era emitido
# por ninguém, e no AlfaControl o Telegram ainda dizia "✅ concluído com
# sucesso" com o upload falhando.
#
# Regras:
#   • Idade da cópia externa é o alerta principal; a cota é secundária.
#   • Não repete o mesmo alerta no mesmo dia (evita virar ruído).
#   • Silêncio = tudo certo. Se está tudo bem, não manda nada.
# =============================================================================
set -uo pipefail

APP_NOME="AlfaHome"
GDRIVE_REMOTE="gdrive:AlfaHome Backups"
DUMP_GLOB='^alfahome_full_.*\.sql\.gz$'
LOCAL_ROOT="/opt/alfahome/backup/data"
ENV_FILE=/opt/alfahome/backup/backup.env
LOG=/opt/alfahome/backup/logs/saude.log
ESTADO=/opt/alfahome/backup/run/saude-ultimo-alerta

# Dias de atraso a partir dos quais avisa / trata como crítico
ALERTA_DIAS="${ALERTA_DIAS:-2}"
CRITICO_DIAS="${CRITICO_DIAS:-3}"
# Só reclama da cota acima disto (o backup corrigido usa ~1 GB, não 12)
COTA_LIMITE="${COTA_LIMITE:-85}"

mkdir -p "$(dirname "$LOG")" "$(dirname "$ESTADO")"
[ -f "$ENV_FILE" ] && . "$ENV_FILE"

log(){ echo "$(TZ=America/Sao_Paulo date '+%F %T') $*" >> "$LOG"; }
notify(){
  [ -n "${TELEGRAM_BOT_TOKEN:-}" ] && [ -n "${TELEGRAM_CHAT_ID:-}" ] || return 0
  curl -s -o /dev/null --max-time 20 \
    "https://api.telegram.org/bot${TELEGRAM_BOT_TOKEN}/sendMessage" \
    -d "chat_id=${TELEGRAM_CHAT_ID}" -d "text=$1" || true
}
# Emite no máximo um alerta por assunto por dia
notify_once(){
  local CHAVE="$1" MSG="$2" HOJE
  HOJE=$(TZ=America/Sao_Paulo date +%F)
  if grep -q "^${CHAVE}=${HOJE}$" "$ESTADO" 2>/dev/null; then
    log "  (alerta '${CHAVE}' já enviado hoje — silenciado)"
    return 0
  fi
  grep -v "^${CHAVE}=" "$ESTADO" 2>/dev/null > "${ESTADO}.tmp" || true
  echo "${CHAVE}=${HOJE}" >> "${ESTADO}.tmp"
  mv "${ESTADO}.tmp" "$ESTADO"
  notify "$MSG"
  log "  ALERTA enviado: ${CHAVE}"
}

dias_desde(){  # dias_desde YYYY-MM-DD -> nº de dias até hoje
  local ALVO HOJE
  ALVO=$(date -d "$1" +%s 2>/dev/null) || { echo 9999; return; }
  HOJE=$(date -d "$(TZ=America/Sao_Paulo date +%F)" +%s)
  echo $(( (HOJE - ALVO) / 86400 ))
}

# ─── 1. Cópia externa: qual o snapshot íntegro mais recente no Drive? ────────
ULTIMO_DRIVE=""
for D in $(rclone lsd "${GDRIVE_REMOTE}/" 2>/dev/null | awk '{print $NF}' \
             | grep -E '^[0-9]{4}-[0-9]{2}-[0-9]{2}$' | sort -r); do
  if rclone lsf "${GDRIVE_REMOTE}/${D}/global/" 2>/dev/null | grep -qE "$DUMP_GLOB"; then
    ULTIMO_DRIVE="$D"
    break
  fi
done

if [ -z "$ULTIMO_DRIVE" ]; then
  log "cópia externa: NENHUMA encontrada"
  notify_once "sem-externa" "🚨 ${APP_NOME}: NENHUMA cópia de backup fora do servidor.
Nenhum snapshot íntegro foi encontrado no Google Drive.
Se este servidor for perdido agora, não há de onde restaurar."
else
  IDADE=$(dias_desde "$ULTIMO_DRIVE")
  log "cópia externa: ${ULTIMO_DRIVE} (${IDADE} dia(s))"
  if [ "$IDADE" -ge "$CRITICO_DIAS" ]; then
    notify_once "externa-atrasada" "🚨 ${APP_NOME}: backup sem cópia externa há ${IDADE} dias.
Último snapshot íntegro no Google Drive: ${ULTIMO_DRIVE}.
Os dados dos clientes estão apenas neste servidor — verifique o log em ${LOG%/*}/."
  elif [ "$IDADE" -ge "$ALERTA_DIAS" ]; then
    notify_once "externa-atrasada" "⚠️ ${APP_NOME}: a cópia externa mais recente é de ${ULTIMO_DRIVE} (${IDADE} dias).
O envio ao Google Drive pode estar falhando."
  fi
fi

# ─── 2. Cópia local: o backup diário está mesmo rodando? ────────────────────
ULTIMO_LOCAL=$(find "$LOCAL_ROOT" -mindepth 1 -maxdepth 1 -type d -name '20*' 2>/dev/null \
                 | xargs -r -n1 basename | sort -r | head -1)
if [ -z "$ULTIMO_LOCAL" ]; then
  log "cópia local: NENHUMA em ${LOCAL_ROOT}"
  notify_once "sem-local" "🚨 ${APP_NOME}: não há nenhuma cópia local em ${LOCAL_ROOT}.
O backup diário não está produzindo arquivo — verifique o cron e o log."
else
  IDADE_LOCAL=$(dias_desde "$ULTIMO_LOCAL")
  log "cópia local: ${ULTIMO_LOCAL} (${IDADE_LOCAL} dia(s))"
  if [ "$IDADE_LOCAL" -ge "$ALERTA_DIAS" ]; then
    notify_once "local-atrasada" "🚨 ${APP_NOME}: o backup diário não roda há ${IDADE_LOCAL} dias.
Última cópia local: ${ULTIMO_LOCAL}. Verifique o cron e o log do backup."
  fi
fi

# ─── 3. Cota do Drive (secundária — só se estiver realmente apertada) ───────
JSON=$(timeout 90 rclone about gdrive: --json 2>/dev/null)
if [ -z "$JSON" ]; then
  log "cota: rclone about falhou"
  notify_once "cota-inacessivel" "⚠️ ${APP_NOME}: não consegui consultar a cota do Google Drive (credencial ou rede)."
else
  read -r TOTAL USED TRASH < <(python3 - "$JSON" <<'PY'
import json, sys
d = json.loads(sys.argv[1])
print(d.get("total", 0), d.get("used", 0), d.get("trashed", 0))
PY
)
  if [ "${TOTAL:-0}" -gt 0 ] 2>/dev/null; then
    PCT=$(( USED * 100 / TOTAL ))
    GB(){ awk -v b="$1" 'BEGIN{printf "%.1f", b/1073741824}'; }
    RESUMO="$(GB "$USED") GB de $(GB "$TOTAL") GB (${PCT}%)"
    [ "${TRASH:-0}" -gt 1073741824 ] && RESUMO="$RESUMO · lixeira: $(GB "$TRASH") GB"
    log "cota: $RESUMO"
    if [ "$PCT" -ge "$COTA_LIMITE" ]; then
      MSG="⚠️ ${APP_NOME}: Google Drive dos backups em ${PCT}% — $RESUMO.
A conta é compartilhada entre os produtos Alfa; acima de ${COTA_LIMITE}% os envios começam a falhar.
Lembrete: no Drive, apagar só libera cota depois de ESVAZIAR A LIXEIRA."
      [ "${TRASH:-0}" -gt 1073741824 ] && MSG="$MSG
Há $(GB "$TRASH") GB na lixeira — esvaziar já resolve boa parte."
      notify_once "cota" "$MSG"
    fi
  else
    log "cota: resposta sem total"
  fi
fi
