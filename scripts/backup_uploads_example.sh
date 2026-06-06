#!/usr/bin/env sh
set -eu

# Exemple de backup quotidien des uploads applicatifs.
# - Archive uniquement le contenu utile genere par l'application
# - Ecrit hors du repo par defaut
# - N'efface rien: la rotation peut etre geree par un autre job
#
# Variables optionnelles:
#   APP_ROOT=/var/www/sitehamala
#   BACKUP_ROOT=/var/backups/sitehamala

umask 077

APP_ROOT="${APP_ROOT:-/var/www/sitehamala}"
BACKUP_ROOT="${BACKUP_ROOT:-/var/backups/sitehamala}"

DATE_STAMP="$(date +%F)"
TIME_STAMP="$(date +%H%M%S)"
DEST_DIR="${BACKUP_ROOT}/uploads/daily"
DEST_FILE="${DEST_DIR}/sitehamala_uploads_${DATE_STAMP}_${TIME_STAMP}.tar.gz"

mkdir -p "${DEST_DIR}"

tar \
  --create \
  --gzip \
  --file "${DEST_FILE}" \
  --directory "${APP_ROOT}" \
  uploads

printf 'Uploads backup created: %s\n' "${DEST_FILE}"
