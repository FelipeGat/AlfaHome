#!/bin/bash
# =============================================================================
# AlfaHome — Backup Script
# Cria backup global + por tenant e envia ao Google Drive via rclone.
# Executado pelo cron diariamente às 03h (horário de Brasília / 06:00 UTC).
#
# Estrutura no Google Drive:
#   AlfaHome Backups/<date>/
#     global/
#       alfahome_full_<date>.sql.gz       ← dump completo do banco
#       alfahome_storage_<date>.tar.gz    ← storage/ do Laravel
#       configs/                          ← .env + certs + crontab
#     clientes/
#       <TENANT_NOME>/
#         tenant_<id>_<NOME>_<date>.sql.gz
# =============================================================================
set -euo pipefail

DATE=$(TZ=America/Sao_Paulo date +%Y-%m-%d)
TMP_DIR="${TMP_ROOT:-/var/tmp}/alfahome_backup_${DATE}_$$"
LOG_DIR="/opt/alfahome/backup/logs"
LOG_FILE="${LOG_DIR}/backup_${DATE}.log"
RUN_DIR="/opt/alfahome/backup/run"
GDRIVE_REMOTE="gdrive:AlfaHome Backups"
DB_CONTAINER="alfa-home-db"
DB_NAME="alfahome"
PROJECT_DIR="/var/www/alfahome"

mkdir -p "$LOG_DIR" "$TMP_DIR/global" "$TMP_DIR/clientes" "$RUN_DIR"

exec > >(tee -a "$LOG_FILE") 2>&1

# Limpeza garantida do staging, inclusive no caminho de falha. Sem isto cada
# falha deixava o staging para trás em /tmp — que é tmpfs (RAM): no AlfaGym três
# dias assim encheram a memória e derrubaram o `docker exec` da VPS (24/07/2026).
# Por isso o staging também saiu de /tmp para /var/tmp (disco).
trap 'rm -rf "$TMP_DIR"' EXIT
find "${TMP_ROOT:-/var/tmp}" -maxdepth 1 -name 'alfahome_backup_*' -mtime +1 -exec rm -rf {} + 2>/dev/null || true
rm -rf /tmp/alfahome_backup_* 2>/dev/null || true


[ -f /opt/alfahome/backup/backup.env ] && source /opt/alfahome/backup/backup.env

DB_USER=$(docker exec "$DB_CONTAINER" printenv MYSQL_USER 2>/dev/null || echo "alfahome")
DB_PASS=$(docker exec "$DB_CONTAINER" printenv MYSQL_PASSWORD 2>/dev/null)

_status() {
  echo "{\"status\":\"$1\",\"message\":\"$2\",\"updated_at\":\"$(date -Iseconds)\"}" > "${RUN_DIR}/status.json"
}
_log() { echo "[$(date +%H:%M:%S)] $*"; }

INICIO=$(date +%s)
SQL_GLOBAL_SIZE=""
TENANTS_COUNT=0

_notify() {
  local MSG="$1"
  [ -z "${TELEGRAM_BOT_TOKEN:-}" ] && return 0
  curl -s "https://api.telegram.org/bot${TELEGRAM_BOT_TOKEN}/sendMessage" \
    --data-urlencode "chat_id=${TELEGRAM_CHAT_ID}" \
    --data-urlencode "text=${MSG}" > /dev/null 2>&1 || true
}
trap '_notify "❌ Backup AlfaHome GLOBAL falhou — $(date +%H:%M:%S)"' ERR

