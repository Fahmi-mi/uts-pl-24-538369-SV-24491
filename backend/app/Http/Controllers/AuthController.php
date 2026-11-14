<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            Log::warning('Validation failed for login', [
                'user' => 'guest',
                'action' => 'login_validation',
                'reason' => $validator->errors()->toArray(),
            ]);

            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            Log::warning('Authentication failed for login', [
                'user' => 'guest',
                'action' => 'user_authentication',
                'reason' => 'invalid credentials',
            ]);

            throw ValidationException::withMessages([
                'email' => ['Email atau password salah'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        Log::info('User logged in successfully', [
            'user' => (string) $user->id,
            'action' => 'user_login',
            'reason' => 'credentials verified',
        ]);

        return response()->json([
            'message' => 'Login berhasil',
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ],
        ], 200);
    }

    public function logout(Request $request): JsonResponse
    {
        $userId = (string) $request->user()->id;
        $request->user()->currentAccessToken()->delete();

        Log::info('User logged out successfully', [
            'user' => $userId,
            'action' => 'user_logout',
            'reason' => 'user requested token revocation',
        ]);

        return response()->json([
            'message' => 'Logout berhasil',
        ], 200);
    }
}
