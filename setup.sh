#!/bin/bash
# BkPortal (Bakery WMS) installer
#
# - Installs Composer / NPM deps
# - Configures MySQL .env + migrate + seed
# - Syncs ModuleFieldConfig → crm_fields (displaytype / mandatory), including Product image
# - Seeds portal_module rows from app/Models/BkPortal/PortalModuleSeed.php
#
# Default module visibility (override in Settings → Profiles):
#   Admin/superadmin     → all modules
#   Warehouse Staff      → ingredients, production batch, material withdrawal, transfers, …
#                          (NOT Production Plan, NOT Returns)
#   Sales Staff          → Billing/POS, Returns, products (view), branch stock, daily report
#   Production Plan      → admin only
#   Returns (SalesReturn) → admin + retail only
#
# Intentionally NOT included (out of scope for BkPortal — do not re-add):
# - Member module / Member↔User sync / member_user_id
# - technician:ensure-access / "Technician" profile+role bootstrap
# - Lead, Contact, Quotation, Invoice, Checklist modules
# - Generating stub Controllers over existing bakery modules
#
# - Transfer access (do not regress):
# - Warehouse staff create transfers TO retail branches (destination ≠ their warehouse).
# - Never gate BranchTransfer store/show/update on assertCanAccessBranch(destination).
# - Use BranchAccess::assertCanAccessTransferDestination / applyTransferListBranchScope.
#
# Future / product notes (see agent docs for details):
# - Shelf-life warnings: GET Reports/BranchShelfLife; POS badges + toast; BranchStock shelfStatus;
#   Dashboard shelf-life block. Warn only — never block POS for expiry. Use Inactive to delist.
# - Heuristic: product has expired ProductionBatch + branch qty > 0 (not FIFO batch tracking).
# - Product.productNumber is mandatory on create/edit (ModuleFieldConfig + crm_fields + Store/Update requests).
#
# Live / production (existing DB — never recreates the database):
#   ./setup.sh --live-update
#     1. Lists pending migrations
#     2. php artisan migrate --force  (ALL pending, including Sept 2026 Returns/shelf-life/etc.)
#     3. Sync crm_fields from ModuleFieldConfig
#     4. Insert missing portal_module rows
#     5. Upsert Warehouse + Sales default profiles/roles (does NOT reset superadmin password)
#     6. Print role matrix (ProductionPlan admin-only, SalesReturn retail-only)
#     7. Product image storage + public/storage link
#     8. Warehouse → retail transfer access repair
#
# Other usage:
#   ./setup.sh                 Full install (new machine)
#   ./setup.sh --fields-only   Re-sync field metadata only
#   ./setup.sh --skip-db       Skip interactive DB / migrate / seed
#   ./setup.sh --verify-only   Storage + pending-migration report + transfer access checks
#
# Optional password:
#   export BK_INSTALLER_PASSWORD="YourSecurePassword" && ./setup.sh
#
# Production .env (after migrate):
#   ALLOW_PUBLIC_REGISTRATION=false          # default in APP_ENV=production
#   BILLING_STAFF_MAX_DISCOUNT_PCT=0.10      # cashiers capped; admins uncapped
#   CACHE_STORE=database                     # required for Idempotency-Key locks (cache_locks table)

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
VERIFY_ONLY=0
LIVE_UPDATE=0
for arg in "$@"; do
  case "$arg" in
    --fields-only) FIELDS_ONLY=1 ;;
    --skip-db)     SKIP_DB=1 ;;
    --verify-only) VERIFY_ONLY=1 ;;
    --live-update) LIVE_UPDATE=1 ;;
    -h|--help)
      sed -n '2,55p' "$0"
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

  # Product images + Laravel runtime dirs (public disk = storage/app/public)
  # Image uploads live under uploads/images/{modulename}/ (e.g. product)
  mkdir -p \
    storage/app/Profiles \
    storage/app/public/uploads/images \
    storage/app/public/uploads/images/product \
    storage/framework/{cache,sessions,views} \
    bootstrap/cache

  chmod -R u+rwX,g+rwX storage bootstrap/cache 2>/dev/null || true

  # Broken/missing public/storage symlink → /storage/uploads/images/... 404/403 in browser
  if [ -L public/storage ] && [ ! -e public/storage ]; then
    log_warning "public/storage is a broken symlink — recreating"
    rm -f public/storage
  fi

  if [ ! -e public/storage ]; then
    if php artisan storage:link >/dev/null 2>&1; then
      log_success "Created public/storage via php artisan storage:link"
    elif ln -sfn ../storage/app/public public/storage 2>/dev/null; then
      log_success "Created public/storage via ln -sfn"
    else
      log_error "Could not create public/storage symlink — product images will not display"
      return 1
    fi
  fi

  # Resolve to real public disk path
  local linked
  linked="$(cd public/storage 2>/dev/null && pwd -P || true)"
  local expected
  expected="$(cd storage/app/public 2>/dev/null && pwd -P || true)"
  if [ -n "$linked" ] && [ -n "$expected" ] && [ "$linked" != "$expected" ]; then
    log_warning "public/storage does not point at storage/app/public — recreating"
    rm -f public/storage
    php artisan storage:link >/dev/null 2>&1 || ln -sfn ../storage/app/public public/storage
  fi

  if [ ! -d storage/app/public/uploads/images ]; then
    log_error "storage/app/public/uploads/images missing after mkdir"
    return 1
  fi

  if [ ! -w storage/app/public/uploads/images ]; then
    log_error "storage/app/public/uploads/images is not writable — fix folder permissions for product images"
    return 1
  fi

  # Touch-test write then clean up (base + product module folder)
  local probe="storage/app/public/uploads/images/.bk_setup_write_test"
  local probe_mod="storage/app/public/uploads/images/product/.bk_setup_write_test"
  if ! touch "$probe" 2>/dev/null; then
    log_error "Cannot write to uploads/images — check OS permissions on storage/"
    return 1
  fi
  if ! touch "$probe_mod" 2>/dev/null; then
    log_error "Cannot write to uploads/images/product — check OS permissions on storage/"
    rm -f "$probe"
    return 1
  fi
  rm -f "$probe" "$probe_mod"

  log_success "storage writable; uploads/images/{modulename} ready; public/storage link OK"
}