# Política GFS: diário 7d / semanal 4 semanas / mensal 3 meses
# IMPORTANTE: no backend Google Drive o rclone usa drive_use_trash=true por padrão,
# o que move os arquivos para a Lixeira (continuam ocupando cota) em vez de apagar.
# Por isso todas as remoções abaixo usam --drive-use-trash=false: a contenção
# precisa liberar espaço de fato. Não remover essa flag.
# ─── Faxina: pastas datadas sem dump global ──────────────────────────────────
# Uma pasta sem o SQL global não é ponto de restauração — é resto de upload
# interrompido. Em 08/2026 a cota estourada deixou vários dias assim, e a GFS
# ainda os "protegia" por estarem dentro da janela dos últimos 7 dias.
# A remoção exige EVIDÊNCIA POSITIVA: listagem que falha preserva a pasta. Sem
# essa guarda, uma falha transitória da API do Drive (comum sob rate limit) é
# lida como "pasta sem dump" — foi assim que o snapshot de 11/08 do AlfaGym foi
# apagado por engano, e sem lixeira não há como voltar.
_purge_incompletos() {
  local D LISTAGEM GLOBAL RC
  while IFS= read -r D; do
    [[ -z "$D" || "$D" == "$DATE" ]] && continue

    LISTAGEM=$(rclone lsf "${GDRIVE_REMOTE}/${D}/" 2>/dev/null) && RC=0 || RC=$?
    if [ "$RC" -ne 0 ]; then
      _log "  listagem de ${D} falhou — preservando por precaução"
      continue
    fi

    if [ -z "$LISTAGEM" ]; then
      _log "  vazia — removendo: $D"
      rclone purge --drive-use-trash=false "${GDRIVE_REMOTE}/${D}/" 2>/dev/null || true
      continue
    fi

    GLOBAL=$(rclone lsf "${GDRIVE_REMOTE}/${D}/global/" 2>/dev/null) && RC=0 || RC=$?
    if [ "$RC" -ne 0 ]; then
      _log "  listagem de ${D}/global falhou — preservando por precaução"
      continue
    fi
    if ! echo "$GLOBAL" | grep -q '^alfahome_full_.*\.sql\.gz$'; then
      _log "  sem dump global — removendo: $D"
      rclone purge --drive-use-trash=false "${GDRIVE_REMOTE}/${D}/" 2>/dev/null || true
    fi
  done < <(rclone lsd "${GDRIVE_REMOTE}/" 2>/dev/null | awk '{print $NF}' \
    | grep -E '^[0-9]{4}-[0-9]{2}-[0-9]{2}$' | sort -r)
}

_gfs_cleanup() {
  local DAILY_CUT WEEKLY_CUT MONTHLY_CUT SEEN_WEEKS SEEN_MONTHS
  DAILY_CUT=$(date -d "-7 days" +%Y-%m-%d)
  WEEKLY_CUT=$(date -d "-28 days" +%Y-%m-%d)
  MONTHLY_CUT=$(date -d "-90 days" +%Y-%m-%d)
  SEEN_WEEKS=""
  SEEN_MONTHS=""

  while IFS= read -r D; do
    [[ -z "$D" ]] && continue

    if [[ "$D" > "$DAILY_CUT" || "$D" == "$DAILY_CUT" ]]; then
      continue
    fi

    if [[ "$D" > "$WEEKLY_CUT" || "$D" == "$WEEKLY_CUT" ]]; then
      WEEK=$(date -d "$D" +%G-W%V)
      if echo "$SEEN_WEEKS" | grep -qw "$WEEK"; then
        _log "  GFS semanal — removendo: $D"
        rclone purge --drive-use-trash=false "${GDRIVE_REMOTE}/${D}/" 2>/dev/null || true
      else
        SEEN_WEEKS="$SEEN_WEEKS $WEEK"
      fi
      continue
    fi

    if [[ "$D" > "$MONTHLY_CUT" || "$D" == "$MONTHLY_CUT" ]]; then
      MONTH=$(echo "$D" | cut -c1-7)
      if echo "$SEEN_MONTHS" | grep -qw "$MONTH"; then
        _log "  GFS mensal — removendo: $D"
        rclone purge --drive-use-trash=false "${GDRIVE_REMOTE}/${D}/" 2>/dev/null || true
      else
        SEEN_MONTHS="$SEEN_MONTHS $MONTH"
      fi
      continue
    fi

    _log "  GFS expirado — removendo: $D"
    rclone purge --drive-use-trash=false "${GDRIVE_REMOTE}/${D}/" 2>/dev/null || true

  done < <(rclone lsd "${GDRIVE_REMOTE}/" 2>/dev/null | awk '{print $NF}' \
    | grep -E '^[0-9]{4}-[0-9]{2}-[0-9]{2}$' | sort -r)
}

_log "=== AlfaHome Backup iniciado ==="
_status "running" "Backup em andamento..."

# ─── 1. DUMP MYSQL GLOBAL ────────────────────────────────────────────────────
_log "[1/5] Dump MySQL completo..."
docker exec "$DB_CONTAINER" mysqldump \
  -u "$DB_USER" -p"$DB_PASS" \
  --single-transaction --routines --triggers --add-drop-table \
  "$DB_NAME" 2>/dev/null \
  | gzip > "${TMP_DIR}/global/alfahome_full_${DATE}.sql.gz"

if ! gzip -t "${TMP_DIR}/global/alfahome_full_${DATE}.sql.gz" 2>/dev/null; then
  _notify "❌ Backup AlfaHome: dump SQL global corrompido — $(date +%H:%M:%S)"
  _status "error" "Dump SQL global corrompido"
  rm -rf "$TMP_DIR"; exit 1
