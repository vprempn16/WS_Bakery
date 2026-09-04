# AI Agent & Developer Guidelines (Bakery WMS)

This file contains the strict architectural rules and guidelines for this codebase. Any AI agent or developer modifying this system MUST adhere to these rules.

> **BkPortal** — Bakery WMS + multi-branch POS. Use bakery modules (`Ingredient`, `MaterialIssue`, `Product`, `Recipe`, `ProductionBatch`, `Branch`, `Billing`, etc.). Do not add sales-CRM modules (Lead / Contact / Quotation / Invoice) or Member portal unless explicitly requested.
>
> **User-facing label (2026-08-30):** The `MaterialIssue` module is displayed as **Material Withdrawal** in the UI (sidebar, field labels, API messages). Keep the internal module key, routes, and table names as `MaterialIssue`.

## 1. Architectural Pattern (HMVC / Modular Design)
This application uses a Modular Architecture (HMVC).
Features are strictly encapsulated within their own module folders rather than grouped by file type.
- **Path**: `app/Modules/Api/V1/{ModuleName}/`
- **Structure**: Every module contains its own `Controllers`, `Models`, `Requests`, `Resources`, etc.
- **Rule**: Do NOT place new feature logic in the global `app/Http/Controllers` or `app/Models` directories. Always build within the specific `app/Modules/Api/V1/{Feature}` directory.

### Bakery modules (current)
Organization, User, Vendor, Ingredient, MaterialIssue, InventoryTransaction, Product, Recipe, ProductionBatch, ProductionPlan, Branch, BranchTransfer / BranchStock, BranchSales (BranchDailyReport), SalesReturn, Billing, Reports, SavedFilter, Profile, Role, Settings, GlobalSearch, AuditLog.

## 2. Fat Models, Skinny Controllers
- **Controllers** should only be responsible for handling the HTTP request, delegating to the Model or a Service, and returning the response.
- **Models** (or dedicated Services) must contain all heavy business logic, database transactions, relationship migrations, and audit logging.

## 3. Standard Record Lifecycle (Create, Read, Update, Delete)
**CRITICAL**: For permission-aware single-record CRUD that goes through the CRM record engine, use `App\Services\CRM\RecordObject` instead of raw `DB::table` when fetching a record for the user or performing generic module CRUD.

`RecordObject` resolves `App\Modules\Api\V1\{Module}\Models\{Module}` (fallback `App\Models\BKModel`) and handles:
- Enforcing user module and field-level permissions.
- Triggering `beforeSave`, `afterSave`, `beforeDelete`, and `afterDelete` hooks (when `HookManager` is available).
- Custom values / org isolation patterns used by BkPortal record engine.

> **Current bakery note:** Many bakery controllers still use direct Eloquent (`Product::where(...)`, `$request->user()->organization_id`). Prefer moving **single-record** create/update/delete paths onto `RecordObject` over time. Keep **list/search** on efficient Eloquent queries (see §7). Domain side-effects (Material Issue stock out, production finished-goods stock) stay in dedicated controller/service transactions — do not invent CRM Lead/Invoice logic.

Base model class is **`BKModel`** (not `AtomModel`).

### Creating or Retrieving a Record
```php
use App\Services\CRM\RecordObject;

// Create new
$record = RecordObject::make('Product', null, $data, 'CreateView');

// Retrieve existing
$record = RecordObject::make('Product', $id);
```

### Updating a Record
```php
$record = RecordObject::make('Product', $id, $updateData, 'EditView');
$record->save();
```

### Deleting a Record
```php
$record = RecordObject::make('Product', $id);
$record->deleteRecord(); // Soft-deletes, hooks, related cleanup when wired
```

## 4. Validating Related Records (Foreign Keys)
When validating if a related record (like `product_id` / `ingredient_id`) exists for a permission-aware path, prefer `RecordObject::make()` inside a `try-catch`. For internal stock/ledger transactions already scoped by `organization_id`, org-scoped Eloquent with `lockForUpdate()` is acceptable.

```php
// Validate that the related record exists and is accessible
if (!empty($data['productId'])) {
    try {
        RecordObject::make('Product', $data['productId']);
    } catch (\Exception $e) {
        return $this->error('The selected product does not exist or access is denied.');
    }
}
```

## 5. Retrieving Related Items
When fetching related records (e.g. billing line items, recipe lines), do **NOT** use unscoped `DB::table(...)`. Load the parent via `RecordObject` (or org-scoped Eloquent), then use mapped Eloquent relationships:

