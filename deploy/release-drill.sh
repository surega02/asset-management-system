#!/usr/bin/env bash
# Disposable Linux/CI environment. Requires Docker Compose, mysql clients and GPG.
set -Eeuo pipefail
umask 077
repo=$(cd "$(dirname "$0")/.." && pwd)
work=$(mktemp -d)
export AMS_ENV_FILE="$work/ams.env" AMS_IMAGE_TAG=release-drill AMS_HTTP_PORT=18080
compose=(docker compose --project-directory "$repo" -p ams-release-drill -f "$repo/compose.yaml" -f "$work/db.yaml")
trap '"${compose[@]}" logs --tail=80; "${compose[@]}" down -v; rm -rf -- "$work"' EXIT
cat >"$work/db.yaml" <<'YAML'
services:
  db:
    image: mysql:8.4
    environment:
      MYSQL_ROOT_PASSWORD: synthetic-root
      MYSQL_DATABASE: ams_release_test
      MYSQL_USER: ams
      MYSQL_PASSWORD: synthetic-only
    ports:
      - "127.0.0.1:33067:3306"
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "127.0.0.1", "--silent"]
      interval: 5s
      timeout: 5s
      retries: 30
YAML
cat >"$AMS_ENV_FILE" <<ENV
APP_NAME=AMS
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:$(openssl rand -base64 32)
APP_URL=http://127.0.0.1:18080
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=ams_release_test
DB_USERNAME=ams
DB_PASSWORD=synthetic-only
SESSION_DRIVER=file
SESSION_SECURE_COOKIE=false
CACHE_STORE=file
QUEUE_CONNECTION=database
LOG_CHANNEL=stderr
ENV
"${compose[@]}" up -d --wait db
gateway=$(docker network inspect ams-release-drill_default --format '{{(index .IPAM.Config 0).Gateway}}')
printf 'AMS_TRUSTED_PROXIES=%s\n' "$gateway" >>"$AMS_ENV_FILE"
"${compose[@]}" build php web
"${compose[@]}" run --rm php php artisan migrate --seed --force
"${compose[@]}" up -d php web worker scheduler
worker_id=$("${compose[@]}" ps -q worker)
docker kill "$worker_id" >/dev/null
for attempt in {1..20}; do
  state=$(docker inspect "$worker_id" --format '{{.State.Running}} {{.RestartCount}}')
  if [[ "$state" == 'true 1' ]]; then break; fi
  sleep 1
done
test "$state" = 'true 1'
"${compose[@]}" exec -T web nginx -t
for attempt in {1..30}; do
  if curl --fail --silent http://127.0.0.1:18080/up >/dev/null; then break; fi
  sleep 2
done
curl --fail --silent http://127.0.0.1:18080/up >/dev/null
curl --fail --silent http://127.0.0.1:18080/login | grep -q 'csrf-token'
curl --fail --silent --header 'X-Forwarded-Proto: https' http://127.0.0.1:18080/login | grep -q 'action="https://'
curl --fail --silent http://127.0.0.1:18080/build/app.css >/dev/null
for path in /.env /index.php /storage/app/private/test; do
  test "$(curl --silent --output /dev/null --write-out '%{http_code}' "http://127.0.0.1:18080$path")" = 404
done
test "$(curl --silent --output /dev/null --write-out '%{http_code}' --request POST \
  --header 'Origin: https://foreign.example' --data 'login=unknown&password=invalid' \
  http://127.0.0.1:18080/login)" = 419

