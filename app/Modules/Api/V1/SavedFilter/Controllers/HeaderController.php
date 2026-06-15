<?php

namespace App\Modules\Api\V1\SavedFilter\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\V1\SavedFilter\Models\SavedFilter;
use App\Modules\Api\V1\SavedFilter\Services\ModuleFieldConfig;
use Illuminate\Http\Request;

class HeaderController extends Controller
{
    /**
     * GET /api/v1/headers?module={module}      → returns default filter headers for the module
     * GET /api/v1/headers/{filterId}            → returns that specific filter's header_details
     *
     * Both routes call this same method. The logic:
     *  - If filterId is given → fetch that filter, return its header_details matched with module fields
     *  - If no filterId → find the default (is_default=true) filter for the given module, return all fields
     */
    public function show(Request $request, ?string $filterId = null)
    {
        if ($filterId) {
            // Specific filter requested
            $filter = SavedFilter::findOrFail($filterId);

            $module = $filter->module;
            $allFields = ModuleFieldConfig::getFields($module);

            if (!$allFields) {
                return $this->error("Unknown module: {$module}", null, null, null, 422);
            }

            // If this filter has header_details saved, use those to determine which fields to show
            $headerDetails = $filter->header_details;

            if (!empty($headerDetails) && is_array($headerDetails)) {
                // Match header_details against module field definitions
                $headerFieldNames = array_column($headerDetails, 'fieldname');
                $fields = array_values(array_filter($allFields, function ($field) use ($headerFieldNames) {
                    return in_array($field['fieldname'], $headerFieldNames);
                }));
            } else {
                // No header_details stored → return all module fields
                $fields = $allFields;
            }

            return $this->success([
                'filter_id' => $filter->id,
                'is_default' => (bool) $filter->is_default,
                'fields' => $fields,
            ]);
        }

        // No filterId → need module query param to find the default filter
        $module = $request->query('module');
        if (!$module) {
            return $this->error('The module query parameter is required.', null, null, null, 422);
        }

        $normalizedModule = ModuleFieldConfig::normalizeModule($module);
        $allFields = ModuleFieldConfig::getFields($normalizedModule);

        if (!$allFields) {
            return $this->error("Unknown module: {$module}", null, null, null, 422);
        }

        // Find the default filter for this module
        $defaultFilter = SavedFilter::where('module', $normalizedModule)
            ->where('is_default', true)
            ->first();

        if ($defaultFilter) {
            $headerDetails = $defaultFilter->header_details;

            if (!empty($headerDetails) && is_array($headerDetails)) {
                $headerFieldNames = array_column($headerDetails, 'fieldname');
                $fields = array_values(array_filter($allFields, function ($field) use ($headerFieldNames) {
                    return in_array($field['fieldname'], $headerFieldNames);
                }));
            } else {
                $fields = $allFields;
            }

            return $this->success([
                'filter_id' => $defaultFilter->id,
                'is_default' => true,
                'fields' => $fields,
            ]);
        }

        // No default filter exists in DB → return all module fields without filter_id
        return $this->success([
            'filter_id' => null,
            'is_default' => true,
            'fields' => $allFields,
        ]);
    }
}
