<?php

namespace App\Modules\Api\V1\GlobalSearch\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\V1\SavedFilter\Services\ModuleFieldConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GlobalSearchController extends Controller
{
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
                'label' => function ($r) { return $r->name; },
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
                'label' => function ($r) { return $r->name; },
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
