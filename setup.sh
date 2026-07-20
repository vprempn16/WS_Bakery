#!/bin/bash
# BkPortal (Bakery WMS) installer
#
# - Installs Composer / NPM deps
# - Configures MySQL .env + migrate + seed
# - Syncs ModuleFieldConfig → crm_fields (displaytype / mandatory)
# - Seeds portal_module rows from app/Models/BkPortal/PortalModuleSeed.php
#
# Intentionally NOT included (out of scope for BkPortal — do not re-add):
# - Member module / Member↔User sync / member_user_id
# - technician:ensure-access / "Technician" profile+role bootstrap
# - Lead, Contact, Quotation, Invoice, Checklist modules
# - Generating stub Controllers over existing bakery modules
#
# Usage:
#   ./setup.sh                 Full install
#   ./setup.sh --fields-only   Re-sync field metadata only
#   ./setup.sh --skip-db       Skip interactive DB / migrate / seed
#
# Optional password:
#   export BK_INSTALLER_PASSWORD="YourSecurePassword" && ./setup.sh

set -uo pipefail

INSTALLER_PASSWORD="${BK_INSTALLER_PASSWORD:-1}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LARAVEL_ROOT="${SCRIPT_DIR}"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log_info()    { echo -e "${BLUE}ℹ${NC} $1"; }
log_success() { echo -e "${GREEN}✅${NC} $1"; }
log_warning() { echo -e "${YELLOW}⚠${NC} $1"; }
log_error()   { echo -e "${RED}❌${NC} $1" >&2; }

FIELDS_ONLY=0
SKIP_DB=0
for arg in "$@"; do
  case "$arg" in
    --fields-only) FIELDS_ONLY=1 ;;
    --skip-db)     SKIP_DB=1 ;;
    -h|--help)
      sed -n '2,16p' "$0"
      exit 0
      ;;
  esac
done

verify_installer_password() {
  echo ""
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  echo "🔐 BkPortal Installation"
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  local attempts=0
  while [ $attempts -lt 3 ]; do
    read -rsp "Enter installation password: " entered
    echo ""
    if [ "$entered" = "$INSTALLER_PASSWORD" ]; then
      log_success "Password verified"
      return 0
    fi
    attempts=$((attempts + 1))
    log_error "Invalid password ($((3 - attempts)) left)"
  done
  exit 1
}

install_dependencies() {
  echo ""
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  echo "📦 Dependencies"
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  cd "$LARAVEL_ROOT"

  if [ ! -f composer.json ]; then
    log_error "composer.json not found in $LARAVEL_ROOT"
    exit 1
  fi

  if [ ! -f vendor/autoload.php ]; then
    log_info "composer install..."
    composer install --no-interaction --prefer-dist --optimize-autoloader || exit 1
  else
    composer dump-autoload --optimize --quiet 2>/dev/null || true
    log_info "Composer deps present"
  fi

  if [ -f package.json ] && command -v npm >/dev/null 2>&1; then
    if [ ! -d node_modules ]; then
      log_info "npm install..."
      npm install --silent || log_warning "npm install failed (non-fatal)"
    fi
  fi
  log_success "Dependencies ready"
}

set_storage_permissions() {
  cd "$LARAVEL_ROOT"
  mkdir -p storage/app/Profiles storage/framework/{cache,sessions,views} bootstrap/cache
  chmod -R u+rwX,g+rwX storage bootstrap/cache 2>/dev/null || true
  log_success "storage / bootstrap/cache writable"
}

create_basic_env_file() {
  local env_file="$1"
  cat > "$env_file" <<'ENVFILE'
APP_NAME="BkPortal"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bakery_wms
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
FILESYSTEM_DISK=local
ENVFILE
}

update_env_db() {
  local env_file="$1" host="$2" port="$3" name="$4" user="$5" pass="$6"
  ENV_FILE_PATH="$env_file" DB_HOST_VAL="$host" DB_PORT_VAL="$port" \
  DB_DATABASE_VAL="$name" DB_USERNAME_VAL="$user" DB_PASSWORD_VAL="$pass" php <<'PHP'
<?php
$envFile = getenv('ENV_FILE_PATH');
$map = [
    'DB_CONNECTION' => 'mysql',
    'DB_HOST' => getenv('DB_HOST_VAL'),
    'DB_PORT' => getenv('DB_PORT_VAL'),
    'DB_DATABASE' => getenv('DB_DATABASE_VAL'),
    'DB_USERNAME' => getenv('DB_USERNAME_VAL'),
    'DB_PASSWORD' => '"' . str_replace('"', '\\"', getenv('DB_PASSWORD_VAL') ?: '') . '"',
];
$lines = file_exists($envFile) ? file($envFile, FILE_IGNORE_NEW_LINES) : [];
$found = array_fill_keys(array_keys($map), false);
$out = [];
foreach ($lines as $line) {
    $matched = false;
    foreach ($map as $key => $value) {
        if (preg_match('/^' . preg_quote($key, '/') . '\s*=/i', trim($line))) {
            $out[] = "{$key}={$value}";
            $found[$key] = true;
            $matched = true;
            break;
        }
    }
    if (!$matched) {
        $out[] = $line;
    }
}
foreach ($found as $key => $ok) {
    if (!$ok) {
        $out[] = "{$key}={$map[$key]}";
    }
}
file_put_contents($envFile, implode("\n", $out) . "\n");
echo "OK";
PHP
}

