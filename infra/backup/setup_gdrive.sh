#!/bin/bash
# =============================================================================
# AlfaHome — Setup do Google Drive para Backups
# Execute uma vez no VPS após o primeiro deploy.
# =============================================================================
set -e

echo "=== AlfaHome Backup Setup ==="

# 1. Instala rclone
if ! command -v rclone &>/dev/null; then
  echo "[1/5] Instalando rclone..."
  curl -fsSL https://rclone.org/install.sh | bash
  echo "  ✓ rclone instalado: $(rclone version | head -1)"
else
  echo "[1/5] rclone já instalado: $(rclone version | head -1)"
fi

# 2. Cria diretórios e copia scripts
echo "[2/5] Criando diretórios e copiando scripts..."
mkdir -p /opt/alfahome/backup/{logs,run}
chmod 755 /opt/alfahome/backup
chmod 777 /opt/alfahome/backup/run

# Copia scripts do repositório para o diretório de operação
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
for SCRIPT in backup.sh restore.sh list_backups.sh backup.env.example; do
  if [ -f "${SCRIPT_DIR}/${SCRIPT}" ]; then
    cp "${SCRIPT_DIR}/${SCRIPT}" "/opt/alfahome/backup/${SCRIPT}"
    echo "  ✓ ${SCRIPT}"
  fi
done

chmod +x /opt/alfahome/backup/backup.sh
chmod +x /opt/alfahome/backup/restore.sh
chmod +x /opt/alfahome/backup/list_backups.sh
echo "  ✓ Diretórios e scripts configurados"

# 3. Configura Google Drive
if rclone listremotes | grep -q "^gdrive:"; then
  echo "[3/5] Remote 'gdrive' já configurado"
else
  echo "[3/5] Configurando Google Drive..."
  echo ""
  echo "  Você será redirecionado para autenticar com o Google."
  echo "  Em servidor headless: abra o link em outro computador e cole o código aqui."
  echo ""
  rclone config create gdrive drive scope=drive
fi

# 4. Testa conexão
echo "[4/5] Testando conexão com Google Drive..."
if rclone mkdir "gdrive:AlfaHome Backups/test" 2>/dev/null; then
  rclone purge --drive-use-trash=false "gdrive:AlfaHome Backups/test" 2>/dev/null
  echo "  ✓ Google Drive acessível"
else
  echo "  ✗ Falha ao acessar Google Drive. Verifique a configuração do rclone."
  exit 1
fi

# 5. Configura cron
echo "[5/5] Configurando cron jobs..."
# Backup diário às 03h BRT (= 06:00 UTC)
CRON_BACKUP="0 6 * * * /opt/alfahome/backup/backup.sh >> /opt/alfahome/backup/logs/cron.log 2>&1"
# Atualização da lista semanal (domingos às 06:30 UTC)
CRON_LIST="30 6 * * 0 /opt/alfahome/backup/list_backups.sh >> /opt/alfahome/backup/logs/cron.log 2>&1"

(crontab -l 2>/dev/null | grep -v "alfahome/backup"; echo "$CRON_BACKUP"; echo "$CRON_LIST") | crontab -
echo "  ✓ Cron configurado:"
echo "    - Backup diário às 03h BRT (06:00 UTC)"
echo "    - Listagem de backups toda segunda às 06:30 UTC"

# 6. Cria backup.env
BACKUP_ENV="/opt/alfahome/backup/backup.env"
if [ ! -f "$BACKUP_ENV" ]; then
  cp /opt/alfahome/backup/backup.env.example "$BACKUP_ENV"
  chmod 600 "$BACKUP_ENV"
  echo ""
  echo "  ✓ $BACKUP_ENV criado"
  echo "  → Para ativar alertas Telegram, edite o arquivo e preencha TELEGRAM_BOT_TOKEN e TELEGRAM_CHAT_ID"
else
  echo ""
  echo "  backup.env já existe — mantido sem alteração"
fi

echo ""
echo "=== Setup concluído! ==="
echo ""
echo "Para testar o backup manualmente:"
echo "  /opt/alfahome/backup/backup.sh"
echo ""
echo "Para restaurar um backup:"
echo "  /opt/alfahome/backup/restore.sh YYYY-MM-DD"
echo ""
echo "Para ver os logs:"
echo "  tail -f /opt/alfahome/backup/logs/backup_\$(date +%Y-%m-%d).log"
