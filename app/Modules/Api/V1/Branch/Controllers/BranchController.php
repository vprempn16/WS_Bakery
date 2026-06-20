<?php

namespace App\Modules\Api\V1\Branch\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\V1\Branch\Models\Branch;
use App\Modules\Api\V1\Branch\Requests\BranchRequest;
use App\Modules\Api\V1\Branch\Resources\BranchResource;
use Illuminate\Http\Request;
use App\Traits\ResultTrait;
use App\Traits\FilterableTrait;

class BranchController extends Controller
{
    public function index(Request $request)
    {
        $organizationId = auth()->user()->organization_id;
        $query = Branch::where('organization_id', $organizationId);

        $query->when($request->query('search'), function ($q, $search) {
            $q->where(function ($inner) use ($search) {
                $inner->where('name', 'like', "%{$search}%")
                      ->orWhere('address', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
            });
        });

        // Apply saved filter if provided
        if ($request->has('savedFilterId')) {
            $savedFilter = \App\Modules\Api\V1\SavedFilter\Models\SavedFilter::where('organization_id', $organizationId)
                ->findOrFail($request->query('savedFilterId'));
            \App\Modules\Api\V1\SavedFilter\Services\QueryFilterService::apply($query, 'branches', $savedFilter->rules);
        }

        // Apply dynamic query rules if provided
        if ($request->has('rules')) {
            $rules = $request->input('rules');
            if (is_string($rules)) {
                $rules = json_decode($rules, true);
            }
            if (is_array($rules)) {
                \App\Modules\Api\V1\SavedFilter\Services\QueryFilterService::apply($query, 'branches', $rules);
            }
        }

        // Check if pagination is requested
        $perPage = $request->query('limit', $request->query('per_page', 20));
        $branches = $query->paginate($perPage);

        $fieldList = \App\Modules\Api\V1\SavedFilter\Services\ModuleFieldConfig::getMappedFields('Branch');

        return $this->paginated(BranchResource::collection($branches)->resource, $fieldList);
    }

    public function store(Request $request)
    {
        $organizationId = auth()->user()->organization_id;
        
        $branchRequest = app(BranchRequest::class);
        $validated = $branchRequest->validated()['data']['values'];

        $validated['organization_id'] = $organizationId;

        $branch = Branch::create($validated);

        return $this->success(new BranchResource($branch), 'Branch created successfully.', 201);
    }

    public function show($id)
    {
        try {
            $organizationId = auth()->user()->organization_id;
            $branch = Branch::where('organization_id', $organizationId)
                ->where('id', $id)
                ->firstOrFail();

            $resource = new BranchResource($branch);
            
            $fieldList = \App\Modules\Api\V1\SavedFilter\Services\ModuleFieldConfig::getMappedFields('Branch');
            
            return $this->success([
                'fields' => $fieldList,
                'values' => $resource->toArray(request())
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Branch not found.', null, null, null, 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $organizationId = auth()->user()->organization_id;
            $branch = Branch::where('organization_id', $organizationId)
                ->where('id', $id)
                ->firstOrFail();

            $branchRequest = app(BranchRequest::class);
            $validated = $branchRequest->validated()['data']['values'];

            $branch->update($validated);

            return $this->success(new BranchResource($branch), 'Branch updated successfully.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Branch not found.', null, null, null, 404);
        }
    }

    public function destroy($id)
    {
        try {
            $organizationId = auth()->user()->organization_id;
            $branch = Branch::where('organization_id', $organizationId)
                ->where('id', $id)
                ->firstOrFail();

            $branch->delete();

            return $this->success(null, 'Branch successfully deleted.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Branch not found.', null, null, null, 404);
        }
    }
}
