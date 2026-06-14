<?php

namespace App\Modules\Api\V1\Vendor\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\V1\Vendor\Models\Vendor;
use App\Modules\Api\V1\Vendor\Requests\StoreVendorRequest;
use App\Modules\Api\V1\Vendor\Requests\UpdateVendorRequest;
use App\Modules\Api\V1\Vendor\Resources\VendorResource;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    public function index(Request $request)
    {
        $orgId = $request->user()->organization_id;

        $query = Vendor::where('organization_id', $orgId);

        $query->when($request->query('search'), function ($q, $search) {
            $q->where(function ($inner) use ($search) {
                $inner->where('name', 'like', "%{$search}%")
                      ->orWhere('contact_person', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
            });
        });

        // Apply saved filter if provided
        if ($request->has('savedFilterId')) {
            $savedFilter = \App\Modules\Api\V1\SavedFilter\Models\SavedFilter::where('organization_id', $orgId)
                ->findOrFail($request->query('savedFilterId'));
            \App\Modules\Api\V1\SavedFilter\Services\QueryFilterService::apply($query, 'vendors', $savedFilter->rules);
        }

        // Apply dynamic query rules if provided
        if ($request->has('rules')) {
            $rules = $request->input('rules');
            if (is_string($rules)) {
                $rules = json_decode($rules, true);
            }
            if (is_array($rules)) {
                \App\Modules\Api\V1\SavedFilter\Services\QueryFilterService::apply($query, 'vendors', $rules);
            }
        }

        $vendors = $query->get();

        return VendorResource::collection($vendors);
    }

    public function store(StoreVendorRequest $request)
    {
        $values = $request->input('data.values');

        $vendor = Vendor::create([
            'organization_id' => $values['organizationId'],
            'name' => $values['name'],
            'contact_person' => $values['contactPerson'] ?? null,
            'email' => $values['email'] ?? null,
            'phone' => $values['phone'] ?? null,
            'address' => $values['address'] ?? null,
        ]);

        return new VendorResource($vendor);
    }

    public function show($id)
    {
        $vendor = Vendor::findOrFail($id);
        return new VendorResource($vendor);
    }

    public function update(UpdateVendorRequest $request, $id)
    {
        $vendor = Vendor::findOrFail($id);
        $values = $request->input('data.values');

        $vendor->update([
            'organization_id' => $values['organizationId'],
            'name' => $values['name'],
            'contact_person' => $values['contactPerson'] ?? null,
            'email' => $values['email'] ?? null,
            'phone' => $values['phone'] ?? null,
            'address' => $values['address'] ?? null,
        ]);

        return new VendorResource($vendor);
    }

    public function destroy($id)
    {
        $vendor = Vendor::findOrFail($id);
        $vendor->delete();

        return response()->json([
            'message' => 'Vendor successfully deleted.'
        ]);
    }
}
