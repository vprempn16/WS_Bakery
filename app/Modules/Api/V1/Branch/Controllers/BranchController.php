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
    use ResultTrait, FilterableTrait;

    public function index(Request $request)
    {
        $organizationId = auth()->user()->organization_id;
        $query = Branch::where('organization_id', $organizationId);

        // Apply filters
        $filters = $this->parseFilters($request);
        $query = $this->applyFilters($query, $filters, 'Branch');

        // Check if pagination is requested
        $perPage = $request->get('limit');
        if ($perPage) {
            $branches = $query->paginate($perPage);
            return $this->paginated(BranchResource::collection($branches));
        }

        $branches = $query->get();
        return $this->success(BranchResource::collection($branches));
    }

    public function store(Request $request)
    {
        // Actually this is an update if ID is provided, else create. But the standard pattern uses /new for store and /{id} for update using POST.
        $organizationId = auth()->user()->organization_id;
        
        $branchRequest = app(BranchRequest::class);
        $validated = $branchRequest->validated()['data']['values'];

        $validated['organization_id'] = $organizationId;

        $branch = Branch::create($validated);

        return $this->created(new BranchResource($branch), 'Branch created successfully.');
    }

    public function show($id)
    {
        $organizationId = auth()->user()->organization_id;
        $branch = Branch::where('organization_id', $organizationId)
            ->where('id', $id)
            ->firstOrFail();

        return $this->success(new BranchResource($branch));
    }

    public function update(Request $request, $id)
    {
        $organizationId = auth()->user()->organization_id;
        $branch = Branch::where('organization_id', $organizationId)
            ->where('id', $id)
            ->firstOrFail();

        $branchRequest = app(BranchRequest::class);
        $validated = $branchRequest->validated()['data']['values'];

        $branch->update($validated);

        return $this->success(new BranchResource($branch), 'Branch updated successfully.');
    }

    public function destroy($id)
    {
        $organizationId = auth()->user()->organization_id;
        $branch = Branch::where('organization_id', $organizationId)
            ->where('id', $id)
            ->firstOrFail();

        $branch->delete();

        return $this->deleted('Branch successfully deleted.');
    }
}
