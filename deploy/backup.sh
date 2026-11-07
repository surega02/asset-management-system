#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

: "${MYSQL_DEFAULTS:?MySQL client option file is required}"
: "${AMS_DATABASE:?Database name is required}"
: "${AMS_STORAGE:?Application storage directory is required}"
: "${AMS_BACKUP_DIR:?Backup directory is required}"
: "${AMS_BACKUP_PASSPHRASE_FILE:?GPG passphrase file is required}"
[[ "$AMS_DATABASE" =~ ^[A-Za-z0-9_]+$ ]]
[[ -r "$MYSQL_DEFAULTS" && -s "$AMS_BACKUP_PASSPHRASE_FILE" && -d "$AMS_STORAGE/app/private" ]]
[[ "$(stat -c %a "$MYSQL_DEFAULTS")" == 600 && "$(stat -c %a "$AMS_BACKUP_PASSPHRASE_FILE")" == 600 ]]
mkdir -p -- "$AMS_BACKUP_DIR"
exec 9>"$AMS_BACKUP_DIR/.backup.lock"
flock -n 9

stamp=$(date -u +%Y%m%dT%H%M%SZ)
work=$(mktemp -d)
pending=$(mktemp "$AMS_BACKUP_DIR/.ams-XXXXXXXX")
trap 'rm -f -- "$pending" "$work/database.sql.gz" "$work/snapshot-utc"; rmdir -- "$work"' EXIT
printf '%s\n' "$stamp" >"$work/snapshot-utc"
mysqldump --defaults-extra-file="$MYSQL_DEFAULTS" --single-transaction --quick --no-tablespaces \
  --set-gtid-purged=OFF --hex-blob "$AMS_DATABASE" | gzip -c >"$work/database.sql.gz"
tar -c -C "$work" snapshot-utc database.sql.gz -C "$AMS_STORAGE/app" private | \
  gpg --batch --yes --pinentry-mode loopback --no-symkey-cache --passphrase-file "$AMS_BACKUP_PASSPHRASE_FILE" \
    --cipher-algo AES256 --compress-algo none --symmetric --output "$pending"
test -s "$pending"
mv -- "$pending" "$AMS_BACKUP_DIR/ams-$stamp.tar.gpg"
find "$AMS_BACKUP_DIR" -maxdepth 1 -type f -name 'ams-????????T??????Z.tar.gpg' -mmin +43200 -delete
printf '%s\n' "$AMS_BACKUP_DIR/ams-$stamp.tar.gpg"
