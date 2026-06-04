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
        $orgId = $request->query('organizationId');

        $query = User::query();

        if ($orgId) {
            $query->where('organization_id', $orgId);
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
