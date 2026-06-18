<?php

namespace App\Modules\Api\V1\GlobalSearch\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Api\V1\SavedFilter\Services\ModuleFieldConfig;

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
                'label' => function($r) { return $r->name; },
                'searchText' => function($r) { return $r->name . ',' . $r->contact_person; }
            ],
            'userId' => [
                'module' => 'User',
                'model' => \App\Modules\Api\V1\User\Models\User::class,
                'searchColumns' => ['first_name', 'last_name', 'email'],
                'label' => function($r) { return trim($r->first_name . ' ' . $r->last_name); },
                'searchText' => function($r) { return $r->first_name . ',' . $r->last_name . ',' . $r->email; }
            ],
            'ingredientId' => [
                'module' => 'Ingredient',
                'model' => \App\Modules\Api\V1\Ingredient\Models\Ingredient::class,
                'searchColumns' => ['name'],
                'label' => function($r) { return $r->name; },
                'searchText' => function($r) { return $r->name; }
            ],
            'productId' => [
                'module' => 'Product',
                'model' => \App\Modules\Api\V1\Product\Models\Product::class,
                'searchColumns' => ['name', 'product_number'],
                'label' => function($r) { return $r->product_number . ' - ' . $r->name; },
                'searchText' => function($r) { return $r->product_number . ',' . $r->name; }
            ],
            'organizationId' => [
                'module' => 'Organization',
                'model' => \App\Modules\Api\V1\Organization\Models\Organization::class,
                'searchColumns' => ['name'],
                'label' => function($r) { return $r->name; },
                'searchText' => function($r) { return $r->name; }
            ],
        ];
    }

    public function searchByField(Request $request, $fieldname)
    {
        $value = $request->query('value');
        if (empty($value)) {
            return $this->error('Search value is required');
        }

        $mappings = $this->getFieldMapping();

        if (!array_key_exists($fieldname, $mappings)) {
            return $this->error('Invalid field name for relation search');
        }

        $mapping = $mappings[$fieldname];
        $modelClass = $mapping['model'];
        $module = $mapping['module'];
        $searchColumns = $mapping['searchColumns'];

        $user = auth()->user();
        
        $query = $modelClass::query();
        
        // Filter by organization unless the model is Organization itself and the user is owner
        if ($module !== 'Organization') {
            $query->where('organization_id', $user->organization_id);
        } else {
            // Can only search their own organization
            $query->where('id', $user->organization_id);
        }

        $searchValue = '%' . addcslashes($value, '%_\\') . '%';
        
        $query->where(function ($q) use ($searchColumns, $searchValue) {
            foreach ($searchColumns as $index => $column) {
                if ($index === 0) {
                    $q->where($column, 'like', $searchValue);
                } else {
                    $q->orWhere($column, 'like', $searchValue);
                }
            }
        });

        $records = $query->limit(50)->get();

        // Build fields using existing ModuleFieldConfig
        $allModuleFields = ModuleFieldConfig::getFields($module);
        
        // Take a few key fields to show in picklist (e.g., top 3) or just show all.
        // The user's example showed ["firstName", "lastName"].
        // We'll return the ones defined in ModuleFieldConfig.
        $fieldList = array_map(function($field) {
            return [
                'fieldname' => $field['fieldname'],
                'fieldlabel' => $field['fieldlabel']
            ];
        }, $allModuleFields);

        $valuesList = [];
        foreach ($records as $record) {
            $valuesList[] = [
                'id' => $record->id,
                'label' => $mapping['label']($record),
                'search_text' => $mapping['searchText']($record)
            ];
        }

        return $this->success([
            'results' => [
                $module => [
                    'fields' => $fieldList,
                    'values' => $valuesList
                ]
            ]
        ]);
    }
}
