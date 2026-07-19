<?php

namespace App\Modules\Api\V1\SavedFilter\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\V1\SavedFilter\Models\SavedFilter;
use App\Modules\Api\V1\SavedFilter\Services\ModuleFieldConfig;
use Illuminate\Http\Request;

class HeaderController extends Controller
{
    /**
     * GET /api/v1/{module}/headers
     * List/detail column fields: displaytype 1 + 3 (never 2). mandatory is boolean.
     */
    public function show(Request $request, string $module, ?string $filterId = null)
    {
        $normalizedModule = ModuleFieldConfig::normalizeModule($module);
        $allFields = ModuleFieldConfig::getApiFieldsForView($module, 'DetailView');

        if ($allFields === [] && !ModuleFieldConfig::getFields($normalizedModule)) {
            return $this->error("Unknown module: {$module}", null, null, null, 422);
        }

        if ($filterId) {
            $filter = SavedFilter::where('id', $filterId)->where('module', $normalizedModule)->firstOrFail();
            $fields = $this->applyHeaderDetails($allFields, $filter->header_details);

            return $this->success([
                'filter_id' => $filter->id,
                'is_default' => (bool) $filter->is_default,
                'fields' => $fields,
            ]);
        }

        $defaultFilter = SavedFilter::where('module', $normalizedModule)
            ->where('is_default', true)
            ->first();

        if ($defaultFilter) {
            $fields = $this->applyHeaderDetails($allFields, $defaultFilter->header_details);

            return $this->success([
                'filter_id' => $defaultFilter->id,
                'is_default' => true,
                'fields' => $fields,
            ]);
        }

        return $this->success([
            'filter_id' => null,
            'is_default' => true,
            'fields' => $allFields,
        ]);
    }

    /**
     * GET /api/v1/{module}/new
     * Create form fields: displaytype 1 only (system/hidden 2 and view-only 3 excluded).
     */
    public function getCreateFields(string $module)
    {
        $normalizedModule = ModuleFieldConfig::normalizeModule($module);
        if (!ModuleFieldConfig::getFields($normalizedModule)) {
            return $this->error("Unknown module: {$module}", null, null, null, 422);
        }

        $fields = ModuleFieldConfig::getApiFieldsForView($module, 'CreateView');

        return $this->success([
            'fields' => $fields,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $allFields
     * @param  mixed  $headerDetails
     * @return array<int, array<string, mixed>>
     */
    private function applyHeaderDetails(array $allFields, $headerDetails): array
    {
        if (empty($headerDetails) || !is_array($headerDetails)) {
            return $allFields;
        }

        $headerFieldNames = array_column($headerDetails, 'fieldname');
        if ($headerFieldNames === []) {
            return $allFields;
        }

        return array_values(array_filter(
            $allFields,
            fn ($field) => in_array($field['fieldname'], $headerFieldNames, true)
        ));
    }
}
