<?php

namespace App\Modules\Api\V1\GlobalSearch\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\V1\SavedFilter\Services\ModuleFieldConfig;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GlobalSearchController extends Controller
{
    /**
     * Cross-module global search endpoint for command-palette modal (Ctrl+K).
     * Route: GET api/v1/GlobalSearch?query=...
     */
    public function index(Request $request)
    {
        $queryText = trim((string) $request->query('query', ''));
        if ($queryText === '') {
            return $this->success(['results' => []]);
        }

        $user = Auth::user();
        $orgId = $user->organization_id ?? null;
        if (!$orgId) {
            return $this->success(['results' => []]);
        }

        $perms = new PermissionService($user);
        $can = fn (string $module) => $perms->hasPermission($module, 'view');

        $like = '%' . addcslashes($queryText, '%_\\') . '%';
        $results = [];

        // 1. Products
        if ($can('Product') && class_exists(\App\Modules\Api\V1\Product\Models\Product::class)) {
            $products = \App\Modules\Api\V1\Product\Models\Product::where('organization_id', $orgId)
                ->where(function ($q) use ($like, $queryText) {
                    $q->where('name', 'like', $like)
                      ->orWhere('product_number', 'like', $like);
                    if (preg_match('/^\d+$/', $queryText)) {
                        $norm = \App\Modules\Api\V1\Product\Services\ProductNumberService::normalize($queryText);
                        if ($norm !== null) {
                            $q->orWhere('product_number', $norm);
                        }
                    }
                })
                ->limit(8)
                ->get();

            foreach ($products as $p) {
                $sub = array_filter(['#' . $p->product_number, $p->unit ? "Unit: {$p->unit}" : null]);
                $results[] = [
                    'id' => (string) $p->id,
                    'module' => 'Product',
                    'record_id' => (string) $p->id,
                    'label' => $p->name ?? 'Product',
                    'subLabel' => implode(' · ', $sub),
                    'category' => 'Products',
                ];
            }
        }

        // 2. Ingredients
        if ($can('Ingredient') && class_exists(\App\Modules\Api\V1\Ingredient\Models\Ingredient::class)) {
            $ingredients = \App\Modules\Api\V1\Ingredient\Models\Ingredient::where('organization_id', $orgId)
                ->where('name', 'like', $like)
                ->limit(8)
                ->get();

            foreach ($ingredients as $ing) {
                $sub = array_filter([$ing->unit ? "Unit: {$ing->unit}" : null]);
                $results[] = [
                    'id' => (string) $ing->id,
                    'module' => 'Ingredient',
                    'record_id' => (string) $ing->id,
                    'label' => $ing->name ?? 'Ingredient',
                    'subLabel' => implode(' · ', $sub),
                    'category' => 'Ingredients',
                ];
            }
        }

        // 3. Branch Transfers
        if ($can('BranchTransfer') && class_exists(\App\Modules\Api\V1\BranchTransfer\Models\BranchTransfer::class)) {
            $transfers = \App\Modules\Api\V1\BranchTransfer\Models\BranchTransfer::where('organization_id', $orgId)
                ->where(function ($q) use ($like) {
                    $q->where('transfer_number', 'like', $like)
                      ->orWhere('status', 'like', $like);
                })
                ->limit(8)
                ->get();

            foreach ($transfers as $t) {
                $results[] = [
                    'id' => (string) $t->id,
                    'module' => 'BranchTransfer',
                    'record_id' => (string) $t->id,
                    'label' => $t->transfer_number ?? 'Transfer',
                    'subLabel' => 'Status: ' . ($t->status ?? 'Draft'),
                    'category' => 'Transfers',
                ];
            }
        }

        // 4. Production Batches
        if ($can('ProductionBatch') && class_exists(\App\Modules\Api\V1\ProductionBatch\Models\ProductionBatch::class)) {
            $batches = \App\Modules\Api\V1\ProductionBatch\Models\ProductionBatch::where('organization_id', $orgId)
                ->where(function ($q) use ($like) {
                    $q->where('batch_number', 'like', $like)
                      ->orWhere('status', 'like', $like);
                })
                ->limit(8)
                ->get();

            foreach ($batches as $b) {
                $results[] = [
                    'id' => (string) $b->id,
                    'module' => 'ProductionBatch',
                    'record_id' => (string) $b->id,
                    'label' => $b->batch_number ?? 'Batch',
                    'subLabel' => 'Status: ' . ($b->status ?? 'In Progress'),
                    'category' => 'Production Batches',
                ];
            }
        }

        // 5. Material Issues
        if ($can('MaterialIssue') && class_exists(\App\Modules\Api\V1\MaterialIssue\Models\MaterialIssue::class)) {
            $issues = \App\Modules\Api\V1\MaterialIssue\Models\MaterialIssue::where('organization_id', $orgId)
                ->where('issue_number', 'like', $like)
                ->limit(8)
                ->get();

            foreach ($issues as $mi) {
                $results[] = [
                    'id' => (string) $mi->id,
                    'module' => 'MaterialIssue',
                    'record_id' => (string) $mi->id,
                    'label' => $mi->issue_number ?? 'Issue',
                    'subLabel' => 'Material Issue',
                    'category' => 'Material Issues',
                ];
            }
        }

        // 6. Vendors
        if ($can('Vendor') && class_exists(\App\Modules\Api\V1\Vendor\Models\Vendor::class)) {
            $vendors = \App\Modules\Api\V1\Vendor\Models\Vendor::where('organization_id', $orgId)
                ->where(function ($q) use ($like) {
                    $q->where('name', 'like', $like)
                      ->orWhere('contact_person', 'like', $like)
                      ->orWhere('phone', 'like', $like)
                      ->orWhere('email', 'like', $like);
                })
                ->limit(8)
                ->get();

            foreach ($vendors as $v) {
                $sub = array_filter([$v->contact_person, $v->phone]);
                $results[] = [
                    'id' => (string) $v->id,
                    'module' => 'Vendor',
                    'record_id' => (string) $v->id,
                    'label' => $v->name ?? 'Vendor',
                    'subLabel' => implode(' · ', $sub),
                    'category' => 'Vendors',
                ];
            }
        }

        // 7. Branches
        if ($can('Branch') && class_exists(\App\Modules\Api\V1\Branch\Models\Branch::class)) {
            $branches = \App\Modules\Api\V1\Branch\Models\Branch::where('organization_id', $orgId)
                ->where(function ($q) use ($like) {
                    $q->where('name', 'like', $like)
                      ->orWhere('address', 'like', $like)
                      ->orWhere('phone', 'like', $like);
                })
                ->limit(8)
                ->get();

            foreach ($branches as $br) {
                $results[] = [
                    'id' => (string) $br->id,
                    'module' => 'Branch',
                    'record_id' => (string) $br->id,
                    'label' => $br->name ?? 'Branch',
                    'subLabel' => ucfirst($br->type ?? 'branch'),
                    'category' => 'Branches',
                ];
            }
        }

        return $this->success([
            'results' => $results,
        ]);
    }

