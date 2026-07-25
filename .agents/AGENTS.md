# AI Agent & Developer Guidelines (Bakery WMS)

This file contains the strict architectural rules and guidelines for this codebase. Any AI agent or developer modifying this system MUST adhere to these rules.

> **BkPortal** — Bakery WMS + multi-branch POS. Use bakery modules (`Ingredient`, `Product`, `Recipe`, `ProductionBatch`, `Branch`, `Billing`, etc.). Do not add sales-CRM modules (Lead / Contact / Quotation / Invoice) or Member portal unless explicitly requested.

## 1. Architectural Pattern (HMVC / Modular Design)
This application uses a Modular Architecture (HMVC).
Features are strictly encapsulated within their own module folders rather than grouped by file type.
- **Path**: `app/Modules/Api/V1/{ModuleName}/`
- **Structure**: Every module contains its own `Controllers`, `Models`, `Requests`, `Resources`, etc.
- **Rule**: Do NOT place new feature logic in the global `app/Http/Controllers` or `app/Models` directories. Always build within the specific `app/Modules/Api/V1/{Feature}` directory.

### Bakery modules (current)
Organization, User, Vendor, Ingredient, InventoryTransaction, Product, Recipe, ProductionBatch, Branch, BranchTransfer / BranchStock, BranchSales (BranchDailyReport), Billing, Reports, SavedFilter, Profile, Role, Settings, GlobalSearch, AuditLog.

## 2. Fat Models, Skinny Controllers
- **Controllers** should only be responsible for handling the HTTP request, delegating to the Model or a Service, and returning the response.
- **Models** (or dedicated Services) must contain all heavy business logic, database transactions, relationship migrations, and audit logging.

## 3. Standard Record Lifecycle (Create, Read, Update, Delete)
**CRITICAL**: For permission-aware single-record CRUD that goes through the CRM record engine, use `App\Services\CRM\RecordObject` instead of raw `DB::table` when fetching a record for the user or performing generic module CRUD.

`RecordObject` resolves `App\Modules\Api\V1\{Module}\Models\{Module}` (fallback `App\Models\BKModel`) and handles:
- Enforcing user module and field-level permissions.
- Triggering `beforeSave`, `afterSave`, `beforeDelete`, and `afterDelete` hooks (when `HookManager` is available).
- Custom values / org isolation patterns used by BkPortal record engine.

> **Current bakery note:** Many bakery controllers still use direct Eloquent (`Product::where(...)`, `$request->user()->organization_id`). Prefer moving **single-record** create/update/delete paths onto `RecordObject` over time. Keep **list/search** on efficient Eloquent queries (see §7). Domain side-effects (stock deduct, recipe consume) stay in dedicated controller/service transactions — do not invent CRM Lead/Invoice logic.

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
Vendor → Ingredient (stock in via InventoryTransaction)
    → Recipe on Product
    → ProductionBatch (consume ingredients → increase Product.current_stock + expiry)
    → BranchTransfer (warehouse Product stock → BranchStock)
    → Sell:
         • POS Billing  → MUST deduct BranchStock for that branch
         • BranchDailyReport → deduct BranchStock (sold + returned/waste)
    → Reports (Dashboard, ExpiringBatches)
```

### Stock rules
1. **ProductionBatch**: deduct ingredient stock + log `InventoryTransaction`; increase finished-goods `Product.current_stock`.
2. **BranchTransfer**: deduct warehouse `Product.current_stock`; increase `BranchStock`.
3. **Billing (POS)**: deduct `BranchStock` for `branch_id` + product lines (same org). Never leave POS as “bill only” without stock movement.
4. **BranchDailyReport**: deduct sold + returned quantities from `BranchStock`.
5. Always scope by `organization_id`. Use `lockForUpdate()` inside stock transactions.

### Multi-tenant
- Middleware: `auth:sanctum` + `check.org`
- Global scopes: `OrganizationScope`, `NotDeletedScope` on `BKModel` where applied
- Never cross-org read/write

### Roles (current bakery)
String roles on users (`admin` / `superadmin`, `warehouse`, branch) plus Profile/Role tables. Branch users typically get Billing, BranchDailyReport, Product. Do not assume Member/technician portal unless it is implemented here.

## 11. Legacy Debt — Do Not Expand
- Brand as **BkPortal**. Do not use third-party CRM product names in new code or docs.
- `HookManager` exists as a bakery-safe no-op runner. Do **not** reintroduce Lead / Contact / Invoice / Checklist helpers into `BKModel`.
- Frontend may still contain dead CRM modules (`Invoice`, `Quotation`, WhatsApp). Do not wire them into bakery APIs by default.
- Rename leftover foreign brand cookies/strings when touched.

## 12. Response Contract
Prefer existing bakery helpers (`success`, `error`, `paginated` via `ResultTrait`). Keep HTTP/status conventions consistent with surrounding controllers. Do not invent a second response shape.