verify_product_image_storage() {
  cd "$LARAVEL_ROOT"
  echo ""
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  echo "  Product image storage check"
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

  if [ ! -d storage/app/public/uploads/images ]; then
    log_error "Missing storage/app/public/uploads/images"
    return 1
  fi
  if [ ! -w storage/app/public/uploads/images ]; then
    log_error "uploads/images not writable"
    return 1
  fi
  if [ ! -d storage/app/public/uploads/images/product ]; then
    mkdir -p storage/app/public/uploads/images/product
  fi
  if [ ! -w storage/app/public/uploads/images/product ]; then
    log_error "uploads/images/product not writable"
    return 1
  fi
  if [ ! -e public/storage ]; then
    log_error "Missing public/storage symlink (run: php artisan storage:link)"
    return 1
  fi
  if [ -L public/storage ] && [ ! -e public/storage ]; then
    log_error "Broken public/storage symlink"
    return 1
  fi

  log_success "Image disk: storage/app/public/uploads/images/{modulename} (writable)"
  log_success "Public URL path: /storage/uploads/images/{modulename}/{file}"
  log_success "Known module folder: uploads/images/product"
  echo "  Tip: Vite proxies /storage → API in vite.config.ts; APP_URL should match artisan serve host/port"
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

# Production: leave false. Local/demo may set true.
ALLOW_PUBLIC_REGISTRATION=false
# Non-admin cashier discount cap (0.10 = 10%). Admins uncapped.
BILLING_STAFF_MAX_DISCOUNT_PCT=0.10
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
} else {
    // Keep Main as warehouse so warehouse→retail transfer rules stay valid.
    DB::table('branches')->where('id', $branchId)->update([
        'type' => 'warehouse',
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
echo "   Sales:    POS + products + incoming transfers + branch stock + daily reports\n";
PHP
  log_success "Default Warehouse + Sales profiles ready"
}

print_module_role_matrix() {
  echo ""
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  echo "🔐  Default module visibility matrix"
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  cd "$LARAVEL_ROOT"
  php <<'PHP'
<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (! Schema::hasTable('profiles') || ! Schema::hasTable('profile_module_actions')) {
    echo "   ↪ profiles tables missing — skip\n";
    exit(0);
}

$profiles = DB::table('profiles')
    ->whereIn('name', ['Warehouse Staff', 'Sales Staff'])
    ->where('deleted', 0)
    ->orderBy('name')
    ->get(['id', 'name', 'organization_id']);

$grouped = [];
foreach ($profiles as $profile) {
    $mods = DB::table('profile_module_actions as pma')
        ->join('system_actions as sa', 'sa.id', '=', 'pma.action_id')
        ->where('pma.profileid', $profile->id)
        ->where('pma.permission', 1)
        ->where('sa.action_key', 'view')
        ->pluck('pma.modulename')
        ->unique()
        ->sort()
        ->values()
        ->all();
    $grouped[$profile->name] = $mods;
}

foreach (['Warehouse Staff', 'Sales Staff'] as $name) {
    $mods = $grouped[$name] ?? [];
    echo "   {$name}:\n";
    echo "     " . (count($mods) ? implode(', ', $mods) : '(none — re-run seed_default_staff_profiles)') . "\n";
}

$warehouseHasPlan = in_array('ProductionPlan', $grouped['Warehouse Staff'] ?? [], true);
$warehouseHasReturn = in_array('SalesReturn', $grouped['Warehouse Staff'] ?? [], true);
$salesHasReturn = in_array('SalesReturn', $grouped['Sales Staff'] ?? [], true);

if ($warehouseHasPlan) {
    echo "   ⚠ Warehouse Staff still has ProductionPlan — expected admin-only\n";
} else {
    echo "   ✅ ProductionPlan not granted to Warehouse Staff\n";
}
if ($warehouseHasReturn) {
    echo "   ⚠ Warehouse Staff has SalesReturn — expected retail-only\n";
} else {
    echo "   ✅ SalesReturn not granted to Warehouse Staff\n";
}
if ($salesHasReturn) {
    echo "   ✅ SalesReturn granted to Sales Staff\n";
} else {
    echo "   ⚠ Sales Staff missing SalesReturn\n";
}

echo "   Admin-only by default: ProductionPlan\n";
echo "   Override anytime: Settings → User Manager → Profiles\n";
PHP
  log_success "Module visibility matrix verified"
}

repair_warehouse_transfer_access() {
  echo ""
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  echo "🚚  Warehouse → retail transfer access"
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  cd "$LARAVEL_ROOT"
  php <<'PHP'
<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\BranchAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

if (! Schema::hasTable('branches') || ! Schema::hasTable('users')) {
    echo "   ↪ branches/users missing — skip\n";
    exit(0);
}

$repairedBranches = 0;
$repairedUsers = 0;

// Every org needs at least one warehouse branch (source of transfers).
$orgIds = Schema::hasTable('organizations')
    ? DB::table('organizations')->pluck('id')
    : collect();

foreach ($orgIds as $orgId) {
    $warehouseId = DB::table('branches')
        ->where('organization_id', $orgId)
        ->whereRaw('LOWER(type) = ?', ['warehouse'])
        ->value('id');

    if (! $warehouseId) {
        $warehouseId = (string) Str::uuid();
        DB::table('branches')->insert([
            'id' => $warehouseId,
            'organization_id' => $orgId,
            'name' => 'Central Warehouse',
            'type' => 'warehouse',
            'address' => null,
            'phone' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $repairedBranches++;
        echo "   ✅ Created warehouse branch for org {$orgId}\n";
    }

    // Admins without a branch → attach to warehouse (transfer UI expects warehouse context).
    $adminFixed = DB::table('users')
        ->where('organization_id', $orgId)
        ->whereIn('role', ['admin', 'superadmin', 'owner'])
        ->where(function ($q) {
            $q->whereNull('branch_id')->orWhere('branch_id', '');
        })
        ->update(['branch_id' => $warehouseId, 'updated_at' => now()]);
    $repairedUsers += (int) $adminFixed;

    // Users assigned the Warehouse role must sit on a warehouse branch.
    if (Schema::hasTable('roles') && Schema::hasTable('role_user_rel')) {
        $warehouseRoleIds = DB::table('roles')
            ->where('organization_id', $orgId)
            ->where('deleted', 0)
            ->whereRaw('LOWER(name) = ?', ['warehouse'])
            ->pluck('id');

        if ($warehouseRoleIds->isNotEmpty()) {
            $userIds = DB::table('role_user_rel')
                ->where('organization_id', $orgId)
                ->whereIn('role_id', $warehouseRoleIds)
                ->pluck('user_id');

            foreach ($userIds as $userId) {
                $user = DB::table('users')->where('id', $userId)->first();
                if (! $user) {
                    continue;
                }
                $onWarehouse = $user->branch_id
                    && DB::table('branches')
                        ->where('id', $user->branch_id)
                        ->whereRaw('LOWER(type) = ?', ['warehouse'])
                        ->exists();
                if (! $onWarehouse) {
                    DB::table('users')->where('id', $userId)->update([
                        'branch_id' => $warehouseId,
                        'role' => 'warehouse',
                        'updated_at' => now(),
                    ]);
                    $repairedUsers++;
                    echo "   ✅ Reassigned warehouse-role user {$user->email} → warehouse branch\n";
                } elseif (strtolower((string) ($user->role ?? '')) !== 'warehouse'
                    && ! in_array(strtolower((string) ($user->role ?? '')), ['admin', 'superadmin', 'owner'], true)
                ) {
                    DB::table('users')->where('id', $userId)->update([
                        'role' => 'warehouse',
                        'updated_at' => now(),
                    ]);
                    $repairedUsers++;
                }
            }
        }
    }
}

// Smoke-check access rules (prevents regressing to assertCanAccessBranch on destination).
$warehouse = DB::table('branches')->whereRaw('LOWER(type) = ?', ['warehouse'])->first();
$retail = DB::table('branches')->whereRaw('LOWER(type) != ?', ['warehouse'])->first();
if ($warehouse && $retail) {
    $whUser = new \App\Modules\Api\V1\User\Models\User();
    $whUser->forceFill([
        'id' => (string) Str::uuid(),
        'organization_id' => $warehouse->organization_id,
        'branch_id' => $warehouse->id,
        'role' => 'staff',
        'email' => 'setup-warehouse-check@local.test',
    ]);

    if (! BranchAccess::isWarehouseUser($whUser)) {
        fwrite(STDERR, "   ❌ BranchAccess::isWarehouseUser failed for warehouse branch user\n");
        exit(1);
    }
    if (! BranchAccess::canAccessTransferDestination($whUser, (string) $retail->id)) {
        fwrite(STDERR, "   ❌ Warehouse user cannot access retail transfer destination — check BranchAccess\n");
        exit(1);
    }
    if (BranchAccess::canAccessBranch($whUser, (string) $retail->id)) {
        fwrite(STDERR, "   ❌ Warehouse user should NOT pass canAccessBranch(retail) — transfer must use canAccessTransferDestination\n");
        exit(1);
    }
    echo "   ✅ BranchAccess warehouse→retail transfer rules OK\n";
} else {
    echo "   ⚠ Need both warehouse + retail branches to smoke-test transfer access (skipped)\n";
}

echo "   Repaired branches: {$repairedBranches} | users: {$repairedUsers}\n";
PHP
  if [ $? -ne 0 ]; then
    log_error "Warehouse transfer access repair/verify failed"
    exit 1
  fi
  log_success "Warehouse transfer access verified"
}

print_pending_migrations() {
  echo ""
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  echo "📜  Pending migrations (must run on live)"
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  cd "$LARAVEL_ROOT"

  if ! php artisan migrate:status >/tmp/bk_migrate_status.txt 2>/tmp/bk_migrate_status.err; then
    log_warning "Could not read migrate:status (is .env DB reachable?)"
    cat /tmp/bk_migrate_status.err 2>/dev/null | tail -n 8 || true
    return 1
  fi

  local pending
  pending="$(awk '/Pending/ {print}' /tmp/bk_migrate_status.txt || true)"
  if [ -z "$pending" ]; then
    log_success "No pending migrations"
    return 0
  fi

  log_warning "Pending migrations (these MUST run before go-live):"
  echo "$pending"
  echo ""
  echo "  Known Sept 2026 live-required migrations (if still Pending):"
  echo "    2026_09_02_220000_add_product_image_to_products_table"
  echo "    2026_09_02_221000_create_sales_returns_table"
  echo "    2026_09_02_221100_seed_sales_return_default_filter"
  echo "    2026_09_03_100000_refactor_sales_returns_to_batches"
  echo "    2026_09_04_100000_move_product_images_into_module_folders"
  echo "    2026_09_04_110000_add_shelf_status_to_branch_stock_list"
  echo "    2026_09_04_120000_add_product_source_to_products_table"
  echo "    2026_09_04_120100_add_category_to_ingredients_table"
  echo "    2026_09_04_120200_create_product_stock_transactions_table"
  echo "    2026_09_04_120300_add_biscuit_chocolate_to_product_category_picklist"
  echo "    2026_09_04_210000_add_shelf_status_to_product_list"
  echo "    2026_09_04_211000_product_shelf_status_badge_on_shelf_life"
  echo "    2026_09_04_220000_seed_billing_default_saved_filter"
  echo "    2026_09_04_221000_make_billing_item_count_readonly"
  echo "    2026_09_04_221000_seed_recipe_and_product_stock_tx_filters"
  echo "    2026_09_04_222000_make_product_number_mandatory"
  echo "    2026_09_04_223000_hide_created_at_on_plan_and_material_lists"
  echo "    2026_09_04_224000_fix_sales_return_crm_fields_for_batches"
  echo "    2026_09_04_230000_hide_created_at_on_sales_return_list"
  return 0
}

run_live_migrations() {
  echo ""
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  echo "🚀  Live migrate --force"
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  cd "$LARAVEL_ROOT"
  php artisan config:clear >/dev/null 2>&1 || true
  php artisan migrate --force || { log_error "migrate --force failed — STOP. Do not continue deploy."; exit 1; }
  log_success "php artisan migrate --force complete"
  print_pending_migrations || true
}

live_update() {
  echo ""
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  echo "🌐  Live / production update"
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  echo "  Does NOT recreate the database or reset superadmin password."
  echo "  Runs pending migrations + field sync + profiles + storage + transfer checks."
  cd "$LARAVEL_ROOT"

  if [ ! -f .env ]; then
    log_error "No .env — live-update cannot run. Copy .env.example and set production DB first."
    exit 1
  fi

  install_dependencies
  set_storage_permissions || true
  verify_product_image_storage || true
  print_pending_migrations || true
  run_live_migrations
  sync_module_fields
  seed_portal_modules
  seed_default_staff_profiles
  print_module_role_matrix
  repair_warehouse_transfer_access

  echo ""
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  log_success "Live update finished"
  echo "  Confirm: php artisan migrate:status   (no Pending rows)"
  echo "  Confirm: ALLOW_PUBLIC_REGISTRATION is false in production"
  echo "  Confirm: CACHE_STORE=database (Idempotency-Key uses cache_locks)"
  echo "  Queue:   php artisan queue:work   (Supervisor in production)"
  echo "  Images:  public/storage → storage/app/public"
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
}

verify_warehouse_transfer_tests() {
  echo ""
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  echo "🧪  Transfer access regression test"
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  cd "$LARAVEL_ROOT"
  if [ ! -f vendor/bin/phpunit ] && [ ! -f vendor/autoload.php ]; then
    log_warning "Skipping tests (vendor missing)"
    return 0
  fi
  if php artisan test --filter='warehouse_staff_can_create_transfer_to_retail_branch|destination_branch_staff_can_receive' >/tmp/bk_transfer_access_test.log 2>&1; then
    log_success "Warehouse dispatch + destination branch receive tests passed"
  else
    log_error "Transfer access regression test failed — see /tmp/bk_transfer_access_test.log"
    tail -n 40 /tmp/bk_transfer_access_test.log || true
    exit 1
  fi
}

main() {
  verify_installer_password
  cd "$LARAVEL_ROOT"

  if [ "$LIVE_UPDATE" -eq 1 ]; then
    live_update
    exit 0
  fi

  if [ "$VERIFY_ONLY" -eq 1 ]; then
    install_dependencies
    set_storage_permissions || true
    verify_product_image_storage || true
    print_pending_migrations || true
    repair_warehouse_transfer_access
    verify_warehouse_transfer_tests
    log_success "Verify-only finished"
    echo "  If migrate:status showed Pending rows, run: ./setup.sh --live-update"
    exit 0
  fi

  if [ "$FIELDS_ONLY" -eq 1 ]; then
    install_dependencies
    print_pending_migrations || true
    sync_module_fields
    log_success "Fields-only setup finished"
    echo "  If migrate:status showed Pending rows, run: ./setup.sh --live-update"
    exit 0
  fi

  install_dependencies
  set_storage_permissions
  verify_product_image_storage

  if [ "$SKIP_DB" -eq 0 ]; then
    setup_database
  else
    log_warning "Skipping DB setup (--skip-db)"
  fi

  sync_module_fields
  seed_portal_modules
  seed_superadmin
  seed_default_staff_profiles
  print_module_role_matrix
  repair_warehouse_transfer_access
  verify_warehouse_transfer_tests

  echo ""
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  log_success "BkPortal setup complete"
  echo "  php artisan serve"
  echo "  Superadmin (dev only): superadmin@example.com / Admin@123"
  echo "  Default staff: Warehouse + Sales profiles/roles per org (including branch receiving/reporting)"
  echo "  Warehouse staff may transfer TO any retail branch (not blocked by destination branch check)"
  echo "  Product images: storage/app/public/uploads/images/{modulename}/ + public/storage link"
  echo "  Re-sync fields anytime: ./setup.sh --fields-only"
  echo "  Re-check transfer + image storage: ./setup.sh --verify-only"
  echo "  Live / existing DB: ./setup.sh --live-update  (migrate --force + fields + profiles + storage)"
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
}

main "$@"
