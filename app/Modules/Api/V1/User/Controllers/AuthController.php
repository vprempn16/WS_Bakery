<?php

namespace App\Modules\Api\V1\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\V1\User\Models\User;
use App\Modules\Api\V1\User\Requests\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        $values = $request->input('data.values');
        
        $user = User::with('organization')->where('email', $values['email'])->first();

        if (!$user || !Hash::check($values['password'], $user->password)) {
            return $this->error('Invalid email or password.', null, null, null, 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->success([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone_number' => $user->phone,
                'role' => $user->role,
                'organization' => $user->organization ? [
                    'id' => $user->organization->id,
                    'name' => $user->organization->name,
                ] : null
            ]
        ], 'Login successful.');
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(null, 'Logout successful.');
    }
}