```php
try {
    $billing = RecordObject::make('Billing', $id, [], 'DetailView');
} catch (\Exception $e) {
    return $this->error("Bill not found for ID: {$id}");
}

$items = $billing->items()->orderBy('created_at')->get();
```

## 6. Namespace Imports
**IMPORTANT**: Always import classes at the top of the file using the `use` statement (e.g., `use App\Services\CRM\RecordObject;`).
Do **NOT** use the fully qualified class name inline in method bodies (e.g., avoid `\App\Services\CRM\RecordObject::make()`).

## 7. Preventing N+1 Queries (Performance)
**CRITICAL**: NEVER put `RecordObject::make()` inside a `foreach` loop when dealing with mass data, lists, or search results. `RecordObject` is a heavy service that runs multiple database queries for permissions and relationships.

### List & Search Endpoints
For **paginated lists**, **search results**, or any endpoint returning many records, do **NOT** use `RecordObject::make()` per row. Use a **single** efficient Eloquent query on the module Model.

Apply org scoping, soft-delete filters, and joins in that one query; map rows to the API shape in PHP after pagination.

```php
use App\Modules\Api\V1\Product\Models\Product;

$products = Product::query()
    ->where('organization_id', $orgId)
    ->where('deleted', 0)
    ->orderByDesc('updated_at')
    ->paginate($perPage);
```

Use `RecordObject` only for **single-record** operations (detail, create, update, delete) — never to hydrate each list row.

### Filtering Search Results by Valid IDs
If you already have an array of search results and need to filter out deleted/invalid records, extract the IDs and run a **single** efficient database query using the Model class:

```php
$recordIds = collect($results)->pluck('record_id')->toArray();

if (!empty($recordIds)) {
    $existingIds = Product::whereIn('id', $recordIds)
        ->where('deleted', 0)
        ->pluck('id')
        ->toArray();

    $results = collect($results)->filter(function ($r) use ($existingIds) {
        return in_array($r->record_id, $existingIds);
    })->values();
}
```

### When `RecordObject` in a Loop Is Acceptable
Only for **small, bounded** write operations inside a transaction (e.g. creating a handful of billing line items). Never use it to **read** or **transform** unbounded list/search result sets.

## 8. No Unsolicited Assumptions or Fallbacks
**IMPORTANT**: Do NOT try to "guess" or invent fallback logic when data is missing, unless explicitly instructed by the user.
Always stick to the strictly given fields, or use a safe default (like `-` or an empty string). If you are unsure how to handle a missing field, ask the user.

Do **not** reintroduce out-of-scope CRM domains (Lead, Contact, Quotation, Invoice, WhatsApp, Zapier, Member portal) unless the user explicitly requests them for BkPortal.

## 9. Laravel Facades & Auth Typing (Intelephense / IDE)
Intelephense does **not** understand Laravel’s magic helpers and root facade aliases. Follow these rules so Problems stay at zero:

### Facades — always import, never root aliases or helpers for user/logging/DB
```php
// CORRECT
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

Auth::user();
Auth::id();
Log::error('...');
DB::beginTransaction();

// WRONG — causes "Undefined method/type" in the IDE
auth()->user();
auth()->id();
\Log::error('...');
\DB::table('...');
```

### Auth user properties
`Auth::user()` is typed as `Authenticatable|null`, which has **no** `organization_id`.

**Target pattern** (use `App\Services\AuthUser` when available):

```php
use App\Services\AuthUser;

$orgId = AuthUser::organizationId();
$userId = AuthUser::id();
$user = AuthUser::user();
```

**Until `AuthUser` exists in this repo**, prefer:

```php
/** @var \App\Modules\Api\V1\User\Models\User $user */
$user = $request->user();
$orgId = $user->organization_id;
```

Do **not** write `auth()->user()->organization_id` in new code.

When changing `\Log::` to `Log::`, never touch already-qualified names like `\Illuminate\Support\Facades\Log::`.

## 10. Bakery Domain Flow (Correct Ops Path)

Agents MUST preserve this stock flow. Do not invent CRM Lead→Invoice conversion here.

