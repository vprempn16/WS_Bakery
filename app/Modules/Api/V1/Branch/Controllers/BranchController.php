<?php

namespace App\Modules\Api\V1\Branch\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\V1\Branch\Models\Branch;
use App\Modules\Api\V1\Branch\Requests\BranchRequest;
use App\Modules\Api\V1\Branch\Resources\BranchResource;
use App\Modules\Api\V1\SavedFilter\Models\SavedFilter;
use App\Modules\Api\V1\SavedFilter\Services\ModuleFieldConfig;
use App\Modules\Api\V1\SavedFilter\Services\QueryFilterService;
use App\Services\AuthUser;
use App\Services\CRM\RecordObject;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function index(Request $request)
    {
        $user = AuthUser::requireUser();
        $permissionService = new \App\Services\PermissionService($user);
        if ($deny = $permissionService->denyMessage('Branch', 'view')) {
            return $this->error($deny, null, null, null, 403);
        }

        $organizationId = AuthUser::organizationId();
        $query = Branch::where('organization_id', $organizationId);

        // Non-admins only see their assigned branch
        if (! $user->isFullAdmin() && $user->branch_id) {
            $query->where('id', $user->branch_id);
        }

        $query->when($request->query('search'), function ($q, $search) {
            $q->where(function ($inner) use ($search) {
                $inner->where('name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        });

        if ($request->has('savedFilterId')) {
            $savedFilter = SavedFilter::where('organization_id', $organizationId)
                ->findOrFail($request->query('savedFilterId'));
            QueryFilterService::apply($query, 'branches', $savedFilter->rules);
        }

        if ($request->has('rules')) {
            $rules = $request->input('rules');
            if (is_string($rules)) {
                $rules = json_decode($rules, true);
            }
            if (is_array($rules)) {
                QueryFilterService::apply($query, 'branches', $rules);
            }
        }

        $perPage = \App\Support\ApiPagination::perPage($request);
        $branches = $query->paginate($perPage);
        $fieldList = ModuleFieldConfig::getApiFieldsForView('Branch', 'DetailView');

        return $this->paginated(BranchResource::collection($branches)->resource, $fieldList);
    }

    public function store(BranchRequest $request)
    {
        $values = $request->validated()['data']['values'];

        try {
            /** @var Branch $branch */
            $branch = RecordObject::make('Branch', null, $values, 'CreateView');
            $branch->organization_id = AuthUser::organizationId();
            $branch->name = $values['name'];
            $branch->type = $values['type'];
            $branch->address = $values['address'] ?? null;
            $branch->phone = $values['phone'] ?? null;
            $branch->save();

            return $this->success(new BranchResource($branch), 'Branch created successfully.', 201);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), null, null, null, 400);
        }
    }

    public function show($id)
    {
        try {
            /** @var Branch $branch */
            $branch = RecordObject::make('Branch', $id, [], 'DetailView');
            $resource = new BranchResource($branch);
            $fieldList = ModuleFieldConfig::getApiFieldsForView('Branch', 'DetailView');

            return $this->success([
                'fields' => $fieldList,
                'values' => $resource->toArray(request()),
            ]);
        } catch (ModelNotFoundException $e) {
            return $this->error('Branch not found.', null, null, null, 404);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), null, null, null, 403);
        }
    }

    public function update(BranchRequest $request, $id)
    {
        try {
            $values = $request->validated()['data']['values'];
            /** @var Branch $branch */
            $branch = RecordObject::make('Branch', $id, $values, 'EditView');
            $branch->name = $values['name'];
            $branch->type = $values['type'];
            if (array_key_exists('address', $values)) {
                $branch->address = $values['address'];
            }
            if (array_key_exists('phone', $values)) {
                $branch->phone = $values['phone'];
            }
            $branch->save();

            return $this->success(new BranchResource($branch), 'Branch updated successfully.');
        } catch (ModelNotFoundException $e) {
            return $this->error('Branch not found.', null, null, null, 404);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), null, null, null, 400);
        }
    }

    public function destroy($id)
    {
        try {
            /** @var Branch $branch */
            $branch = RecordObject::make('Branch', $id, [], 'EditView');
            $branch->deleteRecord();

            return $this->success(null, 'Branch successfully deleted.');
        } catch (ModelNotFoundException $e) {
            return $this->error('Branch not found.', null, null, null, 404);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), null, null, null, 400);
        }
    }
}
