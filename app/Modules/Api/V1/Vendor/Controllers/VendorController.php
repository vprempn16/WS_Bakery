<?php

namespace App\Modules\Api\V1\Vendor\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\V1\SavedFilter\Models\SavedFilter;
use App\Modules\Api\V1\SavedFilter\Services\ModuleFieldConfig;
use App\Modules\Api\V1\SavedFilter\Services\QueryFilterService;
use App\Modules\Api\V1\Vendor\Models\Vendor;
use App\Modules\Api\V1\Vendor\Requests\StoreVendorRequest;
use App\Modules\Api\V1\Vendor\Requests\UpdateVendorRequest;
use App\Modules\Api\V1\Vendor\Resources\VendorResource;
use App\Services\AuthUser;
use App\Services\CRM\RecordObject;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    public function index(Request $request)
    {
        $orgId = AuthUser::organizationId();
        $perPage = $request->query('per_page', 20);

        $query = Vendor::where('organization_id', $orgId);

        $query->when($request->query('search'), function ($q, $search) {
            $q->where(function ($inner) use ($search) {
                $inner->where('name', 'like', "%{$search}%")
                    ->orWhere('contact_person', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        });

        if ($request->has('savedFilterId')) {
            $savedFilter = SavedFilter::where('organization_id', $orgId)
                ->findOrFail($request->query('savedFilterId'));
            QueryFilterService::apply($query, 'vendors', $savedFilter->rules);
        }

        if ($request->has('rules')) {
            $rules = $request->input('rules');
            if (is_string($rules)) {
                $rules = json_decode($rules, true);
            }
            if (is_array($rules)) {
                QueryFilterService::apply($query, 'vendors', $rules);
            }
        }

        $vendors = $query->paginate($perPage);
        $fieldList = ModuleFieldConfig::getApiFieldsForView('Vendor', 'DetailView');

        return $this->paginated(VendorResource::collection($vendors)->resource, $fieldList);
    }

    public function store(StoreVendorRequest $request)
    {
        $values = $request->input('data.values') ?? [];

        try {
            /** @var Vendor $vendor */
            $vendor = RecordObject::make('Vendor', null, $values, 'CreateView');
            $vendor->organization_id = AuthUser::organizationId();
            $vendor->name = $values['name'];
            $vendor->contact_person = $values['contactPerson'] ?? null;
            $vendor->email = $values['email'] ?? null;
            $vendor->phone = $values['phone'] ?? null;
            $vendor->address = $values['address'] ?? null;
            $vendor->save();

            return $this->success(new VendorResource($vendor), 'Vendor created successfully.', 201);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), null, null, null, 400);
        }
    }

    public function show($id)
    {
        try {
            /** @var Vendor $vendor */
            $vendor = RecordObject::make('Vendor', $id, [], 'DetailView');
            $resource = new VendorResource($vendor);
            $fieldList = ModuleFieldConfig::getApiFieldsForView('Vendor', 'DetailView');

            return $this->success([
                'fields' => $fieldList,
                'values' => $resource->toArray(request()),
            ]);
        } catch (ModelNotFoundException $e) {
            return $this->error('Vendor not found.', null, null, null, 404);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), null, null, null, 403);
        }
    }

    public function update(UpdateVendorRequest $request, $id)
    {
        try {
            $values = $request->input('data.values') ?? [];
            /** @var Vendor $vendor */
            $vendor = RecordObject::make('Vendor', $id, $values, 'EditView');
            if (array_key_exists('name', $values)) {
                $vendor->name = $values['name'];
            }
            if (array_key_exists('contactPerson', $values)) {
                $vendor->contact_person = $values['contactPerson'];
            }
            if (array_key_exists('email', $values)) {
                $vendor->email = $values['email'];
            }
            if (array_key_exists('phone', $values)) {
                $vendor->phone = $values['phone'];
            }
            if (array_key_exists('address', $values)) {
                $vendor->address = $values['address'];
            }
            $vendor->save();

            return $this->success(new VendorResource($vendor));
        } catch (ModelNotFoundException $e) {
            return $this->error('Vendor not found.', null, null, null, 404);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), null, null, null, 400);
        }
    }

    public function destroy($id)
    {
        try {
            /** @var Vendor $vendor */
            $vendor = RecordObject::make('Vendor', $id, [], 'EditView');
            $vendor->deleteRecord();

            return $this->success(null, 'Vendor successfully deleted.');
        } catch (ModelNotFoundException $e) {
            return $this->error('Vendor not found.', null, null, null, 404);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), null, null, null, 400);
        }
    }
}
