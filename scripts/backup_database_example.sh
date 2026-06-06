#!/usr/bin/env sh
set -eu

# Exemple de dump MySQL quotidien pour la production.
# - Ne stocke aucun secret dans le script
# - Ecrit hors du repo par defaut
# - N'efface rien: la rotation peut etre geree par un autre job
#
# Variables requises:
#   DB_NAME
# Variables optionnelles:
#   DB_HOST=127.0.0.1
#   DB_PORT=3306
#   DB_USER=sitehamala_backup
#   BACKUP_ROOT=/var/backups/sitehamala
#   MYSQLDUMP_BIN=mysqldump
#
# Authentification recommande:
# - via MYSQL_PWD exporte par le scheduler
# - ou via --defaults-extra-file fourni par l'environnement d'execution

umask 077

DB_NAME="${DB_NAME:?DB_NAME is required}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_USER="${DB_USER:-sitehamala_backup}"
BACKUP_ROOT="${BACKUP_ROOT:-/var/backups/sitehamala}"
MYSQLDUMP_BIN="${MYSQLDUMP_BIN:-mysqldump}"

DATE_STAMP="$(date +%F)"
TIME_STAMP="$(date +%H%M%S)"
DEST_DIR="${BACKUP_ROOT}/db/daily"
DEST_FILE="${DEST_DIR}/${DB_NAME}_${DATE_STAMP}_${TIME_STAMP}.sql.gz"

mkdir -p "${DEST_DIR}"

"${MYSQLDUMP_BIN}" \
  --host="${DB_HOST}" \
  --port="${DB_PORT}" \
  --user="${DB_USER}" \
  --single-transaction \
  --quick \
  --routines \
  --triggers \
  --default-character-set=utf8mb4 \
  "${DB_NAME}" | gzip -9 > "${DEST_FILE}"

printf 'Database backup created: %s\n' "${DEST_FILE}"
