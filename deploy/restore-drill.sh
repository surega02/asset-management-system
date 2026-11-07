#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

: "${MYSQL_DEFAULTS:?MySQL client option file is required}"
: "${AMS_RESTORE_DATABASE:?An empty _restore database is required}"
: "${AMS_RESTORE_STORAGE:?A new local directory is required}"
: "${AMS_BACKUP_PASSPHRASE_FILE:?GPG passphrase file is required}"
archive=${1:?Encrypted archive is required}
[[ "$AMS_RESTORE_DATABASE" =~ ^[A-Za-z0-9_]+_restore$ ]]
[[ -r "$archive" && -s "$AMS_BACKUP_PASSPHRASE_FILE" ]]
[[ "$(stat -c %a "$MYSQL_DEFAULTS")" == 600 && "$(stat -c %a "$AMS_BACKUP_PASSPHRASE_FILE")" == 600 ]]
[[ ! -e "$AMS_RESTORE_STORAGE" && ! -L "$AMS_RESTORE_STORAGE" ]]
client=(mysql "--defaults-extra-file=$MYSQL_DEFAULTS" --batch --skip-column-names)
tables=$("${client[@]}" "$AMS_RESTORE_DATABASE" -e 'SHOW TABLES')
[[ -z "$tables" ]]

started=$(date -u +%s)
work=$(mktemp -d)
trap 'rm -f -- "$work/archive.tar"; rmdir -- "$work"' EXIT
gpg --batch --pinentry-mode loopback --no-symkey-cache --passphrase-file "$AMS_BACKUP_PASSPHRASE_FILE" \
  --decrypt "$archive" >"$work/archive.tar"
stamp=$(tar -xOf "$work/archive.tar" snapshot-utc)
[[ "$stamp" =~ ^[0-9]{8}T[0-9]{6}Z$ ]]
snapshot=$(date -u -d "${stamp:0:4}-${stamp:4:2}-${stamp:6:2} ${stamp:9:2}:${stamp:11:2}:${stamp:13:2}" +%s)
mkdir -- "$AMS_RESTORE_STORAGE"
tar --extract --file="$work/archive.tar" --directory="$AMS_RESTORE_STORAGE" --no-same-owner --no-same-permissions private
tar -xOf "$work/archive.tar" database.sql.gz | gzip -dc | "${client[@]}" "$AMS_RESTORE_DATABASE"
finished=$(date -u +%s)
printf 'snapshot_utc=%s\nrecovery_point_age_seconds=%d\nrestore_duration_seconds=%d\n' "$stamp" "$((started - snapshot))" "$((finished - started))"