# The queue persists this real import job while its worker is stopped.
"${compose[@]}" stop worker
"${compose[@]}" exec -T php php <<'PHP'
<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $exception): void {
    fwrite(STDERR, 'Synthetic fixture failed: '.$exception->getMessage().PHP_EOL);
    exit(1);
});
use Illuminate\Support\Facades\DB;
$branch = DB::table('branches')->insertGetId(['code' => 'DRILL', 'name' => 'Synthetic']);
$division = DB::table('divisions')->insertGetId(['code' => 'DRILL', 'name' => 'Synthetic', 'branch_id' => $branch]);
$department = DB::table('departments')->insertGetId(['code' => 'DRILL', 'name' => 'Synthetic', 'division_id' => $division]);
$position = DB::table('positions')->insertGetId(['code' => 'DRILL', 'name' => 'Synthetic']);
$location = DB::table('locations')->insertGetId(['code' => 'DRILL', 'name' => 'Synthetic', 'branch_id' => $branch]);
$employee = DB::table('employees')->insertGetId(['nik' => 'DRILL', 'name' => 'Synthetic', 'department_id' => $department, 'position_id' => $position, 'home_location_id' => $location, 'joined_at' => '2026-01-01', 'employment_status' => 'active']);
$user = App\Models\User::create(['employee_id' => $employee, 'password' => bin2hex(random_bytes(32))]);
$batch = DB::table('import_batches')->insertGetId(['user_id' => $user->id, 'status' => 'running', 'total_rows' => 1, 'created_at' => now()]);
DB::table('import_rows')->insert(['import_batch_id' => $batch, 'row_number' => 2, 'action' => 'skip', 'status' => 'pending']);
App\Actions\Imports\AssetImportBatches::dispatch($batch);
if (DB::table('jobs')->count() !== 1) {
    exit(1);
}
file_put_contents(storage_path('app/private/assets/restore-probe.txt'), 'synthetic-private-file');
PHP
"${compose[@]}" start worker
for attempt in {1..30}; do
  finished=$("${compose[@]}" exec -T db mysql -uroot -psynthetic-root -N ams_release_test -e "SELECT COUNT(*) FROM import_batches WHERE status='finished'")
  if [[ "$finished" == 1 ]]; then break; fi
  sleep 2
done
test "$finished" = 1
"${compose[@]}" exec -T php php artisan schedule:list
"${compose[@]}" stop worker scheduler
"${compose[@]}" exec -T db mysql -uroot -psynthetic-root -e "CREATE DATABASE ams_release_restore; GRANT ALL ON ams_release_restore.* TO 'ams'@'%';"
mkdir -p "$work/storage/app" "$work/backups"
"${compose[@]}" cp php:/var/www/html/storage/app/private "$work/storage/app/private"
export MYSQL_DEFAULTS="$work/mysql.cnf" AMS_DATABASE=ams_release_test AMS_STORAGE="$work/storage" AMS_BACKUP_DIR="$work/backups"
export AMS_BACKUP_PASSPHRASE_FILE="$work/passphrase" AMS_RESTORE_DATABASE=ams_release_restore AMS_RESTORE_STORAGE="$work/restored"
printf '[client]\nhost=127.0.0.1\nport=33067\nuser=ams\npassword=synthetic-only\n' >"$MYSQL_DEFAULTS"
openssl rand -base64 48 >"$AMS_BACKUP_PASSPHRASE_FILE"
chmod 600 "$MYSQL_DEFAULTS" "$AMS_BACKUP_PASSPHRASE_FILE"
touch -d '31 days ago' "$AMS_BACKUP_DIR/ams-20000101T000000Z.tar.gpg" "$AMS_BACKUP_DIR/keep-unrelated.txt"
touch -d '29 days ago' "$AMS_BACKUP_DIR/ams-20000102T000000Z.tar.gpg"
archive=$(bash "$repo/deploy/backup.sh")
test ! -e "$AMS_BACKUP_DIR/ams-20000101T000000Z.tar.gpg"
test -e "$AMS_BACKUP_DIR/ams-20000102T000000Z.tar.gpg"
test -e "$AMS_BACKUP_DIR/keep-unrelated.txt"
if tar -tf "$archive" >/dev/null 2>&1; then exit 1; fi
bash "$repo/deploy/restore-drill.sh" "$archive"
cmp "$work/storage/app/private/assets/restore-probe.txt" "$work/restored/private/assets/restore-probe.txt"
test "$(mysql --defaults-extra-file="$MYSQL_DEFAULTS" -N ams_release_restore -e "SELECT COUNT(*) FROM import_batches WHERE status='finished'")" = 1
test "$(mysql --defaults-extra-file="$MYSQL_DEFAULTS" -N ams_release_restore -e "SELECT COUNT(*) FROM import_rows WHERE status='skipped' AND attempt_count=1")" = 1
if bash "$repo/deploy/restore-drill.sh" "$archive"; then exit 1; fi
printf 'release_drill=passed\n'