    /**
     * Map field names to their target module and configuration.
     */
    protected function getFieldMapping(): array
    {
        return [
            'vendorId' => [
                'module' => 'Vendor',
                'model' => \App\Modules\Api\V1\Vendor\Models\Vendor::class,
                'searchColumns' => ['name', 'contact_person', 'email', 'phone'],
                'label' => function ($r) { return $r->name; },
                'searchText' => function ($r) { return $r->name . ',' . $r->contact_person; },
            ],
            'userId' => [
                'module' => 'User',
                'model' => \App\Modules\Api\V1\User\Models\User::class,
                'searchColumns' => ['first_name', 'last_name', 'email'],
                'label' => function ($r) { return trim($r->first_name . ' ' . $r->last_name); },
                'searchText' => function ($r) { return $r->first_name . ',' . $r->last_name . ',' . $r->email; },
            ],
            'ingredientId' => [
                'module' => 'Ingredient',
                'model' => \App\Modules\Api\V1\Ingredient\Models\Ingredient::class,
                'searchColumns' => ['name'],
                'label' => function ($r) {
                    $unit = $r->unit ? strtolower((string) $r->unit) : null;

                    return $unit ? "{$r->name} · {$unit}" : $r->name;
                },
                'searchText' => function ($r) { return $r->name; },
            ],
            'productId' => [
                'module' => 'Product',
                'model' => \App\Modules\Api\V1\Product\Models\Product::class,
                'searchColumns' => ['name', 'product_number'],
                'label' => function ($r) {
                    $num = $r->product_number !== null && $r->product_number !== ''
                        ? '#' . $r->product_number
                        : null;
                    $unit = $r->unit ? strtolower((string) $r->unit) : null;
                    $parts = array_filter([$r->name, $num ? "({$num})" : null, $unit ? "· {$unit}" : null]);

                    return implode(' ', $parts);
                },
                'searchText' => function ($r) { return $r->product_number . ',' . $r->name; },
            ],
            'organizationId' => [
                'module' => 'Organization',
                'model' => \App\Modules\Api\V1\Organization\Models\Organization::class,
                'searchColumns' => ['name'],
                'label' => function ($r) { return $r->name; },
                'searchText' => function ($r) { return $r->name; },
            ],
            'branchId' => [
                'module' => 'Branch',
                'model' => \App\Modules\Api\V1\Branch\Models\Branch::class,
                'searchColumns' => ['name', 'address', 'phone'],
                'label' => function ($r) {
                    $type = strtolower((string) ($r->type ?? ''));
                    $suffix = $type === 'warehouse' ? ' (Warehouse)' : ($type === 'retail' ? ' (Retail)' : '');

                    return ($r->name ?? 'Branch') . $suffix;
                },
                'searchText' => function ($r) { return $r->name . ',' . $r->phone; },
            ],
        ];
    }

