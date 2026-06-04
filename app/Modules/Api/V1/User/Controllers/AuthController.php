<?php

namespace App\Modules\Api\V1\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\V1\User\Requests\LoginRequest;
use App\Modules\Api\V1\User\Resources\UserResource;
use App\Modules\Api\V1\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        $user = User::with('organization')->where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials.'
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Successfully logged out.'
        ]);
    }

    public function me(Request $request)
    {
        return new UserResource($request->user()->load('organization'));
    }
}