setup_database() {
  echo ""
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  echo "🗄️  Database"
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  cd "$LARAVEL_ROOT"

  read -rp "Database Host [127.0.0.1]: " db_host
  db_host=${db_host:-127.0.0.1}
  read -rp "Database Port [3306]: " db_port
  db_port=${db_port:-3306}
  read -rp "Database Username [root]: " db_user
  db_user=${db_user:-root}
  read -rsp "Database Password: " db_pass
  echo ""
  read -rp "Database Name [bakery_wms]: " db_name
  db_name=${db_name:-bakery_wms}

  log_info "Creating database '${db_name}' if missing..."
  H="$db_host" P="$db_port" U="$db_user" PW="$db_pass" D="$db_name" \
  php -r '
$host=getenv("H"); $port=getenv("P"); $user=getenv("U"); $pass=getenv("PW"); $db=getenv("D");
try {
  $pdo=new PDO("mysql:host=$host;port=$port;charset=utf8mb4",$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
  $pdo->exec("USE `$db`");
  echo "OK\n";
} catch (Throwable $e) {
  fwrite(STDERR, $e->getMessage() . PHP_EOL);
  exit(1);
}
' || { log_error "MySQL connection / CREATE DATABASE failed"; exit 1; }
  log_success "Database '${db_name}' ready"

  local env_file="${LARAVEL_ROOT}/.env"
  if [ ! -f "$env_file" ]; then
    if [ -f "${LARAVEL_ROOT}/.env.example" ]; then
      cp "${LARAVEL_ROOT}/.env.example" "$env_file"
    else
      create_basic_env_file "$env_file"
    fi
  fi
  chmod u+w "$env_file" 2>/dev/null || true
  cp "$env_file" "${env_file}.backup.$(date +%Y%m%d_%H%M%S)" 2>/dev/null || true
  update_env_db "$env_file" "$db_host" "$db_port" "$db_name" "$db_user" "$db_pass"
  log_success ".env DB settings updated (mysql)"

  php artisan config:clear >/dev/null 2>&1 || true
  rm -f bootstrap/cache/config.php 2>/dev/null || true
  if ! grep -q '^APP_KEY=base64:' "$env_file" 2>/dev/null; then
    php artisan key:generate --force >/dev/null 2>&1 || true
  fi

  log_info "Verifying Laravel MySQL connection..."
  if ! php artisan db:show >/dev/null 2>&1; then
    # Fallback check via PDO through Laravel
    if ! php -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\DB::connection()->getPdo();
echo "OK";
' >/dev/null 2>&1; then
      log_error "Laravel cannot connect with updated .env — check credentials"
      exit 1
    fi
  fi
  log_success "Laravel DB connection OK"

  log_info "Running migrations (creates crm_fields, picklist_values, portal_module, ...)..."
  php artisan migrate --force || { log_error "migrate failed"; exit 1; }
  log_success "Migrations done"

  log_info "Running seeders..."
  php artisan db:seed --force || log_warning "db:seed failed (may already be seeded)"
  log_success "Database setup complete"
}

sync_module_fields() {
  echo ""
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  echo "🧩 Sync CRM fields (displaytype / mandatory)"
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  cd "$LARAVEL_ROOT"

  if ! php -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
exit(Illuminate\Support\Facades\Schema::hasTable("crm_fields") ? 0 : 1);
'; then
    log_error "crm_fields table missing — run migrations first (./setup.sh without --fields-only)"
    exit 1
  fi

  php artisan migrate:module-fields || { log_error "migrate:module-fields failed"; exit 1; }

  php -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$total = DB::table("crm_fields")->where("deleted", 0)->where("is_custom_field", 0)->count();
$byModule = DB::table("crm_fields")
  ->where("deleted", 0)->where("is_custom_field", 0)
  ->select("modulename", DB::raw("count(*) as c"))
  ->groupBy("modulename")->orderBy("modulename")->get();
echo "   Total system fields: {$total}\n";
foreach ($byModule as $row) {
  echo "   - {$row->modulename}: {$row->c}\n";
}
'
  log_success "crm_fields synced from ModuleFieldConfig"
}

seed_portal_modules() {
  echo ""
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  echo "📋 Portal modules"
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  cd "$LARAVEL_ROOT"
  php <<'PHP'
<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Carbon\Carbon;

$file = __DIR__ . '/app/Models/BkPortal/PortalModuleSeed.php';
if (!file_exists($file) || !Schema::hasTable('portal_module')) {
    echo "   ↪ portal_module seed skipped\n";
    exit(0);
}
$rows = include $file;
$now = Carbon::now();
$n = 0;
foreach ($rows as $row) {
    if (empty($row['modulename'])) continue;
    $exists = DB::table('portal_module')->where('modulename', $row['modulename'])->exists();
    if ($exists) continue;
    DB::table('portal_module')->insert([
        'id' => $row['id'] ?: (string) Str::uuid(),
        'modulename' => $row['modulename'],
        'modulelabel' => $row['modulelabel'] ?? $row['modulename'],
        'is_entity' => (int) ($row['is_entity'] ?? 1),
        'status' => $row['status'] ?? 'Active',
        'sort_order' => (int) ($row['sort_order'] ?? 0),
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $n++;
}
echo "   ✅ Inserted {$n} portal modules\n";
PHP
  log_success "Portal modules ready"
}

seed_superadmin() {
  echo ""
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  echo "🛠  Developer superadmin (not for clients)"
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  cd "$LARAVEL_ROOT"
  php <<'PHP'
<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

if (! Schema::hasTable('users') || ! Schema::hasTable('organizations') || ! Schema::hasTable('branches')) {
    echo "   ↪ tables missing — skip\n";
    exit(0);
}

DB::table('users')->where('role', 'owner')->update(['role' => 'admin']);

$orgId = DB::table('organizations')->where('email', 'system@bkportal.local')->value('id');
if (! $orgId) {
    $orgId = (string) Str::uuid();
    DB::table('organizations')->insert([
        'id' => $orgId,
        'name' => 'BkPortal System',
        'description' => 'Internal developer org — not a client bakery',
        'email' => 'system@bkportal.local',
        'phone' => null,
        'address' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

$branchId = DB::table('branches')->where('organization_id', $orgId)->where('name', 'Main')->value('id');
if (! $branchId) {
    $branchId = (string) Str::uuid();
    DB::table('branches')->insert([
        'id' => $branchId,
        'organization_id' => $orgId,
        'name' => 'Main',
        'type' => 'warehouse',
        'address' => null,
        'phone' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

$existing = DB::table('users')->where('email', 'superadmin@example.com')->first();
$payload = [
    'organization_id' => $orgId,
    'branch_id' => $branchId,
    'first_name' => 'Super',
    'last_name' => 'Admin',
    'email' => 'superadmin@example.com',
    'phone' => null,
    'role' => 'superadmin',
    'is_active' => 1,
    'password' => Hash::make('Admin@123'),
    'updated_at' => now(),
];

if ($existing) {
    DB::table('users')->where('id', $existing->id)->update($payload);
    echo "   ✅ Updated superadmin@example.com (role=superadmin)\n";
} else {
    $payload['id'] = (string) Str::uuid();
    $payload['created_at'] = now();
    DB::table('users')->insert($payload);
    echo "   ✅ Created superadmin@example.com / Admin@123\n";
}
PHP
  log_success "Superadmin ready (developer only)"
  echo "   Email:    superadmin@example.com"
  echo "   Password: Admin@123"
  echo "   Role:     superadmin (no restrictions)"
}

seed_default_staff_profiles() {
  echo ""
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  echo "👥  Default staff profiles (Warehouse + Sales)"
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  cd "$LARAVEL_ROOT"
  php <<'PHP'
<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\DefaultStaffProfilesService;
use Illuminate\Support\Facades\Schema;

if (! Schema::hasTable('profiles') || ! Schema::hasTable('organizations')) {
    echo "   ↪ profiles/organizations missing — skip\n";
    exit(0);
}

$totals = app(DefaultStaffProfilesService::class)->ensureForAllOrganizations();
echo "   ✅ Orgs: {$totals['orgs']} | Profiles upserted: {$totals['profiles']} | Roles upserted: {$totals['roles']}\n";
echo "   Profiles: Warehouse Staff, Sales Staff\n";
echo "   Roles:    Warehouse → Warehouse Staff, Sales → Sales Staff\n";
PHP
  log_success "Default Warehouse + Sales profiles ready"
}

main() {
  verify_installer_password
  cd "$LARAVEL_ROOT"

  if [ "$FIELDS_ONLY" -eq 1 ]; then
    install_dependencies
    sync_module_fields
    log_success "Fields-only setup finished"
    exit 0
  fi

  install_dependencies
  set_storage_permissions

  if [ "$SKIP_DB" -eq 0 ]; then
    setup_database
  else
    log_warning "Skipping DB setup (--skip-db)"
  fi

  sync_module_fields
  seed_portal_modules
  seed_superadmin
  seed_default_staff_profiles

  echo ""
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  log_success "BkPortal setup complete"
  echo "  php artisan serve"
  echo "  Superadmin (dev only): superadmin@example.com / Admin@123"
  echo "  Default staff: Warehouse + Sales profiles/roles per org"
  echo "  Re-sync fields anytime: ./setup.sh --fields-only"
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
}

main "$@"
