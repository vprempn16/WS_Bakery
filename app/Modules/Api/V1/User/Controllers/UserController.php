<?php

namespace App\Modules\Api\V1\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\V1\User\Models\User;
use App\Modules\Api\V1\User\Requests\StoreUserRequest;
use App\Modules\Api\V1\User\Requests\UpdateUserRequest;
use App\Modules\Api\V1\User\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $orgId = $request->user()->organization_id;

        $query = User::where('organization_id', $orgId);

        $query->when($request->query('role'), function ($q, $role) {
            $q->where('role', $role);
        });

        $query->when($request->query('search'), function ($q, $search) {
            $q->where(function ($inner) use ($search) {
                $inner->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
            });
        });

        // Apply saved filter if provided
        if ($request->has('savedFilterId')) {
            $savedFilter = \App\Modules\Api\V1\SavedFilter\Models\SavedFilter::where('organization_id', $orgId)
                ->findOrFail($request->query('savedFilterId'));
            \App\Modules\Api\V1\SavedFilter\Services\QueryFilterService::apply($query, 'users', $savedFilter->rules);
        }

        // Apply dynamic query rules if provided
        if ($request->has('rules')) {
            $rules = $request->input('rules');
            if (is_string($rules)) {
                $rules = json_decode($rules, true);
            }
            if (is_array($rules)) {
                \App\Modules\Api\V1\SavedFilter\Services\QueryFilterService::apply($query, 'users', $rules);
            }
        }

        $users = $query->get();

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request)
    {
        $values = $request->input('data.values');

        $user = User::create([
            'organization_id' => $values['organizationId'],
            'first_name' => $values['firstName'],
            'last_name' => $values['lastName'],
            'email' => $values['email'],
            'phone' => $values['phone'] ?? null,
            'role' => $values['role'],
            'password' => Hash::make($values['password']),
        ]);

        // Generate token upon creation
        $token = $user->createToken('auth_token')->plainTextToken;

        return new UserResource($user, $token);
    }

    public function show($id)
    {
        $user = User::findOrFail($id);
        return new UserResource($user);
    }

    public function update(UpdateUserRequest $request, $id)
    {
        $user = User::findOrFail($id);
        $values = $request->input('data.values');

        $data = [
            'organization_id' => $values['organizationId'],
            'first_name' => $values['firstName'],
            'last_name' => $values['lastName'],
            'email' => $values['email'],
            'phone' => $values['phone'] ?? null,
            'role' => $values['role'],
        ];

        if (!empty($values['password'])) {
            $data['password'] = Hash::make($values['password']);
        }

        $user->update($data);

        return new UserResource($user);
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return response()->json([
            'message' => 'User successfully deleted.'
        ]);
    }
}
