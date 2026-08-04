#!/usr/bin/env bash
# T-004 — Importa banco e arquivos da produção no LXC 114.
#
# Dois modos:
#   ensaio  (padrão) — usa a cópia diária que o alfa-server já puxa da VPS
#                      (/srv/dados/backups-vps/alfahome). Não toca na VPS.
#   final            — usa um dump passado em DUMP=/caminho/arquivo.sql.gz,
#                      gerado na janela de corte com a VPS já congelada.
#
# O storage da produção é um VOLUME docker na VPS (alfahome_storage-data), não
# um bind — por isso a cópia sai de dentro do container, não do diretório do
# projeto. A pré-cópia de 31/07 já está em /root/staging-storage no 114.
set -euo pipefail

LXC=${LXC:-114}
APP_DIR=${APP_DIR:-/var/www/alfahome}
MODO=${MODO:-ensaio}
DIR_COPIAS=${DIR_COPIAS:-/srv/dados/backups-vps/alfahome}
ORIGEM_STORAGE=${ORIGEM_STORAGE:-/root/staging-storage}

remoto() { ssh alfa-server "pct exec ${LXC} -- bash -lc \"$1\""; }

if [ "${MODO}" = "final" ]; then
  [ -n "${DUMP:-}" ] || { echo "modo final exige DUMP=/caminho/arquivo.sql.gz"; exit 1; }
else
  DUMP=$(ssh alfa-server "ls -1 ${DIR_COPIAS}/*.sql.gz | tail -1")
fi
echo "==> modo ${MODO} · dump: ${DUMP}"

echo "==> senha do banco (lida do .env do 114, nunca gravada em disco aqui)"
SENHA=$(ssh alfa-server "pct exec ${LXC} -- bash -lc \"grep -m1 '^MYSQL_ROOT_PASSWORD=' ${APP_DIR}/.env | cut -d= -f2-\"")
[ -n "${SENHA}" ] || { echo "MYSQL_ROOT_PASSWORD não encontrado no .env"; exit 1; }

# Atenção: o drop/create vai em chamada SEPARADA, sem -i. Se ele entrar no
# mesmo comando que recebe o dump por stdin, o `docker exec -i` engole parte do
# stream e a importação começa no meio do arquivo (sintoma: "Table
# 'alfahome.jobs' doesn't exist at line 4").
echo "==> recriando o schema (drop/create)"
remoto "docker exec alfa-home-db mysql -uroot -p'${SENHA}' -e \\\"DROP DATABASE IF EXISTS alfahome; CREATE DATABASE alfahome CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\\\" 2>&1 | grep -v 'password on the command line' || true"

echo "==> importando o dump"
ssh alfa-server "gzip -dc ${DUMP}" \
  | ssh alfa-server "pct exec ${LXC} -- docker exec -i alfa-home-db mysql -uroot -p'${SENHA}' alfahome"

echo "==> tabelas importadas"
remoto "docker exec alfa-home-db mysql -uroot -p'${SENHA}' -N -e \\\"SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='alfahome'\\\" 2>/dev/null"

if [ "${PULAR_STORAGE:-nao}" = "sim" ]; then
  echo "==> pulando a cópia de arquivos (já veio pelo 05-cutover.sh)"
else
  echo "==> copiando os arquivos de usuário para o volume storage"
  remoto "test -d ${ORIGEM_STORAGE} && docker cp ${ORIGEM_STORAGE}/. alfa-home-app:/var/www/storage/ && docker exec alfa-home-app chown -R www-data:www-data /var/www/storage"
fi

echo "==> conferindo o storage (arquivos e bytes)"
remoto "docker exec alfa-home-app sh -c 'find /var/www/storage -type f | wc -l; du -sb /var/www/storage | cut -f1'"

echo "==> atualizações de banco pendentes (esperado: nenhuma)"
remoto "docker exec alfa-home-app php artisan migrate:status 2>&1 | grep -ci pending || true"

echo "==> refazendo os caches e reiniciando (OPcache com validate_timestamps=0)"
remoto "cd ${APP_DIR} && docker exec alfa-home-app php artisan config:cache && docker exec alfa-home-app php artisan route:cache && docker exec alfa-home-app php artisan view:cache && docker exec alfa-home-app php artisan event:cache && docker compose -f docker-compose.yml restart app"

echo "==> resposta local após a importação"
sleep 8
remoto "curl -sS -o /dev/null -w 'login http %{http_code}\\n' -H 'Host: home.alfasolucoes.cloud' http://localhost:8080/login"
