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
        $orgId = $request->query('organizationId');

        $query = Vendor::query();

        if ($orgId) {
            $query->where('organization_id', $orgId);
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