```
Vendor → Ingredient (stock in via InventoryTransaction / Adjust Stock — UI: stock IN only)
    → MaterialIssue / Material Withdrawal (master takes raw materials → Ingredient stock OUT + ledger)
    → Recipe on Product (BOM for planning / usage analysis only)
    → ProductionBatch (increase Product.current_stock + expiry; does NOT deduct ingredients)
    → BranchTransfer (warehouse Product stock → BranchStock)
    → Sell / write-off:
         • POS Billing  → MUST deduct BranchStock for that branch
         • SalesReturn (Returns / wastage) → MUST deduct BranchStock (never add stock back; not a POS refund)
         • BranchDailyReport → summary waste fields (does not replace SalesReturn)
    → Reports (Dashboard, ExpiringBatches, BranchShelfLife)
```

### Shelf-life warnings (warn only)
- `ShelfLifeStatusService` + `GET Reports/BranchShelfLife` (active branch via `X-Branch-Id`).
- Heuristic: product has non-wasted `ProductionBatch` past / within 24h of `expiry_timestamp` **and** `BranchStock.current_stock > 0`. Not FIFO (BranchStock has no batch id).
- Surfaces: POS tile badge + one toast; BranchStock `shelfStatus` column; Dashboard shelf-life block + toast.
- **Never** block POS add/pay for expiry. Use Product `status=inactive` to hide from POS.

### Product number
- `productNumber` is **mandatory** on create/edit (`ModuleFieldConfig` + `crm_fields` + Store/Update requests). Digits only; unique per org. Creating-hook auto-number remains only as a seeder/safety fallback when empty.

### Stock rules
1. **MaterialIssue (Material Withdrawal)**: deduct ingredient stock + log `InventoryTransaction` (`out`) when master takes raw materials. Ledger notes use `Material Withdrawal: …`. Cancel restores stock. Do not use Adjust Stock UI for this path.
2. **ProductionBatch**: increase finished-goods `Product.current_stock` only. Do **not** deduct ingredients (already issued via MaterialIssue).
3. **BranchTransfer**: deduct warehouse `Product.current_stock`; increase `BranchStock` on receive.
4. **Billing (POS)**: deduct `BranchStock` for `branch_id` + product lines (same org). Never leave POS as “bill only” without stock movement.
5. **SalesReturn (Returns)**: multi-item wastage batch; deduct `BranchStock` via `BillingStockService::deductForSale`. Header + `sales_return_items`. No billing FK.
6. **BranchDailyReport**: deduct sold + returned quantities from `BranchStock` where that path is enabled; do not treat Daily Report as a substitute for SalesReturn logging.
7. Always scope by `organization_id`. Use `lockForUpdate()` inside stock transactions.

### Product images (storage)
- Column / field: `product_image` / `productImage`. Upload via `HandlesImageUploads` + `ImageUploadService` to disk **`public`**, path **`uploads/images/{modulename}/{file}`** (e.g. `uploads/images/product/….jpg`).
- Serve as **`/storage/uploads/images/{modulename}/{file}`** (prefer root-relative URLs from `transformToUrl`).
- **Setup requirement:** `./setup.sh` ensures `uploads/images` + `uploads/images/product` writable and `public/storage` link. Migration may relocate legacy flat files into `product/`.
- Billing POS product list must call `transformToUrl` before returning `productImage` / `image_url`.
- Frontend companion: `bk-frontend/agent/PRODUCT_IMAGE_AND_STORAGE.md`.

### Multi-tenant
- Middleware: `auth:sanctum` + `check.org`
- Global scopes: `OrganizationScope`, `NotDeletedScope` on `BKModel` where applied
- Never cross-org read/write
- **Org-scoped uniqueness**: business keys that must be unique *per bakery* (e.g. `products.product_number`) MUST use a composite unique index `(organization_id, …)`, never a global unique on the key alone. Global unique breaks new-org setup when another org already used `#1`.
- **Product numbers**: uniqueness + auto-increment are **per organization**. Migration: `2026_08_14_163600_make_product_number_unique_per_organization`. On any new project / production deploy: `php artisan migrate` so this runs. Do not reintroduce `products_product_number_unique` on `product_number` alone.

### Idempotency (stock-mutating creates)
- Shared helper: `App\Support\Idempotency` (`begin` / `remember` / `release`).
- **Required** `Idempotency-Key` header on: Billing paid create/pay, BranchTransfer create, MaterialIssue create, SalesReturn create.
- Frontend: generate once per submit attempt via `nextIdempotencyKey()` and **reuse the same key on retry**; clear only after success.
- Uses Laravel cache + `cache_locks`. Production must have `CACHE_STORE=database` (or Redis) so locks work. `cache_locks` is created by the default cache migration.

