<?php

namespace App\Modules\Api\V1\Organization\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\V1\Organization\Models\Organization;
use App\Modules\Api\V1\Organization\Requests\StoreOrganizationRequest;
use App\Modules\Api\V1\Organization\Resources\OrganizationResource;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function store(StoreOrganizationRequest $request)
    {
        $values = $request->input('data.values');
        
        $organization = Organization::create([
            'name' => $values['name'],
            'description' => $values['description'] ?? null,
            'email' => $values['email'] ?? null,
            'phone' => $values['phone'] ?? null,
            'address' => $values['address'] ?? null,
        ]);

        return new OrganizationResource($organization);
    }

    public function show($id)
    {
        $organization = Organization::findOrFail($id);
        return new OrganizationResource($organization);
    }

    public function update(StoreOrganizationRequest $request, $id)
    {
        $organization = Organization::findOrFail($id);
        $values = $request->input('data.values');

        $organization->update([
            'name' => $values['name'],
            'description' => $values['description'] ?? null,
            'email' => $values['email'] ?? null,
            'phone' => $values['phone'] ?? null,
            'address' => $values['address'] ?? null,
        ]);

        return new OrganizationResource($organization);
    }

    public function destroy($id)
    {
        $organization = Organization::findOrFail($id);
        $organization->delete();

        return response()->json([
            'message' => 'Organization successfully deleted.'
        ]);
    }

    public function search(Request $request)
    {
        $query = $request->query('query');
        
        $results = Organization::where('name', 'like', "%{$query}%")
            ->orWhere('email', 'like', "%{$query}%")
            ->get();

        return OrganizationResource::collection($results);
    }
}