    /**
     * Accept api field name (branchId) or crm_fields UUID.
     */
    protected function resolveFieldKey(string $fieldname): ?string
    {
        $mappings = $this->getFieldMapping();
        if (array_key_exists($fieldname, $mappings)) {
            return $fieldname;
        }

        $crm = DB::table('crm_fields')
            ->where('id', $fieldname)
            ->where('deleted', 0)
            ->first();

        if (! $crm) {
            return null;
        }

        $apiName = $crm->apifieldname ?: Str::camel($crm->fieldname);

        return array_key_exists($apiName, $mappings) ? $apiName : null;
    }

    public function searchByField(Request $request, $fieldname)
    {
        $value = trim((string) $request->query('value', ''));

        $resolvedKey = $this->resolveFieldKey((string) $fieldname);
        if (! $resolvedKey) {
            return $this->error('Invalid field name for relation search');
        }

        $mapping = $this->getFieldMapping()[$resolvedKey];
        $modelClass = $mapping['model'];
        $module = $mapping['module'];
        $searchColumns = $mapping['searchColumns'];

        $user = Auth::user();

        $query = $modelClass::query();

        if ($module !== 'Organization') {
            $query->where('organization_id', $user->organization_id);
        } else {
            $query->where('id', $user->organization_id);
        }

        // Branch picker scoping:
        // - Warehouse staff pick transfer destinations → retail branches only
        // - Retail/sales staff → their assigned branch only
        // - Full admins → all org branches
        if ($module === 'Branch' && $user && method_exists($user, 'isFullAdmin') && ! $user->isFullAdmin()) {
            if (\App\Services\BranchAccess::isWarehouseUser($user)) {
                $query->whereRaw('LOWER(type) != ?', ['warehouse']);
            } elseif ($user->branch_id) {
                $query->where('id', $user->branch_id);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        // Empty / whitespace: browse first records (so Main branch appears without typing).
        if ($value !== '') {
            $searchValue = '%' . addcslashes($value, '%_\\') . '%';
            $query->where(function ($q) use ($searchColumns, $searchValue, $module, $value) {
                foreach ($searchColumns as $index => $column) {
                    if ($index === 0) {
                        $q->where($column, 'like', $searchValue);
                    } else {
                        $q->orWhere($column, 'like', $searchValue);
                    }
                }

                // Product #: match normalized digits so 1 / 01 / 001 hit the same product
                if ($module === 'Product' && preg_match('/^\d+$/', $value)) {
                    $normalized = \App\Modules\Api\V1\Product\Services\ProductNumberService::normalize($value);
                    if ($normalized !== null) {
                        $q->orWhere('product_number', $normalized);
                    }
                }
            });
        }

        $orderCol = $searchColumns[0] ?? 'id';
        $records = $query->orderBy($orderCol)->limit(50)->get();

        $allModuleFields = ModuleFieldConfig::getFields($module) ?? [];
        $fieldList = array_map(function ($field) {
            return [
                'fieldname' => $field['fieldname'],
                'fieldlabel' => $field['fieldlabel'],
            ];
        }, $allModuleFields);

        $valuesList = [];
        foreach ($records as $record) {
            $row = [
                'id' => $record->id,
                'label' => $mapping['label']($record),
                'search_text' => $mapping['searchText']($record),
            ];
            if ($module === 'Product') {
                $row['unit'] = $record->unit;
                $row['productNumber'] = $record->product_number;
            }
            if ($module === 'Ingredient') {
                $row['unit'] = $record->unit;
            }
            $valuesList[] = $row;
        }

        return $this->success([
            'results' => [
                $module => [
                    'fields' => $fieldList,
                    'values' => $valuesList,
                ],
            ],
        ]);
    }
}
