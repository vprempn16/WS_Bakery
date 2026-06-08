<?php

namespace App\Modules\Api\V1\Organization\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\V1\Organization\Models\Organization;
use App\Modules\Api\V1\Organization\Requests\StoreOrganizationRequest;
use App\Modules\Api\V1\Organization\Requests\UpdateOrganizationRequest;
use App\Modules\Api\V1\Organization\Resources\OrganizationResource;
use App\Modules\Api\V1\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class OrganizationController extends Controller
{
    public function store(StoreOrganizationRequest $request)
    {
        $values = $request->input('data.values');
        
        $result = DB::transaction(function () use ($values) {
            $organization = Organization::create([
                'name' => $values['name'],
                'description' => $values['description'] ?? null,
                'email' => $values['email'] ?? null,
                'phone' => $values['phone'] ?? null,
                'address' => $values['address'] ?? null,
            ]);

            $userData = $values['firstUser'];
            $user = User::create([
                'organization_id' => $organization->id,
                'first_name' => $userData['firstName'],
                'last_name' => $userData['lastName'],
                'email' => $userData['email'],
                'phone' => $userData['phoneNumber'] ?? null,
                'role' => 'owner',
                'password' => Hash::make($userData['password']),
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;

            return [
                'organization' => $organization,
                'user' => $user,
                'token' => $token
            ];
        });

        return response()->json([
            'status' => true,
            'message' => 'Organization created successfully.',
            'data' => [
                'token' => $result['token'],
                'user' => [
                    'id' => $result['user']->id,
                    'first_name' => $result['user']->first_name,
                    'last_name' => $result['user']->last_name,
                    'email' => $result['user']->email,
                    'phone_number' => $result['user']->phone,
                    'role' => $result['user']->role,
                    'organization' => [
                        'id' => $result['organization']->id,
                        'name' => $result['organization']->name,
                    ]
                ]
            ]
        ], 201);
    }

    public function show($id)
    {
        $organization = Organization::findOrFail($id);
        return new OrganizationResource($organization);
    }

    public function update(UpdateOrganizationRequest $request, $id)
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