### Billing POS guards
- **Void / re-hold paid bill** (restore stock): full admin only (`PermissionService::userIsFullAdmin`). Logged as warning.
- **Staff discount cap:** non-admin cashiers limited to `config('app.billing_staff_max_discount_pct')` (env `BILLING_STAFF_MAX_DISCOUNT_PCT`, default `0.10`). Admins uncapped. Catalog prices still win over client line prices.

### Public organization registration
- `POST Organization/new` is gated by `config('app.allow_public_registration')`.
- Defaults **false** when `APP_ENV=production`. Set `ALLOW_PUBLIC_REGISTRATION=true` only for local/demo.

### Custom fields (Settings → Module Fields)
- Routes live under `settings/fields` (not the old `custom-field-creation` paths).
- Create-view supports relation picklist types + related module picker. List uses `ProfileView` so displaytypes 1/2/3 all appear in settings.

### Migrations (new project + production updates)
1. **Never edit an already-deployed migration** to change schema on live DBs — add a **new** migration instead.
2. Fresh install / new env: `./setup.sh` (runs `php artisan migrate --force`).
3. **Existing production / live:** backup DB → `./setup.sh --live-update` (lists pending → `migrate --force` → field sync → staff profiles → storage → transfer repair). Does **not** recreate the DB or reset superadmin password.
4. Manual equivalent: `php artisan migrate --force` then `php artisan migrate:module-fields`.
5. When adding multi-tenant features, double-check unique indexes and auto-number generators are org-scoped.
6. After a migration that fixes a bug, note it here so agents do not regress it.

#### Live-required Sept 2026 migrations (run ALL pending — do not skip)

| Migration | Why it must run |
|-----------|-----------------|
| `2026_09_02_220000_add_product_image_to_products_table` | `product_image` column |
| `2026_09_02_221000_create_sales_returns_table` | Returns header table |
| `2026_09_02_221100_seed_sales_return_default_filter` | Returns list columns (no Created At) |
| `2026_09_03_100000_refactor_sales_returns_to_batches` | `sales_return_items` + batch columns |
| `2026_09_04_100000_move_product_images_into_module_folders` | Relocate files into `uploads/images/product/` |
| `2026_09_04_110000_add_shelf_status_to_branch_stock_list` | BranchStock shelf badge column |
| `2026_09_04_120000_add_product_source_to_products_table` | Own vs bought product |
| `2026_09_04_120100_add_category_to_ingredients_table` | Ingredient category (raw/packaging/other) |
| `2026_09_04_120200_create_product_stock_transactions_table` | Bought-product receive-stock ledger |
| `2026_09_04_120300_add_biscuit_chocolate_to_product_category_picklist` | Extra product categories |
| `2026_09_04_210000_add_shelf_status_to_product_list` | Product list shelf chip |
| `2026_09_04_211000_product_shelf_status_badge_on_shelf_life` | Shelf-life field badge |
| `2026_09_04_220000_seed_billing_default_saved_filter` | Bills list default columns |
| `2026_09_04_221000_make_billing_item_count_readonly` | `itemCount` displaytype 3 |
| `2026_09_04_221000_seed_recipe_and_product_stock_tx_filters` | Recipe + product stock tx lists |
| `2026_09_04_222000_make_product_number_mandatory` | `productNumber` mandatory in crm_fields |
| `2026_09_04_223000_hide_created_at_on_plan_and_material_lists` | Hide Created At on Plan / Withdrawal |
| `2026_09_04_224000_fix_sales_return_crm_fields_for_batches` | Soft-delete legacy header product/qty fields; seed SalesReturnItem fields |
| `2026_09_04_230000_hide_created_at_on_sales_return_list` | Hide Created At on Returns list |

Also still required if never applied: `2026_08_14_163600_make_product_number_unique_per_organization`, `2026_08_30_200000_update_material_withdrawal_labels`.

**SalesReturn batches:** if `sales_returns` still has `product_id` and no `sales_return_items`, the refactor migration must run or list/create will fail.

### Roles (current bakery)
String roles on users (`admin` / `superadmin`, `warehouse`, branch) plus Profile/Role tables. Branch/Sales staff typically get Billing, BranchDailyReport, SalesReturn, Product. Warehouse does **not** get SalesReturn or ProductionPlan by default. Do not assume Member/technician portal unless it is implemented here.