fi

SQL_GLOBAL_SIZE=$(du -sh "${TMP_DIR}/global/alfahome_full_${DATE}.sql.gz" | cut -f1)
_log "  ✓ ${SQL_GLOBAL_SIZE}"

# ─── 2. BACKUP STORAGE/ LARAVEL ──────────────────────────────────────────────
_log "[2/5] Backup do storage/ Laravel..."
STORAGE_TAR="${TMP_DIR}/global/alfahome_storage_${DATE}.tar.gz"

if docker volume ls --format '{{.Name}}' | grep -qE "alfahome.*(app.storage|storage)"; then
  VOLUME_NAME=$(docker volume ls --format '{{.Name}}' | grep -E "alfahome.*(app.storage|storage)" | head -1)
  STORAGE_PATH="/var/lib/docker/volumes/${VOLUME_NAME}/_data"
  if [ -d "$STORAGE_PATH" ]; then
    tar -czf "${STORAGE_TAR}" -C "${STORAGE_PATH}" . 2>/dev/null
    _log "  ✓ Storage (volume Docker): $(du -sh "${STORAGE_TAR}" | cut -f1)"
  fi
elif [ -d "${PROJECT_DIR}/storage" ]; then
  tar -czf "${STORAGE_TAR}" -C "${PROJECT_DIR}" storage/ 2>/dev/null
  _log "  ✓ Storage (diretório): $(du -sh "${STORAGE_TAR}" | cut -f1)"
else
  _log "  ⚠ Storage não localizado — pulando"
fi

# ─── 3. BACKUP POR TENANT ────────────────────────────────────────────────────
_log "[3/5] Backup por tenant..."

