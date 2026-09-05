<?php

namespace App\Modules\Api\V1\SavedFilter\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\V1\SavedFilter\Models\SavedFilter;
use App\Modules\Api\V1\SavedFilter\Requests\StoreSavedFilterRequest;
use App\Modules\Api\V1\SavedFilter\Requests\UpdateSavedFilterRequest;
use App\Modules\Api\V1\SavedFilter\Resources\SavedFilterResource;
use App\Modules\Api\V1\SavedFilter\Services\ModuleFieldConfig;
use Illuminate\Http\Request;

class SavedFilterController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $orgId = $user->organization_id;

        // Normalize module param if provided
        $module = $request->query('module');
        if ($module) {
            $module = ModuleFieldConfig::normalizeModule($module);
        }

        // ((org public/own) OR (global defaults)) AND module=?
        // Must group the OR so module filter applies to both branches.
        $query = SavedFilter::query()->where(function ($q) use ($orgId, $user) {
            $q->where(function ($org) use ($orgId, $user) {
                $org->where('organization_id', $orgId)
                    ->where(function ($sub) use ($user) {
                        $sub->where('is_public', true)
                            ->orWhere('user_id', $user->id);
                    });
            })->orWhere(function ($global) {
                $global->whereNull('organization_id')
                    ->where('is_default', true);
            });
        });

        if ($module) {
            $query->where('module', $module);
        }

        $filters = $query->orderBy('is_default', 'desc')->orderBy('created_at', 'asc')->get();

        return $this->success(SavedFilterResource::collection($filters));
    }

    public function store(StoreSavedFilterRequest $request)
    {
        $user = $request->user();
        $values = $request->input('data.values');

        // Normalize module name using ModuleFieldConfig
        $module = ModuleFieldConfig::normalizeModule($values['module']);

        $headerDetails = $values['headerDetails'] ?? null;
        if (!$headerDetails) {
            // Fallback: displaytype 1+3 only (never system/type-2 fields)
            $headerDetails = array_map(
                fn (array $field) => [
                    'fieldname' => $field['fieldname'],
                    'fieldlabel' => $field['fieldlabel'] ?? $field['fieldname'],
                ],
                ModuleFieldConfig::getApiFieldsForView($module, 'DetailView')
            );
        }

        $rules = $values['rules'] ?? [];
        if (!is_array($rules)) {
            $rules = [];
        }
        $rules['logical_operator'] = $rules['logical_operator'] ?? 'AND';
        $rules['conditions'] = array_values($rules['conditions'] ?? []);

        $savedFilter = SavedFilter::create([
            'organization_id' => $user->organization_id,
            'user_id' => $user->id,
            'name' => $values['name'],
            'module' => $module,
            'rules' => $rules,
            'is_public' => $values['isPublic'] ?? false,
            'is_default' => false,
            'header_details' => $headerDetails,
        ]);

        return $this->success(new SavedFilterResource($savedFilter), 'Saved filter created successfully.', 201);
    }

    public function update(UpdateSavedFilterRequest $request, $id)
    {
        $user = $request->user();
        $orgId = $user->organization_id;
        $savedFilter = SavedFilter::where('organization_id', $orgId)->findOrFail($id);

        if ($savedFilter->is_default) {
            return $this->error('Cannot update a default filter.', null, null, null, 403);
        }

        // Only owner (or public filter in same org) may update — keep org-scoped
        if ($savedFilter->user_id && $savedFilter->user_id !== $user->id && !$savedFilter->is_public) {
            return $this->error('You do not have permission to update this filter.', null, null, null, 403);
        }

        $values = $request->input('data.values');

        $rules = $values['rules'] ?? $savedFilter->rules ?? [];
        if (!is_array($rules)) {
            $rules = [];
        }
        $rules['logical_operator'] = $rules['logical_operator'] ?? 'AND';
        $rules['conditions'] = array_values($rules['conditions'] ?? []);

        $savedFilter->update([
            'name' => $values['name'],
            'rules' => $rules,
            'is_public' => $values['isPublic'] ?? $savedFilter->is_public,
            'header_details' => $values['headerDetails'],
        ]);

        return $this->success(new SavedFilterResource($savedFilter->fresh()), 'Saved filter updated successfully.');
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $orgId = $user->organization_id;
        $savedFilter = SavedFilter::where('organization_id', $orgId)->findOrFail($id);

        // Prevent deleting default filters
        if ($savedFilter->is_default) {
            return $this->error('Cannot delete a default filter.', null, null, null, 403);
        }

        if ($savedFilter->user_id && $savedFilter->user_id !== $user->id && !$savedFilter->is_public) {
            return $this->error('You do not have permission to delete this filter.', null, null, null, 403);
        }

        $savedFilter->delete();

        return $this->success(null, 'Saved filter successfully deleted.');
    }
}
