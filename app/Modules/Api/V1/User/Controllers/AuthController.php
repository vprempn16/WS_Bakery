<?php

namespace App\Modules\Api\V1\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\V1\Branch\Models\Branch;
use App\Modules\Api\V1\User\Models\User;
use App\Modules\Api\V1\User\Requests\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        $values = $request->input('data.values');

        $user = User::with(['organization', 'branch'])
            ->where('email', $values['email'])
            ->first();

        if (!$user || !Hash::check($values['password'], $user->password)) {
            return $this->error('Invalid email or password.', null, null, null, 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;
        $role = strtolower((string) $user->role);
        $isOwner = in_array($role, ['owner', 'admin', 'superadmin'], true);

        // Owner: all org branches (can switch). Others: only assigned branch.
        if ($isOwner) {
            $branchModels = Branch::where('organization_id', $user->organization_id)
                ->orderBy('name')
                ->get();
        } elseif ($user->branch_id) {
            $branchModels = Branch::where('organization_id', $user->organization_id)
                ->where('id', $user->branch_id)
                ->get();
        } else {
            $branchModels = collect();
        }

        $branches = $branchModels->map(fn (Branch $b) => [
            'id' => $b->id,
            'org_id' => $b->organization_id,
            'name' => $b->name,
            'address' => $b->address,
            'phone' => $b->phone,
            'type' => $b->type,
        ])->values()->all();

        return $this->success([
            'token' => $token,
            'refresh_token' => null,
            'org_id' => $user->organization_id,
            'branches' => $branches,
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: ($user->email ?? 'User'),
                'email' => $user->email,
                'phone_number' => $user->phone,
                'role' => $user->role,
                'org_id' => $user->organization_id,
                'branch_id' => $user->branch_id,
                'organization' => $user->organization ? [
                    'id' => $user->organization->id,
                    'name' => $user->organization->name,
                ] : null,
                'allowed_modules' => $user->getAllowedModules(),
            ],
        ], 'Login successful.');
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(null, 'Logout successful.');
    }
}