# Tabelas com tenant_id (exceto tenants, tratada separadamente)
TABLES=$(docker exec "$DB_CONTAINER" mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" 2>/dev/null \
  -N -e "SELECT TABLE_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA='${DB_NAME}' AND COLUMN_NAME='tenant_id'
         AND TABLE_NAME != 'tenants'
         ORDER BY TABLE_NAME;")

# Lista de tenants
TENANTS=$(docker exec "$DB_CONTAINER" mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" 2>/dev/null \
  -N -e "SELECT id, REGEXP_REPLACE(UPPER(nome), '[^A-Z0-9]', '_') FROM tenants ORDER BY id;")

while IFS=$'\t' read -r TID TNOME; do
  [ -z "$TID" ] && continue
  TENANTS_COUNT=$((TENANTS_COUNT + 1))
  _log "  → tenant_id=${TID} (${TNOME})"

  CLIENT_DIR="${TMP_DIR}/clientes/${TNOME}"
  mkdir -p "$CLIENT_DIR"
  CLIENT_SQL="${CLIENT_DIR}/tenant_${TID}_${TNOME}_${DATE}.sql"

  {
    echo "-- AlfaHome backup tenant_id=${TID} nome=${TNOME} date=${DATE}"
    echo "SET FOREIGN_KEY_CHECKS=0;"
    echo ""

    # Dump do tenant (linha da tabela tenants)
    docker exec "$DB_CONTAINER" mysqldump \
      -u "$DB_USER" -p"$DB_PASS" \
      --no-create-info --compact --skip-triggers \
      --where="id=${TID}" \
      "$DB_NAME" tenants 2>/dev/null

    # Dump de cada tabela com tenant_id
    while IFS= read -r TABLE; do
      [ -z "$TABLE" ] && continue
      docker exec "$DB_CONTAINER" mysqldump \
        -u "$DB_USER" -p"$DB_PASS" \
        --no-create-info --compact --skip-triggers \
        --where="tenant_id=${TID}" \
        "$DB_NAME" "$TABLE" 2>/dev/null
    done <<< "$TABLES"

    echo "SET FOREIGN_KEY_CHECKS=1;"
  } > "$CLIENT_SQL"

  gzip -f "$CLIENT_SQL"

  if ! gzip -t "${CLIENT_SQL}.gz" 2>/dev/null; then
    _notify "❌ Backup AlfaHome corrompido (tenant ${TID}) — dump SQL inválido"
    _status "error" "Dump SQL corrompido: ${CLIENT_SQL}.gz"
    rm -rf "$TMP_DIR"; exit 1
  fi
  _log "    SQL: $(du -sh "${CLIENT_SQL}.gz" | cut -f1)"

done <<< "$TENANTS"

# ─── 4. BACKUP CONFIGS DO SERVIDOR ───────────────────────────────────────────
_log "[4/5] Backup de configurações do servidor..."
CONFIG_DIR="${TMP_DIR}/global/configs"
mkdir -p "$CONFIG_DIR"

for ENV_FILE in "${PROJECT_DIR}/.env.production" "${PROJECT_DIR}/.env"; do
  if [ -f "$ENV_FILE" ]; then
    cp "$ENV_FILE" "${CONFIG_DIR}/$(basename "$ENV_FILE")"
    _log "  ✓ $(basename "$ENV_FILE")"
  fi
done

if [ -d /etc/letsencrypt ]; then
  tar -czf "${CONFIG_DIR}/letsencrypt_${DATE}.tar.gz" \
    -C /etc letsencrypt/ 2>/dev/null
  _log "  ✓ Let's Encrypt: $(du -sh "${CONFIG_DIR}/letsencrypt_${DATE}.tar.gz" | cut -f1)"
fi

crontab -l > "${CONFIG_DIR}/crontab_root.txt" 2>/dev/null && _log "  ✓ crontab" || true

# ─── 5. CÓPIA LOCAL + FAXINA + UPLOAD ────────────────────────────────────────
# A cópia local é a base; o Drive é a cópia externa. Nenhuma depende da outra.
# Até 08/2026 não havia cópia local nenhuma: quando o upload falhava, o backup
# daquele dia simplesmente deixava de existir. Foi o que aconteceu de 13 a
# 18/08, com a cota do Drive estourada.
LOCAL_ROOT="/opt/alfahome/backup/data"
LOCAL_BACKUP_DIR="${LOCAL_ROOT}/${DATE}"
rm -rf "$LOCAL_BACKUP_DIR"
mkdir -p "$LOCAL_BACKUP_DIR"
cp -r "${TMP_DIR}/." "$LOCAL_BACKUP_DIR/" 2>/dev/null || true
_log "[5/5] Cópia local: ${LOCAL_BACKUP_DIR} — $(du -sh "$LOCAL_BACKUP_DIR" | cut -f1)"
find "$LOCAL_ROOT" -maxdepth 1 -type d -name '20*' -mtime +7 -exec rm -rf {} + 2>/dev/null || true

GDRIVE_OK=false
if rclone lsd "${GDRIVE_REMOTE}/" >/dev/null 2>&1; then

  # FAXINA ANTES DO UPLOAD — ORDEM CRÍTICA. Até 08/2026 ela vinha depois: com
  # `set -e`, a falha de cota abortava o script antes dela, então a nuvem cheia
  # impedia a única rotina capaz de esvaziá-la e o estado não se recuperava.
  _log "  Faxina do Google Drive..."
  _purge_incompletos
  _gfs_cleanup

  _log "  Enviando ao Google Drive..."
  if rclone copy "${TMP_DIR}" "${GDRIVE_REMOTE}/${DATE}/" 2>&1 | tail -3; then
    GDRIVE_OK=true
    _log "  ✓ Upload concluído"
  else
    _log "  ⚠ Upload falhou — backup mantido localmente"
  fi
else
  _log "  ⚠ Google Drive indisponível — backup apenas local"
fi

/opt/alfahome/backup/list_backups.sh 2>/dev/null || true

DURACAO=$(( $(date +%s) - INICIO ))
[ "$DURACAO" -ge 60 ] && DUR_FMT="$((DURACAO/60))m $((DURACAO%60))s" || DUR_FMT="${DURACAO}s"

# O aviso precisa distinguir "completo" de "só local" — backup sem cópia fora
# do servidor é meio backup.
if $GDRIVE_OK; then
  _notify "✅ Backup Global AlfaHome concluído
🏠 ${TENANTS_COUNT} tenant(s) processado(s)
📦 SQL global: ${SQL_GLOBAL_SIZE}
☁️ Local + Google Drive
📅 ${DATE} | ⏱ ${DUR_FMT}"
  _log "=== Backup concluído com sucesso (local + Drive) ==="
  _status "success" "Backup global concluído com sucesso."
else
  _notify "⚠️ Backup AlfaHome SEM CÓPIA EXTERNA
🏠 ${TENANTS_COUNT} tenant(s) — cópia local OK em ${LOCAL_BACKUP_DIR}
❌ Envio ao Google Drive falhou — os dados estão apenas neste servidor
📅 ${DATE} | ⏱ ${DUR_FMT}"
  _log "=== Backup local OK — Drive falhou ==="
  _status "warning" "Backup local concluído; envio ao Google Drive falhou."
fi
