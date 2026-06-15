<?php

namespace App\Modules\Api\V1\SavedFilter\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\V1\SavedFilter\Models\SavedFilter;
use App\Modules\Api\V1\SavedFilter\Requests\StoreSavedFilterRequest;
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

        // Get org-specific filters (user's own + public ones) AND global default filters
        $query = SavedFilter::where(function ($q) use ($orgId, $user) {
            // Org-specific: public or owned by current user
            $q->where('organization_id', $orgId)
              ->where(function ($sub) use ($user) {
                  $sub->where('is_public', true)
                      ->orWhere('user_id', $user->id);
              });
        })->orWhere(function ($q) {
            // Global defaults (organization_id is null, is_default is true)
            $q->whereNull('organization_id')
              ->where('is_default', true);
        });

        // Filter by module if provided
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

        // Get all module fields as default header_details if not provided
        $headerDetails = $values['headerDetails'] ?? null;
        if (!$headerDetails) {
            $headerDetails = ModuleFieldConfig::getFields($module);
        }

        $savedFilter = SavedFilter::create([
            'organization_id' => $user->organization_id,
            'user_id' => $user->id,
            'name' => $values['name'],
            'module' => $module,
            'rules' => $values['rules'],
            'is_public' => $values['isPublic'] ?? false,
            'is_default' => false,
            'header_details' => $headerDetails,
        ]);

        return $this->success(new SavedFilterResource($savedFilter), 'Saved filter created successfully.', 201);
    }

    public function destroy(Request $request, $id)
    {
        $orgId = $request->user()->organization_id;
        $savedFilter = SavedFilter::where('organization_id', $orgId)->findOrFail($id);

        // Prevent deleting default filters
        if ($savedFilter->is_default) {
            return $this->error('Cannot delete a default filter.', null, null, null, 403);
        }

        $savedFilter->delete();

        return $this->success(null, 'Saved filter successfully deleted.');
    }
}