## 11. Legacy Debt — Do Not Expand
- Brand as **BkPortal**. Do not use third-party CRM product names in new code or docs.
- `HookManager` exists as a bakery-safe no-op runner. Do **not** reintroduce Lead / Contact / Invoice / Checklist helpers into `BKModel`.
- Frontend may still contain dead CRM modules (`Invoice`, `Quotation`, WhatsApp). Do not wire them into bakery APIs by default.
- Rename leftover foreign brand cookies/strings when touched.

## 12. Response Contract
Prefer existing bakery helpers (`success`, `error`, `paginated` via `ResultTrait`). Keep HTTP/status conventions consistent with surrounding controllers. Do not invent a second response shape.

## 13. Changelog (agent reference)

### 2026-09-04

| Area | Change |
|------|--------|
| **Live installer** | `./setup.sh --live-update` — pending migrate list + `migrate --force` + fields + profiles + storage + transfer repair. No DB recreate, no superadmin password reset. `--verify-only` now prints pending migrations. |
| **Idempotency** | Shared `App\Support\Idempotency`. Required on Billing (paid), BranchTransfer, MaterialIssue, SalesReturn. Frontend reuses one key per submit attempt (`nextIdempotencyKey`). |
| **SalesReturn loss** | Catalog price only (ignore client `unitPrice`). Weight lines use `BillingPriceService::lineTotal` same as POS (`g/1000 × pricePerKg`). |
| **Billing** | Staff discount cap (`BILLING_STAFF_MAX_DISCOUNT_PCT`, default 10%). Void/re-hold paid bill is admin-only. |
| **Signup** | `ALLOW_PUBLIC_REGISTRATION` — off in production by default. |
| **Settings fields** | Custom fields API under `settings/fields`; relation picklist + related module; frontend Module Fields modal/table aligned. |
| **Migrations** | `2026_09_04_224000_fix_sales_return_crm_fields_for_batches` (unique timestamp — do not reuse 223000). `2026_09_04_230000_hide_created_at_on_sales_return_list`. Seed/refactor filters drop `createdAt`. |
| **UX** | List date filter on the right; delete confirms; Recipe tab “per 1 kg BOM” copy; `multiPickList`/`array` in `BkFieldType`. |

### 2026-09-03

| Area | Change |
|------|--------|
| **SalesReturn** | Multi-item wastage batches (`sales_return_items`); stock **deduct** only; role matrix Admin+Sales. Docs: frontend `RETURNS_AND_ROLES.md`. |
| **Product images** | Paths under `uploads/images/{modulename}/`; Billing POS transforms URLs; `setup.sh` verifies module folders + `public/storage` link. |
| **BranchStock** | Read-only detail + Eye; no date-range trap on list. |

### 2026-08-30

| Area | Change |
|------|--------|
| **Material Withdrawal** | User-facing labels renamed from Material Issue. Migration `2026_08_30_200000_update_material_withdrawal_labels.php`. Field labels: Withdrawal Number/Date/By. Controller success messages and ledger notes updated. |
| **Billing** | List endpoint uses `withCount('items')`; `BillingResource` adds `itemCount`. `ModuleFieldConfig` billings module includes `itemCount` (label **Items**). |
| **Product number** | Validated as string in create/update requests (frontend must stringify numeric input from `InputNumber`). Uniqueness remains per `organization_id`. |
| **Adjust Stock vs Withdrawal** | Frontend Adjust Stock modal is stock-in only; operational stock-out is Material Withdrawal. Backend `InventoryTransaction` still accepts `out` via API. |

Frontend companion docs: [`RETURNS_AND_ROLES.md`](../../../bk-frontend/agent/RETURNS_AND_ROLES.md), [`PRODUCT_IMAGE_AND_STORAGE.md`](../../../bk-frontend/agent/PRODUCT_IMAGE_AND_STORAGE.md), [`PRODUCTION_WORKFLOW.md`](../../../bk-frontend/agent/PRODUCTION_WORKFLOW.md), [`RECENT_UPDATES_2026-09-04.md`](../../../bk-frontend/agent/RECENT_UPDATES_2026-09-04.md), [`RECENT_UPDATES_2026-09-03.md`](../../../bk-frontend/agent/RECENT_UPDATES_2026-09-03.md), [`RECENT_UPDATES_2026-08-30.md`](../../../bk-frontend/agent/RECENT_UPDATES_2026-08-30.md).

Workspace copy (frontend README import): [`../../.agents/AGENTS.md`](../../.agents/AGENTS.md). Keep both files in sync.
